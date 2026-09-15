<?php
/**
 * Veloura Tec — Inquiry queries.
 *
 * Phase 3 covers the admin side: listing, filtering, status changes and
 * internal notes. The public submission flow arrives in Phase 6 and will
 * use inquiry_create() / inquiry_reference() from this same file.
 */

declare(strict_types=1);

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

/** The status values the schema accepts. */
const INQUIRY_STATUSES = ['new', 'contacted', 'quoted', 'completed', 'cancelled'];

/**
 * Paginated, searchable inquiry list.
 *
 * @param array{search?:string,status?:string,sort?:string} $filters
 * @return array{rows:array<int,array<string,mixed>>, total:int, pages:int, page:int}
 */
function inquiries_admin_list(array $filters = [], int $page = 1, int $perPage = 20): array
{
    $where  = [];
    $params = [];

    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        // One named placeholder per occurrence: MySQL's native prepares
        // reject a name that is bound more than once.
        $where[] = '(i.reference LIKE :s_ref OR i.full_name LIKE :s_name
                     OR i.email LIKE :s_email OR i.company LIKE :s_company
                     OR i.whatsapp LIKE :s_whatsapp OR i.country LIKE :s_country)';
        $term = '%' . $search . '%';
        foreach (['s_ref', 's_name', 's_email', 's_company', 's_whatsapp', 's_country'] as $placeholder) {
            $params[$placeholder] = $term;
        }
    }

    $status = (string) ($filters['status'] ?? '');
    if (in_array($status, INQUIRY_STATUSES, true)) {
        $where[] = 'i.status = :status';
        $params['status'] = $status;
    }

    $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

    $sortMap = [
        'newest' => 'i.created_at DESC',
        'oldest' => 'i.created_at ASC',
        'name'   => 'i.full_name ASC',
        'status' => 'i.status ASC, i.created_at DESC',
    ];
    $orderBy = $sortMap[(string) ($filters['sort'] ?? 'newest')] ?? $sortMap['newest'];

    $total = (int) db_value('SELECT COUNT(*) FROM inquiries i' . $clause, $params, 0);

    $perPage = max(1, min(100, $perPage));
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = max(1, min($page, $pages));

    $rows = db_all(
        'SELECT i.* FROM inquiries i'
        . $clause
        . ' ORDER BY ' . $orderBy
        . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
        $params
    );

    return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
}

/**
 * @return array<string,mixed>|null
 */
function inquiry_by_id(int $id): ?array
{
    return db_one('SELECT * FROM inquiries WHERE id = :id LIMIT 1', ['id' => $id]);
}

/**
 * The line items of one inquiry, including the live product link where the
 * product still exists.
 *
 * @return array<int,array<string,mixed>>
 */
function inquiry_items(int $inquiryId): array
{
    return db_all(
        'SELECT ii.*, p.slug AS product_slug, p.status AS product_status
           FROM inquiry_items ii
           LEFT JOIN products p ON p.id = ii.product_id
          WHERE ii.inquiry_id = :id
          ORDER BY ii.id',
        ['id' => $inquiryId]
    );
}

/**
 * Change an inquiry's status.
 */
function inquiry_set_status(int $id, string $status): bool
{
    if (!in_array($status, INQUIRY_STATUSES, true)) {
        return false;
    }

    return db_execute(
        'UPDATE inquiries SET status = :status WHERE id = :id',
        ['status' => $status, 'id' => $id]
    ) > 0;
}

/**
 * Save internal notes. These are never shown to the customer.
 */
function inquiry_set_notes(int $id, string $notes): void
{
    db_execute(
        'UPDATE inquiries SET admin_notes = :notes WHERE id = :id',
        ['notes' => trim($notes) ?: null, 'id' => $id]
    );
}

function inquiry_delete(int $id): bool
{
    // inquiry_items rows go with it via ON DELETE CASCADE.
    return db_delete('inquiries', $id) > 0;
}

/**
 * Counts per status, for the dashboard and the filter chips.
 *
 * @return array<string,int>
 */
function inquiry_status_counts(): array
{
    $counts = array_fill_keys(INQUIRY_STATUSES, 0);

    foreach (db_all('SELECT status, COUNT(*) AS total FROM inquiries GROUP BY status') as $row) {
        $counts[(string) $row['status']] = (int) $row['total'];
    }

    return $counts;
}

/**
 * Generate the next customer-facing reference, e.g. VT-2026-000042.
 *
 * Used by the Phase 6 submission flow; defined here so the format lives in
 * exactly one place.
 */
function inquiry_reference(): string
{
    $prefix = (string) setting('inquiry_prefix', 'VT');
    $year   = date('Y');

    $lastId = (int) db_value('SELECT COALESCE(MAX(id), 0) FROM inquiries', [], 0);

    return sprintf('%s-%s-%06d', $prefix, $year, $lastId + 1);
}

/**
 * A human label for a status value.
 */
function inquiry_status_label(string $status): string
{
    return match ($status) {
        'new'       => 'New',
        'contacted' => 'Contacted',
        'quoted'    => 'Quoted',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        default     => ucfirst($status),
    };
}

// =====================================================================
// Cart resolution (Phase 4)
//
// The browser stores only product ids and quantities. Names, prices and
// URLs are ALWAYS read back from the database here, so a tampered
// localStorage payload cannot change what an inquiry says a product
// costs or is called.
// =====================================================================

/** A single inquiry may not exceed this many distinct products. */
const CART_MAX_LINES = 40;

/** Per-line quantity ceiling. */
const CART_MAX_QUANTITY = 999;

/**
 * Turn a raw [{id, quantity}, ...] payload into authoritative cart lines.
 *
 * Unknown, unpublished or deleted products are dropped and reported
 * separately so the cart page can tell the customer what was removed.
 *
 * @param array<int,mixed> $raw
 * @return array{lines:array<int,array<string,mixed>>, dropped:array<int,int>}
 */
function cart_resolve(array $raw): array
{
    $quantities = [];

    foreach (array_slice($raw, 0, CART_MAX_LINES) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $id  = (int) ($item['id'] ?? 0);
        $qty = (int) ($item['quantity'] ?? 1);

        if ($id <= 0) {
            continue;
        }

        $quantities[$id] = max(1, min(CART_MAX_QUANTITY, $qty));
    }

    if ($quantities === []) {
        return ['lines' => [], 'dropped' => []];
    }

    // One query for every id, with a placeholder per value.
    $ids          = array_keys($quantities);
    $placeholders = [];
    $params       = [];

    foreach ($ids as $index => $id) {
        $placeholders[] = ':id' . $index;
        $params['id' . $index] = $id;
    }

    $rows = db_all(
        'SELECT p.id, p.name, p.slug, p.price, p.currency, p.price_mode, p.main_image,
                p.main_image_alt, c.name AS category_name
           FROM products p
           JOIN categories c ON c.id = p.category_id
          WHERE p.status = "published"
            AND p.id IN (' . implode(', ', $placeholders) . ')',
        $params
    );

    $found = [];
    $lines = [];

    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $found[] = $id;

        $row['quantity']    = $quantities[$id];
        $row['url']         = product_url($row);
        $row['price_label'] = product_price_label($row);
        $row['line_total']  = ($row['price'] !== null && $row['price_mode'] === 'show')
            ? (float) $row['price'] * $quantities[$id]
            : null;

        $lines[] = $row;
    }

    // Preserve the order the customer added them in.
    usort($lines, static fn (array $a, array $b): int =>
        array_search((int) $a['id'], $ids, true) <=> array_search((int) $b['id'], $ids, true));

    return ['lines' => $lines, 'dropped' => array_values(array_diff($ids, $found))];
}

/**
 * Cart totals. A total is only meaningful when every line carries a price.
 *
 * @param array<int,array<string,mixed>> $lines
 * @return array{units:int, total:?float, currency:string, all_priced:bool}
 */
function cart_totals(array $lines): array
{
    $units      = 0;
    $total      = 0.0;
    $allPriced  = $lines !== [];
    $currency   = (string) setting('default_currency', 'USD');

    foreach ($lines as $line) {
        $units += (int) $line['quantity'];

        if ($line['line_total'] === null) {
            $allPriced = false;
            continue;
        }

        $total   += (float) $line['line_total'];
        $currency = (string) ($line['currency'] ?: $currency);
    }

    return [
        'units'      => $units,
        'total'      => $allPriced ? $total : null,
        'currency'   => $currency,
        'all_priced' => $allPriced,
    ];
}

// =====================================================================
// Submission (Phase 4)
// =====================================================================

/**
 * Validate the customer's inquiry form.
 *
 * @param array<string,mixed> $data
 * @return array<string,string> field => message
 */
function inquiry_validate(array $data): array
{
    $errors = [];

    $name = trim((string) ($data['full_name'] ?? ''));
    if ($name === '') {
        $errors['full_name'] = 'Please enter your full name.';
    } elseif (mb_strlen($name) > 160) {
        $errors['full_name'] = 'Your name must be 160 characters or fewer.';
    }

    $country = trim((string) ($data['country'] ?? ''));
    if ($country === '') {
        $errors['country'] = 'Please enter your country.';
    } elseif (mb_strlen($country) > 120) {
        $errors['country'] = 'The country must be 120 characters or fewer.';
    }

    if (mb_strlen((string) ($data['city'] ?? '')) > 120) {
        $errors['city'] = 'The city must be 120 characters or fewer.';
    }

    $email = trim((string) ($data['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address, or leave it empty.';
    } elseif (mb_strlen($email) > 190) {
        $errors['email'] = 'The email address is too long.';
    }

    // Digits only, ignoring spaces, dashes, brackets and a leading plus.
    $whatsapp = trim((string) ($data['whatsapp'] ?? ''));
    $digits   = preg_replace('/\D+/', '', $whatsapp) ?? '';

    if ($whatsapp === '') {
        $errors['whatsapp'] = 'Please enter your WhatsApp number.';
    } elseif (strlen($digits) < 7 || strlen($digits) > 20) {
        $errors['whatsapp'] = 'Enter your number with the country code, for example +49 170 1234567.';
    }

    if (mb_strlen((string) ($data['company'] ?? '')) > 190) {
        $errors['company'] = 'The company name must be 190 characters or fewer.';
    }

    if (mb_strlen((string) ($data['notes'] ?? '')) > 2000) {
        $errors['notes'] = 'Please keep notes under 2000 characters.';
    }

    return $errors;
}

/**
 * Save an inquiry and its line items.
 *
 * The reference is derived from the row's own auto-increment id inside a
 * transaction, so two customers submitting at the same moment can never
 * be handed the same number — the earlier MAX(id)+1 approach could.
 *
 * Line values are snapshots: the inquiry stays readable even after the
 * catalog changes or a product is deleted.
 *
 * @param array<string,mixed>            $customer
 * @param array<int,array<string,mixed>> $lines  from cart_resolve()
 * @return array{id:int, reference:string}
 */
function inquiry_create(array $customer, array $lines): array
{
    return db_transaction(static function () use ($customer, $lines): array {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        $id = db_insert('inquiries', [
            // Temporary unique value; replaced below with the real reference.
            'reference'   => 'TMP-' . bin2hex(random_bytes(8)),
            'full_name'   => trim((string) ($customer['full_name'] ?? '')),
            'country'     => trim((string) ($customer['country'] ?? '')),
            'city'        => trim((string) ($customer['city'] ?? '')) ?: null,
            'email'       => trim((string) ($customer['email'] ?? '')) ?: null,
            'whatsapp'    => trim((string) ($customer['whatsapp'] ?? '')),
            'company'     => trim((string) ($customer['company'] ?? '')) ?: null,
            'notes'       => trim((string) ($customer['notes'] ?? '')) ?: null,
            'status'      => 'new',
            'items_count' => count($lines),
            'ip_address'  => $ip !== null ? @inet_pton($ip) ?: null : null,
            'user_agent'  => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
        ]);

        $reference = sprintf(
            '%s-%s-%06d',
            (string) setting('inquiry_prefix', 'VT'),
            date('Y'),
            $id
        );

        db_execute('UPDATE inquiries SET reference = :ref WHERE id = :id', [
            'ref' => $reference,
            'id'  => $id,
        ]);

        foreach ($lines as $line) {
            $priced = $line['price'] !== null && $line['price_mode'] === 'show';

            db_insert('inquiry_items', [
                'inquiry_id'   => $id,
                'product_id'   => (int) $line['id'],
                'product_name' => (string) $line['name'],
                'product_url'  => (string) $line['url'],
                'unit_price'   => $priced ? (float) $line['price'] : null,
                'currency'     => $priced ? (string) $line['currency'] : null,
                'quantity'     => (int) $line['quantity'],
            ]);
        }

        return ['id' => $id, 'reference' => $reference];
    });
}

/**
 * Build the WhatsApp message body for one inquiry.
 *
 * Plain text only: WhatsApp's own link format carries no markup, and a
 * long message survives URL encoding better without it.
 *
 * @param array<int,array<string,mixed>> $lines
 * @param array<string,mixed>            $customer
 */
function inquiry_whatsapp_message(string $reference, array $customer, array $lines): string
{
    $company  = (string) setting('company_name', 'Optical Cargo');
    $siteName = (string) setting('site_name', 'Veloura Tec');

    $parts   = [];
    $parts[] = 'New inquiry — ' . $siteName . ' (' . $company . ')';
    $parts[] = 'Reference: ' . $reference;
    $parts[] = '';
    $parts[] = 'PRODUCTS';

    $index = 1;
    foreach ($lines as $line) {
        $price = ($line['price'] !== null && $line['price_mode'] === 'show')
            ? (string) money((float) $line['price'], (string) $line['currency'])
            : 'Price on request';

        $parts[] = sprintf('%d) %s', $index++, $line['name']);
        $parts[] = sprintf('   Quantity: %d  |  %s', (int) $line['quantity'], $price);
        $parts[] = '   ' . $line['url'];
    }

    $totals = cart_totals($lines);
    if ($totals['all_priced'] && $totals['total'] !== null) {
        $parts[] = '';
        $parts[] = 'Listed total: ' . money($totals['total'], $totals['currency'])
            . ' (before shipping and any applicable charges)';
    }

    $parts[] = '';
    $parts[] = 'CUSTOMER';
    $parts[] = 'Name: ' . $customer['full_name'];

    if (!empty($customer['company'])) {
        $parts[] = 'Company / clinic: ' . $customer['company'];
    }

    $parts[] = 'Country: ' . $customer['country']
        . (!empty($customer['city']) ? ' — ' . $customer['city'] : '');
    $parts[] = 'WhatsApp: ' . $customer['whatsapp'];

    if (!empty($customer['email'])) {
        $parts[] = 'Email: ' . $customer['email'];
    }

    if (!empty($customer['notes'])) {
        $parts[] = '';
        $parts[] = 'NOTES';
        $parts[] = (string) $customer['notes'];
    }

    return implode("\n", $parts);
}

/**
 * Simple per-session submission throttle.
 *
 * Not a substitute for a real rate limiter, but it stops the form being
 * hammered from one browser without adding infrastructure.
 */
function inquiry_throttled(int $seconds = 20): bool
{
    $last = (int) ($_SESSION['inquiry_last_submit'] ?? 0);

    return $last > 0 && (time() - $last) < $seconds;
}

function inquiry_mark_submitted(): void
{
    $_SESSION['inquiry_last_submit'] = time();
}

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

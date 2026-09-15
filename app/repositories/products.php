<?php
/**
 * Veloura Tec — Product queries.
 *
 * Phase 1 provides the read functions the homepage needs. Search,
 * filtering, sorting and the full CRUD arrive in later phases and belong
 * in this same file.
 */

declare(strict_types=1);

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Published products marked as featured.
 *
 * @return array<int,array<string,mixed>>
 */
function products_featured(int $limit = 8): array
{
    return db_all(
        'SELECT p.*, c.name AS category_name, c.slug AS category_slug
           FROM products p
           JOIN categories c ON c.id = p.category_id
          WHERE p.status = "published" AND p.is_featured = 1
          ORDER BY p.created_at DESC
          LIMIT ' . max(1, $limit)
    );
}

/**
 * Most recently added published products.
 *
 * @return array<int,array<string,mixed>>
 */
function products_latest(int $limit = 8): array
{
    return db_all(
        'SELECT p.*, c.name AS category_name, c.slug AS category_slug
           FROM products p
           JOIN categories c ON c.id = p.category_id
          WHERE p.status = "published"
          ORDER BY p.created_at DESC
          LIMIT ' . max(1, $limit)
    );
}

/**
 * @return array<string,mixed>|null
 */
function product_by_slug(string $slug, bool $publishedOnly = true): ?array
{
    $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug
              FROM products p
              JOIN categories c ON c.id = p.category_id
             WHERE p.slug = :slug';

    if ($publishedOnly) {
        $sql .= ' AND p.status = "published"';
    }

    return db_one($sql . ' LIMIT 1', ['slug' => $slug]);
}

/**
 * @return array<string,mixed>|null
 */
function product_by_id(int $id): ?array
{
    return db_one('SELECT * FROM products WHERE id = :id LIMIT 1', ['id' => $id]);
}

function products_count(string $status = 'published'): int
{
    if ($status === 'all') {
        return (int) db_value('SELECT COUNT(*) FROM products', [], 0);
    }

    return (int) db_value(
        'SELECT COUNT(*) FROM products WHERE status = :s',
        ['s' => $status],
        0
    );
}

/**
 * Public URL for a product. Kept in one place so the URL scheme can change
 * without touching templates.
 */
function product_url(array $product): string
{
    return url('product.php?slug=' . urlencode((string) $product['slug']));
}

function category_url(array $category): string
{
    return url('category.php?slug=' . urlencode((string) $category['slug']));
}

/**
 * The price label for a card or detail page: a formatted amount when the
 * product is priced and prices are enabled, otherwise the quote wording.
 */
function product_price_label(array $product): string
{
    $showGlobally = setting_bool('show_prices', true);
    $mode  = (string) ($product['price_mode'] ?? 'quote');
    $price = $product['price'];

    if (!$showGlobally || $mode !== 'show' || $price === null) {
        return t('common.price_on_request');
    }

    return (string) money((float) $price, (string) ($product['currency'] ?? 'USD'));
}

// =====================================================================
// Admin listing (Phase 3)
// =====================================================================

/**
 * Paginated, searchable, filterable product list for the admin table.
 *
 * Every user-supplied value is bound. The ORDER BY clause is chosen from
 * a fixed whitelist — it can never be assembled from request input.
 *
 * @param array{search?:string,category?:int,status?:string,featured?:string,sort?:string} $filters
 * @return array{rows:array<int,array<string,mixed>>, total:int, pages:int, page:int}
 */
function products_admin_list(array $filters = [], int $page = 1, int $perPage = 20): array
{
    $where  = [];
    $params = [];

    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        // MySQL's native prepared statements bind each placeholder once, so
        // a repeated :search would fail — every occurrence gets its own name.
        $where[] = '(p.name LIKE :search_name OR p.slug LIKE :search_slug OR p.brand LIKE :search_brand)';
        $term = '%' . $search . '%';
        $params['search_name']  = $term;
        $params['search_slug']  = $term;
        $params['search_brand'] = $term;
    }

    $categoryId = (int) ($filters['category'] ?? 0);
    if ($categoryId > 0) {
        $where[] = 'p.category_id = :category';
        $params['category'] = $categoryId;
    }

    $status = (string) ($filters['status'] ?? '');
    if (in_array($status, ['draft', 'published'], true)) {
        $where[] = 'p.status = :status';
        $params['status'] = $status;
    }

    if (($filters['featured'] ?? '') === '1') {
        $where[] = 'p.is_featured = 1';
    }

    $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

    // Whitelist: the request can only pick a key, never supply SQL.
    $sortMap = [
        'newest'    => 'p.created_at DESC',
        'oldest'    => 'p.created_at ASC',
        'name'      => 'p.name ASC',
        'name_desc' => 'p.name DESC',
        'price'     => 'p.price IS NULL, p.price ASC',
        'price_desc'=> 'p.price IS NULL, p.price DESC',
        'category'  => 'c.name ASC, p.name ASC',
    ];
    $orderBy = $sortMap[(string) ($filters['sort'] ?? 'newest')] ?? $sortMap['newest'];

    $total = (int) db_value(
        'SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id' . $clause,
        $params,
        0
    );

    $perPage = max(1, min(100, $perPage));
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = max(1, min($page, $pages));
    $offset  = ($page - 1) * $perPage;

    $rows = db_all(
        'SELECT p.*, c.name AS category_name
           FROM products p
           JOIN categories c ON c.id = p.category_id'
        . $clause
        . ' ORDER BY ' . $orderBy
        . ' LIMIT ' . $perPage . ' OFFSET ' . $offset,
        $params
    );

    return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
}

// =====================================================================
// Admin write operations (Phase 3)
// =====================================================================

/**
 * Validate a submitted product.
 *
 * @param array<string,mixed> $data
 * @return array<string,string> field => message
 */
function product_validate(array $data): array
{
    $errors = [];

    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        $errors['name'] = 'Please enter a product name.';
    } elseif (mb_strlen($name) > 190) {
        $errors['name'] = 'The name must be 190 characters or fewer.';
    }

    $slug = trim((string) ($data['slug'] ?? ''));
    if ($slug !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        $errors['slug'] = 'The slug may contain lowercase letters, numbers and hyphens only.';
    }

    $categoryId = (int) ($data['category_id'] ?? 0);
    if ($categoryId <= 0) {
        $errors['category_id'] = 'Please choose a category.';
    } elseif (category_by_id($categoryId) === null) {
        $errors['category_id'] = 'That category no longer exists.';
    }

    $priceMode = (string) ($data['price_mode'] ?? 'quote');
    if (!in_array($priceMode, ['show', 'quote'], true)) {
        $errors['price_mode'] = 'Choose either "Show price" or "Request a quote".';
    }

    $price = trim((string) ($data['price'] ?? ''));
    if ($price !== '') {
        if (!is_numeric($price)) {
            $errors['price'] = 'The price must be a number, for example 7900 or 7900.50.';
        } elseif ((float) $price < 0) {
            $errors['price'] = 'The price cannot be negative.';
        } elseif ((float) $price > 99999999.99) {
            $errors['price'] = 'The price is too large.';
        }
    } elseif ($priceMode === 'show') {
        $errors['price'] = 'Enter a price, or switch this product to "Request a quote".';
    }

    $currency = strtoupper(trim((string) ($data['currency'] ?? 'USD')));
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
        $errors['currency'] = 'Use a three-letter currency code, for example USD or EUR.';
    }

    $status = (string) ($data['status'] ?? 'draft');
    if (!in_array($status, ['draft', 'published'], true)) {
        $errors['status'] = 'Choose either Draft or Published.';
    }

    if (mb_strlen((string) ($data['short_description'] ?? '')) > 400) {
        $errors['short_description'] = 'The short description must be 400 characters or fewer.';
    }
    if (mb_strlen((string) ($data['seo_title'] ?? '')) > 190) {
        $errors['seo_title'] = 'The SEO title must be 190 characters or fewer.';
    }
    if (mb_strlen((string) ($data['seo_description'] ?? '')) > 320) {
        $errors['seo_description'] = 'The SEO description must be 320 characters or fewer.';
    }

    return $errors;
}

/**
 * Build the product column set from validated input.
 *
 * @param array<string,mixed> $data
 * @return array<string,mixed>
 */
function product_columns(array $data, ?int $id = null): array
{
    $name = trim((string) ($data['name'] ?? ''));
    $slug = trim((string) ($data['slug'] ?? ''));
    $slug = $slug !== '' ? slugify($slug) : slugify($name);

    $price = trim((string) ($data['price'] ?? ''));

    return [
        'category_id'       => (int) ($data['category_id'] ?? 0),
        'name'              => $name,
        'slug'              => db_unique_slug('products', $slug, $id),
        'short_description' => trim((string) ($data['short_description'] ?? '')) ?: null,
        'description'       => trim((string) ($data['description'] ?? '')) ?: null,
        'price'             => $price === '' ? null : round((float) $price, 2),
        'currency'          => strtoupper(trim((string) ($data['currency'] ?? 'USD'))),
        'price_mode'        => (string) ($data['price_mode'] ?? 'quote'),
        'main_image'        => $data['main_image'] ?? null,
        'main_image_alt'    => trim((string) ($data['main_image_alt'] ?? '')) ?: null,
        'specs'             => product_specs_encode($data['specs'] ?? null),
        'brand'             => trim((string) ($data['brand'] ?? '')) ?: null,
        'origin_country'    => trim((string) ($data['origin_country'] ?? '')) ?: null,
        'warranty'          => trim((string) ($data['warranty'] ?? '')) ?: null,
        'installation'      => trim((string) ($data['installation'] ?? '')) ?: null,
        'training'          => trim((string) ($data['training'] ?? '')) ?: null,
        'support'           => trim((string) ($data['support'] ?? '')) ?: null,
        'is_featured'       => !empty($data['is_featured']) ? 1 : 0,
        'status'            => (string) ($data['status'] ?? 'draft'),
        'seo_title'         => trim((string) ($data['seo_title'] ?? '')) ?: null,
        'seo_description'   => trim((string) ($data['seo_description'] ?? '')) ?: null,
    ];
}

/**
 * @param array<string,mixed> $data
 */
function product_create(array $data): int
{
    return db_insert('products', product_columns($data));
}

/**
 * @param array<string,mixed> $data
 */
function product_update(int $id, array $data): int
{
    return db_update('products', product_columns($data, $id), $id);
}

/**
 * Delete a product and every file it owns.
 *
 * product_images, product_videos and product_related rows go with it via
 * ON DELETE CASCADE; inquiry_items keep their snapshot with product_id
 * set to NULL, so inquiry history survives.
 */
function product_delete(int $id): bool
{
    $product = product_by_id($id);

    if ($product === null) {
        return false;
    }

    // Collect file paths before the cascade removes the rows.
    $files = [$product['main_image'] ?? null];

    foreach (product_images($id) as $image) {
        $files[] = $image['path'];
    }
    foreach (product_videos($id) as $video) {
        if ($video['video_type'] === 'file') {
            $files[] = $video['source'];
        }
    }

    db_delete('products', $id);

    foreach (array_filter($files) as $file) {
        upload_delete((string) $file);
    }

    return true;
}

function product_toggle_status(int $id): string
{
    db_execute(
        'UPDATE products
            SET status = IF(status = "published", "draft", "published")
          WHERE id = :id',
        ['id' => $id]
    );

    return (string) db_value('SELECT status FROM products WHERE id = :id', ['id' => $id], 'draft');
}

function product_toggle_featured(int $id): bool
{
    db_execute('UPDATE products SET is_featured = 1 - is_featured WHERE id = :id', ['id' => $id]);

    return (bool) db_value('SELECT is_featured FROM products WHERE id = :id', ['id' => $id], 0);
}

// =====================================================================
// Specifications
// =====================================================================

/**
 * Decode the stored specs JSON into label/value pairs.
 *
 * Bad or legacy JSON never throws — it degrades to an empty list, so one
 * malformed row cannot break the product page.
 *
 * @return array<int,array{label:string,value:string}>
 */
function product_specs_decode(?string $json): array
{
    if ($json === null || trim($json) === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }

    $specs = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }

        $label = trim((string) ($row['label'] ?? ''));
        $value = trim((string) ($row['value'] ?? ''));

        if ($label !== '' || $value !== '') {
            $specs[] = ['label' => $label, 'value' => $value];
        }
    }

    return $specs;
}

/**
 * Encode submitted spec rows for storage. Empty rows are dropped.
 *
 * Accepts either an already-built array of pairs, or the parallel
 * spec_label[] / spec_value[] arrays the form submits.
 */
function product_specs_encode(mixed $specs): ?string
{
    if (is_string($specs)) {
        return trim($specs) !== '' ? $specs : null;
    }

    if (!is_array($specs)) {
        return null;
    }

    $clean = [];
    foreach ($specs as $row) {
        if (!is_array($row)) {
            continue;
        }

        $label = trim((string) ($row['label'] ?? ''));
        $value = trim((string) ($row['value'] ?? ''));

        if ($label === '' && $value === '') {
            continue;
        }

        $clean[] = [
            'label' => mb_substr($label, 0, 120),
            'value' => mb_substr($value, 0, 400),
        ];
    }

    if ($clean === []) {
        return null;
    }

    return json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Pair up the form's spec_label[] / spec_value[] arrays.
 *
 * @return array<int,array{label:string,value:string}>
 */
function product_specs_from_request(): array
{
    $labels = $_POST['spec_label'] ?? [];
    $values = $_POST['spec_value'] ?? [];

    if (!is_array($labels)) {
        return [];
    }

    $specs = [];
    foreach ($labels as $index => $label) {
        $specs[] = [
            'label' => (string) $label,
            'value' => (string) (is_array($values) ? ($values[$index] ?? '') : ''),
        ];
    }

    return $specs;
}

// =====================================================================
// Gallery images
// =====================================================================

/**
 * @return array<int,array<string,mixed>>
 */
function product_images(int $productId): array
{
    return db_all(
        'SELECT * FROM product_images WHERE product_id = :id ORDER BY sort_order, id',
        ['id' => $productId]
    );
}

function product_image_add(int $productId, string $path, string $altText = ''): int
{
    $next = (int) db_value(
        'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_images WHERE product_id = :id',
        ['id' => $productId],
        0
    );

    return db_insert('product_images', [
        'product_id' => $productId,
        'path'       => $path,
        'alt_text'   => trim($altText) ?: null,
        'sort_order' => $next,
    ]);
}

/**
 * Delete one gallery image, its file included.
 *
 * Scoped by product id so an id from another product cannot be targeted.
 * If the image had been promoted to the product's main image, that
 * reference is cleared first — otherwise the product would be left
 * pointing at a file that no longer exists.
 */
function product_image_delete(int $imageId, int $productId): bool
{
    $image = db_one(
        'SELECT * FROM product_images WHERE id = :id AND product_id = :product',
        ['id' => $imageId, 'product' => $productId]
    );

    if ($image === null) {
        return false;
    }

    db_execute(
        'UPDATE products
            SET main_image = NULL, main_image_alt = NULL
          WHERE id = :product AND main_image = :path',
        ['product' => $productId, 'path' => $image['path']]
    );

    db_delete('product_images', $imageId);
    upload_delete((string) $image['path']);

    return true;
}

/**
 * Apply a new display order from the form's sort_order[id] => position map.
 *
 * @param array<int|string,mixed> $order
 */
function product_images_reorder(int $productId, array $order): void
{
    foreach ($order as $imageId => $position) {
        db_execute(
            'UPDATE product_images SET sort_order = :pos WHERE id = :id AND product_id = :product',
            ['pos' => max(0, (int) $position), 'id' => (int) $imageId, 'product' => $productId]
        );
    }
}

/**
 * Update alt text for one gallery image.
 */
function product_image_set_alt(int $imageId, int $productId, string $altText): void
{
    db_execute(
        'UPDATE product_images SET alt_text = :alt WHERE id = :id AND product_id = :product',
        ['alt' => trim($altText) ?: null, 'id' => $imageId, 'product' => $productId]
    );
}

/**
 * Promote a gallery image to the product's main image.
 */
function product_image_make_main(int $imageId, int $productId): bool
{
    $image = db_one(
        'SELECT * FROM product_images WHERE id = :id AND product_id = :product',
        ['id' => $imageId, 'product' => $productId]
    );

    if ($image === null) {
        return false;
    }

    db_execute(
        'UPDATE products SET main_image = :path, main_image_alt = :alt WHERE id = :id',
        [
            'path' => $image['path'],
            'alt'  => $image['alt_text'],
            'id'   => $productId,
        ]
    );

    return true;
}

// =====================================================================
// Videos
// =====================================================================

/**
 * @return array<int,array<string,mixed>>
 */
function product_videos(int $productId): array
{
    return db_all(
        'SELECT * FROM product_videos WHERE product_id = :id ORDER BY sort_order, id',
        ['id' => $productId]
    );
}

/**
 * Extract a YouTube video id from any of its URL shapes.
 */
function youtube_id(string $url): ?string
{
    $url = trim($url);

    if ($url === '') {
        return null;
    }

    // A bare id was pasted.
    if (preg_match('~^[A-Za-z0-9_-]{11}$~', $url)) {
        return $url;
    }

    $patterns = [
        '~youtube\.com/watch\?(?:.*&)?v=([A-Za-z0-9_-]{11})~',
        '~youtu\.be/([A-Za-z0-9_-]{11})~',
        '~youtube\.com/embed/([A-Za-z0-9_-]{11})~',
        '~youtube\.com/shorts/([A-Za-z0-9_-]{11})~',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }

    return null;
}

/**
 * The privacy-friendly embed URL for a YouTube video.
 */
function youtube_embed_url(string $url): ?string
{
    $id = youtube_id($url);

    return $id === null ? null : 'https://www.youtube-nocookie.com/embed/' . $id;
}

/**
 * Validate a Facebook video URL. Facebook's plugin accepts the page URL
 * itself, so this only checks that it is a facebook.com/fb.watch link.
 */
function facebook_video_url(string $url): ?string
{
    $url  = trim($url);
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));

    if ($host === '') {
        return null;
    }

    $allowed = ['facebook.com', 'www.facebook.com', 'm.facebook.com', 'web.facebook.com', 'fb.watch'];

    if (!in_array($host, $allowed, true)) {
        return null;
    }

    if (!in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
        return null;
    }

    return $url;
}

/**
 * Add a video to a product.
 *
 * @param 'youtube'|'facebook'|'file' $type
 * @return string|null null on success, otherwise the reason
 */
function product_video_add(int $productId, string $type, string $source, string $title = ''): ?string
{
    $source = trim($source);

    if (!in_array($type, ['youtube', 'facebook', 'file'], true)) {
        return 'Unknown video type.';
    }

    if ($type === 'youtube') {
        if (youtube_id($source) === null) {
            return 'That does not look like a YouTube link.';
        }
    } elseif ($type === 'facebook') {
        if (facebook_video_url($source) === null) {
            return 'That does not look like a Facebook video link.';
        }
    } elseif ($source === '') {
        return 'No video file was stored.';
    }

    $next = (int) db_value(
        'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_videos WHERE product_id = :id',
        ['id' => $productId],
        0
    );

    db_insert('product_videos', [
        'product_id' => $productId,
        'video_type' => $type,
        'source'     => mb_substr($source, 0, 500),
        'title'      => trim($title) ?: null,
        'sort_order' => $next,
    ]);

    return null;
}

/**
 * Delete a video, removing the uploaded file when it owns one.
 */
function product_video_delete(int $videoId, int $productId): bool
{
    $video = db_one(
        'SELECT * FROM product_videos WHERE id = :id AND product_id = :product',
        ['id' => $videoId, 'product' => $productId]
    );

    if ($video === null) {
        return false;
    }

    db_delete('product_videos', $videoId);

    if ($video['video_type'] === 'file') {
        upload_delete((string) $video['source']);
    }

    return true;
}

// =====================================================================
// Related products
// =====================================================================

/**
 * @return array<int,array<string,mixed>>
 */
function product_related(int $productId): array
{
    return db_all(
        'SELECT p.id, p.name, p.slug, p.main_image
           FROM product_related r
           JOIN products p ON p.id = r.related_product_id
          WHERE r.product_id = :id
          ORDER BY r.sort_order, p.name',
        ['id' => $productId]
    );
}

/**
 * Replace the related-product set in one transaction.
 *
 * @param array<int,int|string> $relatedIds
 */
function product_related_set(int $productId, array $relatedIds): void
{
    db_transaction(static function () use ($productId, $relatedIds): void {
        db_execute('DELETE FROM product_related WHERE product_id = :id', ['id' => $productId]);

        $position = 0;
        foreach (array_unique(array_map('intval', $relatedIds)) as $relatedId) {
            // A product cannot relate to itself, and the target must exist.
            if ($relatedId <= 0 || $relatedId === $productId || product_by_id($relatedId) === null) {
                continue;
            }

            db_insert('product_related', [
                'product_id'         => $productId,
                'related_product_id' => $relatedId,
                'sort_order'         => $position++,
            ]);
        }
    });
}

/**
 * Candidate products for the related-products picker.
 *
 * @return array<int,array<string,mixed>>
 */
function products_selectable(int $excludeId = 0): array
{
    return db_all(
        'SELECT p.id, p.name, c.name AS category_name
           FROM products p
           JOIN categories c ON c.id = p.category_id
          WHERE p.id <> :exclude
          ORDER BY c.name, p.name',
        ['exclude' => $excludeId]
    );
}

// =====================================================================
// Storefront listing (Phase 4)
// =====================================================================

/**
 * Published products for the shop and category pages.
 *
 * Mirrors products_admin_list() but is hard-scoped to published rows, so
 * a draft can never leak through a crafted query string.
 *
 * @param array{search?:string,category?:int,sort?:string} $filters
 * @return array{rows:array<int,array<string,mixed>>, total:int, pages:int, page:int}
 */
function products_public_list(array $filters = [], int $page = 1, ?int $perPage = null): array
{
    $where  = ['p.status = "published"'];
    $params = [];

    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        // One placeholder per occurrence — MySQL's native prepares bind
        // each name exactly once.
        $where[] = '(p.name LIKE :s_name OR p.short_description LIKE :s_short OR p.brand LIKE :s_brand)';
        $term = '%' . $search . '%';
        $params['s_name']  = $term;
        $params['s_short'] = $term;
        $params['s_brand'] = $term;
    }

    $categoryId = (int) ($filters['category'] ?? 0);
    if ($categoryId > 0) {
        $where[] = 'p.category_id = :category';
        $params['category'] = $categoryId;
    }

    $clause = ' WHERE ' . implode(' AND ', $where);

    // The request picks a key; it can never supply SQL.
    $sortMap = [
        'newest'     => 'p.is_featured DESC, p.created_at DESC',
        'name'       => 'p.name ASC',
        'name_desc'  => 'p.name DESC',
        'price'      => 'p.price IS NULL, p.price ASC',
        'price_desc' => 'p.price IS NULL, p.price DESC',
    ];
    $orderBy = $sortMap[(string) ($filters['sort'] ?? 'newest')] ?? $sortMap['newest'];

    $total = (int) db_value(
        'SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id' . $clause,
        $params,
        0
    );

    $perPage = max(1, min(60, $perPage ?? setting_int('products_per_page', 12)));
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = max(1, min($page, $pages));

    $rows = db_all(
        'SELECT p.*, c.name AS category_name, c.slug AS category_slug
           FROM products p
           JOIN categories c ON c.id = p.category_id'
        . $clause
        . ' ORDER BY ' . $orderBy
        . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
        $params
    );

    return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
}

/**
 * Related products for the product page, falling back to others in the
 * same category when none were picked by hand.
 *
 * @return array<int,array<string,mixed>>
 */
function product_related_published(int $productId, int $categoryId, int $limit = 4): array
{
    $rows = db_all(
        'SELECT p.*, c.name AS category_name, c.slug AS category_slug
           FROM product_related r
           JOIN products p ON p.id = r.related_product_id
           JOIN categories c ON c.id = p.category_id
          WHERE r.product_id = :id AND p.status = "published"
          ORDER BY r.sort_order
          LIMIT ' . max(1, $limit),
        ['id' => $productId]
    );

    if ($rows !== []) {
        return $rows;
    }

    return db_all(
        'SELECT p.*, c.name AS category_name, c.slug AS category_slug
           FROM products p
           JOIN categories c ON c.id = p.category_id
          WHERE p.status = "published" AND p.category_id = :category AND p.id <> :id
          ORDER BY p.is_featured DESC, p.created_at DESC
          LIMIT ' . max(1, $limit),
        ['category' => $categoryId, 'id' => $productId]
    );
}

/**
 * Product structured data. Only fields backed by real values are
 * included — nothing about ratings, reviews or approvals is invented.
 *
 * @param array<string,mixed> $product
 * @return array<string,mixed>
 */
function product_schema(array $product): array
{
    $schema = [
        '@context' => 'https://schema.org',
        '@type'    => 'Product',
        'name'     => (string) $product['name'],
        'url'      => product_url($product),
        'category' => (string) ($product['category_name'] ?? ''),
    ];

    if (!empty($product['short_description'])) {
        $schema['description'] = excerpt((string) $product['short_description'], 300);
    }
    if (!empty($product['main_image'])) {
        $schema['image'] = upload_url($product['main_image']);
    }
    if (!empty($product['brand'])) {
        $schema['brand'] = ['@type' => 'Brand', 'name' => (string) $product['brand']];
    }

    // A quote-only product has no public price, so it advertises
    // availability for enquiry rather than a fabricated offer.
    if ($product['price'] !== null && $product['price_mode'] === 'show' && setting_bool('show_prices', true)) {
        $schema['offers'] = [
            '@type'         => 'Offer',
            'price'         => number_format((float) $product['price'], 2, '.', ''),
            'priceCurrency' => (string) $product['currency'],
            'availability'  => 'https://schema.org/InStock',
            'url'           => product_url($product),
        ];
    }

    return $schema;
}

<?php
/**
 * Veloura Tec — Category queries.
 *
 * Pages never write SQL: they call these functions.
 */

declare(strict_types=1);

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Published categories for the storefront, with a live product count.
 *
 * @return array<int,array<string,mixed>>
 */
function categories_published(?int $limit = null): array
{
    $sql = 'SELECT c.*,
                   (SELECT COUNT(*) FROM products p
                     WHERE p.category_id = c.id AND p.status = "published") AS product_count
              FROM categories c
             WHERE c.is_published = 1
             ORDER BY c.sort_order, c.name';

    if ($limit !== null) {
        $sql .= ' LIMIT ' . max(1, $limit);   // integer-cast, never user text
    }

    return db_all($sql);
}

/**
 * Every category, published or not — admin listings.
 *
 * @return array<int,array<string,mixed>>
 */
function categories_all(): array
{
    return db_all(
        'SELECT c.*,
                (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
           FROM categories c
          ORDER BY c.sort_order, c.name'
    );
}

/**
 * @return array<string,mixed>|null
 */
function category_by_slug(string $slug, bool $publishedOnly = true): ?array
{
    $sql = 'SELECT * FROM categories WHERE slug = :slug';
    if ($publishedOnly) {
        $sql .= ' AND is_published = 1';
    }

    return db_one($sql . ' LIMIT 1', ['slug' => $slug]);
}

/**
 * @return array<string,mixed>|null
 */
function category_by_id(int $id): ?array
{
    return db_one('SELECT * FROM categories WHERE id = :id LIMIT 1', ['id' => $id]);
}

function categories_count(): int
{
    return (int) db_value('SELECT COUNT(*) FROM categories', [], 0);
}

// =====================================================================
// Admin write operations (Phase 3)
// =====================================================================

/**
 * Validate a submitted category.
 *
 * @param array<string,mixed> $data
 * @return array<string,string> field => message, empty when valid
 */
function category_validate(array $data, ?int $ignoreId = null): array
{
    $errors = [];

    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        $errors['name'] = 'Please enter a category name.';
    } elseif (mb_strlen($name) > 160) {
        $errors['name'] = 'The name must be 160 characters or fewer.';
    }

    $slug = trim((string) ($data['slug'] ?? ''));
    if ($slug !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        $errors['slug'] = 'The slug may contain lowercase letters, numbers and hyphens only.';
    }

    if (mb_strlen((string) ($data['seo_title'] ?? '')) > 190) {
        $errors['seo_title'] = 'The SEO title must be 190 characters or fewer.';
    }
    if (mb_strlen((string) ($data['seo_description'] ?? '')) > 320) {
        $errors['seo_description'] = 'The SEO description must be 320 characters or fewer.';
    }

    $sortOrder = $data['sort_order'] ?? 0;
    if (!is_numeric($sortOrder) || (int) $sortOrder < 0 || (int) $sortOrder > 65535) {
        $errors['sort_order'] = 'Sort order must be a number between 0 and 65535.';
    }

    unset($ignoreId);   // reserved for future cross-record rules

    return $errors;
}

/**
 * Build the column set shared by insert and update.
 *
 * @param array<string,mixed> $data
 * @return array<string,mixed>
 */
function category_columns(array $data, ?int $id = null): array
{
    $name = trim((string) ($data['name'] ?? ''));
    $slug = trim((string) ($data['slug'] ?? ''));
    $slug = $slug !== '' ? slugify($slug) : slugify($name);

    return [
        'name'            => $name,
        'slug'            => db_unique_slug('categories', $slug, $id),
        'description'     => trim((string) ($data['description'] ?? '')) ?: null,
        'image'           => $data['image'] ?? null,
        'sort_order'      => (int) ($data['sort_order'] ?? 0),
        'is_published'    => !empty($data['is_published']) ? 1 : 0,
        'seo_title'       => trim((string) ($data['seo_title'] ?? '')) ?: null,
        'seo_description' => trim((string) ($data['seo_description'] ?? '')) ?: null,
    ];
}

/**
 * @param array<string,mixed> $data
 */
function category_create(array $data): int
{
    return db_insert('categories', category_columns($data));
}

/**
 * @param array<string,mixed> $data
 */
function category_update(int $id, array $data): int
{
    return db_update('categories', category_columns($data, $id), $id);
}

/**
 * Delete a category.
 *
 * The schema uses ON DELETE RESTRICT, so a category holding products
 * cannot be removed — this reports that as a message rather than letting
 * a foreign key error reach the user.
 *
 * @return string|null null on success, otherwise the reason
 */
function category_delete(int $id): ?string
{
    $category = category_by_id($id);

    if ($category === null) {
        return 'That category no longer exists.';
    }

    $productCount = (int) db_value(
        'SELECT COUNT(*) FROM products WHERE category_id = :id',
        ['id' => $id],
        0
    );

    if ($productCount > 0) {
        return sprintf(
            'This category still holds %d product%s. Move or delete them first.',
            $productCount,
            $productCount === 1 ? '' : 's'
        );
    }

    db_delete('categories', $id);
    upload_delete($category['image'] ?? null);

    return null;
}

/**
 * Flip the published flag. Returns the new state.
 */
function category_toggle_published(int $id): bool
{
    db_execute('UPDATE categories SET is_published = 1 - is_published WHERE id = :id', ['id' => $id]);

    return (bool) db_value('SELECT is_published FROM categories WHERE id = :id', ['id' => $id], 0);
}

/**
 * Categories as an id => name map, for <select> inputs.
 *
 * @return array<int,string>
 */
function categories_options(): array
{
    $options = [];

    foreach (db_all('SELECT id, name FROM categories ORDER BY sort_order, name') as $row) {
        $options[(int) $row['id']] = (string) $row['name'];
    }

    return $options;
}

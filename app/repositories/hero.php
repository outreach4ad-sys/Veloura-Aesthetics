<?php
/**
 * Veloura Tec — Hero slide queries.
 */

declare(strict_types=1);

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Published slides for the homepage hero, in display order.
 *
 * @return array<int,array<string,mixed>>
 */
function hero_slides_published(): array
{
    return db_all(
        'SELECT * FROM hero_slides WHERE is_published = 1 ORDER BY sort_order, id'
    );
}

/**
 * Every slide — admin listing.
 *
 * @return array<int,array<string,mixed>>
 */
function hero_slides_all(): array
{
    return db_all('SELECT * FROM hero_slides ORDER BY sort_order, id');
}

/**
 * @return array<string,mixed>|null
 */
function hero_slide_by_id(int $id): ?array
{
    return db_one('SELECT * FROM hero_slides WHERE id = :id LIMIT 1', ['id' => $id]);
}

// =====================================================================
// Admin write operations (Phase 3)
// =====================================================================

/**
 * Validate a submitted hero slide.
 *
 * @param array<string,mixed> $data
 * @return array<string,string>
 */
function hero_slide_validate(array $data): array
{
    $errors = [];

    $title = trim((string) ($data['title'] ?? ''));
    if ($title === '') {
        $errors['title'] = 'Please enter a headline.';
    } elseif (mb_strlen($title) > 190) {
        $errors['title'] = 'The headline must be 190 characters or fewer.';
    }

    if (mb_strlen((string) ($data['subtitle'] ?? '')) > 400) {
        $errors['subtitle'] = 'The supporting text must be 400 characters or fewer.';
    }

    foreach (['cta_primary_url' => 'primary', 'cta_secondary_url' => 'secondary'] as $field => $label) {
        $value = trim((string) ($data[$field] ?? ''));

        // Site-relative paths are fine; absolute URLs must be well formed.
        if ($value !== '' && str_contains($value, '://') && !filter_var($value, FILTER_VALIDATE_URL)) {
            $errors[$field] = 'Enter a valid ' . $label . ' link, or a path such as /shop.php.';
        }
    }

    $sortOrder = $data['sort_order'] ?? 0;
    if (!is_numeric($sortOrder) || (int) $sortOrder < 0 || (int) $sortOrder > 65535) {
        $errors['sort_order'] = 'Sort order must be a number between 0 and 65535.';
    }

    return $errors;
}

/**
 * @param array<string,mixed> $data
 * @return array<string,mixed>
 */
function hero_slide_columns(array $data): array
{
    return [
        'title'               => trim((string) ($data['title'] ?? '')),
        'subtitle'            => trim((string) ($data['subtitle'] ?? '')) ?: null,
        'image'               => $data['image'] ?? null,
        'image_alt'           => trim((string) ($data['image_alt'] ?? '')) ?: null,
        'cta_primary_label'   => trim((string) ($data['cta_primary_label'] ?? '')) ?: null,
        'cta_primary_url'     => trim((string) ($data['cta_primary_url'] ?? '')) ?: null,
        'cta_secondary_label' => trim((string) ($data['cta_secondary_label'] ?? '')) ?: null,
        'cta_secondary_url'   => trim((string) ($data['cta_secondary_url'] ?? '')) ?: null,
        'sort_order'          => (int) ($data['sort_order'] ?? 0),
        'is_published'        => !empty($data['is_published']) ? 1 : 0,
    ];
}

/**
 * @param array<string,mixed> $data
 */
function hero_slide_create(array $data): int
{
    return db_insert('hero_slides', hero_slide_columns($data));
}

/**
 * @param array<string,mixed> $data
 */
function hero_slide_update(int $id, array $data): int
{
    return db_update('hero_slides', hero_slide_columns($data), $id);
}

function hero_slide_delete(int $id): bool
{
    $slide = hero_slide_by_id($id);

    if ($slide === null) {
        return false;
    }

    db_delete('hero_slides', $id);
    upload_delete($slide['image'] ?? null);

    return true;
}

function hero_slide_toggle_published(int $id): bool
{
    db_execute('UPDATE hero_slides SET is_published = 1 - is_published WHERE id = :id', ['id' => $id]);

    return (bool) db_value('SELECT is_published FROM hero_slides WHERE id = :id', ['id' => $id], 0);
}

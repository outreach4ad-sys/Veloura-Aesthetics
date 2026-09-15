<?php
/**
 * Veloura Tec — Media library.
 *
 * The library is the uploads/ directory itself rather than a separate
 * table: files are discovered from disk and cross-referenced against the
 * rows that point at them. That keeps a file and its database record from
 * ever drifting apart, and makes orphaned files visible.
 */

declare(strict_types=1);

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Every path currently referenced by a database row.
 *
 * @return array<string,string> path => what uses it
 */
function media_referenced_paths(): array
{
    $used = [];

    foreach (db_all('SELECT main_image, name FROM products WHERE main_image IS NOT NULL') as $row) {
        $used[(string) $row['main_image']] = 'Product main image — ' . $row['name'];
    }

    foreach (db_all('SELECT path FROM product_images') as $row) {
        $used[(string) $row['path']] = 'Product gallery';
    }

    foreach (db_all('SELECT source FROM product_videos WHERE video_type = "file"') as $row) {
        $used[(string) $row['source']] = 'Product video';
    }

    foreach (db_all('SELECT image, name FROM categories WHERE image IS NOT NULL') as $row) {
        $used[(string) $row['image']] = 'Category image — ' . $row['name'];
    }

    foreach (db_all('SELECT image, title FROM hero_slides WHERE image IS NOT NULL') as $row) {
        $used[(string) $row['image']] = 'Hero slide — ' . $row['title'];
    }

    foreach (db_all('SELECT setting_value FROM site_settings WHERE setting_type = "image"') as $row) {
        $value = (string) ($row['setting_value'] ?? '');
        if ($value !== '') {
            $used[$value] = 'Site setting';
        }
    }

    return $used;
}

/**
 * List the files in one upload folder, newest first.
 *
 * @param 'products'|'categories'|'hero'|'all' $folder
 * @param 'all'|'used'|'orphan'                $filter
 * @return array<int,array{path:string,folder:string,name:string,size:int,modified:int,used_by:?string,is_video:bool}>
 */
function media_list(string $folder = 'all', string $filter = 'all'): array
{
    $folders = $folder === 'all' ? UPLOAD_FOLDERS : [$folder];
    $used    = media_referenced_paths();
    $items   = [];

    foreach ($folders as $name) {
        if (!in_array($name, UPLOAD_FOLDERS, true)) {
            continue;
        }

        $directory = VELOURA_ROOT . '/uploads/' . $name;
        if (!is_dir($directory)) {
            continue;
        }

        foreach ((array) scandir($directory) as $file) {
            if (!is_string($file) || $file === '.' || $file === '..' || str_starts_with($file, '.')) {
                continue;
            }

            $absolute = $directory . '/' . $file;
            if (!is_file($absolute)) {
                continue;
            }

            $extension = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
            $relative  = 'uploads/' . $name . '/' . $file;
            $usedBy    = $used[$relative] ?? null;

            if ($filter === 'used' && $usedBy === null) {
                continue;
            }
            if ($filter === 'orphan' && $usedBy !== null) {
                continue;
            }

            $items[] = [
                'path'     => $relative,
                'folder'   => $name,
                'name'     => $file,
                'size'     => (int) filesize($absolute),
                'modified' => (int) filemtime($absolute),
                'used_by'  => $usedBy,
                'is_video' => in_array($extension, ['mp4', 'webm'], true),
            ];
        }
    }

    usort($items, static fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

    return $items;
}

/**
 * Totals for the media page header.
 *
 * @return array{files:int,bytes:int,orphans:int}
 */
function media_stats(): array
{
    $items   = media_list();
    $bytes   = 0;
    $orphans = 0;

    foreach ($items as $item) {
        $bytes += $item['size'];
        if ($item['used_by'] === null) {
            $orphans++;
        }
    }

    return ['files' => count($items), 'bytes' => $bytes, 'orphans' => $orphans];
}

/**
 * Delete a media file.
 *
 * A file still referenced by a row is refused, so deleting from the media
 * page can never leave a product pointing at a missing image.
 *
 * @return string|null null on success, otherwise the reason
 */
function media_delete(string $path): ?string
{
    $used = media_referenced_paths();

    if (isset($used[$path])) {
        return 'That file is still in use (' . $used[$path] . '). Remove it there first.';
    }

    return upload_delete($path) ? null : 'The file could not be deleted.';
}

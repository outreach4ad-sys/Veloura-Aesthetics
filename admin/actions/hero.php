<?php
/**
 * Veloura Tec — Admin action: hero slide operations.
 */

require dirname(__DIR__) . '/_auth.php';

if (!is_post()) {
    redirect('admin/hero.php');
}

csrf_guard();

$op       = (string) input('op', '');
$id       = input_int('id');
$returnTo = admin_safe_return(input('return_to'), 'admin/hero.php');

if ($id <= 0 || hero_slide_by_id($id) === null) {
    flash('error', 'That slide no longer exists.');
    redirect('admin/hero.php');
}

switch ($op) {
    case 'delete':
        hero_slide_delete($id);
        flash('success', 'Slide deleted.');

        if (str_contains($returnTo, 'hero-edit.php')) {
            $returnTo = 'admin/hero.php';
        }
        break;

    case 'toggle':
        $published = hero_slide_toggle_published($id);
        flash('success', $published ? 'Slide published.' : 'Slide hidden.');
        break;

    default:
        flash('error', 'Unknown action.');
}

redirect($returnTo);

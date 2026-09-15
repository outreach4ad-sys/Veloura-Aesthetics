<?php
/**
 * Veloura Tec — Admin action: category operations.
 *
 * POST only, CSRF-checked, admin-only. Every branch ends in a redirect so
 * a refresh cannot repeat the operation.
 */

require dirname(__DIR__) . '/_auth.php';

if (!is_post()) {
    redirect('admin/categories.php');
}

csrf_guard();

$op       = (string) input('op', '');
$id       = input_int('id');
$returnTo = admin_safe_return(input('return_to'), 'admin/categories.php');

switch ($op) {
    case 'delete':
        $reason = category_delete($id);

        if ($reason === null) {
            flash('success', 'Category deleted.');
        } else {
            flash('error', $reason);
        }
        break;

    case 'toggle':
        if (category_by_id($id) === null) {
            flash('error', 'That category no longer exists.');
            break;
        }

        $published = category_toggle_published($id);
        flash('success', $published ? 'Category published.' : 'Category hidden.');
        break;

    default:
        flash('error', 'Unknown action.');
}

redirect($returnTo);

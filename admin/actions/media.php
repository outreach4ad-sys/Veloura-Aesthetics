<?php
/**
 * Veloura Tec — Admin action: media operations.
 */

require dirname(__DIR__) . '/_auth.php';

if (!is_post()) {
    redirect('admin/media.php');
}

csrf_guard();

$op       = (string) input('op', '');
$returnTo = admin_safe_return(input('return_to'), 'admin/media.php');

if ($op === 'delete') {
    // media_delete() refuses a referenced file; upload_delete() refuses any
    // path that resolves outside uploads/.
    $reason = media_delete((string) input('path', ''));

    if ($reason === null) {
        flash('success', 'File deleted.');
    } else {
        flash('error', $reason);
    }
} else {
    flash('error', 'Unknown action.');
}

redirect($returnTo);

<?php
/**
 * Veloura Tec — Admin sign-out.
 *
 * POST only and CSRF-checked, so a stray link or image tag cannot log an
 * administrator out.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

if (!is_post()) {
    redirect('admin/login.php');
}

csrf_guard();
auth_logout();

session_boot();
flash('info', t('auth.signed_out'));

redirect('admin/login.php');

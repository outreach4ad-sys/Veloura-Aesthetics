<?php
/**
 * Veloura Tec — Customer sign out (POST only, CSRF-checked).
 */

require __DIR__ . '/app/bootstrap.php';

if (!is_post()) {
    redirect('account.php');
}

csrf_guard();
customer_logout();
flash('info', 'You have been signed out.');
redirect('index.php');

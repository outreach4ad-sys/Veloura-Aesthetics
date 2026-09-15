<?php
/**
 * Veloura Tec — Admin bootstrap.
 *
 * Every protected admin page requires THIS file (not app/bootstrap.php
 * directly). It boots the application and then refuses the request unless
 * a valid admin session exists.
 *
 *     require __DIR__ . '/_auth.php';
 */

require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/_form.php';

security_headers();

// Admin pages must never be indexed or cached by intermediaries.
if (!headers_sent()) {
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
}

auth_guard();

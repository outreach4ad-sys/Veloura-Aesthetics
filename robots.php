<?php
/**
 * Veloura Tec — robots.txt (dynamic).
 *
 * Served at /robots.txt via the .htaccess rewrite. The Sitemap line is built
 * from the current request host, so it carries no hardcoded domain and is
 * correct on whatever domain the site runs on.
 */

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

$lines = [
    'User-agent: *',
    'Allow: /',
    '',
    'Disallow: /admin/',
    'Disallow: /app/',
    'Disallow: /database/',
    'Disallow: /storage/',
    'Disallow: /includes/',
    'Disallow: /api/',
    'Disallow: /inquiry.php',
    '',
    'Sitemap: ' . abs_url('sitemap.xml'),
];

echo implode("\n", $lines) . "\n";

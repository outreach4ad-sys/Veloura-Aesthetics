<?php
/**
 * Veloura Tec — Cart resolution endpoint.
 *
 * The browser keeps only product ids and quantities. It POSTs them here
 * and gets back the authoritative product data from the database, so
 * prices and names shown in the cart can never come from the client.
 *
 * POST JSON: {"items":[{"id":12,"quantity":2}, ...]}
 * Returns:   {"lines":[...], "totals":{...}, "dropped":[ids]}
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (!is_post()) {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

// Read-only endpoint, but still same-origin only: a cross-site page has
// no business enumerating the catalog through it.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $expected = parse_url((string) config('base_url', ''), PHP_URL_HOST);
    $actual   = parse_url($origin, PHP_URL_HOST);

    if ($expected !== null && $actual !== $expected) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden.']);
        exit;
    }
}

$body  = file_get_contents('php://input') ?: '';
$input = json_decode($body, true);

if (!is_array($input) || !isset($input['items']) || !is_array($input['items'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request body.']);
    exit;
}

$resolved = cart_resolve($input['items']);
$totals   = cart_totals($resolved['lines']);

// Send only what the cart page renders — no internal columns.
$lines = array_map(static fn (array $line): array => [
    'id'          => (int) $line['id'],
    'name'        => (string) $line['name'],
    'category'    => (string) $line['category_name'],
    'url'         => (string) $line['url'],
    'image'       => upload_url($line['main_image']),
    'image_alt'   => (string) ($line['main_image_alt'] ?: $line['name']),
    'quantity'    => (int) $line['quantity'],
    'price_label' => (string) $line['price_label'],
    'line_total'  => $line['line_total'] !== null
        ? money((float) $line['line_total'], (string) $line['currency'])
        : null,
], $resolved['lines']);

echo json_encode([
    'lines'   => $lines,
    'dropped' => $resolved['dropped'],
    'totals'  => [
        'units'      => $totals['units'],
        'all_priced' => $totals['all_priced'],
        'total'      => $totals['total'] !== null
            ? money($totals['total'], $totals['currency'])
            : null,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

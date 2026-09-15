<?php
/**
 * Veloura Tec — General utility functions.
 *
 * Nothing in this file touches the database. Escaping, URLs, formatting,
 * slugs, flash messages and the translation layer live here.
 */

declare(strict_types=1);

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

// =====================================================================
// Configuration access
// =====================================================================

/**
 * Store the configuration array once at bootstrap.
 *
 * @param array<string,mixed>|null $config
 * @return array<string,mixed>
 */
function config_init(?array $config = null): array
{
    static $store = [];

    if ($config !== null) {
        $store = $config;
    }

    return $store;
}

/**
 * Read a configuration value with dot notation: config('db.host').
 */
function config(string $key, mixed $default = null): mixed
{
    $store = config_init();
    $value = $store;

    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function is_dev(): bool
{
    return config('env', 'production') === 'development';
}

// =====================================================================
// Output escaping
// =====================================================================

/**
 * Escape a value for HTML output. Use on EVERY echoed dynamic value.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Escape for use inside a JavaScript context or a data-* attribute.
 */
function e_json(mixed $value): string
{
    return htmlspecialchars(
        (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ENT_QUOTES,
        'UTF-8'
    );
}

// =====================================================================
// URLs and assets
// =====================================================================

/**
 * Build an absolute site URL: url('shop.php') or url('/product.php?slug=x').
 */
function url(string $path = ''): string
{
    $base = rtrim((string) config('base_url', ''), '/');
    $path = ltrim($path, '/');

    return $path === '' ? $base . '/' : $base . '/' . $path;
}

/**
 * Asset URL with a cache-busting stamp based on file modification time.
 */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = VELOURA_ROOT . '/' . $path;
    $stamp = is_file($file) ? '?v=' . filemtime($file) : '';

    return url($path) . $stamp;
}

/**
 * Resolve a stored upload path to a public URL, with a fallback image.
 */
function upload_url(?string $path, string $fallback = 'assets/img/placeholder.svg'): string
{
    $path = trim((string) $path);

    if ($path === '' || !is_file(VELOURA_ROOT . '/' . ltrim($path, '/'))) {
        return url($fallback);
    }

    return url($path);
}

/**
 * The current request URI path, used for marking active navigation links.
 */
function current_path(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($uri, PHP_URL_PATH);

    return is_string($path) ? $path : '/';
}

function is_current(string $file): bool
{
    return basename(current_path()) === ltrim($file, '/');
}

/**
 * Send a redirect and stop. Always used after a successful POST.
 */
function redirect(string $path): never
{
    $target = str_starts_with($path, 'http') ? $path : url($path);
    header('Location: ' . $target, true, 302);
    exit;
}

// =====================================================================
// Input
// =====================================================================

function input(string $key, ?string $default = null): ?string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

function input_int(string $key, int $default = 0): int
{
    $value = $_POST[$key] ?? $_GET[$key] ?? null;

    return is_numeric($value) ? (int) $value : $default;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

// =====================================================================
// Strings and formatting
// =====================================================================

/**
 * Build a URL-safe slug. Non-ASCII characters are transliterated where
 * the intl/iconv extension allows, then stripped.
 */
function slugify(string $text): string
{
    $text = trim($text);

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }

    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');

    return $text !== '' ? $text : 'item-' . bin2hex(random_bytes(3));
}

/**
 * Truncate on a word boundary for card excerpts and meta descriptions.
 */
function excerpt(?string $text, int $limit = 160): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)) ?? '');

    if ($text === '' || mb_strlen($text) <= $limit) {
        return $text;
    }

    $cut = mb_substr($text, 0, $limit);
    $lastSpace = mb_strrpos($cut, ' ');

    return rtrim($lastSpace !== false ? mb_substr($cut, 0, $lastSpace) : $cut, ' ,.;:') . '…';
}

/**
 * Format a price using the configured currency.
 * Returns null when there is no price to show (quote-only products).
 */
function money(?float $amount, ?string $currency = null): ?string
{
    if ($amount === null) {
        return null;
    }

    $currency = $currency ?: (string) setting('default_currency', 'USD');
    $symbol   = (string) setting('currency_symbol', '$');

    return $symbol . number_format($amount, 2) . ' ' . $currency;
}

function format_date(?string $datetime, string $format = 'M j, Y'): string
{
    if (!$datetime) {
        return '';
    }

    $ts = strtotime($datetime);

    return $ts ? date($format, $ts) : '';
}

// =====================================================================
// Flash messages
// =====================================================================

/**
 * Queue a one-time message for the next request.
 *
 * @param 'success'|'error'|'info' $type
 */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Pull and clear all queued flash messages.
 *
 * @return array<int,array{type:string,message:string}>
 */
function flash_take(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

// =====================================================================
// Translation layer (single locale today, multi-locale ready)
// =====================================================================

/**
 * Load a language file into the in-memory dictionary.
 */
function lang_load(string $locale): void
{
    $file = VELOURA_APP . '/lang/' . basename($locale) . '.php';

    if (!is_file($file)) {
        $file = VELOURA_APP . '/lang/en.php';
    }

    lang_dictionary(require $file);
}

/**
 * @param array<string,string>|null $strings
 * @return array<string,string>
 */
function lang_dictionary(?array $strings = null): array
{
    static $store = [];

    if ($strings !== null) {
        $store = $strings;
    }

    return $store;
}

/**
 * Translate a key. Falls back to the key itself so a missing string is
 * visible rather than blank.
 *
 * @param array<string,string|int> $replace
 */
function t(string $key, array $replace = []): string
{
    $dictionary = lang_dictionary();
    $text = $dictionary[$key] ?? $key;

    foreach ($replace as $placeholder => $value) {
        $text = str_replace(':' . $placeholder, (string) $value, $text);
    }

    return $text;
}

/**
 * Text direction for the current locale — 'ltr' today, ready for 'rtl'.
 */
function direction(): string
{
    return (string) config('direction', 'ltr');
}

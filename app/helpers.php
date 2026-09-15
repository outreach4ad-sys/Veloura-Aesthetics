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
 * Build a site URL for an internal link, form or redirect.
 *
 * ORIGIN-RELATIVE: it carries only the path (plus any subfolder prefix from
 * base_url), never a scheme or host. So every link works on whatever domain
 * the site is served from — the final domain, a hosting temporary URL, an IP,
 * http or https — with no configuration. Nothing hardcodes a domain.
 *
 * For the few places that must be absolute (canonical, Open Graph, sitemap,
 * the WhatsApp product links), use abs_url() / to_abs(), which build from the
 * current request host.
 */
function url(string $path = ''): string
{
    $prefix = base_path();
    $path   = ltrim($path, '/');

    return $path === '' ? ($prefix === '' ? '/' : $prefix . '/') : $prefix . '/' . $path;
}

/**
 * The scheme + host of the current request, e.g. "https://example.com".
 *
 * Honours X-Forwarded-Proto (Hostinger terminates TLS upstream). Returns ''
 * when there is no request (CLI), in which case abs_url() falls back to
 * base_url from config.
 */
function request_origin(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return '';
    }

    // Keep only a sane host[:port]; ignore anything unexpected in the header.
    if (!preg_match('/^[A-Za-z0-9.\-]+(:[0-9]+)?$/', $host)) {
        return '';
    }

    $https = (
        (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
    );

    return ($https ? 'https://' : 'http://') . $host;
}

/**
 * An absolute URL for the current domain: request host + url($path).
 *
 * Falls back to base_url from config when there is no request (CLI). Used
 * only where an absolute URL is required (SEO tags, sitemap, links that leave
 * the site such as the WhatsApp message).
 */
function abs_url(string $path = ''): string
{
    $origin = request_origin();

    if ($origin === '') {
        // CLI fallback: use the configured base_url's scheme+host if present.
        $configured = (string) config('base_url', '');
        $scheme = (string) parse_url($configured, PHP_URL_SCHEME);
        $host   = (string) parse_url($configured, PHP_URL_HOST);
        $origin = ($scheme !== '' && $host !== '') ? $scheme . '://' . $host : '';
    }

    return $origin . url($path);
}

/**
 * Turn any link (relative or absolute) into an absolute URL for the current
 * domain. An already-absolute http(s) URL is returned unchanged.
 */
function to_abs(string $link): string
{
    $link = trim($link);

    if ($link === '') {
        return abs_url();
    }
    if (preg_match('#^https?://#i', $link)) {
        return $link;
    }

    return abs_url(ltrim($link, '/'));
}

/**
 * The site's path prefix, derived from the PATH part of base_url only.
 *
 * Empty when the site is at the domain root, "/sub" when it lives in a
 * subfolder. Ignores the scheme and host, so assets built on top of it
 * resolve against whatever origin the page is actually served from.
 */
function base_path(): string
{
    static $prefix = null;

    if ($prefix === null) {
        $path   = (string) parse_url((string) config('base_url', ''), PHP_URL_PATH);
        $prefix = rtrim($path, '/');   // '' for root, '/sub' for a subfolder
    }

    return $prefix;
}

/**
 * URL for a static asset (CSS, JS, image), with a cache-busting stamp.
 *
 * Deliberately ORIGIN-RELATIVE, not absolute: it uses only the path prefix
 * from base_url, never its scheme or host. So the stylesheet loads from the
 * same origin the page is served on — whether that is the final domain, a
 * hosting temporary URL, an IP, or http vs https. A base_url whose domain
 * or protocol does not match the browsing origin no longer leaves the page
 * unstyled. (Canonical, Open Graph and sitemap URLs stay absolute via
 * url(), because SEO needs the real domain.)
 */
function asset(string $path): string
{
    $path  = ltrim($path, '/');
    $file  = VELOURA_ROOT . '/' . $path;
    $stamp = is_file($file) ? '?v=' . filemtime($file) : '';

    return base_path() . '/' . $path . $stamp;
}

/**
 * Origin-relative URL for an uploaded/static file path (no cache stamp).
 * Same rationale as asset(): resolves against the current origin.
 */
function asset_path(string $path): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/**
 * Resolve a stored upload path to a public URL, with a fallback image.
 */
function upload_url(?string $path, string $fallback = 'assets/img/placeholder.svg'): string
{
    $path = trim((string) $path);

    // Origin-relative (asset_path), so in-page images load from the current
    // origin regardless of the configured domain/protocol — same reasoning
    // as asset(). Absolute URLs for images belong in og:image / schema,
    // which build them explicitly from base_url.
    if ($path === '' || !is_file(VELOURA_ROOT . '/' . ltrim($path, '/'))) {
        return asset_path($fallback);
    }

    return asset_path($path);
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

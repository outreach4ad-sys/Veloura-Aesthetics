<?php
/**
 * Veloura Tec — Site settings.
 *
 * All configurable business values (WhatsApp number, currency, contact
 * details, social links, policy copy) live in the site_settings table and
 * are read through setting(). Nothing is hardcoded in two places.
 */

declare(strict_types=1);

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Load every setting once per request into a static cache.
 *
 * @return array<string,string|null>
 */
function settings_all(bool $refresh = false): array
{
    static $cache = null;

    if ($cache !== null && !$refresh) {
        return $cache;
    }

    $cache = [];

    foreach (db_all('SELECT setting_key, setting_value FROM site_settings') as $row) {
        $cache[(string) $row['setting_key']] = $row['setting_value'];
    }

    return $cache;
}

/**
 * Read one setting.
 */
function setting(string $key, mixed $default = null): mixed
{
    $all = settings_all();
    $value = $all[$key] ?? null;

    return ($value === null || $value === '') ? $default : $value;
}

/**
 * Read a boolean setting ('1'/'0' in the database).
 */
function setting_bool(string $key, bool $default = false): bool
{
    $value = setting($key);

    return $value === null ? $default : in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
}

function setting_int(string $key, int $default = 0): int
{
    $value = setting($key);

    return is_numeric($value) ? (int) $value : $default;
}

/**
 * Persist one setting. Creates the key if it does not exist yet.
 */
function setting_set(string $key, ?string $value): void
{
    db_execute(
        'INSERT INTO site_settings (setting_key, setting_value, label)
              VALUES (:key, :value, :label)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        ['key' => $key, 'value' => $value, 'label' => $key]
    );

    settings_all(true);
}

/**
 * Settings for one admin panel group, in display order.
 *
 * @return array<int,array<string,mixed>>
 */
function settings_group(string $group): array
{
    return db_all(
        'SELECT * FROM site_settings WHERE setting_group = :g ORDER BY sort_order, setting_key',
        ['g' => $group]
    );
}

/**
 * True when a setting still holds an unreplaced placeholder, so the UI can
 * hide it rather than print "[PLACEHOLDER: ...]" to visitors.
 */
function setting_is_placeholder(string $key): bool
{
    return str_starts_with((string) setting($key, ''), '[PLACEHOLDER');
}

/**
 * A setting that is safe to display publicly: placeholders return the
 * fallback instead of leaking the bracketed marker.
 */
function setting_public(string $key, string $fallback = ''): string
{
    if (setting_is_placeholder($key)) {
        return $fallback;
    }

    return (string) setting($key, $fallback);
}

/**
 * The configured WhatsApp number, digits only, ready for a wa.me link.
 * Returns null while the placeholder is still in place.
 */
function whatsapp_number(): ?string
{
    if (setting_is_placeholder('whatsapp_number')) {
        return null;
    }

    $digits = preg_replace('/\D+/', '', (string) setting('whatsapp_number', '')) ?? '';

    return $digits !== '' ? $digits : null;
}

/**
 * Build a wa.me link with an optional prefilled message.
 */
function whatsapp_link(string $message = ''): ?string
{
    $number = whatsapp_number();

    if ($number === null) {
        return null;
    }

    return 'https://wa.me/' . $number . ($message !== '' ? '?text=' . rawurlencode($message) : '');
}

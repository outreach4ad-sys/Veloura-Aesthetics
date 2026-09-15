<?php
/**
 * Veloura Tec — Page metadata.
 *
 * A page sets its metadata before including the header:
 *
 *     page_meta([
 *         'title'       => 'Shop',
 *         'description' => '…',
 *         'canonical'   => url('shop.php'),
 *     ]);
 *
 * header.php then renders title, meta description, canonical, Open Graph
 * and Twitter tags from one source, so metadata is never duplicated.
 */

declare(strict_types=1);

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Set and/or read the current page metadata.
 *
 * @param array<string,mixed>|null $meta
 * @return array<string,mixed>
 */
function page_meta(?array $meta = null): array
{
    static $store = [];

    if ($meta !== null) {
        $store = array_merge($store, $meta);
    }

    return $store;
}

function meta_get(string $key, mixed $default = null): mixed
{
    return page_meta()[$key] ?? $default;
}

/**
 * The full <title> value: "Page — Veloura Tec", or just the site name on
 * the homepage.
 */
function meta_title(): string
{
    $siteName = (string) setting('site_name', 'Veloura Tec');
    $title    = trim((string) meta_get('title', ''));

    if ($title === '') {
        return $siteName . ' — ' . (string) setting('site_tagline', '');
    }

    return $title . ' — ' . $siteName;
}

function meta_description(): string
{
    return excerpt(
        (string) meta_get('description', (string) setting('site_description', '')),
        300
    );
}

function meta_canonical(): string
{
    return (string) meta_get('canonical', url(ltrim(current_path(), '/')));
}

function meta_image(): string
{
    $image = meta_get('image');

    if (is_string($image) && $image !== '') {
        return str_starts_with($image, 'http') ? $image : url(ltrim($image, '/'));
    }

    $default = (string) setting('seo_default_og_image', '');

    return $default !== '' ? url(ltrim($default, '/')) : url('assets/img/logo.png');
}

/**
 * Whether search engines should index this page.
 */
function meta_robots(): string
{
    return (string) meta_get('robots', 'index, follow');
}

/**
 * Register a JSON-LD block to be printed in the footer.
 *
 * @param array<string,mixed>|null $schema
 * @return array<int,array<string,mixed>>
 */
function schema_add(?array $schema = null): array
{
    static $store = [];

    if ($schema !== null) {
        $store[] = $schema;
    }

    return $store;
}

/**
 * Organization schema, emitted on every page.
 *
 * Only fields backed by real settings are included — no invented
 * certifications, ratings or credentials.
 *
 * @return array<string,mixed>
 */
function schema_organization(): array
{
    $schema = [
        '@context' => 'https://schema.org',
        '@type'    => 'Organization',
        'name'     => (string) setting('company_name', 'Optical Cargo'),
        'alternateName' => (string) setting('site_name', 'Veloura Tec'),
        'url'      => url(),
        'logo'     => url((string) setting('site_logo', 'assets/img/logo.png')),
        'description' => (string) setting('site_description', ''),
    ];

    $email = setting_public('contact_email');
    $phone = setting_public('contact_phone');

    if ($email !== '' || $phone !== '') {
        $point = ['@type' => 'ContactPoint', 'contactType' => 'sales'];
        if ($email !== '') {
            $point['email'] = $email;
        }
        if ($phone !== '') {
            $point['telephone'] = $phone;
        }
        $schema['contactPoint'] = [$point];
    }

    $social = array_values(array_filter([
        setting_public('social_facebook'),
        setting_public('social_instagram'),
        setting_public('social_linkedin'),
        setting_public('social_youtube'),
    ]));

    if ($social !== []) {
        $schema['sameAs'] = $social;
    }

    return $schema;
}

/**
 * Render every registered JSON-LD block.
 */
function schema_render(): string
{
    $out = '';

    // The nonce matches the one in the CSP header, so these inline blocks
    // are allowed while injected inline scripts are not.
    $nonce = function_exists('csp_nonce') ? csp_nonce() : '';
    $nonceAttr = $nonce !== '' ? ' nonce="' . $nonce . '"' : '';

    foreach (schema_add() as $schema) {
        $json = json_encode(
            $schema,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );
        $out .= '<script type="application/ld+json"' . $nonceAttr . '>' . $json . '</script>' . "\n";
    }

    return $out;
}

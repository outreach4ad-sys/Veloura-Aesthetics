<?php
/**
 * Veloura Tec — XML sitemap.
 *
 * Served at /sitemap.xml via the .htaccess rewrite. Lists only pages that
 * exist and are indexable: the homepage, the shop, every published
 * category, and every published product. Draft products and the
 * noindex inquiry cart are deliberately excluded.
 *
 * Content pages (about, contact, …) are added here as they are built, so
 * the sitemap never advertises a URL that returns 404.
 */

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

// Serve as XML, and let the browser/crawler cache it briefly.
header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');

/**
 * One <url> entry. $lastmod is an ISO-8601 date or null.
 */
$entry = static function (string $loc, ?string $lastmod, string $changefreq, string $priority): string {
    $xml = "  <url>\n";
    $xml .= '    <loc>' . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc>\n";
    if ($lastmod !== null) {
        $xml .= '    <lastmod>' . htmlspecialchars($lastmod, ENT_XML1, 'UTF-8') . "</lastmod>\n";
    }
    $xml .= '    <changefreq>' . $changefreq . "</changefreq>\n";
    $xml .= '    <priority>' . $priority . "</priority>\n";
    $xml .= "  </url>\n";

    return $xml;
};

$toDate = static fn (?string $ts): ?string => $ts ? date('Y-m-d', strtotime($ts)) : null;

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Homepage
echo $entry(abs_url(), null, 'weekly', '1.0');

// Shop
echo $entry(abs_url('shop.php'), null, 'daily', '0.9');

// Static content pages (only those that exist and are indexable)
$staticPages = [
    'about.php'            => '0.5',
    'solutions.php'        => '0.5',
    'contact.php'          => '0.5',
    'faq.php'              => '0.5',
    'privacy.php'          => '0.3',
    'terms.php'            => '0.3',
    'shipping-returns.php' => '0.3',
];
foreach ($staticPages as $page => $priority) {
    if (is_file(__DIR__ . '/' . $page)) {
        echo $entry(abs_url($page), null, 'monthly', $priority);
    }
}

// Published categories, newest activity first is irrelevant here — order
// by the same sort the storefront uses.
foreach (db_all(
    'SELECT slug, updated_at FROM categories WHERE is_published = 1 ORDER BY sort_order, name'
) as $category) {
    echo $entry(
        abs_url('category.php?slug=' . urlencode((string) $category['slug'])),
        $toDate($category['updated_at']),
        'weekly',
        '0.7'
    );
}

// Published products
foreach (db_all(
    'SELECT slug, updated_at FROM products WHERE status = "published" ORDER BY updated_at DESC'
) as $product) {
    echo $entry(
        abs_url('product.php?slug=' . urlencode((string) $product['slug'])),
        $toDate($product['updated_at']),
        'weekly',
        '0.8'
    );
}

echo '</urlset>' . "\n";

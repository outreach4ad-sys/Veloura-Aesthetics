<?php
/**
 * Veloura Tec — Breadcrumb trail.
 *
 * Usage:
 *     $crumbs = [['label' => 'Shop', 'url' => url('shop.php')], ['label' => 'Product']];
 *     require VELOURA_ROOT . '/includes/breadcrumbs.php';
 *
 * Also registers BreadcrumbList structured data for the page.
 */

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

if (empty($crumbs) || !is_array($crumbs)) {
    return;
}

$trail = array_merge([['label' => t('nav.home'), 'url' => url()]], $crumbs);

$schemaItems = [];
foreach ($trail as $position => $crumb) {
    $item = [
        '@type'    => 'ListItem',
        'position' => $position + 1,
        'name'     => $crumb['label'],
    ];
    if (!empty($crumb['url'])) {
        // Schema needs absolute URLs; the visible <a> below stays relative.
        $item['item'] = to_abs((string) $crumb['url']);
    }
    $schemaItems[] = $item;
}

schema_add([
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => $schemaItems,
]);
?>
<nav class="breadcrumbs" aria-label="Breadcrumb">
  <ol class="breadcrumbs__list">
    <?php foreach ($trail as $index => $crumb): ?>
      <li class="breadcrumbs__item">
        <?php if (!empty($crumb['url']) && $index < count($trail) - 1): ?>
          <a href="<?= e($crumb['url']) ?>"><?= e($crumb['label']) ?></a>
        <?php else: ?>
          <span aria-current="page"><?= e($crumb['label']) ?></span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
</nav>

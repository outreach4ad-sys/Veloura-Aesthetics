<?php
/**
 * Veloura Tec — Category listing.
 */

require __DIR__ . '/app/bootstrap.php';

$slug     = (string) input('slug', '');
$category = $slug !== '' ? category_by_slug($slug) : null;

if ($category === null) {
    http_response_code(404);
    page_meta(['title' => 'Category not found', 'robots' => 'noindex, follow']);
    require __DIR__ . '/includes/header.php';
    ?>
    <section class="section">
      <div class="container">
        <div class="empty-state">
          <h1>Category not found</h1>
          <p>The category you are looking for is not available.</p>
          <p><a class="btn btn--primary" href="<?= e(url('shop.php')) ?>">Browse all equipment</a></p>
        </div>
      </div>
    </section>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$filters = [
    'category' => (int) $category['id'],
    'sort'     => (string) input('sort', 'newest'),
];

$result = products_public_list($filters, max(1, input_int('page', 1)));

page_meta([
    'title'       => $category['seo_title'] ?: $category['name'],
    'description' => $category['seo_description']
        ?: excerpt($category['description'] ?: ('Professional ' . $category['name'] . ' equipment for clinics and aesthetic centers.'), 200),
    'canonical'   => url('category.php?slug=' . urlencode((string) $category['slug'])),
    'robots'      => $result['page'] > 1 ? 'noindex, follow' : 'index, follow',
    'image'       => $category['image'] ? upload_url($category['image']) : null,
]);

require __DIR__ . '/includes/header.php';

$crumbs = [
    ['label' => t('nav.shop'), 'url' => url('shop.php')],
    ['label' => $category['name']],
];
require __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section">
  <div class="container">

    <div class="section__head">
      <span class="section__eyebrow">Category</span>
      <h1><?= e($category['name']) ?></h1>
      <?php if (!empty($category['description'])): ?>
        <p class="section__lead"><?= e($category['description']) ?></p>
      <?php endif; ?>
    </div>

    <div class="category-toolbar">
      <p class="shop-count">
        <?= (int) $result['total'] ?> <?= (int) $result['total'] === 1 ? 'product' : 'products' ?>
      </p>

      <form method="get" action="<?= e(url('category.php')) ?>">
        <input type="hidden" name="slug" value="<?= e($category['slug']) ?>">
        <label class="sr-only" for="cat-sort">Sort by</label>
        <select class="field__select" id="cat-sort" name="sort" data-auto-submit>
          <?php
          $sortLabels = [
              'newest'     => 'Newest first',
              'name'       => 'Name A-Z',
              'name_desc'  => 'Name Z-A',
              'price'      => 'Price low to high',
              'price_desc' => 'Price high to low',
          ];
          foreach ($sortLabels as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $filters['sort'] === $value ? 'selected' : '' ?>>
              <?= e($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <noscript><button class="btn btn--ghost btn--sm" type="submit">Sort</button></noscript>
      </form>
    </div>

    <?php if ($result['rows'] === []): ?>
      <div class="empty-state">
        <p>No products have been published in this category yet.</p>
        <p><a class="btn btn--primary" href="<?= e(url('shop.php')) ?>">Browse all equipment</a></p>
      </div>
    <?php else: ?>
      <div class="card-grid">
        <?php foreach ($result['rows'] as $product): ?>
          <?php require __DIR__ . '/includes/product-card.php'; ?>
        <?php endforeach; ?>
      </div>

      <?php
      $paginationBase  = url('category.php');
      $paginationQuery = array_filter([
          'slug' => $category['slug'],
          'sort' => $filters['sort'] !== 'newest' ? $filters['sort'] : null,
      ]);
      $currentPage = (int) $result['page'];
      $totalPages  = (int) $result['pages'];
      require __DIR__ . '/includes/pagination.php';
      ?>
    <?php endif; ?>

  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

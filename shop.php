<?php
/**
 * Veloura Tec — Shop / all products.
 *
 * Built in Phase 4 because the inquiry flow needs somewhere for the
 * "Add to Inquiry" button to live. The full visual treatment arrives with
 * the design system; the data layer and behaviour here are final.
 */

require __DIR__ . '/app/bootstrap.php';

$filters = [
    'search'   => (string) input('search', ''),
    'category' => input_int('category'),
    'sort'     => (string) input('sort', 'newest'),
];

$result     = products_public_list($filters, max(1, input_int('page', 1)));
$categories = categories_published();

page_meta([
    'title'       => 'Shop',
    'description' => 'Browse professional aesthetic equipment for clinics, beauty centers and wellness facilities.',
    'canonical'   => url('shop.php'),
    // Filtered and paged views must not compete with the canonical listing.
    'robots'      => ($filters['search'] !== '' || $filters['category'] > 0 || $result['page'] > 1)
        ? 'noindex, follow'
        : 'index, follow',
]);

require __DIR__ . '/includes/header.php';

$crumbs = [['label' => t('nav.shop')]];
require __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section">
  <div class="container">

    <div class="section__head">
      <span class="section__eyebrow">Equipment</span>
      <h1>Professional aesthetic equipment</h1>
      <p class="section__lead">
        Systems and devices for aesthetic clinics, beauty centers and wellness facilities.
        Add what you need to your inquiry list and send it to our team for a quote.
      </p>
    </div>

    <form class="shop-filters" method="get" action="<?= e(url('shop.php')) ?>" role="search">
      <div class="shop-filters__field shop-filters__field--grow">
        <label class="sr-only" for="shop-search">Search products</label>
        <input class="field__input" type="search" id="shop-search" name="search"
               value="<?= e($filters['search']) ?>" placeholder="Search equipment by name or brand">
      </div>

      <div class="shop-filters__field">
        <label class="sr-only" for="shop-category">Category</label>
        <select class="field__select" id="shop-category" name="category" data-auto-submit>
          <option value="">All categories</option>
          <?php foreach ($categories as $category): ?>
            <option value="<?= (int) $category['id'] ?>"
              <?= $filters['category'] === (int) $category['id'] ? 'selected' : '' ?>>
              <?= e($category['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="shop-filters__field">
        <label class="sr-only" for="shop-sort">Sort by</label>
        <select class="field__select" id="shop-sort" name="sort" data-auto-submit>
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
      </div>

      <button class="btn btn--dark btn--sm" type="submit">Apply</button>
      <?php if ($filters['search'] !== '' || $filters['category'] > 0 || $filters['sort'] !== 'newest'): ?>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('shop.php')) ?>">Reset</a>
      <?php endif; ?>
    </form>

    <p class="shop-count">
      <?= (int) $result['total'] ?> <?= (int) $result['total'] === 1 ? 'product' : 'products' ?>
      <?php if ($filters['search'] !== ''): ?>
        matching &ldquo;<?= e($filters['search']) ?>&rdquo;
      <?php endif; ?>
    </p>

    <?php if ($result['rows'] === []): ?>
      <div class="empty-state">
        <p>
          <?php if ($filters['search'] !== '' || $filters['category'] > 0): ?>
            No products match your search. Try a different term or clear the filters.
          <?php else: ?>
            No products have been published yet.
          <?php endif; ?>
        </p>
        <?php if ($filters['search'] !== '' || $filters['category'] > 0): ?>
          <p><a class="btn btn--ghost" href="<?= e(url('shop.php')) ?>">Clear filters</a></p>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="card-grid">
        <?php foreach ($result['rows'] as $product): ?>
          <?php require __DIR__ . '/includes/product-card.php'; ?>
        <?php endforeach; ?>
      </div>

      <?php
      $paginationBase  = url('shop.php');
      $paginationQuery = array_filter([
          'search'   => $filters['search'],
          'category' => $filters['category'] ?: null,
          'sort'     => $filters['sort'] !== 'newest' ? $filters['sort'] : null,
      ]);
      $currentPage = (int) $result['page'];
      $totalPages  = (int) $result['pages'];
      require __DIR__ . '/includes/pagination.php';
      ?>
    <?php endif; ?>

  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

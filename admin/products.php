<?php
/**
 * Veloura Tec — Admin: product list with search, filters and sorting.
 */

require __DIR__ . '/_auth.php';

$filters = [
    'search'   => (string) input('search', ''),
    'category' => input_int('category'),
    'status'   => (string) input('status', ''),
    'featured' => (string) input('featured', ''),
    'sort'     => (string) input('sort', 'newest'),
];

$result = products_admin_list($filters, max(1, input_int('page', 1)), 20);
$categoryOptions = categories_options();

$pageTitle = t('admin.products');
require __DIR__ . '/_layout.php';
?>

<div class="admin-toolbar">
  <p class="admin-lead">
    <?= (int) $result['total'] ?> product<?= (int) $result['total'] === 1 ? '' : 's' ?> found.
  </p>
  <a class="btn btn--primary" href="<?= e(url('admin/product-edit.php')) ?>">Add product</a>
</div>

<form class="filter-bar" method="get" action="<?= e(url('admin/products.php')) ?>">
  <div class="filter-bar__field filter-bar__field--grow">
    <label class="sr-only" for="filter-search">Search products</label>
    <input class="field__input" type="search" id="filter-search" name="search"
           value="<?= e($filters['search']) ?>" placeholder="Search by name, slug or brand">
  </div>

  <div class="filter-bar__field">
    <label class="sr-only" for="filter-category">Category</label>
    <select class="field__select" id="filter-category" name="category">
      <option value="">All categories</option>
      <?php foreach ($categoryOptions as $categoryId => $categoryName): ?>
        <option value="<?= (int) $categoryId ?>"
          <?= $filters['category'] === $categoryId ? 'selected' : '' ?>><?= e($categoryName) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="filter-bar__field">
    <label class="sr-only" for="filter-status">Status</label>
    <select class="field__select" id="filter-status" name="status">
      <option value="">Any status</option>
      <option value="published" <?= $filters['status'] === 'published' ? 'selected' : '' ?>>Published</option>
      <option value="draft"     <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
    </select>
  </div>

  <div class="filter-bar__field">
    <label class="sr-only" for="filter-sort">Sort</label>
    <select class="field__select" id="filter-sort" name="sort">
      <?php
      $sortLabels = [
          'newest'     => 'Newest first',
          'oldest'     => 'Oldest first',
          'name'       => 'Name A-Z',
          'name_desc'  => 'Name Z-A',
          'price'      => 'Price low to high',
          'price_desc' => 'Price high to low',
          'category'   => 'Category',
      ];
      foreach ($sortLabels as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= $filters['sort'] === $value ? 'selected' : '' ?>>
          <?= e($label) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <label class="check check--sm">
    <input type="checkbox" name="featured" value="1" <?= $filters['featured'] === '1' ? 'checked' : '' ?>>
    <span>Featured only</span>
  </label>

  <button class="btn btn--dark btn--sm" type="submit">Apply</button>
  <a class="btn btn--ghost btn--sm" href="<?= e(url('admin/products.php')) ?>">Reset</a>
</form>

<?php if ($result['rows'] === []): ?>
  <?php admin_empty(
      $filters['search'] !== '' || $filters['category'] > 0 || $filters['status'] !== ''
          ? 'No products match these filters.'
          : 'No products yet.',
      'Add the first product',
      url('admin/product-edit.php')
  ); ?>
<?php else: ?>
  <div class="table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th scope="col">Image</th>
          <th scope="col">Product</th>
          <th scope="col">Category</th>
          <th scope="col">Price</th>
          <th scope="col">Status</th>
          <th scope="col" class="col-actions">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($result['rows'] as $product): ?>
          <tr>
            <td>
              <img class="thumb" src="<?= e(upload_url($product['main_image'])) ?>"
                   alt="" width="48" height="48" loading="lazy">
            </td>
            <td>
              <a href="<?= e(url('admin/product-edit.php?id=' . (int) $product['id'])) ?>">
                <strong><?= e($product['name']) ?></strong>
              </a>
              <span class="cell-meta">
                <code><?= e($product['slug']) ?></code>
                <?= (int) $product['is_featured'] === 1 ? status_badge('Featured', 'accent') : '' ?>
              </span>
            </td>
            <td><?= e($product['category_name']) ?></td>
            <td>
              <?php if ($product['price'] !== null && $product['price_mode'] === 'show'): ?>
                <?= e(money((float) $product['price'], (string) $product['currency'])) ?>
              <?php else: ?>
                <span class="cell-muted">On request</span>
              <?php endif; ?>
            </td>
            <td>
              <?= $product['status'] === 'published'
                    ? status_badge('Published', 'success')
                    : status_badge('Draft', 'muted') ?>
            </td>
            <td class="col-actions">
              <div class="row-actions">
                <a class="btn btn--ghost btn--xs"
                   href="<?= e(url('admin/product-edit.php?id=' . (int) $product['id'])) ?>">Edit</a>

                <?php action_button(
                    'products',
                    'toggle-status',
                    ['id' => (int) $product['id']],
                    $product['status'] === 'published' ? 'Unpublish' : 'Publish'
                ); ?>

                <?php action_button(
                    'products',
                    'toggle-featured',
                    ['id' => (int) $product['id']],
                    (int) $product['is_featured'] === 1 ? 'Unfeature' : 'Feature'
                ); ?>

                <?php action_button(
                    'products',
                    'delete',
                    ['id' => (int) $product['id']],
                    'Delete',
                    'btn btn--danger btn--xs',
                    'Delete "' . $product['name'] . '" and all its images? This cannot be undone.'
                ); ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php admin_pagination((int) $result['page'], (int) $result['pages'], $filters); ?>
<?php endif; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>

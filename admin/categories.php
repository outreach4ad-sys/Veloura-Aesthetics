<?php
/**
 * Veloura Tec — Admin: category list.
 */

require __DIR__ . '/_auth.php';

$categories = categories_all();

$pageTitle = t('admin.categories');
require __DIR__ . '/_layout.php';
?>

<div class="admin-toolbar">
  <p class="admin-lead">
    <?= count($categories) ?> categor<?= count($categories) === 1 ? 'y' : 'ies' ?>.
    Unpublished categories stay hidden from the storefront.
  </p>
  <a class="btn btn--primary" href="<?= e(url('admin/category-edit.php')) ?>">Add category</a>
</div>

<?php if ($categories === []): ?>
  <?php admin_empty('No categories yet.', 'Add the first category', url('admin/category-edit.php')); ?>
<?php else: ?>
  <div class="table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th scope="col">Image</th>
          <th scope="col">Name</th>
          <th scope="col">Slug</th>
          <th scope="col">Products</th>
          <th scope="col">Order</th>
          <th scope="col">Status</th>
          <th scope="col" class="col-actions">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($categories as $category): ?>
          <tr>
            <td>
              <img class="thumb" src="<?= e(upload_url($category['image'])) ?>"
                   alt="" width="48" height="48" loading="lazy">
            </td>
            <td>
              <a href="<?= e(url('admin/category-edit.php?id=' . (int) $category['id'])) ?>">
                <strong><?= e($category['name']) ?></strong>
              </a>
            </td>
            <td><code><?= e($category['slug']) ?></code></td>
            <td><?= (int) $category['product_count'] ?></td>
            <td><?= (int) $category['sort_order'] ?></td>
            <td>
              <?= (int) $category['is_published'] === 1
                    ? status_badge('Published', 'success')
                    : status_badge('Hidden', 'muted') ?>
            </td>
            <td class="col-actions">
              <div class="row-actions">
                <a class="btn btn--ghost btn--xs"
                   href="<?= e(url('admin/category-edit.php?id=' . (int) $category['id'])) ?>">Edit</a>

                <?php action_button(
                    'categories',
                    'toggle',
                    ['id' => (int) $category['id']],
                    (int) $category['is_published'] === 1 ? 'Unpublish' : 'Publish'
                ); ?>

                <?php action_button(
                    'categories',
                    'delete',
                    ['id' => (int) $category['id']],
                    'Delete',
                    'btn btn--danger btn--xs',
                    'Delete "' . $category['name'] . '"? This cannot be undone.'
                ); ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>

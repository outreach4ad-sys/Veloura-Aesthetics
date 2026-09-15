<?php
/**
 * Veloura Tec — Admin: hero slider list.
 */

require __DIR__ . '/_auth.php';

$slides = hero_slides_all();

$pageTitle = t('admin.hero');
require __DIR__ . '/_layout.php';
?>

<div class="admin-toolbar">
  <p class="admin-lead">
    <?= count($slides) ?> slide<?= count($slides) === 1 ? '' : 's' ?>.
    Published slides appear on the homepage in sort order.
  </p>
  <a class="btn btn--primary" href="<?= e(url('admin/hero-edit.php')) ?>">Add slide</a>
</div>

<?php if ($slides === []): ?>
  <?php admin_empty('No hero slides yet.', 'Add the first slide', url('admin/hero-edit.php')); ?>
<?php else: ?>
  <div class="table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th scope="col">Image</th>
          <th scope="col">Headline</th>
          <th scope="col">Buttons</th>
          <th scope="col">Order</th>
          <th scope="col">Status</th>
          <th scope="col" class="col-actions">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($slides as $slide): ?>
          <tr>
            <td>
              <img class="thumb thumb--wide" src="<?= e(upload_url($slide['image'])) ?>"
                   alt="" width="96" height="54" loading="lazy">
            </td>
            <td>
              <a href="<?= e(url('admin/hero-edit.php?id=' . (int) $slide['id'])) ?>">
                <strong><?= e($slide['title']) ?></strong>
              </a>
              <?php if (!empty($slide['subtitle'])): ?>
                <span class="cell-meta"><?= e(excerpt($slide['subtitle'], 80)) ?></span>
              <?php endif; ?>
            </td>
            <td class="cell-small">
              <?= e($slide['cta_primary_label'] ?: '—') ?>
              <?php if (!empty($slide['cta_secondary_label'])): ?>
                <br><?= e($slide['cta_secondary_label']) ?>
              <?php endif; ?>
            </td>
            <td><?= (int) $slide['sort_order'] ?></td>
            <td>
              <?= (int) $slide['is_published'] === 1
                    ? status_badge('Published', 'success')
                    : status_badge('Hidden', 'muted') ?>
            </td>
            <td class="col-actions">
              <div class="row-actions">
                <a class="btn btn--ghost btn--xs"
                   href="<?= e(url('admin/hero-edit.php?id=' . (int) $slide['id'])) ?>">Edit</a>

                <?php action_button(
                    'hero',
                    'toggle',
                    ['id' => (int) $slide['id']],
                    (int) $slide['is_published'] === 1 ? 'Unpublish' : 'Publish'
                ); ?>

                <?php action_button(
                    'hero',
                    'delete',
                    ['id' => (int) $slide['id']],
                    'Delete',
                    'btn btn--danger btn--xs',
                    'Delete this slide? This cannot be undone.'
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

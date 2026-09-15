<?php
/**
 * Veloura Tec — Admin: media library.
 *
 * Files are read from uploads/ and cross-referenced against the rows that
 * point at them, so an orphaned file is visible rather than invisible.
 */

require __DIR__ . '/_auth.php';

$folder = (string) input('folder', 'all');
$filter = (string) input('filter', 'all');

if (!in_array($folder, ['all', ...UPLOAD_FOLDERS], true)) {
    $folder = 'all';
}
if (!in_array($filter, ['all', 'used', 'orphan'], true)) {
    $filter = 'all';
}

$items = media_list($folder, $filter);
$stats = media_stats();

$pageTitle = t('admin.media');
require __DIR__ . '/_layout.php';
?>

<p class="admin-lead">
  <?= (int) $stats['files'] ?> file<?= (int) $stats['files'] === 1 ? '' : 's' ?>,
  <?= e(format_bytes((int) $stats['bytes'])) ?> total,
  <strong><?= (int) $stats['orphans'] ?></strong> not referenced by any record.
</p>

<form class="filter-bar" method="get" action="<?= e(url('admin/media.php')) ?>">
  <div class="filter-bar__field">
    <label class="sr-only" for="media-folder">Folder</label>
    <select class="field__select" id="media-folder" name="folder">
      <option value="all" <?= $folder === 'all' ? 'selected' : '' ?>>All folders</option>
      <?php foreach (UPLOAD_FOLDERS as $name): ?>
        <option value="<?= e($name) ?>" <?= $folder === $name ? 'selected' : '' ?>>
          uploads/<?= e($name) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="filter-bar__field">
    <label class="sr-only" for="media-filter">Usage</label>
    <select class="field__select" id="media-filter" name="filter">
      <option value="all"    <?= $filter === 'all' ? 'selected' : '' ?>>All files</option>
      <option value="used"   <?= $filter === 'used' ? 'selected' : '' ?>>In use</option>
      <option value="orphan" <?= $filter === 'orphan' ? 'selected' : '' ?>>Not in use</option>
    </select>
  </div>

  <button class="btn btn--dark btn--sm" type="submit">Apply</button>
  <a class="btn btn--ghost btn--sm" href="<?= e(url('admin/media.php')) ?>">Reset</a>
</form>

<?php if ($items === []): ?>
  <?php admin_empty('No files match this view. Upload images from a product, category or hero slide.'); ?>
<?php else: ?>
  <ul class="media-grid">
    <?php foreach ($items as $item): ?>
      <li class="media-item">
        <div class="media-item__preview">
          <?php if ($item['is_video']): ?>
            <span class="media-item__video">Video</span>
          <?php else: ?>
            <img src="<?= e(url($item['path'])) ?>" alt="" width="200" height="140" loading="lazy">
          <?php endif; ?>
        </div>

        <div class="media-item__body">
          <p class="media-item__name" title="<?= e($item['name']) ?>"><?= e($item['name']) ?></p>
          <p class="media-item__meta">
            uploads/<?= e($item['folder']) ?> · <?= e(format_bytes($item['size'])) ?>
            · <?= e(date('M j, Y', $item['modified'])) ?>
          </p>

          <?php if ($item['used_by'] !== null): ?>
            <p class="media-item__used"><?= status_badge('In use', 'success') ?>
              <span class="cell-small"><?= e($item['used_by']) ?></span>
            </p>
          <?php else: ?>
            <p class="media-item__used"><?= status_badge('Not in use', 'warn') ?></p>
          <?php endif; ?>

          <div class="row-actions">
            <a class="btn btn--ghost btn--xs" href="<?= e(url($item['path'])) ?>"
               target="_blank" rel="noopener">Open</a>

            <?php if ($item['used_by'] === null): ?>
              <?php action_button(
                  'media',
                  'delete',
                  ['path' => $item['path']],
                  'Delete',
                  'btn btn--danger btn--xs',
                  'Delete this file permanently?'
              ); ?>
            <?php endif; ?>
          </div>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>

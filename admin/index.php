<?php
/**
 * Veloura Tec — Admin dashboard.
 */

require __DIR__ . '/_auth.php';

$stats = [
    ['label' => 'Published products', 'value' => products_count('published'), 'url' => 'admin/products.php?status=published'],
    ['label' => 'Draft products',     'value' => products_count('draft'),     'url' => 'admin/products.php?status=draft'],
    ['label' => 'Categories',         'value' => categories_count(),          'url' => 'admin/categories.php'],
    ['label' => 'Hero slides',        'value' => count(hero_slides_all()),    'url' => 'admin/hero.php'],
];

$inquiryCounts = inquiry_status_counts();
$totalInquiries = array_sum($inquiryCounts);

$recentInquiries = inquiries_admin_list([], 1, 5)['rows'];
$recentProducts  = products_admin_list([], 1, 5)['rows'];

$mediaStats = media_stats();

// Settings still holding a bracketed placeholder need the owner's real data.
$placeholders = [];
foreach (db_all('SELECT setting_key, label FROM site_settings ORDER BY setting_group, sort_order') as $row) {
    if (setting_is_placeholder((string) $row['setting_key'])) {
        $placeholders[] = $row;
    }
}

$pageTitle = t('admin.dashboard');
require __DIR__ . '/_layout.php';
?>

<p class="admin-lead">
  Signed in as <strong><?= e(auth_user()['email'] ?? '') ?></strong>.
</p>

<?php if ($placeholders !== []): ?>
  <div class="notice-setup">
    <h2>Settings still using placeholders</h2>
    <p>These are not real values yet and are hidden from visitors until replaced:</p>
    <ul>
      <?php foreach ($placeholders as $row): ?>
        <li><?= e($row['label']) ?> <code><?= e($row['setting_key']) ?></code></li>
      <?php endforeach; ?>
    </ul>
    <p><a class="btn btn--primary btn--sm" href="<?= e(url('admin/settings.php')) ?>">Open settings</a></p>
  </div>
<?php endif; ?>

<ul class="stat-grid">
  <?php foreach ($stats as $stat): ?>
    <li class="stat">
      <a href="<?= e(url($stat['url'])) ?>">
        <span class="stat__value"><?= (int) $stat['value'] ?></span>
        <span class="stat__label"><?= e($stat['label']) ?></span>
      </a>
    </li>
  <?php endforeach; ?>

  <li class="stat stat--accent">
    <a href="<?= e(url('admin/inquiries.php?status=new')) ?>">
      <span class="stat__value"><?= (int) $inquiryCounts['new'] ?></span>
      <span class="stat__label">New inquiries</span>
    </a>
  </li>

  <li class="stat">
    <a href="<?= e(url('admin/inquiries.php')) ?>">
      <span class="stat__value"><?= (int) $totalInquiries ?></span>
      <span class="stat__label">Inquiries total</span>
    </a>
  </li>

  <li class="stat">
    <a href="<?= e(url('admin/media.php')) ?>">
      <span class="stat__value"><?= (int) $mediaStats['files'] ?></span>
      <span class="stat__label">Media files (<?= e(format_bytes((int) $mediaStats['bytes'])) ?>)</span>
    </a>
  </li>
</ul>

<div class="form-grid">
  <section class="form-main">

    <div class="panel">
      <h2 class="panel__title">Latest inquiries</h2>

      <?php if ($recentInquiries === []): ?>
        <p class="panel__hint">No inquiries yet.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th scope="col">Reference</th>
                <th scope="col">Customer</th>
                <th scope="col">Received</th>
                <th scope="col">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentInquiries as $inquiry): ?>
                <tr>
                  <td>
                    <a href="<?= e(url('admin/inquiry-view.php?id=' . (int) $inquiry['id'])) ?>">
                      <code><?= e($inquiry['reference']) ?></code>
                    </a>
                  </td>
                  <td><?= e($inquiry['full_name']) ?> — <?= e($inquiry['country']) ?></td>
                  <td class="cell-small"><?= e(format_date($inquiry['created_at'], 'M j, H:i')) ?></td>
                  <td><?= status_badge(inquiry_status_label((string) $inquiry['status']),
                          (string) $inquiry['status'] === 'new' ? 'accent' : 'neutral') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2 class="panel__title">Recently added products</h2>

      <?php if ($recentProducts === []): ?>
        <p class="panel__hint">
          No products yet.
          <a href="<?= e(url('admin/product-edit.php')) ?>">Add the first one</a>.
        </p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th scope="col">Product</th>
                <th scope="col">Category</th>
                <th scope="col">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentProducts as $product): ?>
                <tr>
                  <td>
                    <a href="<?= e(url('admin/product-edit.php?id=' . (int) $product['id'])) ?>">
                      <?= e($product['name']) ?>
                    </a>
                  </td>
                  <td><?= e($product['category_name']) ?></td>
                  <td>
                    <?= $product['status'] === 'published'
                          ? status_badge('Published', 'success')
                          : status_badge('Draft', 'muted') ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  </section>

  <aside class="form-side">

    <div class="panel">
      <h2 class="panel__title">Quick actions</h2>
      <p><a class="btn btn--primary btn--block" href="<?= e(url('admin/product-edit.php')) ?>">Add product</a></p>
      <p><a class="btn btn--ghost btn--block" href="<?= e(url('admin/category-edit.php')) ?>">Add category</a></p>
      <p><a class="btn btn--ghost btn--block" href="<?= e(url('admin/hero-edit.php')) ?>">Add hero slide</a></p>
    </div>

    <div class="panel">
      <h2 class="panel__title">Inquiries by status</h2>
      <dl class="detail-list">
        <?php foreach (INQUIRY_STATUSES as $status): ?>
          <dt><?= e(inquiry_status_label($status)) ?></dt>
          <dd><?= (int) $inquiryCounts[$status] ?></dd>
        <?php endforeach; ?>
      </dl>
    </div>

    <?php if ((int) $mediaStats['orphans'] > 0): ?>
      <div class="panel">
        <h2 class="panel__title">Unused media</h2>
        <p class="panel__hint">
          <?= (int) $mediaStats['orphans'] ?> file<?= (int) $mediaStats['orphans'] === 1 ? '' : 's' ?>
          are not referenced by any record and can be removed.
        </p>
        <a class="btn btn--ghost btn--block" href="<?= e(url('admin/media.php?filter=orphan')) ?>">
          Review unused files
        </a>
      </div>
    <?php endif; ?>

    <div class="panel">
      <h2 class="panel__title">Build status</h2>
      <ul class="check-list">
        <li class="is-done">Phase 1 — foundation and authentication</li>
        <li class="is-done">Phase 3 — admin dashboard and product CMS</li>
        <li>Phase 2 — storefront design system</li>
        <li>Phase 5 — shop, category and product pages</li>
        <li>Phase 6 — inquiry cart and WhatsApp</li>
      </ul>
    </div>

  </aside>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>

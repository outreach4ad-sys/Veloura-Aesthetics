<?php
/**
 * Veloura Tec — Admin: inquiry list.
 */

require __DIR__ . '/_auth.php';

$filters = [
    'search' => (string) input('search', ''),
    'status' => (string) input('status', ''),
    'sort'   => (string) input('sort', 'newest'),
];

$result = inquiries_admin_list($filters, max(1, input_int('page', 1)), 20);
$counts = inquiry_status_counts();

$pageTitle = t('admin.inquiries');
require __DIR__ . '/_layout.php';
?>

<p class="admin-lead">
  <?= (int) $result['total'] ?> inquir<?= (int) $result['total'] === 1 ? 'y' : 'ies' ?> found.
</p>

<ul class="chip-row">
  <li>
    <a class="chip<?= $filters['status'] === '' ? ' is-active' : '' ?>"
       href="<?= e(url('admin/inquiries.php')) ?>">All</a>
  </li>
  <?php foreach (INQUIRY_STATUSES as $status): ?>
    <li>
      <a class="chip<?= $filters['status'] === $status ? ' is-active' : '' ?>"
         href="<?= e(url('admin/inquiries.php?status=' . $status)) ?>">
        <?= e(inquiry_status_label($status)) ?>
        <span class="chip__count"><?= (int) $counts[$status] ?></span>
      </a>
    </li>
  <?php endforeach; ?>
</ul>

<form class="filter-bar" method="get" action="<?= e(url('admin/inquiries.php')) ?>">
  <div class="filter-bar__field filter-bar__field--grow">
    <label class="sr-only" for="inq-search">Search inquiries</label>
    <input class="field__input" type="search" id="inq-search" name="search"
           value="<?= e($filters['search']) ?>"
           placeholder="Search by reference, name, email, company or country">
  </div>

  <div class="filter-bar__field">
    <label class="sr-only" for="inq-status">Status</label>
    <select class="field__select" id="inq-status" name="status">
      <option value="">Any status</option>
      <?php foreach (INQUIRY_STATUSES as $status): ?>
        <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>>
          <?= e(inquiry_status_label($status)) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="filter-bar__field">
    <label class="sr-only" for="inq-sort">Sort</label>
    <select class="field__select" id="inq-sort" name="sort">
      <option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>>Newest first</option>
      <option value="oldest" <?= $filters['sort'] === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
      <option value="name"   <?= $filters['sort'] === 'name' ? 'selected' : '' ?>>Name A-Z</option>
      <option value="status" <?= $filters['sort'] === 'status' ? 'selected' : '' ?>>Status</option>
    </select>
  </div>

  <button class="btn btn--dark btn--sm" type="submit">Apply</button>
  <a class="btn btn--ghost btn--sm" href="<?= e(url('admin/inquiries.php')) ?>">Reset</a>
</form>

<?php if ($result['rows'] === []): ?>
  <?php admin_empty(
      $filters['search'] !== '' || $filters['status'] !== ''
          ? 'No inquiries match these filters.'
          : 'No inquiries yet. They will appear here once customers submit the inquiry form.'
  ); ?>
<?php else: ?>
  <div class="table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th scope="col">Reference</th>
          <th scope="col">Customer</th>
          <th scope="col">Country</th>
          <th scope="col">Items</th>
          <th scope="col">Received</th>
          <th scope="col">Status</th>
          <th scope="col" class="col-actions">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($result['rows'] as $inquiry): ?>
          <tr>
            <td>
              <a href="<?= e(url('admin/inquiry-view.php?id=' . (int) $inquiry['id'])) ?>">
                <code><?= e($inquiry['reference']) ?></code>
              </a>
            </td>
            <td>
              <strong><?= e($inquiry['full_name']) ?></strong>
              <?php if (!empty($inquiry['company'])): ?>
                <span class="cell-meta"><?= e($inquiry['company']) ?></span>
              <?php endif; ?>
            </td>
            <td><?= e($inquiry['country']) ?></td>
            <td><?= (int) $inquiry['items_count'] ?></td>
            <td class="cell-small"><?= e(format_date($inquiry['created_at'], 'M j, Y H:i')) ?></td>
            <td>
              <?= status_badge(
                  inquiry_status_label((string) $inquiry['status']),
                  match ((string) $inquiry['status']) {
                      'new'       => 'accent',
                      'contacted' => 'info',
                      'quoted'    => 'info',
                      'completed' => 'success',
                      'cancelled' => 'muted',
                      default     => 'neutral',
                  }
              ) ?>
            </td>
            <td class="col-actions">
              <a class="btn btn--ghost btn--xs"
                 href="<?= e(url('admin/inquiry-view.php?id=' . (int) $inquiry['id'])) ?>">View</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php admin_pagination((int) $result['page'], (int) $result['pages'], $filters); ?>
<?php endif; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>

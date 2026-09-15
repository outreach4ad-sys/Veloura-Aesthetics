<?php
/**
 * Veloura Tec — Admin: one inquiry in full.
 */

require __DIR__ . '/_auth.php';

$id      = input_int('id');
$inquiry = $id > 0 ? inquiry_by_id($id) : null;

if ($inquiry === null) {
    flash('error', 'That inquiry no longer exists.');
    redirect('admin/inquiries.php');
}

$items   = inquiry_items($id);
$replies = inquiry_replies($id);
$canEmail = trim((string) ($inquiry['email'] ?? '')) !== '';

// Total is only meaningful when every line carries a price.
$total    = 0.0;
$priced   = true;
$currency = (string) setting('default_currency', 'USD');

foreach ($items as $item) {
    if ($item['unit_price'] === null) {
        $priced = false;
        continue;
    }
    $total += (float) $item['unit_price'] * (int) $item['quantity'];
    $currency = (string) ($item['currency'] ?: $currency);
}

$whatsappDigits = preg_replace('/\D+/', '', (string) $inquiry['whatsapp']) ?? '';

$pageTitle = 'Inquiry ' . $inquiry['reference'];
require __DIR__ . '/_layout.php';
?>

<div class="admin-toolbar">
  <p class="admin-lead">
    <a href="<?= e(url('admin/inquiries.php')) ?>">&larr; Back to inquiries</a>
  </p>
  <?php if ($whatsappDigits !== ''): ?>
    <a class="btn btn--primary btn--sm" target="_blank" rel="noopener"
       href="https://wa.me/<?= e($whatsappDigits) ?>">Reply on WhatsApp</a>
  <?php endif; ?>
</div>

<div class="form-grid">
  <section class="form-main">

    <div class="panel">
      <h2 class="panel__title">Requested products</h2>

      <?php if ($items === []): ?>
        <p class="panel__hint">This inquiry has no line items.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th scope="col">Product</th>
                <th scope="col">Unit price</th>
                <th scope="col">Qty</th>
                <th scope="col">Line total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $item): ?>
                <tr>
                  <td>
                    <strong><?= e($item['product_name']) ?></strong>
                    <span class="cell-meta">
                      <?php if (!empty($item['product_slug'])): ?>
                        <a href="<?= e(url('admin/product-edit.php?id=' . (int) $item['product_id'])) ?>">
                          Open product
                        </a>
                      <?php elseif (!empty($item['product_url'])): ?>
                        <?= e($item['product_url']) ?>
                      <?php else: ?>
                        Product no longer in the catalog
                      <?php endif; ?>
                    </span>
                  </td>
                  <td>
                    <?= $item['unit_price'] !== null
                        ? e(money((float) $item['unit_price'], (string) $item['currency']))
                        : '<span class="cell-muted">On request</span>' ?>
                  </td>
                  <td><?= (int) $item['quantity'] ?></td>
                  <td>
                    <?= $item['unit_price'] !== null
                        ? e(money((float) $item['unit_price'] * (int) $item['quantity'],
                                  (string) $item['currency']))
                        : '<span class="cell-muted">—</span>' ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <?php if ($priced && $total > 0): ?>
              <tfoot>
                <tr>
                  <th colspan="3" scope="row">Total (listed prices)</th>
                  <td><strong><?= e(money($total, $currency)) ?></strong></td>
                </tr>
              </tfoot>
            <?php endif; ?>
          </table>
        </div>

        <?php if (!$priced): ?>
          <p class="panel__hint">
            Some items are quote-only, so no total is calculated.
          </p>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php if (!empty($inquiry['notes'])): ?>
      <div class="panel">
        <h2 class="panel__title">Customer notes</h2>
        <p class="preserve-lines"><?= nl2br(e($inquiry['notes'])) ?></p>
      </div>
    <?php endif; ?>

    <form class="panel" method="post" action="<?= e(url('admin/actions/inquiries.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="notes">
      <input type="hidden" name="id" value="<?= (int) $id ?>">
      <input type="hidden" name="return_to" value="<?= e(admin_return_to()) ?>">

      <h2 class="panel__title">Internal notes</h2>
      <p class="panel__hint">Visible to administrators only. Never shown to the customer.</p>

      <div class="field">
        <label class="sr-only" for="f_admin_notes">Internal notes</label>
        <textarea class="field__textarea" id="f_admin_notes" name="admin_notes"
                  rows="5"><?= e($inquiry['admin_notes']) ?></textarea>
      </div>

      <button class="btn btn--dark" type="submit">Save notes</button>
    </form>

    <!-- ------------------------------------------------ reply by email -->
    <div class="panel">
      <h2 class="panel__title">Reply by email</h2>

      <?php if (!$canEmail): ?>
        <p class="panel__hint">
          This inquiry has no email address, so a reply cannot be emailed. You can still
          reach the customer on WhatsApp.
        </p>
      <?php else: ?>
        <?php if (!mail_ready()): ?>
          <p class="flash flash--info">
            Email sending is not configured yet. Set it up in
            <a href="<?= e(url('admin/settings.php')) ?>">Settings &rarr; Email</a>.
            Replies you send now will be logged but not delivered.
          </p>
        <?php endif; ?>

        <form method="post" action="<?= e(url('admin/actions/inquiries.php')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="op" value="reply">
          <input type="hidden" name="id" value="<?= (int) $id ?>">
          <input type="hidden" name="return_to" value="<?= e(admin_return_to()) ?>">

          <p class="panel__hint">
            Sends an email to <strong><?= e($inquiry['email']) ?></strong>. Replies come back
            to your contact email.
          </p>

          <div class="field">
            <label class="field__label" for="f_reply_subject">Subject</label>
            <input class="field__input" type="text" id="f_reply_subject" name="subject"
                   value="Regarding your inquiry <?= e($inquiry['reference']) ?>" maxlength="255" required>
          </div>

          <div class="field">
            <label class="field__label" for="f_reply_body">Message</label>
            <textarea class="field__textarea" id="f_reply_body" name="body" rows="7" required
                      placeholder="Write your reply to the customer…"></textarea>
          </div>

          <button class="btn btn--primary" type="submit">Send reply</button>
        </form>
      <?php endif; ?>

      <?php if ($replies !== []): ?>
        <h3 class="panel__title panel__title--spaced">Previous replies</h3>
        <ul class="reply-list">
          <?php foreach ($replies as $reply): ?>
            <li class="reply-item">
              <div class="reply-item__head">
                <strong><?= e($reply['subject']) ?></strong>
                <?= status_badge(
                    $reply['status'] === 'sent' ? 'Sent' : 'Failed',
                    $reply['status'] === 'sent' ? 'success' : 'muted'
                ) ?>
              </div>
              <p class="reply-item__meta">
                <?= e(format_date($reply['created_at'], 'M j, Y H:i')) ?>
                <?php if (!empty($reply['admin_name'])): ?> · <?= e($reply['admin_name']) ?><?php endif; ?>
                <?php if ($reply['status'] === 'failed' && !empty($reply['error'])): ?>
                  · <span class="cell-muted"><?= e($reply['error']) ?></span>
                <?php endif; ?>
              </p>
              <p class="reply-item__body preserve-lines"><?= nl2br(e($reply['body'])) ?></p>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

  </section>

  <aside class="form-side">

    <div class="panel">
      <h2 class="panel__title">Status</h2>

      <form method="post" action="<?= e(url('admin/actions/inquiries.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="op" value="status">
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <input type="hidden" name="return_to" value="<?= e(admin_return_to()) ?>">

        <div class="field">
          <label class="field__label" for="f_status">Current status</label>
          <select class="field__select" id="f_status" name="status">
            <?php foreach (INQUIRY_STATUSES as $status): ?>
              <option value="<?= e($status) ?>"
                <?= (string) $inquiry['status'] === $status ? 'selected' : '' ?>>
                <?= e(inquiry_status_label($status)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <button class="btn btn--primary btn--block" type="submit">Update status</button>
      </form>
    </div>

    <div class="panel">
      <h2 class="panel__title">Customer</h2>
      <dl class="detail-list">
        <dt>Reference</dt>
        <dd><code><?= e($inquiry['reference']) ?></code></dd>

        <dt>Name</dt>
        <dd><?= e($inquiry['full_name']) ?></dd>

        <?php if (!empty($inquiry['company'])): ?>
          <dt>Company / clinic</dt>
          <dd><?= e($inquiry['company']) ?></dd>
        <?php endif; ?>

        <dt>Country</dt>
        <dd><?= e($inquiry['country']) ?></dd>

        <?php if (!empty($inquiry['city'])): ?>
          <dt>City</dt>
          <dd><?= e($inquiry['city']) ?></dd>
        <?php endif; ?>

        <?php if (!empty($inquiry['email'])): ?>
          <dt>Email</dt>
          <dd><a href="mailto:<?= e($inquiry['email']) ?>"><?= e($inquiry['email']) ?></a></dd>
        <?php endif; ?>

        <dt>WhatsApp</dt>
        <dd>
          <?php if ($whatsappDigits !== ''): ?>
            <a href="https://wa.me/<?= e($whatsappDigits) ?>" target="_blank" rel="noopener">
              <?= e($inquiry['whatsapp']) ?>
            </a>
          <?php else: ?>
            <?= e($inquiry['whatsapp']) ?>
          <?php endif; ?>
        </dd>

        <dt>Received</dt>
        <dd><?= e(format_date($inquiry['created_at'], 'M j, Y \a\t H:i')) ?></dd>
      </dl>
    </div>

    <div class="panel panel--danger">
      <h2 class="panel__title">Delete</h2>
      <p class="panel__hint">
        Removes this inquiry and its line items permanently.
      </p>
      <?php action_button(
          'inquiries',
          'delete',
          ['id' => (int) $id],
          'Delete inquiry',
          'btn btn--danger btn--block',
          'Delete inquiry ' . $inquiry['reference'] . '? This cannot be undone.'
      ); ?>
    </div>

  </aside>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>

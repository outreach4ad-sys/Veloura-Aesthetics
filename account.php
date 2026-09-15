<?php
/**
 * Veloura Tec — Customer account area: profile + their inquiries.
 */

require __DIR__ . '/app/bootstrap.php';

if (!accounts_enabled()) {
    redirect('index.php');
}

$customer = customer_user();
if ($customer === null) {
    redirect('account-login.php');
}

$inquiries = customer_inquiries((string) $customer['email']);

page_meta(['title' => 'My account', 'canonical' => url('account.php'), 'robots' => 'noindex']);
require __DIR__ . '/includes/header.php';
$heroEyebrow = 'Account';
$heroTitle   = 'Hello, ' . $customer['name'];
require __DIR__ . '/includes/page-hero.php';
?>
<section class="section">
  <div class="container">
    <div class="account-grid">

      <aside class="account-side">
        <div class="panel-lite">
          <p class="channel__label">Signed in as</p>
          <p class="channel__value"><?= e($customer['email']) ?></p>
          <?php if (!empty($customer['company'])): ?>
            <p class="channel__note"><?= e($customer['company']) ?></p>
          <?php endif; ?>
          <form method="post" action="<?= e(url('account-logout.php')) ?>" class="account-logout">
            <?= csrf_field() ?>
            <button class="btn btn--ghost btn--sm btn--block" type="submit">Sign out</button>
          </form>
        </div>
      </aside>

      <div class="account-main">
        <h2>Your inquiries</h2>

        <?php if ($inquiries === []): ?>
          <div class="empty-state">
            <p>You have not sent any inquiries yet.</p>
            <p><a class="btn btn--primary" href="<?= e(url('shop.php')) ?>"><?= e(t('cta.explore')) ?></a></p>
          </div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="account-table">
              <thead>
                <tr><th scope="col">Reference</th><th scope="col">Items</th>
                    <th scope="col">Status</th><th scope="col">Date</th></tr>
              </thead>
              <tbody>
                <?php foreach ($inquiries as $inq): ?>
                  <tr>
                    <td><code><?= e($inq['reference']) ?></code></td>
                    <td><?= (int) $inq['items_count'] ?></td>
                    <td><?= e(inquiry_status_label((string) $inq['status'])) ?></td>
                    <td><?= e(format_date($inq['created_at'], 'M j, Y')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <p class="channel__note">
            Inquiries are matched to your account by email address.
          </p>
        <?php endif; ?>
      </div>

    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

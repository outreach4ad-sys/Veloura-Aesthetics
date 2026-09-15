<?php
/**
 * Veloura Tec — Shipping & Returns.
 *
 * Pulls the shipping and returns text from Site Settings, so the company
 * controls exactly what is promised. Until those settings hold real,
 * confirmed terms (they ship as placeholders), the page shows an editorial
 * note instead of inventing shipping guarantees.
 */

require __DIR__ . '/app/bootstrap.php';

$shipping = setting_public('shipping_info');
$returns  = setting_public('returns_info');

page_meta([
    'title'       => 'Shipping & Returns',
    'description' => 'How shipping and returns are handled for professional aesthetic equipment orders.',
    'canonical'   => url('shipping-returns.php'),
]);

require __DIR__ . '/includes/header.php';

$crumbs = [['label' => 'Shipping & Returns']];
require __DIR__ . '/includes/breadcrumbs.php';

$heroEyebrow = 'Orders';
$heroTitle   = 'Shipping & Returns';
require __DIR__ . '/includes/page-hero.php';
?>

<section class="section">
  <div class="container">
    <div class="prose">

      <h2>Shipping</h2>
      <?php if ($shipping !== ''): ?>
        <?php foreach (preg_split('/\n\s*\n/', $shipping) ?: [] as $para): ?>
          <?php if (trim($para) !== ''): ?><p><?= nl2br(e(trim($para))) ?></p><?php endif; ?>
        <?php endforeach; ?>
      <?php else: ?>
        <p>
          Shipping options, lead times and costs are confirmed for each inquiry, because
          they depend on the destination and the specific equipment. When you send an
          inquiry, tell us your location and we will include shipping details in our reply.
        </p>
        <div class="editor-note">
          <strong>To complete before launch:</strong> add your confirmed shipping terms in
          Admin &rarr; Settings (<code>shipping_info</code>). Do not promise destinations,
          carriers or delivery times that are not agreed.
        </div>
      <?php endif; ?>

      <h2>Returns</h2>
      <?php if ($returns !== ''): ?>
        <?php foreach (preg_split('/\n\s*\n/', $returns) ?: [] as $para): ?>
          <?php if (trim($para) !== ''): ?><p><?= nl2br(e(trim($para))) ?></p><?php endif; ?>
        <?php endforeach; ?>
      <?php else: ?>
        <p>
          Returns for professional equipment depend on the product and the terms agreed
          in your quote. Any return or warranty terms that apply to your order are
          confirmed in writing when the sale is agreed.
        </p>
        <div class="editor-note">
          <strong>To complete before launch:</strong> add your returns policy in
          Admin &rarr; Settings (<code>returns_info</code>).
        </div>
      <?php endif; ?>

      <h2>Questions</h2>
      <p>
        For anything about shipping or returns on a specific product, please
        <a href="<?= e(url('contact.php')) ?>">get in touch</a> or include your question
        with your inquiry.
      </p>

    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

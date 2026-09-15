<?php
/**
 * Veloura Tec — Public page footer.
 */

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

$socialLinks = array_filter([
    'Facebook'  => setting_public('social_facebook'),
    'Instagram' => setting_public('social_instagram'),
    'LinkedIn'  => setting_public('social_linkedin'),
    'YouTube'   => setting_public('social_youtube'),
]);

$contactEmail = setting_public('contact_email');
$contactPhone = setting_public('contact_phone');
$contactAddr  = setting_public('contact_address');
?>
</main>

<footer class="site-footer">
  <div class="container site-footer__grid">

    <div class="site-footer__brand">
      <img src="<?= e(upload_url(setting('site_logo', 'assets/img/logo.png'), 'assets/img/placeholder.svg')) ?>"
           alt="<?= e(setting('site_logo_alt', 'Veloura Tec')) ?>"
           width="64" height="64" loading="lazy">
      <p class="site-footer__about"><?= e(setting('site_description', '')) ?></p>
    </div>

    <div class="site-footer__col">
      <h2 class="site-footer__heading"><?= e(t('footer.catalog')) ?></h2>
      <ul class="site-footer__list">
        <li><a href="<?= e(url('shop.php')) ?>"><?= e(t('nav.shop')) ?></a></li>
        <li><a href="<?= e(url('solutions.php')) ?>"><?= e(t('nav.solutions')) ?></a></li>
        <li><a href="<?= e(url('inquiry.php')) ?>"><?= e(t('cta.inquiry_cart')) ?></a></li>
      </ul>
    </div>

    <div class="site-footer__col">
      <h2 class="site-footer__heading"><?= e(t('footer.company')) ?></h2>
      <ul class="site-footer__list">
        <li><a href="<?= e(url('about.php')) ?>"><?= e(t('nav.about')) ?></a></li>
        <li><a href="<?= e(url('contact.php')) ?>"><?= e(t('nav.contact')) ?></a></li>
        <li><a href="<?= e(url('faq.php')) ?>"><?= e(t('nav.faq')) ?></a></li>
      </ul>
    </div>

    <div class="site-footer__col">
      <h2 class="site-footer__heading"><?= e(t('footer.legal')) ?></h2>
      <ul class="site-footer__list">
        <li><a href="<?= e(url('privacy.php')) ?>">Privacy Policy</a></li>
        <li><a href="<?= e(url('terms.php')) ?>">Terms &amp; Conditions</a></li>
        <li><a href="<?= e(url('shipping-returns.php')) ?>">Shipping &amp; Returns</a></li>
      </ul>
    </div>

    <div class="site-footer__col">
      <h2 class="site-footer__heading"><?= e(t('footer.contact')) ?></h2>
      <ul class="site-footer__list">
        <?php if ($contactEmail !== ''): ?>
          <li><a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a></li>
        <?php endif; ?>
        <?php if ($contactPhone !== ''): ?>
          <li><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $contactPhone)) ?>"><?= e($contactPhone) ?></a></li>
        <?php endif; ?>
        <?php if ($contactAddr !== ''): ?>
          <li><?= nl2br(e($contactAddr)) ?></li>
        <?php endif; ?>
      </ul>

      <?php if ($socialLinks !== []): ?>
        <ul class="site-footer__social">
          <?php foreach ($socialLinks as $label => $href): ?>
            <li><a href="<?= e($href) ?>" rel="noopener noreferrer" target="_blank"><?= e($label) ?></a></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

  </div>

  <div class="container site-footer__bottom">
    <p>&copy; <?= date('Y') ?> <?= e(setting('company_name', 'Optical Cargo')) ?>. <?= e(t('footer.rights')) ?></p>
  </div>
</footer>

<?= schema_render() ?>
<script src="<?= e(asset('assets/js/main.js')) ?>" defer></script>
</body>
</html>

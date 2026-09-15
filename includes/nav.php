<?php
/**
 * Veloura Tec — Site navigation.
 *
 * Links are declared once here; the header and the mobile drawer share
 * the same array so they can never drift apart.
 */

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

$navLinks = [
    'index.php'     => t('nav.home'),
    'shop.php'      => t('nav.shop'),
    'solutions.php' => t('nav.solutions'),
    'about.php'     => t('nav.about'),
    'contact.php'   => t('nav.contact'),
];

$logo = (string) setting('site_logo', 'assets/img/logo.png');
?>
<header class="site-header" data-header>
  <div class="container site-header__inner">

    <a class="brand" href="<?= e(url()) ?>">
      <img class="brand__mark"
           src="<?= e(upload_url($logo, 'assets/img/logo.png')) ?>"
           alt="<?= e(setting('site_logo_alt', 'Veloura Tec')) ?>"
           width="48" height="48">
      <span class="brand__text">
        <span class="brand__name"><?= e(setting('site_name', 'Veloura Tec')) ?></span>
        <span class="brand__tagline"><?= e(setting('site_tagline', '')) ?></span>
      </span>
    </a>

    <nav class="site-nav" id="site-nav" aria-label="Main navigation">
      <ul class="site-nav__list">
        <?php foreach ($navLinks as $file => $label): ?>
          <li>
            <a class="site-nav__link<?= is_current($file) ? ' is-active' : '' ?>"
               href="<?= e(url($file)) ?>"
               <?= is_current($file) ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <div class="site-header__actions">
      <a class="btn btn--ghost btn--sm" href="<?= e(url('inquiry.php')) ?>">
        <?= e(t('cta.inquiry_cart')) ?>
        <span class="cart-count" data-cart-count hidden>0</span>
      </a>
      <a class="btn btn--primary btn--sm" href="<?= e(url('inquiry.php')) ?>">
        <?= e(t('cta.request_quote')) ?>
      </a>
      <button class="nav-toggle" type="button"
              aria-controls="site-nav" aria-expanded="false"
              data-nav-toggle>
        <span class="nav-toggle__bar" aria-hidden="true"></span>
        <span class="sr-only"><?= e(t('nav.menu')) ?></span>
      </button>
    </div>

  </div>
</header>

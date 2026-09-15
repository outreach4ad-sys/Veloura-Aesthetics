<?php
/**
 * Veloura Tec — Professional Solutions.
 *
 * Positions the catalogue by customer type and by how the inquiry process
 * works. No outcomes, results or medical claims are promised.
 */

require __DIR__ . '/app/bootstrap.php';

$companyName = (string) setting('company_name', 'Optical Cargo');

page_meta([
    'title'       => 'Professional Solutions',
    'description' => 'Professional aesthetic equipment and sourcing support for clinics, aesthetic centers, wellness facilities and distributors.',
    'canonical'   => url('solutions.php'),
]);

require __DIR__ . '/includes/header.php';

$crumbs = [['label' => 'Professional Solutions']];
require __DIR__ . '/includes/breadcrumbs.php';

$heroEyebrow = 'For professionals';
$heroTitle   = 'Professional Solutions';
$heroLead    = 'Equipment and sourcing support shaped around how aesthetic and wellness professionals actually buy.';
require __DIR__ . '/includes/page-hero.php';

$audiences = [
    ['Aesthetic clinics', 'Treatment platforms and devices to add or expand service lines, selected from a single professional catalogue.'],
    ['Beauty centers & salons', 'Equipment for facial, body and skin services, with clear product details to support your decision.'],
    ['Wellness & physiotherapy', 'Physiotherapy and wellness equipment alongside aesthetic technology, sourced through one point of contact.'],
    ['Distributors & buyers', 'Sourcing for regional resale and larger orders. Send your requirements and we will respond with what is available.'],
];

$steps = [
    ['Browse the catalogue', 'Explore equipment by technology and add the items you are interested in to an inquiry list.'],
    ['Send your inquiry', 'Share your details and any notes. This is a request for a quote — no payment is taken.'],
    ['We respond', 'We reply with availability, pricing and shipping options for your location, and answer any questions.'],
];
?>

<section class="section">
  <div class="container">
    <div class="section__head">
      <span class="section__eyebrow">Who we work with</span>
      <h2>Built for professional buyers</h2>
      <p class="section__lead">
        Whether you run a single clinic or source equipment for a market, the catalogue
        and the inquiry process are the same: clear information, direct answers.
      </p>
    </div>

    <div class="feature-grid">
      <?php foreach ($audiences as [$title, $body]): ?>
        <div class="feature">
          <h3><?= e($title) ?></h3>
          <p><?= e($body) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section--alt">
  <div class="container">
    <div class="section__head">
      <span class="section__eyebrow">How it works</span>
      <h2>A quote process, not a checkout</h2>
      <p class="section__lead">
        Professional equipment purchases depend on your location, volume and
        requirements, so we quote each inquiry individually.
      </p>
    </div>

    <div class="feature-grid">
      <?php foreach ($steps as $i => [$title, $body]): ?>
        <div class="feature">
          <h3><?= (int) $i + 1 ?>. <?= e($title) ?></h3>
          <p><?= e($body) ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="section__head section__head--spaced">
      <p>
        <a class="btn btn--primary" href="<?= e(url('shop.php')) ?>"><?= e(t('cta.explore')) ?></a>
        <a class="btn btn--ghost" href="<?= e(url('contact.php')) ?>">Talk to us</a>
      </p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

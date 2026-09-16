<?php
/**
 * Veloura Tec — Homepage.
 *
 * Phase 1: structural only. Content comes from the database (hero slides,
 * categories, products) — nothing is hardcoded here. The full visual
 * design, hero slideshow and the remaining homepage sections are built in
 * Phase 2 and Phase 5 on top of this same data.
 */

require __DIR__ . '/app/bootstrap.php';

$slides     = hero_slides_published();
$categories = categories_published();
$featured   = products_featured(8);

if ($featured === []) {
    $featured = products_latest(8);
}

$hero = $slides[0] ?? null;

page_meta([
    'title'       => '',                       // homepage uses the site name only
    'description' => (string) setting('site_description', ''),
    'canonical'   => url(),
]);

require __DIR__ . '/includes/header.php';
?>

<?php if ($hero !== null): ?>
  <section class="section section--dark" aria-labelledby="hero-title">
    <div class="container">
      <span class="section__eyebrow"><?= e(setting('site_tagline', '')) ?></span>
      <h1 id="hero-title"><?= e($hero['title']) ?></h1>
      <?php if (!empty($hero['subtitle'])): ?>
        <p class="section__lead"><?= e($hero['subtitle']) ?></p>
      <?php endif; ?>

      <p>
        <?php if (!empty($hero['cta_primary_label'])): ?>
          <a class="btn btn--primary" href="<?= e(url($hero['cta_primary_url'] ?: 'shop.php')) ?>">
            <?= e($hero['cta_primary_label']) ?>
          </a>
        <?php endif; ?>
        <?php if (!empty($hero['cta_secondary_label'])): ?>
          <a class="btn btn--ghost" href="<?= e(url($hero['cta_secondary_url'] ?: 'inquiry.php')) ?>">
            <?= e($hero['cta_secondary_label']) ?>
          </a>
        <?php endif; ?>
      </p>
    </div>
  </section>
<?php endif; ?>

<section class="section" aria-labelledby="categories-title">
  <div class="container">
    <div class="section__head">
      <span class="section__eyebrow">Equipment Categories</span>
      <h2 id="categories-title">Browse by technology</h2>
      <p class="section__lead">
        Professional systems organised by treatment technology, for clinics,
        aesthetic centers and wellness facilities.
      </p>
    </div>

    <?php if ($categories === []): ?>
      <p class="empty-state">No categories are published yet.</p>
    <?php else: ?>
      <div class="cat-cards">
        <?php foreach ($categories as $category): ?>
          <?php require __DIR__ . '/includes/category-card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="section section--alt" aria-labelledby="featured-title">
  <div class="container">
    <div class="section__head">
      <span class="section__eyebrow"><?= e(t('common.featured')) ?></span>
      <h2 id="featured-title">Featured equipment</h2>
    </div>

    <?php if ($featured === []): ?>
      <p class="empty-state">
        No products have been published yet. Add products from the admin dashboard
        and they will appear here automatically.
      </p>
    <?php else: ?>
      <div class="card-grid">
        <?php foreach ($featured as $product): ?>
          <?php require __DIR__ . '/includes/product-card.php'; ?>
        <?php endforeach; ?>
      </div>

      <p class="section__more">
        <a class="btn btn--ghost" href="<?= e(url('shop.php')) ?>">View all equipment</a>
      </p>
    <?php endif; ?>
  </div>
</section>

<section class="section" aria-labelledby="inquiry-title">
  <div class="container">
    <div class="section__head">
      <h2 id="inquiry-title">Request a quote</h2>
      <p class="section__lead">
        Build an inquiry list of the equipment you need and send it to our team.
        We will respond with availability, pricing and shipping details for your location.
      </p>
      <p>
        <a class="btn btn--primary" href="<?= e(url('shop.php')) ?>"><?= e(t('cta.explore')) ?></a>
        <a class="btn btn--ghost" href="<?= e(url('contact.php')) ?>"><?= e(t('nav.contact')) ?></a>
      </p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

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

<?php if ($slides !== []): ?>
  <section class="hero" aria-label="Featured" data-hero
           data-hero-interval="4000">
    <div class="hero__track">
      <?php foreach ($slides as $i => $slide): ?>
        <?php $hasImg = !empty($slide['image'])
              && is_file(VELOURA_ROOT . '/' . ltrim((string) $slide['image'], '/')); ?>
        <div class="hero__slide<?= $i === 0 ? ' is-active' : '' ?><?= $hasImg ? '' : ' hero__slide--plain' ?>"
             data-hero-slide role="group"
             aria-roledescription="slide"
             aria-label="<?= (int) $i + 1 ?> of <?= count($slides) ?>"
             <?= $i === 0 ? '' : 'aria-hidden="true"' ?>>
          <?php if ($hasImg): ?>
            <img class="hero__img" src="<?= e(upload_url($slide['image'])) ?>"
                 alt="<?= e($slide['image_alt'] ?: $slide['title']) ?>"
                 <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
            <span class="hero__scrim" aria-hidden="true"></span>
          <?php endif; ?>

          <div class="container hero__content">
            <span class="section__eyebrow"><?= e(setting('site_tagline', '')) ?></span>
            <?php if ($i === 0): ?>
              <h1 class="hero__title"><?= e($slide['title']) ?></h1>
            <?php else: ?>
              <p class="hero__title" role="heading" aria-level="2"><?= e($slide['title']) ?></p>
            <?php endif; ?>
            <?php if (!empty($slide['subtitle'])): ?>
              <p class="hero__lead"><?= e($slide['subtitle']) ?></p>
            <?php endif; ?>
            <p class="hero__actions">
              <?php if (!empty($slide['cta_primary_label'])): ?>
                <a class="btn btn--primary" href="<?= e(url($slide['cta_primary_url'] ?: 'shop.php')) ?>">
                  <?= e($slide['cta_primary_label']) ?>
                </a>
              <?php endif; ?>
              <?php if (!empty($slide['cta_secondary_label'])): ?>
                <a class="btn btn--ghost" href="<?= e(url($slide['cta_secondary_url'] ?: 'inquiry.php')) ?>">
                  <?= e($slide['cta_secondary_label']) ?>
                </a>
              <?php endif; ?>
            </p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (count($slides) > 1): ?>
      <div class="hero__dots" role="tablist" aria-label="Choose slide" data-hero-dots>
        <?php foreach ($slides as $i => $slide): ?>
          <button class="hero__dot<?= $i === 0 ? ' is-active' : '' ?>" type="button"
                  role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                  aria-label="Slide <?= (int) $i + 1 ?>" data-hero-dot="<?= (int) $i ?>"></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
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

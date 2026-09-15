<?php
/**
 * Veloura Tec — Product card.
 *
 * Usage:
 *     $product = [...];               // a row from the products repository
 *     require VELOURA_ROOT . '/includes/product-card.php';
 *
 * Expects $product in scope. Every page that lists products uses this one
 * file, so a card change lands everywhere at once.
 */

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

if (!isset($product) || !is_array($product)) {
    return;
}

$productId   = (int) $product['id'];
$priceLabel  = product_price_label($product);
$isQuoteOnly = $product['price'] === null || ($product['price_mode'] ?? 'quote') !== 'show';
?>
<article class="card">
  <a class="card__media" href="<?= e(product_url($product)) ?>" tabindex="-1" aria-hidden="true">
    <img src="<?= e(upload_url($product['main_image'])) ?>"
         alt="" width="400" height="400" loading="lazy" decoding="async">
    <?php if ((int) ($product['is_featured'] ?? 0) === 1): ?>
      <span class="card__badge"><?= e(t('common.featured')) ?></span>
    <?php endif; ?>
  </a>

  <div class="card__body">
    <?php if (!empty($product['category_name'])): ?>
      <p class="card__category"><?= e($product['category_name']) ?></p>
    <?php endif; ?>

    <h3 class="card__title">
      <a href="<?= e(product_url($product)) ?>"><?= e($product['name']) ?></a>
    </h3>

    <?php if (!empty($product['short_description'])): ?>
      <p class="card__excerpt"><?= e(excerpt($product['short_description'], 90)) ?></p>
    <?php endif; ?>

    <p class="card__price<?= $isQuoteOnly ? ' card__price--quote' : '' ?>">
      <?= e($priceLabel) ?>
    </p>

    <div class="card__actions">
      <button class="btn btn--primary btn--sm"
              type="button"
              data-add-to-inquiry="<?= $productId ?>"
              data-added-label="<?= e(t('cta.in_inquiry')) ?>"
              data-cart-url="<?= e(url('inquiry.php')) ?>">
        <span data-add-label><?= e(t('cta.add_inquiry')) ?></span>
      </button>

      <a class="btn btn--ghost btn--sm" href="<?= e(product_url($product)) ?>">
        <?= e(t('cta.view_details')) ?>
      </a>
    </div>
  </div>
</article>

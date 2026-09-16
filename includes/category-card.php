<?php
/**
 * Veloura Tec — Category image card (hover-reveal).
 *
 * Side-by-side cards, each with the category image, its name and product
 * count. When hovering the grid, other cards dim and blur while the hovered
 * one lifts — the effect the client asked for, in vanilla CSS (no React).
 *
 * Expects $category in scope. A category with no image gets a tasteful
 * navy→copper gradient so the grid looks complete before images are added.
 */

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

if (!isset($category) || !is_array($category)) {
    return;
}

$hasImage = !empty($category['image'])
    && is_file(VELOURA_ROOT . '/' . ltrim((string) $category['image'], '/'));
$count = (int) ($category['product_count'] ?? 0);
?>
<a class="cat-card<?= $hasImage ? '' : ' cat-card--placeholder' ?>"
   href="<?= e(category_url($category)) ?>"
   aria-label="<?= e($category['name']) ?>, <?= $count ?> <?= $count === 1 ? 'product' : 'products' ?>">
  <?php if ($hasImage): ?>
    <img class="cat-card__img"
         src="<?= e(upload_url($category['image'])) ?>"
         alt="<?= e($category['name']) ?>"
         width="400" height="500" loading="lazy" decoding="async">
  <?php endif; ?>
  <span class="cat-card__overlay" aria-hidden="true"></span>
  <span class="cat-card__body">
    <span class="cat-card__count"><?= $count ?> <?= $count === 1 ? 'product' : 'products' ?></span>
    <span class="cat-card__name"><?= e($category['name']) ?></span>
  </span>
</a>

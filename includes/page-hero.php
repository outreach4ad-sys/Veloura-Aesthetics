<?php
/**
 * Veloura Tec — Compact page hero for content pages.
 *
 * Usage:
 *   $heroEyebrow = 'About'; $heroTitle = 'About Us'; $heroLead = '…';
 *   require VELOURA_ROOT . '/includes/page-hero.php';
 */

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}
?>
<section class="page-hero">
  <div class="container page-hero__inner">
    <?php if (!empty($heroEyebrow)): ?>
      <span class="page-hero__eyebrow"><?= e($heroEyebrow) ?></span>
    <?php endif; ?>
    <h1><?= e($heroTitle ?? '') ?></h1>
    <?php if (!empty($heroLead)): ?>
      <p class="page-hero__lead"><?= e($heroLead) ?></p>
    <?php endif; ?>
  </div>
</section>

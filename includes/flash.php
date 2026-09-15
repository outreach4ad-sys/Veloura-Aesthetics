<?php
/**
 * Veloura Tec — Flash message output.
 */

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

$messages = flash_take();

if ($messages !== []): ?>
  <div class="container flash-stack">
    <?php foreach ($messages as $message): ?>
      <p class="flash flash--<?= e($message['type']) ?>" role="status">
        <?= e($message['message']) ?>
      </p>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php
/**
 * Veloura Tec — 404 page.
 *
 * Referenced by `.htaccess` (ErrorDocument 404). Sends a real 404 status
 * with the site chrome, so a missing URL is a proper Not Found for search
 * engines rather than a soft 404 or a bare server page.
 */

require __DIR__ . '/app/bootstrap.php';

http_response_code(404);

page_meta([
    'title'  => 'Page not found',
    'robots' => 'noindex, follow',
]);

require __DIR__ . '/includes/header.php';
?>

<section class="section">
  <div class="container">
    <div class="empty-state">
      <h1>Page not found</h1>
      <p>
        The page you are looking for does not exist, or has moved.
        It may have been an old link.
      </p>
      <p>
        <a class="btn btn--primary" href="<?= e(url('shop.php')) ?>">Browse equipment</a>
        <a class="btn btn--ghost" href="<?= e(url()) ?>">Go to the homepage</a>
      </p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

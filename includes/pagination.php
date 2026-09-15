<?php
/**
 * Veloura Tec — Storefront pagination.
 *
 * Expects $currentPage, $totalPages, $paginationBase and optionally
 * $paginationQuery (an array of filters to carry across pages).
 */

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

$currentPage = (int) ($currentPage ?? 1);
$totalPages  = (int) ($totalPages ?? 1);

if ($totalPages < 2) {
    return;
}

$paginationQuery = $paginationQuery ?? [];
$paginationBase  = $paginationBase ?? url();

$pageUrl = static function (int $page) use ($paginationBase, $paginationQuery): string {
    $query = $paginationQuery;
    if ($page > 1) {
        $query['page'] = $page;
    }

    return $paginationBase . ($query === [] ? '' : '?' . http_build_query($query));
};
?>
<nav class="pagination" aria-label="Pagination">
  <?php if ($currentPage > 1): ?>
    <a class="pagination__link" rel="prev" href="<?= e($pageUrl($currentPage - 1)) ?>">
      &larr; Previous
    </a>
  <?php else: ?>
    <span class="pagination__link is-disabled" aria-hidden="true">&larr; Previous</span>
  <?php endif; ?>

  <span class="pagination__status">Page <?= $currentPage ?> of <?= $totalPages ?></span>

  <?php if ($currentPage < $totalPages): ?>
    <a class="pagination__link" rel="next" href="<?= e($pageUrl($currentPage + 1)) ?>">
      Next &rarr;
    </a>
  <?php else: ?>
    <span class="pagination__link is-disabled" aria-hidden="true">Next &rarr;</span>
  <?php endif; ?>
</nav>

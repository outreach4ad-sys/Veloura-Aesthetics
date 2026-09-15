<?php
/**
 * Veloura Tec — Admin chrome.
 *
 * Usage inside an admin page:
 *
 *     $pageTitle = 'Dashboard';
 *     require __DIR__ . '/_layout.php';          // opens the layout
 *     ... page markup ...
 *     require __DIR__ . '/_layout_end.php';      // closes it
 */

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

$admin = auth_user();
$pageTitle = $pageTitle ?? t('admin.dashboard');

$adminNav = [
    'index.php'      => t('admin.dashboard'),
    'categories.php' => t('admin.categories'),
    'products.php'   => t('admin.products'),
    'media.php'      => t('admin.media'),
    'inquiries.php'  => t('admin.inquiries'),
    'hero.php'       => t('admin.hero'),
    'settings.php'   => t('admin.settings'),
];
?>
<!DOCTYPE html>
<html lang="<?= e(config('default_locale', 'en')) ?>" dir="<?= e(direction()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle) ?> — <?= e(setting('site_name', 'Veloura Tec')) ?> Admin</title>
<link rel="icon" href="<?= e(asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body class="admin">

<header class="admin-bar">
  <a class="admin-bar__brand" href="<?= e(url('admin/index.php')) ?>">
    <img src="<?= e(upload_url(setting('site_logo', 'assets/img/logo.png'), 'assets/img/placeholder.svg')) ?>"
         alt="" width="32" height="32">
    <span><?= e(setting('site_name', 'Veloura Tec')) ?> Admin</span>
  </a>

  <div class="admin-bar__right">
    <a class="admin-bar__link" href="<?= e(url()) ?>" target="_blank" rel="noopener">View site</a>
    <span class="admin-bar__user"><?= e($admin['name'] ?? '') ?></span>
    <form method="post" action="<?= e(url('admin/logout.php')) ?>" class="admin-bar__logout">
      <?= csrf_field() ?>
      <button class="btn btn--ghost btn--sm" type="submit"><?= e(t('auth.logout')) ?></button>
    </form>
  </div>
</header>

<div class="admin-shell">
  <nav class="admin-nav" aria-label="Admin navigation">
    <ul>
      <?php foreach ($adminNav as $file => $label): ?>
        <li>
          <a class="admin-nav__link<?= is_current($file) ? ' is-active' : '' ?>"
             href="<?= e(url('admin/' . $file)) ?>"><?= e($label) ?></a>
        </li>
      <?php endforeach; ?>
    </ul>
  </nav>

  <main class="admin-main">
    <h1 class="admin-title"><?= e($pageTitle) ?></h1>
    <?php require VELOURA_ROOT . '/includes/flash.php'; ?>

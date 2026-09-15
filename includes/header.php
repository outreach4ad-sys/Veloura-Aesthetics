<?php
/**
 * Veloura Tec — Public page header.
 *
 * Expects app/bootstrap.php to have been required already, and the page to
 * have called page_meta([...]) beforehand.
 */

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

security_headers();
schema_add(schema_organization());
?>
<!DOCTYPE html>
<html lang="<?= e(config('default_locale', 'en')) ?>" dir="<?= e(direction()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(meta_title()) ?></title>
<meta name="description" content="<?= e(meta_description()) ?>">
<meta name="robots" content="<?= e(meta_robots()) ?>">
<link rel="canonical" href="<?= e(meta_canonical()) ?>">

<meta property="og:type" content="<?= e(meta_get('og_type', 'website')) ?>">
<meta property="og:site_name" content="<?= e(setting('site_name', 'Veloura Tec')) ?>">
<meta property="og:title" content="<?= e(meta_title()) ?>">
<meta property="og:description" content="<?= e(meta_description()) ?>">
<meta property="og:url" content="<?= e(meta_canonical()) ?>">
<meta property="og:image" content="<?= e(meta_image()) ?>">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e(meta_title()) ?>">
<meta name="twitter:description" content="<?= e(meta_description()) ?>">
<meta name="twitter:image" content="<?= e(meta_image()) ?>">

<link rel="icon" href="<?= e(asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(asset('assets/img/logo.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/base.css')) ?>">
</head>
<body class="page page--<?= e(basename(current_path(), '.php') ?: 'home') ?>">

<a class="skip-link" href="#main"><?= e(t('nav.skip')) ?></a>

<?php require VELOURA_ROOT . '/includes/nav.php'; ?>

<main id="main" class="main">
<?php require VELOURA_ROOT . '/includes/flash.php'; ?>

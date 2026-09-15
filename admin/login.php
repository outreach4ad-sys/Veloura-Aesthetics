<?php
/**
 * Veloura Tec — Admin sign-in.
 *
 * This page boots the application WITHOUT auth_guard() (it is the one
 * admin page that must be reachable while signed out).
 */

require dirname(__DIR__) . '/app/bootstrap.php';

security_headers();
if (!headers_sent()) {
    header('X-Robots-Tag: noindex, nofollow');
}

// Already signed in? Go straight to the dashboard.
if (auth_check()) {
    redirect('admin/index.php');
}

$error = null;
$email = '';

if (is_post()) {
    csrf_guard();

    $email    = (string) input('email', '');
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = t('auth.required');
    } else {
        $error = auth_attempt($email, $password);

        if ($error === null) {
            $intended = $_SESSION['admin_intended'] ?? 'admin/index.php';
            unset($_SESSION['admin_intended']);

            // Only ever redirect to a path inside this site.
            $intended = ltrim((string) $intended, '/');
            if ($intended === '' || !str_starts_with($intended, 'admin/')) {
                $intended = 'admin/index.php';
            }

            redirect($intended);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e(config('default_locale', 'en')) ?>" dir="<?= e(direction()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(t('auth.title')) ?> — <?= e(setting('site_name', 'Veloura Tec')) ?></title>
<link rel="icon" href="<?= e(asset('assets/img/favicon.png')) ?>" type="image/png">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body class="admin admin--auth">

<main class="auth-card">
  <img class="auth-card__logo"
       src="<?= e(upload_url(setting('site_logo', 'assets/img/logo.png'), 'assets/img/logo.png')) ?>"
       alt="<?= e(setting('site_logo_alt', 'Veloura Tec')) ?>" width="72" height="72">

  <h1 class="auth-card__title"><?= e(t('auth.title')) ?></h1>

  <?php if ($error !== null): ?>
    <p class="flash flash--error" role="alert"><?= e($error) ?></p>
  <?php endif; ?>

  <form method="post" action="<?= e(url('admin/login.php')) ?>" novalidate>
    <?= csrf_field() ?>

    <label class="field">
      <span class="field__label"><?= e(t('auth.email')) ?></span>
      <input class="field__input" type="email" name="email" value="<?= e($email) ?>"
             autocomplete="username" required autofocus>
    </label>

    <label class="field">
      <span class="field__label"><?= e(t('auth.password')) ?></span>
      <input class="field__input" type="password" name="password"
             autocomplete="current-password" required>
    </label>

    <button class="btn btn--primary btn--block" type="submit"><?= e(t('auth.submit')) ?></button>
  </form>

  <p class="auth-card__foot"><a href="<?= e(url()) ?>">&larr; Back to site</a></p>
</main>

</body>
</html>

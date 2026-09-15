<?php
/**
 * Veloura Tec — First administrator setup.
 *
 * RUN THIS ONCE, THEN DELETE THE FILE.
 *
 * Browser:  https://veloura-tec.com/database/seed_admin.php
 * CLI:      php database/seed_admin.php "Name" "email@example.com" "password"
 *
 * Safety rails:
 *   - Refuses to run once ANY admin row exists, so it cannot be used to
 *     add a back-door account after launch.
 *   - Passwords are stored only as password_hash() output.
 *   - The web form is CSRF-protected.
 *
 * NOTE: the root .htaccess blocks /database/ over HTTP. To run the web
 * form, temporarily comment out the "app|database|storage|includes" rule,
 * or use the CLI form above (recommended), or run it from the hPanel
 * terminal. Delete this file when you are done either way.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$existing = (int) db_value('SELECT COUNT(*) FROM admins', [], 0);

// ---------------------------------------------------------------------
// CLI mode
// ---------------------------------------------------------------------
if (PHP_SAPI === 'cli') {
    if ($existing > 0) {
        fwrite(STDERR, "An administrator already exists. Delete this file.\n");
        exit(1);
    }

    $name     = $argv[1] ?? '';
    $email    = $argv[2] ?? '';
    $password = $argv[3] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        fwrite(STDERR, "Usage: php database/seed_admin.php \"Name\" \"email\" \"password\"\n");
        exit(1);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fwrite(STDERR, "Invalid email address.\n");
        exit(1);
    }

    if (strlen($password) < 10) {
        fwrite(STDERR, "Password must be at least 10 characters.\n");
        exit(1);
    }

    db_insert('admins', [
        'name'          => $name,
        'email'         => strtolower($email),
        'password_hash' => auth_hash($password),
        'role'          => 'owner',
        'is_active'     => 1,
    ]);

    fwrite(STDOUT, "Administrator created. DELETE database/seed_admin.php now.\n");
    exit(0);
}

// ---------------------------------------------------------------------
// Web mode
// ---------------------------------------------------------------------
$errors = [];
$done   = false;
$name   = '';
$email  = '';

if ($existing === 0 && is_post()) {
    csrf_guard();

    $name     = (string) input('name', '');
    $email    = strtolower((string) input('email', ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');

    if ($name === '') {
        $errors[] = 'Please enter a name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 10) {
        $errors[] = 'The password must be at least 10 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    if ($errors === []) {
        db_insert('admins', [
            'name'          => $name,
            'email'         => $email,
            'password_hash' => auth_hash($password),
            'role'          => 'owner',
            'is_active'     => 1,
        ]);

        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Create administrator — Veloura Tec</title>
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body class="admin admin--auth">

<main class="auth-card">
  <h1 class="auth-card__title">Create administrator</h1>

  <?php if ($existing > 0): ?>
    <p class="flash flash--error">
      An administrator account already exists. This script is disabled.
      <strong>Delete <code>database/seed_admin.php</code> now.</strong>
    </p>
    <p><a href="<?= e(url('admin/login.php')) ?>">Go to sign in &rarr;</a></p>

  <?php elseif ($done): ?>
    <p class="flash flash--success">
      Administrator created. <strong>Delete <code>database/seed_admin.php</code> from the
      server before doing anything else.</strong>
    </p>
    <p><a href="<?= e(url('admin/login.php')) ?>">Go to sign in &rarr;</a></p>

  <?php else: ?>
    <?php foreach ($errors as $message): ?>
      <p class="flash flash--error"><?= e($message) ?></p>
    <?php endforeach; ?>

    <form method="post" novalidate>
      <?= csrf_field() ?>

      <label class="field">
        <span class="field__label">Full name</span>
        <input class="field__input" type="text" name="name" value="<?= e($name) ?>" required>
      </label>

      <label class="field">
        <span class="field__label">Email address</span>
        <input class="field__input" type="email" name="email" value="<?= e($email) ?>"
               autocomplete="username" required>
      </label>

      <label class="field">
        <span class="field__label">Password</span>
        <input class="field__input" type="password" name="password"
               autocomplete="new-password" minlength="10" required>
        <span class="field__hint">Minimum 10 characters. Use a unique password.</span>
      </label>

      <label class="field">
        <span class="field__label">Confirm password</span>
        <input class="field__input" type="password" name="password_confirm"
               autocomplete="new-password" minlength="10" required>
      </label>

      <button class="btn btn--primary btn--block" type="submit">Create administrator</button>
    </form>
  <?php endif; ?>
</main>

</body>
</html>

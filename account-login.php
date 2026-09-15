<?php
/**
 * Veloura Tec — Customer sign in.
 */

require __DIR__ . '/app/bootstrap.php';

if (!accounts_enabled()) {
    redirect('index.php');
}
if (customer_check()) {
    redirect('account.php');
}

$error = null;
$email = '';

if (is_post()) {
    csrf_guard();

    $email    = (string) input('email', '');
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $result = customer_attempt_login($email, $password);

        if (is_array($result)) {
            customer_login_session((int) $result['id']);
            redirect('account.php');
        }

        // If unverified, route them to verification.
        $pending = customer_by_email($email);
        if ($pending !== null && (int) $pending['is_verified'] !== 1
            && str_contains($result, 'confirm your email')) {
            $_SESSION['verify_customer_id'] = (int) $pending['id'];
            $code = customer_reissue_code((int) $pending['id']);
            if ($code !== null) {
                customer_send_code($pending, $code);
            }
            flash('info', 'Please confirm your email. We sent you a new code.');
            redirect('verify.php');
        }

        $error = $result;
    }
}

page_meta(['title' => 'Sign in', 'canonical' => url('account-login.php'), 'robots' => 'noindex, follow']);
require __DIR__ . '/includes/header.php';
$heroEyebrow = 'Account';
$heroTitle   = 'Sign in';
require __DIR__ . '/includes/page-hero.php';
?>
<section class="section">
  <div class="container narrow">
    <?php if ($error !== null): ?><p class="flash flash--error" role="alert"><?= e($error) ?></p><?php endif; ?>

    <form method="post" class="auth-form" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label class="field__label" for="l_email">Email</label>
        <input class="field__input" type="email" id="l_email" name="email" value="<?= e($email) ?>"
               autocomplete="username" required autofocus>
      </div>
      <div class="field">
        <label class="field__label" for="l_password">Password</label>
        <input class="field__input" type="password" id="l_password" name="password"
               autocomplete="current-password" required>
      </div>
      <button class="btn btn--primary btn--lg btn--block" type="submit">Sign in</button>

      <p class="auth-form__alt">
        New here? <a href="<?= e(url('register.php')) ?>">Create an account</a>.
      </p>
    </form>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

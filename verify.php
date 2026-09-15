<?php
/**
 * Veloura Tec — Confirm your email with the code.
 */

require __DIR__ . '/app/bootstrap.php';

if (!accounts_enabled()) {
    redirect('index.php');
}
if (customer_check()) {
    redirect('account.php');
}

$customerId = (int) ($_SESSION['verify_customer_id'] ?? 0);
$customer   = $customerId > 0 ? customer_by_id($customerId) : null;

if ($customer === null) {
    flash('error', 'Start by creating an account or signing in.');
    redirect('register.php');
}
if ((int) $customer['is_verified'] === 1) {
    unset($_SESSION['verify_customer_id']);
    flash('success', 'Your email is already confirmed. Please sign in.');
    redirect('account-login.php');
}

$error = null;

if (is_post()) {
    csrf_guard();

    if (input('resend') !== null) {
        $code = customer_reissue_code($customerId);
        if ($code !== null) {
            customer_send_code($customer, $code);
            flash('info', 'A new code has been sent.');
        }
        redirect('verify.php');
    }

    $code  = trim((string) input('code', ''));
    $error = customer_verify_code($customerId, $code);

    if ($error === null) {
        unset($_SESSION['verify_customer_id']);
        customer_login_session($customerId);
        flash('success', 'Your email is confirmed. Welcome!');
        redirect('account.php');
    }
}

page_meta(['title' => 'Confirm your email', 'canonical' => url('verify.php'), 'robots' => 'noindex']);
require __DIR__ . '/includes/header.php';
$heroEyebrow = 'Account';
$heroTitle   = 'Confirm your email';
$heroLead    = 'Enter the 6-digit code we sent to ' . $customer['email'] . '.';
require __DIR__ . '/includes/page-hero.php';
?>
<section class="section">
  <div class="container narrow">
    <?php if ($error !== null): ?><p class="flash flash--error" role="alert"><?= e($error) ?></p><?php endif; ?>

    <?php if (!mail_ready()): ?>
      <p class="flash flash--info">
        Note: email sending is not configured on this site yet, so the code may not have
        been delivered. Ask the site owner to set up email in the dashboard.
      </p>
    <?php endif; ?>

    <form method="post" class="auth-form" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label class="field__label" for="v_code">Confirmation code</label>
        <input class="field__input code-input" type="text" id="v_code" name="code"
               inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code"
               placeholder="123456" required autofocus>
      </div>
      <button class="btn btn--primary btn--lg btn--block" type="submit">Confirm</button>
    </form>

    <form method="post" class="auth-form__resend">
      <?= csrf_field() ?>
      <input type="hidden" name="resend" value="1">
      <button class="btn btn--ghost btn--sm" type="submit">Resend code</button>
    </form>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

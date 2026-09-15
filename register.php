<?php
/**
 * Veloura Tec — Create an account.
 *
 * Registration creates an unverified account and emails a 6-digit code.
 * The account cannot sign in until the code is confirmed on verify.php.
 * Gated by the accounts_enabled setting.
 */

require __DIR__ . '/app/bootstrap.php';

if (!accounts_enabled()) {
    http_response_code(404);
    page_meta(['title' => 'Not available', 'robots' => 'noindex']);
    require __DIR__ . '/includes/header.php';
    echo '<section class="section"><div class="container"><div class="empty-state">'
       . '<h1>Accounts are not available</h1><p>Please use the '
       . '<a href="' . e(url('inquiry.php')) . '">inquiry form</a> to contact us.</p></div></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if (customer_check()) {
    redirect('account.php');
}

$errors = [];
$form = ['name' => '', 'email' => '', 'country' => '', 'company' => ''];

if (is_post()) {
    csrf_guard();

    foreach (['name', 'email', 'country', 'company'] as $f) {
        $form[$f] = trim((string) ($_POST[$f] ?? ''));
    }
    $password = (string) ($_POST['password'] ?? '');

    $errors = customer_validate($form + ['password' => $password]);

    // Reject a duplicate email (but do not reveal verified/unverified state).
    if (!isset($errors['email']) && customer_by_email($form['email']) !== null) {
        $errors['email'] = 'An account with this email already exists. Try signing in.';
    }

    if ($errors === []) {
        $created = customer_create($form + ['password' => $password]);
        $customer = customer_by_id($created['id']);

        customer_send_code($customer, $created['code']);

        // Remember which account is verifying, so verify.php knows who.
        $_SESSION['verify_customer_id'] = $created['id'];

        flash('info', 'We sent a confirmation code to ' . $form['email'] . '.');
        redirect('verify.php');
    }

    flash('error', 'Please correct the highlighted fields.');
}

page_meta([
    'title'     => 'Create an account',
    'canonical' => url('register.php'),
    'robots'    => 'noindex, follow',
]);

require __DIR__ . '/includes/header.php';
$heroEyebrow = 'Account';
$heroTitle   = 'Create an account';
$heroLead    = 'Register to track your inquiries. We will email you a confirmation code.';
require __DIR__ . '/includes/page-hero.php';
?>
<section class="section">
  <div class="container narrow">
    <form method="post" class="auth-form" novalidate>
      <?= csrf_field() ?>

      <div class="field<?= isset($errors['name']) ? ' field--invalid' : '' ?>">
        <label class="field__label" for="r_name">Full name <span class="field__req">*</span></label>
        <input class="field__input" type="text" id="r_name" name="name" value="<?= e($form['name']) ?>"
               maxlength="160" autocomplete="name" required>
        <?php if (isset($errors['name'])): ?><span class="field__error"><?= e($errors['name']) ?></span><?php endif; ?>
      </div>

      <div class="field<?= isset($errors['email']) ? ' field--invalid' : '' ?>">
        <label class="field__label" for="r_email">Email <span class="field__req">*</span></label>
        <input class="field__input" type="email" id="r_email" name="email" value="<?= e($form['email']) ?>"
               maxlength="190" autocomplete="email" required>
        <?php if (isset($errors['email'])): ?><span class="field__error"><?= e($errors['email']) ?></span><?php endif; ?>
      </div>

      <div class="field-pair">
        <div class="field">
          <label class="field__label" for="r_country">Country <span class="field__optional">(optional)</span></label>
          <input class="field__input" type="text" id="r_country" name="country" value="<?= e($form['country']) ?>"
                 maxlength="120" autocomplete="country-name">
        </div>
        <div class="field">
          <label class="field__label" for="r_company">Company / clinic <span class="field__optional">(optional)</span></label>
          <input class="field__input" type="text" id="r_company" name="company" value="<?= e($form['company']) ?>"
                 maxlength="190" autocomplete="organization">
        </div>
      </div>

      <div class="field<?= isset($errors['password']) ? ' field--invalid' : '' ?>">
        <label class="field__label" for="r_password">Password <span class="field__req">*</span></label>
        <input class="field__input" type="password" id="r_password" name="password" minlength="8"
               autocomplete="new-password" required>
        <span class="field__hint">At least 8 characters.</span>
        <?php if (isset($errors['password'])): ?><span class="field__error"><?= e($errors['password']) ?></span><?php endif; ?>
      </div>

      <button class="btn btn--primary btn--lg btn--block" type="submit">Create account</button>

      <p class="auth-form__alt">
        Already have an account? <a href="<?= e(url('account-login.php')) ?>">Sign in</a>.
      </p>
    </form>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

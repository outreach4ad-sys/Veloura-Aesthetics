<?php
/**
 * Veloura Tec — Inquiry cart.
 *
 * This is an INQUIRY flow, not a checkout. No payment is taken, no order
 * is placed, and nothing here claims otherwise. The customer builds a
 * list, submits their details, and the request is delivered to the team
 * over WhatsApp with a reference number they can quote back.
 *
 * Where a payment gateway would slot in later:
 *   - the cart resolves server-side in cart_resolve(), which already
 *     returns authoritative prices and line totals;
 *   - inquiry_create() writes a header row plus line items, the same
 *     shape an order needs;
 *   - a gateway would add a payment step between validation and the
 *     success state, and a second status set alongside INQUIRY_STATUSES.
 * Nothing in this file would need to be rewritten for that.
 */

require __DIR__ . '/app/bootstrap.php';

$errors  = [];
$success = null;

// The form re-renders with what the customer typed when validation fails.
$form = [
    'full_name' => '',
    'country'   => '',
    'city'      => '',
    'email'     => '',
    'whatsapp'  => '',
    'company'   => '',
    'notes'     => '',
];

// ---------------------------------------------------------------------
// Success state: reached by redirect after a successful submission. The
// reference is held in the session, so the page cannot be used to look up
// somebody else's inquiry by guessing a number.
// ---------------------------------------------------------------------
if (input('sent') !== null && !empty($_SESSION['inquiry_success'])) {
    $success = $_SESSION['inquiry_success'];
    unset($_SESSION['inquiry_success']);
}

// ---------------------------------------------------------------------
// Submission
// ---------------------------------------------------------------------
if (is_post()) {
    csrf_guard();

    foreach (array_keys($form) as $field) {
        $form[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    // Honeypot: a real customer never fills a field they cannot see.
    $trap = trim((string) ($_POST['website'] ?? ''));

    $rawItems = json_decode((string) ($_POST['cart'] ?? '[]'), true);
    $rawItems = is_array($rawItems) ? $rawItems : [];

    // Prices, names and URLs are read from the database here — never from
    // the submitted payload.
    $resolved = cart_resolve($rawItems);
    $lines    = $resolved['lines'];

    if ($lines === []) {
        $errors['cart'] = 'Your inquiry list is empty, or the products in it are no longer available.';
    }

    $errors += inquiry_validate($form);

    if ($errors === [] && inquiry_throttled()) {
        $errors['cart'] = 'You just sent an inquiry. Please wait a moment before sending another.';
    }

    if ($errors === [] && $trap === '') {
        $created = inquiry_create($form, $lines);
        inquiry_mark_submitted();

        // Best-effort emails: confirmation to the customer, notice to admin.
        // A mail failure never affects the saved inquiry.
        inquiry_send_emails($created, $form, $lines);

        $message = inquiry_whatsapp_message($created['reference'], $form, $lines);
        $link    = whatsapp_link($message);

        $_SESSION['inquiry_success'] = [
            'reference'     => $created['reference'],
            'whatsapp_url'  => $link,
            'items'         => count($lines),
            'units'         => cart_totals($lines)['units'],
            'customer_name' => $form['full_name'],
            'email'         => $form['email'],
        ];

        // POST-then-redirect: a refresh cannot submit the inquiry twice.
        redirect('inquiry.php?sent=1');
    }

    if ($trap !== '') {
        // Silently accept and discard: a bot gets no signal either way.
        redirect('inquiry.php?sent=1');
    }
}

page_meta([
    'title'       => t('inquiry.title'),
    'description' => 'Review the equipment you selected and send your inquiry to our team.',
    'canonical'   => url('inquiry.php'),
    'robots'      => 'noindex, follow',
]);

$pageScript = 'inquiry.js';

require __DIR__ . '/includes/header.php';
?>

<?php if ($success !== null): ?>

  <!-- ==================================================== success -->
  <section class="section">
    <div class="container">
      <div class="success" data-clear-cart>
        <p class="success__eyebrow">Inquiry sent</p>
        <h1 class="success__title"><?= e(t('inquiry.success')) ?></h1>

        <p class="success__reference">
          Reference <strong><?= e($success['reference']) ?></strong>
        </p>

        <p class="success__body">
          Thank you, <?= e($success['customer_name']) ?>. We have recorded your request for
          <?= (int) $success['items'] ?> <?= (int) $success['items'] === 1 ? 'product' : 'products' ?>
          (<?= (int) $success['units'] ?> <?= (int) $success['units'] === 1 ? 'unit' : 'units' ?>).
          Quote your reference number in any follow-up.
        </p>

        <?php if ($success['whatsapp_url'] !== null): ?>
          <p class="success__body">
            The last step is to send it to our team on WhatsApp. Your message is already
            prepared — just press send in WhatsApp.
          </p>

          <p class="success__actions">
            <a class="btn btn--primary btn--lg"
               id="whatsapp-send"
               href="<?= e($success['whatsapp_url']) ?>"
               target="_blank" rel="noopener">
              <?= e(t('cta.send_whatsapp')) ?>
            </a>
            <a class="btn btn--ghost" href="<?= e(url('shop.php')) ?>">
              <?= e(t('cta.continue')) ?>
            </a>
          </p>

          <p class="success__hint">
            WhatsApp did not open? <a href="<?= e($success['whatsapp_url']) ?>"
              target="_blank" rel="noopener">Open it manually</a>.
            Your inquiry is saved either way.
          </p>
        <?php else: ?>
          <p class="flash flash--info">
            Your inquiry is saved, but the WhatsApp number has not been configured yet.
            Our team will still receive it. You can also reach us
            <a href="<?= e(url('contact.php')) ?>">through the contact page</a>.
          </p>
        <?php endif; ?>
      </div>
    </div>
  </section>

<?php else: ?>

  <!-- ======================================================== cart -->
  <section class="section">
    <div class="container">

      <div class="section__head">
        <span class="section__eyebrow">Request a quote</span>
        <h1><?= e(t('inquiry.title')) ?></h1>
        <p class="section__lead">
          Review your list, set the quantities you need, and send it to our team.
          This is a request for a quote — no payment is taken at this stage.
        </p>
      </div>

      <?php foreach ($errors as $field => $message): ?>
        <?php if ($field === 'cart'): ?>
          <p class="flash flash--error" role="alert"><?= e($message) ?></p>
        <?php endif; ?>
      <?php endforeach; ?>

      <!-- Rendered by inquiry.js from localStorage + api/cart.php -->
      <div class="inquiry" id="inquiry-root">

        <div class="inquiry__cart">
          <div id="cart-loading" class="cart-loading">
            <p><?= e(t('common.loading')) ?></p>
          </div>

          <div id="cart-empty" class="empty-state" hidden>
            <p><strong><?= e(t('inquiry.empty')) ?></strong></p>
            <p><?= e(t('inquiry.empty_hint')) ?></p>
            <p><a class="btn btn--primary" href="<?= e(url('shop.php')) ?>">
              <?= e(t('cta.explore')) ?>
            </a></p>
          </div>

          <div id="cart-content" hidden>
            <p id="cart-dropped" class="flash flash--info" hidden></p>

            <ul class="cart-list" id="cart-list"></ul>

            <div class="cart-summary">
              <p class="cart-summary__line">
                <span>Products</span>
                <strong id="cart-units">0</strong>
              </p>
              <p class="cart-summary__line" id="cart-total-line" hidden>
                <span>Listed total</span>
                <strong id="cart-total"></strong>
              </p>
              <p class="cart-summary__note" id="cart-quote-note" hidden>
                Some items are quote-only, so no total is shown. Our team will price the
                full list for you.
              </p>
              <p class="cart-summary__note">
                Shipping and any applicable charges are quoted separately for your location.
              </p>
            </div>
          </div>
        </div>

        <!-- ------------------------------------------------ form -->
        <div class="inquiry__form" id="inquiry-form-wrap" hidden>
          <form method="post" action="<?= e(url('inquiry.php')) ?>" novalidate id="inquiry-form">
            <?= csrf_field() ?>
            <input type="hidden" name="cart" id="cart-payload" value="[]">

            <h2 class="inquiry__form-title"><?= e(t('inquiry.your_details')) ?></h2>

            <div class="field-pair">
              <div class="field<?= isset($errors['full_name']) ? ' field--invalid' : '' ?>">
                <label class="field__label" for="full_name">
                  <?= e(t('inquiry.full_name')) ?> <span class="field__req">*</span>
                </label>
                <input class="field__input" type="text" id="full_name" name="full_name"
                       value="<?= e($form['full_name']) ?>" maxlength="160"
                       autocomplete="name" required>
                <?php if (isset($errors['full_name'])): ?>
                  <span class="field__error"><?= e($errors['full_name']) ?></span>
                <?php endif; ?>
              </div>

              <div class="field<?= isset($errors['company']) ? ' field--invalid' : '' ?>">
                <label class="field__label" for="company">
                  <?= e(t('inquiry.company')) ?>
                  <span class="field__optional">(<?= e(t('inquiry.optional')) ?>)</span>
                </label>
                <input class="field__input" type="text" id="company" name="company"
                       value="<?= e($form['company']) ?>" maxlength="190"
                       autocomplete="organization">
                <?php if (isset($errors['company'])): ?>
                  <span class="field__error"><?= e($errors['company']) ?></span>
                <?php endif; ?>
              </div>
            </div>

            <div class="field-pair">
              <div class="field<?= isset($errors['country']) ? ' field--invalid' : '' ?>">
                <label class="field__label" for="country">
                  <?= e(t('inquiry.country')) ?> <span class="field__req">*</span>
                </label>
                <input class="field__input" type="text" id="country" name="country"
                       value="<?= e($form['country']) ?>" maxlength="120"
                       autocomplete="country-name" required>
                <?php if (isset($errors['country'])): ?>
                  <span class="field__error"><?= e($errors['country']) ?></span>
                <?php endif; ?>
              </div>

              <div class="field<?= isset($errors['city']) ? ' field--invalid' : '' ?>">
                <label class="field__label" for="city">
                  <?= e(t('inquiry.city')) ?>
                  <span class="field__optional">(<?= e(t('inquiry.optional')) ?>)</span>
                </label>
                <input class="field__input" type="text" id="city" name="city"
                       value="<?= e($form['city']) ?>" maxlength="120"
                       autocomplete="address-level2">
                <?php if (isset($errors['city'])): ?>
                  <span class="field__error"><?= e($errors['city']) ?></span>
                <?php endif; ?>
              </div>
            </div>

            <div class="field-pair">
              <div class="field<?= isset($errors['whatsapp']) ? ' field--invalid' : '' ?>">
                <label class="field__label" for="whatsapp">
                  <?= e(t('inquiry.whatsapp')) ?> <span class="field__req">*</span>
                </label>
                <input class="field__input" type="tel" id="whatsapp" name="whatsapp"
                       value="<?= e($form['whatsapp']) ?>" maxlength="40"
                       placeholder="+49 170 1234567" autocomplete="tel"
                       inputmode="tel" required>
                <span class="field__hint">Include your country code so we can reply.</span>
                <?php if (isset($errors['whatsapp'])): ?>
                  <span class="field__error"><?= e($errors['whatsapp']) ?></span>
                <?php endif; ?>
              </div>

              <div class="field<?= isset($errors['email']) ? ' field--invalid' : '' ?>">
                <label class="field__label" for="email">
                  <?= e(t('inquiry.email')) ?>
                  <span class="field__optional">(<?= e(t('inquiry.optional')) ?>)</span>
                </label>
                <input class="field__input" type="email" id="email" name="email"
                       value="<?= e($form['email']) ?>" maxlength="190"
                       autocomplete="email" inputmode="email">
                <?php if (isset($errors['email'])): ?>
                  <span class="field__error"><?= e($errors['email']) ?></span>
                <?php endif; ?>
              </div>
            </div>

            <div class="field<?= isset($errors['notes']) ? ' field--invalid' : '' ?>">
              <label class="field__label" for="notes">
                <?= e(t('inquiry.notes')) ?>
                <span class="field__optional">(<?= e(t('inquiry.optional')) ?>)</span>
              </label>
              <textarea class="field__textarea" id="notes" name="notes" rows="4"
                        maxlength="2000"
                        placeholder="Delivery timeline, installation needs, questions about a model…"><?= e($form['notes']) ?></textarea>
              <?php if (isset($errors['notes'])): ?>
                <span class="field__error"><?= e($errors['notes']) ?></span>
              <?php endif; ?>
            </div>

            <!-- Honeypot: hidden from people, tempting to bots. -->
            <div class="field-trap" aria-hidden="true">
              <label for="website">Website</label>
              <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <button class="btn btn--primary btn--lg btn--block" type="submit">
              <?= e(t('cta.send_whatsapp')) ?>
            </button>

            <p class="inquiry__legal">
              Sending this saves your request and opens WhatsApp with the details prepared.
              No payment is taken and no order is placed — our team will reply with a quote.
            </p>
          </form>
        </div>

      </div>
    </div>
  </section>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

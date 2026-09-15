<?php
/**
 * Veloura Tec — Contact Us.
 *
 * Contact channels are pulled from Site Settings; any still holding a
 * placeholder is simply hidden (setting_public()). The message form
 * composes a WhatsApp message with the configured number — the same
 * channel the inquiry flow uses — so there is no new data store, no email
 * server dependency and no spam-collecting endpoint. When WhatsApp is not
 * configured, the form falls back to an email (mailto) draft.
 */

require __DIR__ . '/app/bootstrap.php';

$email    = setting_public('contact_email');
$phone    = setting_public('contact_phone');
$address  = setting_public('contact_address');
$hours    = setting_public('business_hours');
$waNumber = whatsapp_number();

page_meta([
    'title'       => 'Contact Us',
    'description' => 'Get in touch with our team about professional aesthetic equipment, quotes, availability and shipping.',
    'canonical'   => url('contact.php'),
]);

$pageScript = 'contact.js';

require __DIR__ . '/includes/header.php';

$crumbs = [['label' => t('nav.contact')]];
require __DIR__ . '/includes/breadcrumbs.php';

$heroEyebrow = 'Contact';
$heroTitle   = 'Contact us';
$heroLead    = 'Questions about a product, a quote, availability or shipping? Send us a message and we will get back to you.';
require __DIR__ . '/includes/page-hero.php';
?>

<section class="section">
  <div class="container">
    <div class="contact-grid">

      <!-- Channels -->
      <div>
        <div class="channel-list">
          <?php if ($waNumber !== null): ?>
            <div class="channel">
              <div>
                <p class="channel__label">WhatsApp</p>
                <p class="channel__value">
                  <a href="<?= e(whatsapp_link('')) ?>" target="_blank" rel="noopener">
                    Message us on WhatsApp
                  </a>
                </p>
                <p class="channel__note">Fastest way to reach us for quotes and availability.</p>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($email !== ''): ?>
            <div class="channel">
              <div>
                <p class="channel__label">Email</p>
                <p class="channel__value"><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($phone !== ''): ?>
            <div class="channel">
              <div>
                <p class="channel__label">Phone</p>
                <p class="channel__value">
                  <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= e($phone) ?></a>
                </p>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($address !== ''): ?>
            <div class="channel">
              <div>
                <p class="channel__label">Address</p>
                <p class="channel__value"><?= nl2br(e($address)) ?></p>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($hours !== ''): ?>
            <div class="channel">
              <div>
                <p class="channel__label">Business hours</p>
                <p class="channel__value"><?= nl2br(e($hours)) ?></p>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($waNumber === null && $email === '' && $phone === ''): ?>
            <div class="editor-note">
              <strong>To complete before launch:</strong> add contact details in
              Admin &rarr; Settings (WhatsApp number, email, phone, address, business
              hours). Until then this page has no way for visitors to reach you.
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Message form -->
      <div class="contact-form">
        <h2>Send a message</h2>

        <?php if ($waNumber !== null || $email !== ''): ?>
          <form id="contact-form" novalidate>
            <div class="field">
              <label class="field__label" for="c_name">Your name <span class="field__req">*</span></label>
              <input class="field__input" type="text" id="c_name" name="name" maxlength="160"
                     autocomplete="name" required>
            </div>

            <div class="field-pair">
              <div class="field">
                <label class="field__label" for="c_company">Company / clinic
                  <span class="field__optional">(optional)</span></label>
                <input class="field__input" type="text" id="c_company" name="company"
                       maxlength="190" autocomplete="organization">
              </div>
              <div class="field">
                <label class="field__label" for="c_country">Country
                  <span class="field__optional">(optional)</span></label>
                <input class="field__input" type="text" id="c_country" name="country"
                       maxlength="120" autocomplete="country-name">
              </div>
            </div>

            <div class="field">
              <label class="field__label" for="c_message">Message <span class="field__req">*</span></label>
              <textarea class="field__textarea" id="c_message" name="message" rows="5"
                        maxlength="2000" required
                        placeholder="Which product or service are you interested in? Include any details that help us respond."></textarea>
            </div>

            <p class="field__error" id="c_error" hidden></p>

            <button class="btn btn--primary btn--lg btn--block" type="submit"
                    data-wa="<?= e($waNumber !== null ? '1' : '0') ?>"
                    data-email="<?= e($email) ?>">
              <?= $waNumber !== null ? 'Send via WhatsApp' : 'Send by email' ?>
            </button>

            <p class="channel__note channel__note--spaced">
              This opens <?= $waNumber !== null ? 'WhatsApp' : 'your email app' ?> with your
              message ready to send. No payment is taken and no order is placed here.
            </p>
          </form>
        <?php else: ?>
          <p class="channel__note">The contact form will be available once contact
          details are configured.</p>
        <?php endif; ?>
      </div>

    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

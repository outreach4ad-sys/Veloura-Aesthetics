<?php
/**
 * Veloura Tec — Privacy Policy.
 *
 * Describes the data the site actually collects (the inquiry form fields,
 * plus the IP/user-agent stored with each inquiry, and the localStorage
 * cart). Company- and jurisdiction-specific legal details are marked as
 * placeholders, and an editorial note flags that legal review is required
 * before this is relied upon.
 */

require __DIR__ . '/app/bootstrap.php';

$companyName = (string) setting('company_name', 'Optical Cargo');
$email       = setting_public('contact_email');

page_meta([
    'title'       => 'Privacy Policy',
    'description' => 'How ' . $companyName . ' handles the information you provide through the Veloura Tec website.',
    'canonical'   => url('privacy.php'),
    'robots'      => 'index, follow',
]);

require __DIR__ . '/includes/header.php';

$crumbs = [['label' => 'Privacy Policy']];
require __DIR__ . '/includes/breadcrumbs.php';

$heroEyebrow = 'Legal';
$heroTitle   = 'Privacy Policy';
require __DIR__ . '/includes/page-hero.php';
?>

<section class="section">
  <div class="container">
    <div class="prose">

      <p class="legal-updated">Last updated: <span class="ph">[DATE]</span></p>

      <div class="editor-note">
        <strong>Review required:</strong> this policy describes the data this website
        collects, but company- and jurisdiction-specific details (legal entity,
        governing law, data-retention periods, any analytics or third-party services
        you add) must be completed and reviewed by qualified counsel before you rely on
        it. Placeholders are marked <span class="ph">like this</span>.
      </div>

      <p>
        This policy explains how <strong><?= e($companyName) ?></strong> handles the
        information you provide through this website. It applies to the Veloura Tec
        website only.
      </p>

      <h2>Information we collect</h2>
      <p>When you send an inquiry through the website, we collect the details you enter in the form:</p>
      <ul>
        <li>Your full name</li>
        <li>Country and, if you provide it, city</li>
        <li>WhatsApp number</li>
        <li>Email address (optional)</li>
        <li>Company or clinic name (optional)</li>
        <li>Any notes you add, and the products and quantities in your inquiry</li>
      </ul>
      <p>
        For each submitted inquiry we also record technical information — the IP address
        and browser user-agent of the request — to help prevent abuse of the form.
      </p>
      <p>
        Your inquiry list before you submit it is stored only in your own browser
        (local storage) and is not sent to us until you submit the inquiry.
      </p>

      <h2>How we use it</h2>
      <ul>
        <li>To respond to your inquiry with availability, pricing and shipping</li>
        <li>To contact you about your request, primarily over WhatsApp or email</li>
        <li>To keep a record of inquiries so we can follow up and reference them</li>
      </ul>
      <p>We do not sell your information.</p>

      <h2>Sharing</h2>
      <p>
        We use <span class="ph">[hosting provider]</span> to host this website and store
        inquiries. We do not share your details with other parties except as needed to
        respond to your inquiry or as required by law. <span class="ph">[List any other
        third-party services you use, e.g. analytics, email, before publishing.]</span>
      </p>

      <h2>Retention</h2>
      <p>
        We keep inquiry records for <span class="ph">[retention period]</span> so we can
        follow up and maintain a business record, after which they are deleted or
        anonymised.
      </p>

      <h2>Your choices</h2>
      <p>
        You can ask us what information we hold about you, ask us to correct it, or ask
        us to delete it, by contacting us
        <?php if ($email !== ''): ?>at <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a><?php else: ?>through the <a href="<?= e(url('contact.php')) ?>">contact page</a><?php endif; ?>.
        <span class="ph">[Add any rights specific to your jurisdiction.]</span>
      </p>

      <h2>Contact</h2>
      <p>
        Questions about this policy can be sent
        <?php if ($email !== ''): ?>to <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a><?php else: ?>through the <a href="<?= e(url('contact.php')) ?>">contact page</a><?php endif; ?>.
      </p>

    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

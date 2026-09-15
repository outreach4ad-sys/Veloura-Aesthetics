<?php
/**
 * Veloura Tec — Terms & Conditions.
 *
 * Covers use of the website and the inquiry (not a sale) nature of the
 * flow. Jurisdiction and company-specific clauses are placeholders and
 * flagged for legal review.
 */

require __DIR__ . '/app/bootstrap.php';

$companyName = (string) setting('company_name', 'Optical Cargo');
$siteName    = (string) setting('site_name', 'Veloura Tec');

page_meta([
    'title'       => 'Terms & Conditions',
    'description' => 'The terms that apply to using the ' . $siteName . ' website and sending inquiries.',
    'canonical'   => url('terms.php'),
]);

require __DIR__ . '/includes/header.php';

$crumbs = [['label' => 'Terms & Conditions']];
require __DIR__ . '/includes/breadcrumbs.php';

$heroEyebrow = 'Legal';
$heroTitle   = 'Terms & Conditions';
require __DIR__ . '/includes/page-hero.php';
?>

<section class="section">
  <div class="container">
    <div class="prose">

      <p class="legal-updated">Last updated: <span class="ph">[DATE]</span></p>

      <div class="editor-note">
        <strong>Review required:</strong> these terms are a starting point describing how
        the website and inquiry process work. The legal entity, governing law,
        liability and warranty clauses must be completed and reviewed by qualified
        counsel before you rely on them. Placeholders are marked
        <span class="ph">like this</span>.
      </div>

      <p>
        These terms apply to your use of the <strong><?= e($siteName) ?></strong> website,
        operated by <strong><?= e($companyName) ?></strong>. By using the website you
        agree to them.
      </p>

      <h2>Inquiries are not orders</h2>
      <p>
        The website lets you build a list of equipment and send it to us as an inquiry.
        An inquiry is a request for information and a quote. It is not an order, not a
        contract of sale, and no payment is taken through the website. A sale is only
        agreed separately, in writing, once details such as price, specification,
        shipping and payment terms are confirmed between you and us.
      </p>

      <h2>Product information</h2>
      <p>
        We aim to describe products accurately, but information on the website —
        including images, specifications and prices — may contain errors or change
        without notice, and is provided for general information. Details that apply to
        your purchase are confirmed in your quote.
      </p>

      <h2>Acceptable use</h2>
      <ul>
        <li>Use the website and the inquiry form only for genuine business inquiries.</li>
        <li>Do not submit false information or use the form to send unsolicited or automated content.</li>
        <li>Do not attempt to disrupt, probe or gain unauthorised access to the website.</li>
      </ul>

      <h2>Intellectual property</h2>
      <p>
        The content of this website is owned by <?= e($companyName) ?> or its suppliers
        and may not be copied or reused without permission, except as needed to use the
        site normally.
      </p>

      <h2>Liability</h2>
      <p>
        The website is provided "as is". <span class="ph">[Insert your limitation of
        liability and warranty disclaimer, reviewed by counsel and valid in your
        jurisdiction.]</span>
      </p>

      <h2>Governing law</h2>
      <p>
        These terms are governed by the laws of <span class="ph">[jurisdiction]</span>,
        and any dispute is subject to the courts of <span class="ph">[jurisdiction]</span>.
      </p>

      <h2>Changes</h2>
      <p>
        We may update these terms from time to time. The version published here is the
        one that applies.
      </p>

    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

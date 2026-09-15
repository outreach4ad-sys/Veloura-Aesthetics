<?php
/**
 * Veloura Tec — FAQ.
 *
 * Native <details> accordion (works without JS) plus FAQPage structured
 * data. Answers describe only how the inquiry process works — no claims
 * about results, certifications, or shipping guarantees.
 */

require __DIR__ . '/app/bootstrap.php';

$faqs = [
    [
        'Do I buy directly on the website?',
        'No. The website works on an inquiry basis rather than a fixed checkout. You add the equipment you are interested in to an inquiry list, send it to us with your details, and we respond with availability, pricing and shipping for your location. No payment is taken on the site.',
    ],
    [
        'Why do some products not show a price?',
        'Professional equipment pricing can depend on configuration, destination and order size, so some products are listed as "Price on request". Add them to your inquiry and we will quote them for you.',
    ],
    [
        'How do I request a quote?',
        'Browse the shop, add products to your inquiry list, then open the inquiry cart and fill in your name, country and WhatsApp number (email and company are optional). Sending the inquiry saves your request and opens WhatsApp with the details prepared for our team.',
    ],
    [
        'Who can order from you?',
        'We work with aesthetic clinics, beauty centers and salons, dermatology and aesthetic professionals, wellness and physiotherapy professionals, and distributors and business buyers.',
    ],
    [
        'Do you ship internationally?',
        'We confirm shipping options, lead times and costs per inquiry, because they depend on the destination and the specific equipment. Send us your location with your inquiry and we will tell you what is available.',
    ],
    [
        'What information will you send back?',
        'After you submit an inquiry we follow up with availability, pricing and shipping details, and answer any questions about the equipment. Your inquiry reference number helps us track the conversation.',
    ],
    [
        'Is installation, training or support included?',
        'This varies by product. Where a product page lists installation, training, warranty or support details, those reflect what has been provided for that item. If a product page does not list them, ask in your inquiry and we will confirm.',
    ],
];

page_meta([
    'title'       => 'Frequently Asked Questions',
    'description' => 'Answers about ordering, quotes, pricing, shipping and how the inquiry process works.',
    'canonical'   => url('faq.php'),
]);

// FAQPage structured data.
schema_add([
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'mainEntity' => array_map(static fn (array $f): array => [
        '@type'          => 'Question',
        'name'           => $f[0],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
    ], $faqs),
]);

require __DIR__ . '/includes/header.php';

$crumbs = [['label' => t('nav.faq')]];
require __DIR__ . '/includes/breadcrumbs.php';

$heroEyebrow = 'Help';
$heroTitle   = 'Frequently asked questions';
$heroLead    = 'How ordering, quotes and shipping work with us.';
require __DIR__ . '/includes/page-hero.php';
?>

<section class="section">
  <div class="container">
    <div class="faq-list">
      <?php foreach ($faqs as $i => [$q, $a]): ?>
        <details class="faq-item"<?= $i === 0 ? ' open' : '' ?>>
          <summary><?= e($q) ?></summary>
          <div class="faq-item__body"><p><?= e($a) ?></p></div>
        </details>
      <?php endforeach; ?>
    </div>

    <div class="section__head section__head--spaced">
      <p class="section__lead">Still have a question?</p>
      <p>
        <a class="btn btn--primary" href="<?= e(url('contact.php')) ?>"><?= e(t('nav.contact')) ?></a>
        <a class="btn btn--ghost" href="<?= e(url('shop.php')) ?>"><?= e(t('cta.explore')) ?></a>
      </p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

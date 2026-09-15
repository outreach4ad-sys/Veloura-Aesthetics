<?php
/**
 * Veloura Tec — About Us.
 *
 * Copy describes only what the brief states: Optical Cargo markets,
 * supplies and equips professional aesthetic and wellness facilities. No
 * founding date, headcount, certifications, awards or partnerships are
 * invented; where a specific fact would belong it is either omitted or
 * marked as a placeholder for the company to complete.
 */

require __DIR__ . '/app/bootstrap.php';

$companyName = (string) setting('company_name', 'Optical Cargo');
$siteName    = (string) setting('site_name', 'Veloura Tec');

page_meta([
    'title'       => 'About Us',
    'description' => $companyName . ' supplies and equips aesthetic clinics, beauty centers and wellness facilities with professional aesthetic technology.',
    'canonical'   => url('about.php'),
]);

require __DIR__ . '/includes/header.php';

$crumbs = [['label' => 'About Us']];
require __DIR__ . '/includes/breadcrumbs.php';

$heroEyebrow = 'About';
$heroTitle   = 'About ' . $companyName;
$heroLead    = 'Professional aesthetic technology and equipment for clinics, beauty centers and wellness professionals.';
require __DIR__ . '/includes/page-hero.php';
?>

<section class="section">
  <div class="container">
    <div class="prose">

      <p>
        <strong><?= e($companyName) ?></strong> specialises in marketing, supplying and
        equipping beauty centers, aesthetic clinics and professional wellness facilities
        with aesthetic devices, equipment and related products. <?= e($siteName) ?> is the
        brand under which we present our catalogue to professionals worldwide.
      </p>

      <p>
        Our focus is the professional buyer: the clinic owner selecting a treatment
        platform, the aesthetic center adding a new service line, the distributor
        sourcing equipment for a regional market. We work to make that selection
        straightforward — clear product information, direct communication, and a
        quote process built around how professionals actually buy.
      </p>

      <h2>What we do</h2>
      <p>
        We bring together professional aesthetic equipment across the technologies our
        customers ask for most, and present it in one catalogue with the details that
        matter for a purchasing decision. Rather than a fixed online checkout, we work
        on an inquiry basis: you build a list of the equipment you are interested in and
        send it to us, and we respond with availability, pricing and shipping for your
        location.
      </p>

      <h2>Who we serve</h2>
      <ul>
        <li>Beauty clinic and aesthetic center owners</li>
        <li>Dermatology and aesthetic professionals</li>
        <li>Beauty salon owners</li>
        <li>Wellness and physiotherapy professionals</li>
        <li>Distributors and international business buyers</li>
      </ul>

      <h2>Our categories</h2>
      <p>
        Our catalogue spans Hydrafacial, hair removal, CO2 laser, Nd:YAG tattoo removal,
        facial products, EMS technology, body contouring and weight management,
        physiotherapy equipment and microneedling. You can
        <a href="<?= e(url('shop.php')) ?>">browse the full catalogue</a> or explore a
        specific technology from the shop.
      </p>

      <h2>Working internationally</h2>
      <p>
        We are building toward serving professional buyers across multiple markets, not
        a single country. Shipping, lead times and available options are confirmed per
        inquiry, because they depend on the destination and the specific equipment —
        we prefer to tell you what is actually possible for your location rather than
        make a blanket promise.
      </p>

      <div class="editor-note">
        <strong>To complete before launch:</strong> add the company's real background
        details here — for example registered business name, location, years of
        operation and any verifiable credentials. Do not publish specific claims
        (certifications, partnerships, approvals) unless they can be substantiated.
      </div>

    </div>
  </div>
</section>

<section class="section section--alt">
  <div class="container">
    <div class="section__head">
      <h2>Request a quote</h2>
      <p class="section__lead">
        Tell us which equipment you are considering and we will follow up with
        availability, pricing and shipping for your location.
      </p>
      <p>
        <a class="btn btn--primary" href="<?= e(url('shop.php')) ?>"><?= e(t('cta.explore')) ?></a>
        <a class="btn btn--ghost" href="<?= e(url('contact.php')) ?>"><?= e(t('nav.contact')) ?></a>
      </p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

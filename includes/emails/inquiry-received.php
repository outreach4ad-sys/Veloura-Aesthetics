<?php
/** Expects: $name, $reference, $items (array of [name, qty]), $shopUrl */
if (!defined('VELOURA')) { exit('Forbidden'); }
?>
<h1 style="margin:0 0 12px;font-size:20px;color:#0B1F35;">We received your inquiry</h1>
<p style="margin:0 0 16px;line-height:1.6;">Hello <?= e($name) ?>,</p>
<p style="margin:0 0 16px;line-height:1.6;">
  Thank you for your inquiry. Our team will reply with availability, pricing and shipping
  for your location. Please quote your reference in any follow-up.
</p>
<p style="margin:0 0 20px;">
  <span style="display:inline-block;background:#F7F5F2;border:1px dashed #B8734B;border-radius:6px;
               padding:8px 16px;font-size:16px;color:#0B1F35;">
    Reference: <strong><?= e($reference) ?></strong></span>
</p>
<?php if (!empty($items)): ?>
  <h2 style="font-size:15px;color:#0B1F35;margin:0 0 8px;">Requested products</h2>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:20px;">
    <?php foreach ($items as $it): ?>
      <tr>
        <td style="padding:8px 0;border-bottom:1px solid #E3E6EA;font-size:14px;"><?= e($it['name']) ?></td>
        <td style="padding:8px 0;border-bottom:1px solid #E3E6EA;font-size:14px;text-align:right;color:#5A6675;">
          Qty: <?= (int) $it['quantity'] ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>
<p style="margin:0 0 8px;line-height:1.6;">
  <a href="<?= e($shopUrl ?? abs_url('shop.php')) ?>"
     style="display:inline-block;background:#B8734B;color:#ffffff;text-decoration:none;
            padding:11px 22px;border-radius:5px;font-weight:bold;">Continue browsing</a>
</p>
<p style="margin:16px 0 0;color:#5A6675;font-size:13px;line-height:1.6;">
  This is a confirmation of your request. No payment has been taken and no order has been placed.
</p>

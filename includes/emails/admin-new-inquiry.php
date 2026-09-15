<?php
/** Expects: $reference, $customer (array), $items, $adminUrl */
if (!defined('VELOURA')) { exit('Forbidden'); }
?>
<h1 style="margin:0 0 12px;font-size:20px;color:#0B1F35;">New inquiry received</h1>
<p style="margin:0 0 16px;">
  <span style="display:inline-block;background:#F7F5F2;border:1px dashed #B8734B;border-radius:6px;
        padding:6px 14px;">Reference: <strong><?= e($reference) ?></strong></span>
</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;font-size:14px;">
  <tr><td style="padding:4px 0;color:#5A6675;">Name</td><td style="padding:4px 0;"><?= e($customer['full_name'] ?? '') ?></td></tr>
  <tr><td style="padding:4px 0;color:#5A6675;">Country</td><td style="padding:4px 0;"><?= e($customer['country'] ?? '') ?></td></tr>
  <tr><td style="padding:4px 0;color:#5A6675;">WhatsApp</td><td style="padding:4px 0;"><?= e($customer['whatsapp'] ?? '') ?></td></tr>
  <?php if (!empty($customer['email'])): ?>
  <tr><td style="padding:4px 0;color:#5A6675;">Email</td><td style="padding:4px 0;"><?= e($customer['email']) ?></td></tr>
  <?php endif; ?>
  <?php if (!empty($customer['company'])): ?>
  <tr><td style="padding:4px 0;color:#5A6675;">Company</td><td style="padding:4px 0;"><?= e($customer['company']) ?></td></tr>
  <?php endif; ?>
</table>
<p><a href="<?= e($adminUrl ?? abs_url('admin/inquiries.php')) ?>"
      style="display:inline-block;background:#0B1F35;color:#ffffff;text-decoration:none;
      padding:11px 22px;border-radius:5px;font-weight:bold;">Open in dashboard</a></p>

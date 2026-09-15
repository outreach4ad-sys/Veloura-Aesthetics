<?php
/**
 * Veloura Tec — Email layout wrapper.
 * Expects: $content (HTML string), $title (string).
 * Inline styles only — email clients ignore <style>/external CSS, and CSP
 * does not apply to email.
 */
if (!defined('VELOURA')) { exit('Forbidden'); }
$brand   = e(setting('site_name', 'Veloura Tec'));
$company = e(setting('company_name', 'Optical Cargo'));
$siteUrl = e(abs_url());
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#F7F5F2;font-family:Arial,Helvetica,sans-serif;color:#16202C;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7F5F2;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0"
             style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #E3E6EA;">
        <tr>
          <td style="background:#0B1F35;padding:22px 28px;">
            <a href="<?= $siteUrl ?>" style="color:#ffffff;text-decoration:none;font-size:20px;letter-spacing:2px;">
              <?= strtoupper($brand) ?>
            </a>
          </td>
        </tr>
        <tr><td style="padding:28px;"><?= $content ?? '' ?></td></tr>
        <tr>
          <td style="padding:20px 28px;background:#0B1F35;color:#9DB0C4;font-size:12px;line-height:1.6;">
            &copy; <?= date('Y') ?> <?= $company ?>. This message was sent from
            <a href="<?= $siteUrl ?>" style="color:#E4C4AE;"><?= $siteUrl ?></a>.
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>

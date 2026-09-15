<?php
/** Expects: $name, $code, $minutes */
if (!defined('VELOURA')) { exit('Forbidden'); }
?>
<h1 style="margin:0 0 12px;font-size:20px;color:#0B1F35;">Confirm your email</h1>
<p style="margin:0 0 16px;line-height:1.6;">Hello <?= e($name) ?>,</p>
<p style="margin:0 0 20px;line-height:1.6;">
  Use this code to confirm your account. It expires in <?= (int) $minutes ?> minutes.
</p>
<p style="text-align:center;margin:0 0 24px;">
  <span style="display:inline-block;font-size:32px;letter-spacing:10px;font-weight:bold;
               color:#0B1F35;background:#F7F5F2;border:1px dashed #B8734B;border-radius:8px;
               padding:14px 24px;"><?= e($code) ?></span>
</p>
<p style="margin:0;color:#5A6675;font-size:13px;line-height:1.6;">
  If you did not create an account, you can ignore this email.
</p>

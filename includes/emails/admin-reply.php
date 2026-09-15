<?php
/** Expects: $name, $reference, $bodyText */
if (!defined('VELOURA')) { exit('Forbidden'); }
?>
<h1 style="margin:0 0 12px;font-size:20px;color:#0B1F35;">Regarding your inquiry <?= e($reference) ?></h1>
<p style="margin:0 0 16px;line-height:1.6;">Hello <?= e($name) ?>,</p>
<div style="margin:0 0 16px;line-height:1.7;font-size:15px;white-space:pre-wrap;"><?= nl2br(e($bodyText)) ?></div>
<p style="margin:16px 0 0;color:#5A6675;font-size:13px;line-height:1.6;">
  You can reply directly to this email.
</p>

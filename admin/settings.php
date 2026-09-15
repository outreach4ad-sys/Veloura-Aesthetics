<?php
/**
 * Veloura Tec — Admin: site settings.
 *
 * Settings are rendered from the database rows themselves, so adding a new
 * key to site_settings makes it editable here with no code change.
 */

require __DIR__ . '/_auth.php';

$groups = [
    'general'  => 'General',
    'contact'  => 'Contact details',
    'commerce' => 'Catalog & pricing',
    'social'   => 'Social links',
    'policy'   => 'Policies',
    'mail'     => 'Email & accounts',
    'seo'      => 'SEO',
];

if (is_post()) {
    csrf_guard();

    $submitted = $_POST['settings'] ?? [];
    $saved     = 0;
    $errors    = [];

    if (is_array($submitted)) {
        // Only keys that already exist in the database are writable, so a
        // crafted form cannot inject new settings.
        $known = array_column(
            db_all('SELECT setting_key, setting_type FROM site_settings'),
            'setting_type',
            'setting_key'
        );

        foreach ($submitted as $key => $value) {
            $key = (string) $key;

            if (!isset($known[$key])) {
                continue;
            }

            $value = is_string($value) ? trim($value) : '';

            switch ($known[$key]) {
                case 'email':
                    if ($value !== '' && !str_starts_with($value, '[PLACEHOLDER')
                        && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = 'The value for "' . $key . '" is not a valid email address.';
                        continue 2;
                    }
                    break;

                case 'url':
                    if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                        $errors[] = 'The value for "' . $key . '" is not a valid URL.';
                        continue 2;
                    }
                    break;

                case 'number':
                    if ($value !== '' && !is_numeric($value)) {
                        $errors[] = 'The value for "' . $key . '" must be a number.';
                        continue 2;
                    }
                    break;

                case 'boolean':
                    $value = $value === '1' ? '1' : '0';
                    break;
            }

            setting_set($key, $value);
            $saved++;
        }
    }

    // Image settings upload through their own file inputs.
    foreach (db_all('SELECT setting_key FROM site_settings WHERE setting_type = "image"') as $row) {
        $key   = (string) $row['setting_key'];
        $field = 'file_' . $key;

        if (!upload_present($field)) {
            continue;
        }

        try {
            $previous = (string) setting($key, '');
            $path     = upload_image($field, 'hero');

            setting_set($key, $path);

            // Only remove the old file when it lived in uploads/ — the
            // default logo ships in assets/ and must survive.
            if ($previous !== '' && str_starts_with($previous, 'uploads/')) {
                upload_delete($previous);
            }

            $saved++;
        } catch (UploadException $e) {
            $errors[] = $key . ': ' . $e->getMessage();
        }
    }

    foreach ($errors as $message) {
        flash('error', $message);
    }

    if ($saved > 0) {
        flash('success', $saved . ' setting' . ($saved === 1 ? '' : 's') . ' saved.');
    }

    redirect('admin/settings.php');
}

$pageTitle = t('admin.settings');
require __DIR__ . '/_layout.php';
?>

<p class="admin-lead">
  These values feed the whole site. Anything still reading
  <code>[PLACEHOLDER: …]</code> is hidden from visitors until you replace it.
</p>

<form method="post" enctype="multipart/form-data" class="admin-form" novalidate>
  <?= csrf_field() ?>

  <?php foreach ($groups as $groupKey => $groupLabel): ?>
    <?php $rows = settings_group($groupKey); ?>
    <?php if ($rows === []) { continue; } ?>

    <fieldset class="panel">
      <legend class="panel__title"><?= e($groupLabel) ?></legend>

      <?php foreach ($rows as $row): ?>
        <?php
        $key   = (string) $row['setting_key'];
        $value = (string) ($row['setting_value'] ?? '');
        $name  = 'settings[' . $key . ']';
        $id    = 'set_' . preg_replace('/[^a-z0-9_]/i', '_', $key);
        $isPlaceholder = str_starts_with($value, '[PLACEHOLDER');
        ?>

        <div class="field<?= $isPlaceholder ? ' field--placeholder' : '' ?>">
          <label class="field__label" for="<?= e($id) ?>">
            <?= e($row['label']) ?>
            <?= $isPlaceholder ? status_badge('Placeholder', 'warn') : '' ?>
          </label>

          <?php if ($row['setting_type'] === 'textarea'): ?>
            <textarea class="field__textarea" id="<?= e($id) ?>" name="<?= e($name) ?>"
                      rows="4"><?= e($value) ?></textarea>

          <?php elseif ($row['setting_type'] === 'boolean'): ?>
            <select class="field__select" id="<?= e($id) ?>" name="<?= e($name) ?>">
              <option value="1" <?= $value === '1' ? 'selected' : '' ?>>Yes</option>
              <option value="0" <?= $value !== '1' ? 'selected' : '' ?>>No</option>
            </select>

          <?php elseif ($row['setting_type'] === 'image'): ?>
            <div class="image-field">
              <div class="image-field__preview">
                <img src="<?= e(upload_url($value)) ?>" alt=""
                     data-preview-for="<?= e($id) ?>_file" width="120" height="120" loading="lazy">
              </div>
              <div class="image-field__controls">
                <input class="field__input" type="text" id="<?= e($id) ?>"
                       name="<?= e($name) ?>" value="<?= e($value) ?>"
                       placeholder="assets/img/logo.png">
                <span class="field__hint">Path relative to the site root, or upload a replacement:</span>
                <input class="field__input" type="file" id="<?= e($id) ?>_file"
                       name="file_<?= e($key) ?>" accept="image/jpeg,image/png,image/webp"
                       data-image-input>
              </div>
            </div>

          <?php else: ?>
            <input class="field__input"
                   type="<?= e($row['setting_type'] === 'number' ? 'number' : 'text') ?>"
                   id="<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>">
          <?php endif; ?>

          <?php if ($key === 'whatsapp_number'): ?>
            <span class="field__hint">
              Digits only, including the country code and no leading zeros or plus sign —
              for example 491701234567. Used for the inquiry WhatsApp link.
            </span>
          <?php elseif ($key === 'shipping_info'): ?>
            <span class="field__hint">
              Describe only shipping terms you have confirmed. Do not promise destinations
              or lead times that are not agreed.
            </span>
          <?php endif; ?>
        </div>

      <?php endforeach; ?>
    </fieldset>
  <?php endforeach; ?>

  <div class="form-footer">
    <button class="btn btn--primary" type="submit">Save settings</button>
  </div>
</form>

<?php require __DIR__ . '/_layout_end.php'; ?>

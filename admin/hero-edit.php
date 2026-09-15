<?php
/**
 * Veloura Tec — Admin: create / edit a hero slide.
 */

require __DIR__ . '/_auth.php';

$id    = input_int('id');
$slide = $id > 0 ? hero_slide_by_id($id) : null;

if ($id > 0 && $slide === null) {
    flash('error', 'That slide no longer exists.');
    redirect('admin/hero.php');
}

$isNew  = $slide === null;
$errors = [];

$form = [
    'title'               => $slide['title']               ?? '',
    'subtitle'            => $slide['subtitle']            ?? '',
    'image_alt'           => $slide['image_alt']           ?? '',
    'cta_primary_label'   => $slide['cta_primary_label']   ?? 'Explore Equipment',
    'cta_primary_url'     => $slide['cta_primary_url']     ?? '/shop.php',
    'cta_secondary_label' => $slide['cta_secondary_label'] ?? 'Request a Quote',
    'cta_secondary_url'   => $slide['cta_secondary_url']   ?? '/inquiry.php',
    'sort_order'          => $slide['sort_order']          ?? 0,
    'is_published'        => (int) ($slide['is_published'] ?? 1),
];

$currentImage = $slide['image'] ?? null;

if (is_post()) {
    csrf_guard();

    foreach (array_keys($form) as $key) {
        $form[$key] = $_POST[$key] ?? '';
    }
    $form['is_published'] = (int) !empty($_POST['is_published']);

    $errors = hero_slide_validate($form);

    $imagePath = $currentImage;

    if ($errors === []) {
        try {
            if (!empty($_POST['image_remove'])) {
                upload_delete($currentImage);
                $imagePath = null;
            }

            $imagePath = upload_replace_image('image', 'hero', $imagePath);
        } catch (UploadException $e) {
            $errors['image'] = $e->getMessage();
        }
    }

    if ($errors === []) {
        $payload = $form + ['image' => $imagePath];

        if ($isNew) {
            $newId = hero_slide_create($payload);
            flash('success', 'Slide created.');
            redirect('admin/hero-edit.php?id=' . $newId);
        }

        hero_slide_update($id, $payload);
        flash('success', 'Slide updated.');
        redirect('admin/hero-edit.php?id=' . $id);
    }

    $currentImage = $imagePath;
    flash('error', 'Please correct the highlighted fields.');
}

$pageTitle = $isNew ? 'Add hero slide' : 'Edit hero slide';
require __DIR__ . '/_layout.php';
?>

<p class="admin-lead"><a href="<?= e(url('admin/hero.php')) ?>">&larr; Back to hero slider</a></p>

<form method="post" enctype="multipart/form-data" class="admin-form" novalidate>
  <?= csrf_field() ?>
  <?php if (!$isNew): ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
  <?php endif; ?>

  <div class="form-grid">
    <section class="form-main">

      <fieldset class="panel">
        <legend class="panel__title">Slide content</legend>

        <?php field_input('title', 'Headline', $form['title'], $errors, [
            'required'  => true,
            'maxlength' => 190,
        ]); ?>

        <?php field_textarea('subtitle', 'Supporting text', $form['subtitle'], $errors, [
            'rows'      => 3,
            'maxlength' => 400,
        ]); ?>
      </fieldset>

      <fieldset class="panel">
        <legend class="panel__title">Buttons</legend>
        <p class="panel__hint">Leave a label empty to hide that button.</p>

        <div class="field-row">
          <?php field_input('cta_primary_label', 'Primary button label',
              $form['cta_primary_label'], $errors, ['maxlength' => 80]); ?>
          <?php field_input('cta_primary_url', 'Primary button link',
              $form['cta_primary_url'], $errors, [
                  'hint' => 'A site path such as /shop.php, or a full URL.',
              ]); ?>
        </div>

        <div class="field-row">
          <?php field_input('cta_secondary_label', 'Secondary button label',
              $form['cta_secondary_label'], $errors, ['maxlength' => 80]); ?>
          <?php field_input('cta_secondary_url', 'Secondary button link',
              $form['cta_secondary_url'], $errors); ?>
        </div>
      </fieldset>

    </section>

    <aside class="form-side">

      <div class="panel">
        <h2 class="panel__title">Visibility</h2>

        <?php field_checkbox('is_published', 'Published', (int) $form['is_published'] === 1); ?>

        <?php field_input('sort_order', 'Sort order', $form['sort_order'], $errors, [
            'type'  => 'number',
            'attrs' => ['min' => '0', 'max' => '65535', 'step' => '1'],
            'hint'  => 'Lower numbers appear first.',
        ]); ?>
      </div>

      <div class="panel">
        <h2 class="panel__title">Background image</h2>
        <?php field_image('image', 'Image', $currentImage, $errors,
            'A wide landscape image works best, around 1800x1000.'); ?>
        <?php field_input('image_alt', 'Image alt text', $form['image_alt'], $errors, [
            'maxlength' => 190,
        ]); ?>
      </div>

      <div class="panel panel--actions">
        <button class="btn btn--primary btn--block" type="submit">
          <?= $isNew ? 'Create slide' : 'Save changes' ?>
        </button>
        <a class="btn btn--ghost btn--block" href="<?= e(url('admin/hero.php')) ?>">Cancel</a>
      </div>

    </aside>
  </div>
</form>

<?php require __DIR__ . '/_layout_end.php'; ?>

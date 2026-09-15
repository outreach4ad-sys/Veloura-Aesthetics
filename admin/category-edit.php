<?php
/**
 * Veloura Tec — Admin: create / edit a category.
 *
 * One file handles both the form and its submission, so validation rules
 * and the fields they guard stay next to each other.
 */

require __DIR__ . '/_auth.php';

$id       = input_int('id');
$category = $id > 0 ? category_by_id($id) : null;

if ($id > 0 && $category === null) {
    flash('error', 'That category no longer exists.');
    redirect('admin/categories.php');
}

$isNew  = $category === null;
$errors = [];

// Form state: the submitted values on failure, the stored row otherwise.
$form = [
    'name'            => $category['name']            ?? '',
    'slug'            => $category['slug']            ?? '',
    'description'     => $category['description']     ?? '',
    'sort_order'      => $category['sort_order']      ?? 0,
    'is_published'    => (int) ($category['is_published'] ?? 1),
    'seo_title'       => $category['seo_title']       ?? '',
    'seo_description' => $category['seo_description'] ?? '',
];

$currentImage = $category['image'] ?? null;

if (is_post()) {
    csrf_guard();

    foreach (array_keys($form) as $key) {
        $form[$key] = $_POST[$key] ?? '';
    }
    $form['is_published'] = (int) !empty($_POST['is_published']);

    $errors = category_validate($form, $id > 0 ? $id : null);

    // Handle the image only once the rest of the input is known good, so a
    // rejected form does not leave an orphaned upload behind.
    $imagePath = $currentImage;

    if ($errors === []) {
        try {
            if (!empty($_POST['image_remove'])) {
                upload_delete($currentImage);
                $imagePath = null;
            }

            $imagePath = upload_replace_image('image', 'categories', $imagePath);
        } catch (UploadException $e) {
            $errors['image'] = $e->getMessage();
        }
    }

    if ($errors === []) {
        $payload = $form + ['image' => $imagePath];

        if ($isNew) {
            $newId = category_create($payload);
            flash('success', 'Category created.');
            redirect('admin/category-edit.php?id=' . $newId);
        }

        category_update($id, $payload);
        flash('success', 'Category updated.');
        redirect('admin/category-edit.php?id=' . $id);
    }

    $currentImage = $imagePath;
    flash('error', 'Please correct the highlighted fields.');
}

$pageTitle = $isNew ? 'Add category' : 'Edit category';
require __DIR__ . '/_layout.php';
?>

<p class="admin-lead">
  <a href="<?= e(url('admin/categories.php')) ?>">&larr; Back to categories</a>
</p>

<form method="post" enctype="multipart/form-data" class="admin-form" novalidate>
  <?= csrf_field() ?>
  <?php if (!$isNew): ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
  <?php endif; ?>

  <div class="form-grid">
    <section class="form-main">

      <fieldset class="panel">
        <legend class="panel__title">Category details</legend>

        <?php field_input('name', 'Category name', $form['name'], $errors, [
            'required'  => true,
            'maxlength' => 160,
            'attrs'     => ['data-slug-source' => 'f_slug'],
        ]); ?>

        <?php field_input('slug', 'URL slug', $form['slug'], $errors, [
            'hint'      => 'Leave empty to generate it from the name. Used in the address: /category/your-slug',
            'maxlength' => 180,
        ]); ?>

        <?php field_textarea('description', 'Description', $form['description'], $errors, [
            'rows' => 5,
            'hint' => 'Shown at the top of the category page. Plain text.',
        ]); ?>
      </fieldset>

      <fieldset class="panel">
        <legend class="panel__title">Search engine listing</legend>

        <?php field_input('seo_title', 'SEO title', $form['seo_title'], $errors, [
            'maxlength' => 190,
            'hint'      => 'Falls back to the category name when empty.',
        ]); ?>

        <?php field_textarea('seo_description', 'SEO description', $form['seo_description'], $errors, [
            'rows'      => 3,
            'maxlength' => 320,
            'hint'      => 'Around 150-160 characters reads best in search results.',
        ]); ?>
      </fieldset>

    </section>

    <aside class="form-side">

      <div class="panel">
        <h2 class="panel__title">Visibility</h2>

        <?php field_checkbox(
            'is_published',
            'Published',
            (int) $form['is_published'] === 1,
            'Unpublished categories are hidden from the storefront.'
        ); ?>

        <?php field_input('sort_order', 'Sort order', $form['sort_order'], $errors, [
            'type'  => 'number',
            'attrs' => ['min' => '0', 'max' => '65535', 'step' => '1'],
            'hint'  => 'Lower numbers appear first.',
        ]); ?>
      </div>

      <div class="panel">
        <h2 class="panel__title">Category image</h2>
        <?php field_image('image', 'Image', $currentImage, $errors); ?>
      </div>

      <div class="panel panel--actions">
        <button class="btn btn--primary btn--block" type="submit">
          <?= $isNew ? 'Create category' : 'Save changes' ?>
        </button>
        <a class="btn btn--ghost btn--block" href="<?= e(url('admin/categories.php')) ?>">Cancel</a>
      </div>

    </aside>
  </div>
</form>

<?php require __DIR__ . '/_layout_end.php'; ?>

<?php
/**
 * Veloura Tec — Admin: create / edit a product.
 *
 * Gallery images, videos and related products need a product id to attach
 * to, so on a new product those panels appear only after the first save.
 */

require __DIR__ . '/_auth.php';

$id      = input_int('id');
$product = $id > 0 ? product_by_id($id) : null;

if ($id > 0 && $product === null) {
    flash('error', 'That product no longer exists.');
    redirect('admin/products.php');
}

$isNew  = $product === null;
$errors = [];

$categoryOptions = categories_options();

if ($categoryOptions === []) {
    flash('error', 'Create a category before adding products.');
    redirect('admin/category-edit.php');
}

$form = [
    'name'              => $product['name']              ?? '',
    'slug'              => $product['slug']              ?? '',
    'category_id'       => $product['category_id']       ?? array_key_first($categoryOptions),
    'short_description' => $product['short_description'] ?? '',
    'description'       => $product['description']       ?? '',
    'price'             => $product['price']             ?? '',
    'currency'          => $product['currency']          ?? setting('default_currency', 'USD'),
    'price_mode'        => $product['price_mode']        ?? 'quote',
    'main_image_alt'    => $product['main_image_alt']    ?? '',
    'brand'             => $product['brand']             ?? '',
    'origin_country'    => $product['origin_country']    ?? '',
    'warranty'          => $product['warranty']          ?? '',
    'installation'      => $product['installation']      ?? '',
    'training'          => $product['training']          ?? '',
    'support'           => $product['support']           ?? '',
    'is_featured'       => (int) ($product['is_featured'] ?? 0),
    'status'            => $product['status']            ?? 'draft',
    'seo_title'         => $product['seo_title']         ?? '',
    'seo_description'   => $product['seo_description']   ?? '',
];

$specs        = product_specs_decode($product['specs'] ?? null);
$mainImage    = $product['main_image'] ?? null;
$relatedIds   = array_map(static fn (array $r): int => (int) $r['id'], $isNew ? [] : product_related($id));
$galleryNotes = [];

if (is_post()) {
    csrf_guard();

    foreach (array_keys($form) as $key) {
        $form[$key] = $_POST[$key] ?? '';
    }
    $form['is_featured'] = (int) !empty($_POST['is_featured']);

    $specs      = product_specs_from_request();
    $relatedIds = array_map('intval', (array) ($_POST['related'] ?? []));

    $errors = product_validate($form);

    $imagePath = $mainImage;

    if ($errors === []) {
        try {
            if (!empty($_POST['main_image_remove'])) {
                upload_delete($mainImage);
                $imagePath = null;
            }

            $imagePath = upload_replace_image('main_image', 'products', $imagePath);
        } catch (UploadException $e) {
            $errors['main_image'] = $e->getMessage();
        }
    }

    if ($errors === []) {
        $payload = $form + [
            'main_image' => $imagePath,
            'specs'      => $specs,
        ];

        if ($isNew) {
            $id = product_create($payload);
            product_related_set($id, $relatedIds);

            // Gallery files posted alongside a brand-new product.
            $uploaded = upload_image_multiple('gallery', 'products');
            foreach ($uploaded['paths'] as $path) {
                product_image_add($id, $path);
            }
            foreach ($uploaded['errors'] as $message) {
                flash('error', $message);
            }

            flash('success', 'Product created. You can now add gallery images and videos.');
            redirect('admin/product-edit.php?id=' . $id);
        }

        product_update($id, $payload);
        product_related_set($id, $relatedIds);

        $uploaded = upload_image_multiple('gallery', 'products');
        foreach ($uploaded['paths'] as $path) {
            product_image_add($id, $path);
        }
        foreach ($uploaded['errors'] as $message) {
            flash('error', $message);
        }

        // Gallery alt text and ordering submitted with the main form.
        if (isset($_POST['image_alt']) && is_array($_POST['image_alt'])) {
            foreach ($_POST['image_alt'] as $imageId => $altText) {
                product_image_set_alt((int) $imageId, $id, (string) $altText);
            }
        }
        if (isset($_POST['image_order']) && is_array($_POST['image_order'])) {
            product_images_reorder($id, $_POST['image_order']);
        }

        flash('success', 'Product updated.');
        redirect('admin/product-edit.php?id=' . $id);
    }

    $mainImage = $imagePath;
    flash('error', 'Please correct the highlighted fields.');
}

// Always show at least three empty spec rows to type into.
while (count($specs) < 3) {
    $specs[] = ['label' => '', 'value' => ''];
}

$images = $isNew ? [] : product_images($id);
$videos = $isNew ? [] : product_videos($id);

$pageTitle = $isNew ? 'Add product' : 'Edit product';
require __DIR__ . '/_layout.php';
?>

<div class="admin-toolbar">
  <p class="admin-lead">
    <a href="<?= e(url('admin/products.php')) ?>">&larr; Back to products</a>
  </p>
  <?php if (!$isNew && $product['status'] === 'published'): ?>
    <a class="btn btn--ghost btn--sm" target="_blank" rel="noopener"
       href="<?= e(url('product.php?slug=' . urlencode((string) $product['slug']))) ?>">
      View on site
    </a>
  <?php endif; ?>
</div>

<form method="post" enctype="multipart/form-data" class="admin-form" novalidate>
  <?= csrf_field() ?>
  <?php if (!$isNew): ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
  <?php endif; ?>

  <div class="form-grid">
    <section class="form-main">

      <fieldset class="panel">
        <legend class="panel__title">Product details</legend>

        <?php field_input('name', 'Product name', $form['name'], $errors, [
            'required'  => true,
            'maxlength' => 190,
            'attrs'     => ['data-slug-source' => 'f_slug'],
        ]); ?>

        <?php field_input('slug', 'URL slug', $form['slug'], $errors, [
            'hint'      => 'Leave empty to generate from the name. Address: /product/your-slug',
            'maxlength' => 200,
        ]); ?>

        <?php field_select('category_id', 'Category', $form['category_id'], $categoryOptions, $errors, [
            'required' => true,
        ]); ?>

        <?php field_textarea('short_description', 'Short description', $form['short_description'], $errors, [
            'rows'      => 3,
            'maxlength' => 400,
            'hint'      => 'One or two sentences. Shown on product cards and in search results.',
        ]); ?>

        <?php field_textarea('description', 'Full description', $form['description'], $errors, [
            'rows' => 12,
            'hint' => 'Plain text. Blank lines become paragraphs on the product page.',
        ]); ?>
      </fieldset>

      <fieldset class="panel">
        <legend class="panel__title">Specifications</legend>
        <p class="panel__hint">
          Enter only specifications supplied by the manufacturer. Leave a row empty to skip it.
        </p>

        <div class="spec-rows" data-spec-rows>
          <?php foreach ($specs as $index => $spec): ?>
            <div class="spec-row">
              <input class="field__input" type="text" name="spec_label[]"
                     value="<?= e($spec['label']) ?>" placeholder="Label, e.g. Wavelength"
                     maxlength="120" aria-label="Specification label <?= (int) $index + 1 ?>">
              <input class="field__input" type="text" name="spec_value[]"
                     value="<?= e($spec['value']) ?>" placeholder="Value, e.g. 1064 nm"
                     maxlength="400" aria-label="Specification value <?= (int) $index + 1 ?>">
              <button class="btn btn--ghost btn--xs" type="button" data-spec-remove>Remove</button>
            </div>
          <?php endforeach; ?>
        </div>

        <button class="btn btn--ghost btn--sm" type="button" data-spec-add>Add specification</button>
      </fieldset>

      <fieldset class="panel">
        <legend class="panel__title">Supply information</legend>
        <p class="panel__hint">
          Leave a field empty if it does not apply. Empty fields are hidden on the product page —
          do not enter terms that have not been confirmed.
        </p>

        <div class="field-row">
          <?php field_input('brand', 'Brand', $form['brand'], $errors, ['maxlength' => 120]); ?>
          <?php field_input('origin_country', 'Country of origin', $form['origin_country'], $errors, [
              'maxlength' => 120,
          ]); ?>
        </div>

        <?php field_input('warranty', 'Warranty', $form['warranty'], $errors, [
            'maxlength' => 255,
            'hint'      => 'For example: 12 months manufacturer warranty.',
        ]); ?>

        <?php field_input('installation', 'Installation', $form['installation'], $errors, [
            'maxlength' => 255,
        ]); ?>

        <?php field_input('training', 'Training', $form['training'], $errors, ['maxlength' => 255]); ?>

        <?php field_input('support', 'Technical support', $form['support'], $errors, ['maxlength' => 255]); ?>
      </fieldset>

      <fieldset class="panel">
        <legend class="panel__title">Search engine listing</legend>

        <?php field_input('seo_title', 'SEO title', $form['seo_title'], $errors, [
            'maxlength' => 190,
            'hint'      => 'Falls back to the product name when empty.',
        ]); ?>

        <?php field_textarea('seo_description', 'SEO description', $form['seo_description'], $errors, [
            'rows'      => 3,
            'maxlength' => 320,
            'hint'      => 'Falls back to the short description when empty.',
        ]); ?>
      </fieldset>

    </section>

    <aside class="form-side">

      <div class="panel panel--actions">
        <button class="btn btn--primary btn--block" type="submit">
          <?= $isNew ? 'Create product' : 'Save changes' ?>
        </button>
        <a class="btn btn--ghost btn--block" href="<?= e(url('admin/products.php')) ?>">Cancel</a>
      </div>

      <div class="panel">
        <h2 class="panel__title">Publication</h2>

        <?php field_select('status', 'Status', $form['status'], [
            'draft'     => 'Draft — hidden from the site',
            'published' => 'Published — visible on the site',
        ], $errors); ?>

        <?php field_checkbox(
            'is_featured',
            'Featured product',
            (int) $form['is_featured'] === 1,
            'Featured products appear on the homepage.'
        ); ?>
      </div>

      <div class="panel">
        <h2 class="panel__title">Pricing</h2>

        <?php field_select('price_mode', 'Price visibility', $form['price_mode'], [
            'quote' => 'Request a quote — hide the price',
            'show'  => 'Show the price',
        ], $errors, [
            'hint' => 'Quote-only products show "Price on request".',
        ]); ?>

        <?php field_input('price', 'Price', $form['price'], $errors, [
            'type'  => 'number',
            'attrs' => ['step' => '0.01', 'min' => '0'],
            'hint'  => 'Numbers only, no currency symbol.',
        ]); ?>

        <?php field_input('currency', 'Currency code', $form['currency'], $errors, [
            'maxlength' => 3,
            'attrs'     => ['autocapitalize' => 'characters'],
            'hint'      => 'Three letters, e.g. USD or EUR.',
        ]); ?>
      </div>

      <div class="panel">
        <h2 class="panel__title">Main image</h2>
        <?php field_image('main_image', 'Main image', $mainImage, $errors); ?>
        <?php field_input('main_image_alt', 'Image alt text', $form['main_image_alt'], $errors, [
            'maxlength' => 190,
            'hint'      => 'Describes the image for screen readers and search engines.',
        ]); ?>
      </div>

      <?php if (!$isNew): ?>
        <div class="panel">
          <h2 class="panel__title">Related products</h2>
          <p class="panel__hint">Hold Ctrl (or Cmd) to select more than one.</p>

          <label class="sr-only" for="f_related">Related products</label>
          <select class="field__select field__select--multi" id="f_related"
                  name="related[]" multiple size="8">
            <?php foreach (products_selectable($id) as $option): ?>
              <option value="<?= (int) $option['id'] ?>"
                <?= in_array((int) $option['id'], $relatedIds, true) ? 'selected' : '' ?>>
                <?= e($option['name']) ?> — <?= e($option['category_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

    </aside>
  </div>

  <!-- ------------------------------------------------------ gallery -->
  <fieldset class="panel">
    <legend class="panel__title">Gallery images</legend>

    <?php if (!$isNew && $images !== []): ?>
      <ul class="gallery-grid">
        <?php foreach ($images as $position => $image): ?>
          <li class="gallery-item">
            <img src="<?= e(upload_url($image['path'])) ?>" alt=""
                 width="160" height="160" loading="lazy">

            <label class="sr-only" for="alt_<?= (int) $image['id'] ?>">Alt text</label>
            <input class="field__input field__input--sm" type="text"
                   id="alt_<?= (int) $image['id'] ?>"
                   name="image_alt[<?= (int) $image['id'] ?>]"
                   value="<?= e($image['alt_text']) ?>" placeholder="Alt text" maxlength="190">

            <label class="sr-only" for="order_<?= (int) $image['id'] ?>">Position</label>
            <input class="field__input field__input--sm" type="number"
                   id="order_<?= (int) $image['id'] ?>"
                   name="image_order[<?= (int) $image['id'] ?>]"
                   value="<?= (int) $image['sort_order'] ?>" min="0" max="999"
                   title="Display order">
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="panel__hint">
        Alt text and ordering save with the product. Use the buttons below the form to
        delete an image or promote it to the main image.
      </p>
    <?php endif; ?>

    <div class="field">
      <label class="field__label" for="f_gallery">Add gallery images</label>
      <input class="field__input" type="file" id="f_gallery" name="gallery[]"
             accept="image/jpeg,image/png,image/webp" multiple>
      <span class="field__hint">
        Select several files at once. Up to 12 per upload.
      </span>
    </div>
  </fieldset>

  <div class="form-footer">
    <button class="btn btn--primary" type="submit">
      <?= $isNew ? 'Create product' : 'Save changes' ?>
    </button>
  </div>
</form>

<?php if (!$isNew): ?>

  <!-- Gallery per-image operations. Kept outside the main form because
       nested forms are invalid HTML. -->
  <?php if ($images !== []): ?>
    <section class="panel">
      <h2 class="panel__title">Gallery actions</h2>
      <ul class="gallery-grid">
        <?php foreach ($images as $image): ?>
          <li class="gallery-item">
            <img src="<?= e(upload_url($image['path'])) ?>" alt=""
                 width="160" height="160" loading="lazy">
            <div class="row-actions">
              <?php action_button(
                  'products',
                  'image-make-main',
                  ['id' => (int) $id, 'image_id' => (int) $image['id']],
                  'Make main'
              ); ?>
              <?php action_button(
                  'products',
                  'image-delete',
                  ['id' => (int) $id, 'image_id' => (int) $image['id']],
                  'Delete',
                  'btn btn--danger btn--xs',
                  'Delete this image? This cannot be undone.'
              ); ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <!-- ------------------------------------------------------- videos -->
  <section class="panel">
    <h2 class="panel__title">Videos</h2>

    <?php if ($videos === []): ?>
      <p class="panel__hint">No videos yet.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th scope="col">Type</th>
              <th scope="col">Title</th>
              <th scope="col">Source</th>
              <th scope="col" class="col-actions">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($videos as $video): ?>
              <tr>
                <td><?= status_badge(ucfirst((string) $video['video_type']), 'accent') ?></td>
                <td><?= e($video['title'] ?: '—') ?></td>
                <td class="cell-truncate">
                  <?php if ($video['video_type'] === 'file'): ?>
                    <a href="<?= e(upload_url($video['source'], '')) ?>" target="_blank" rel="noopener">
                      <?= e(basename((string) $video['source'])) ?>
                    </a>
                  <?php else: ?>
                    <a href="<?= e($video['source']) ?>" target="_blank" rel="noopener noreferrer">
                      <?= e($video['source']) ?>
                    </a>
                  <?php endif; ?>
                </td>
                <td class="col-actions">
                  <?php action_button(
                      'products',
                      'video-delete',
                      ['id' => (int) $id, 'video_id' => (int) $video['id']],
                      'Delete',
                      'btn btn--danger btn--xs',
                      'Delete this video?'
                  ); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <form class="video-form" method="post" enctype="multipart/form-data"
          action="<?= e(url('admin/actions/products.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="video-add">
      <input type="hidden" name="id" value="<?= (int) $id ?>">
      <input type="hidden" name="return_to" value="<?= e(admin_return_to()) ?>">

      <div class="field-row">
        <?php field_select('video_type', 'Video type', 'youtube', [
            'youtube'  => 'YouTube link',
            'facebook' => 'Facebook video link',
            'file'     => 'Upload MP4 / WebM',
        ]); ?>

        <?php field_input('video_title', 'Title (optional)', '', [], ['maxlength' => 190]); ?>
      </div>

      <?php field_input('video_url', 'YouTube or Facebook URL', '', [], [
          'type' => 'url',
          'hint' => 'Required for the YouTube and Facebook types. Ignored for uploads.',
      ]); ?>

      <div class="field">
        <label class="field__label" for="f_video_file">Video file</label>
        <input class="field__input" type="file" id="f_video_file" name="video_file"
               accept="video/mp4,video/webm">
        <span class="field__hint">
          MP4 or WebM, up to <?= (int) round((int) config('max_video_bytes', 33554432) / 1048576) ?> MB.
          For longer videos use a YouTube link instead.
        </span>
      </div>

      <button class="btn btn--dark" type="submit">Add video</button>
    </form>
  </section>

<?php endif; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>

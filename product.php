<?php
/**
 * Veloura Tec — Product detail.
 */

require __DIR__ . '/app/bootstrap.php';

$slug    = (string) input('slug', '');
$product = $slug !== '' ? product_by_slug($slug) : null;

if ($product === null) {
    http_response_code(404);
    page_meta(['title' => 'Product not found', 'robots' => 'noindex, follow']);
    require __DIR__ . '/includes/header.php';
    ?>
    <section class="section">
      <div class="container">
        <div class="empty-state">
          <h1>Product not found</h1>
          <p>This product is no longer available, or the address is incorrect.</p>
          <p><a class="btn btn--primary" href="<?= e(url('shop.php')) ?>">Browse all equipment</a></p>
        </div>
      </div>
    </section>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$productId = (int) $product['id'];
$images    = product_images($productId);
$videos    = product_videos($productId);
$specs     = product_specs_decode($product['specs'] ?? null);
$related   = product_related_published($productId, (int) $product['category_id']);

// Supply details are only rendered when the admin actually filled them in.
$supply = array_filter([
    'Brand'             => $product['brand'],
    'Country of origin' => $product['origin_country'],
    'Warranty'          => $product['warranty'],
    'Installation'      => $product['installation'],
    'Training'          => $product['training'],
    'Technical support' => $product['support'],
]);

page_meta([
    'title'       => $product['seo_title'] ?: $product['name'],
    'description' => $product['seo_description'] ?: excerpt($product['short_description'], 200),
    'canonical'   => product_url($product),
    'og_type'     => 'product',
    'image'       => $product['main_image'] ? upload_url($product['main_image']) : null,
]);

schema_add(product_schema($product));

require __DIR__ . '/includes/header.php';

$crumbs = [
    ['label' => t('nav.shop'), 'url' => url('shop.php')],
    ['label' => $product['category_name'], 'url' => url('category.php?slug=' . urlencode((string) $product['category_slug']))],
    ['label' => $product['name']],
];
require __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section">
  <div class="container">
    <div class="product">

      <!-- ------------------------------------------------- gallery -->
      <div class="product__media" data-gallery>
        <div class="product__image">
          <img data-gallery-main
               src="<?= e(upload_url($product['main_image'])) ?>"
               alt="<?= e($product['main_image_alt'] ?: $product['name']) ?>"
               width="800" height="800" decoding="async">
        </div>

        <?php if ($images !== []): ?>
          <ul class="product__thumbs">
            <li>
              <button class="product__thumb" type="button" aria-current="true"
                      data-gallery-src="<?= e(upload_url($product['main_image'])) ?>"
                      data-gallery-alt="<?= e($product['main_image_alt'] ?: $product['name']) ?>">
                <img src="<?= e(upload_url($product['main_image'])) ?>" alt=""
                     width="90" height="90" loading="lazy">
              </button>
            </li>
            <?php foreach ($images as $image): ?>
              <li>
                <button class="product__thumb" type="button" aria-current="false"
                        data-gallery-src="<?= e(upload_url($image['path'])) ?>"
                        data-gallery-alt="<?= e($image['alt_text'] ?: $product['name']) ?>">
                  <img src="<?= e(upload_url($image['path'])) ?>"
                       alt="<?= e($image['alt_text'] ?: '') ?>"
                       width="90" height="90" loading="lazy">
                </button>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <!-- ------------------------------------------------ summary -->
      <div class="product__summary">
        <p class="product__category">
          <a href="<?= e(url('category.php?slug=' . urlencode((string) $product['category_slug']))) ?>">
            <?= e($product['category_name']) ?>
          </a>
        </p>

        <h1 class="product__title"><?= e($product['name']) ?></h1>

        <?php if (!empty($product['short_description'])): ?>
          <p class="product__lead"><?= e($product['short_description']) ?></p>
        <?php endif; ?>

        <p class="product__price"><?= e(product_price_label($product)) ?></p>

        <div class="product__buy">
          <div class="qty">
            <label class="qty__label" for="product-qty"><?= e(t('common.quantity')) ?></label>
            <input class="qty__input" type="number" id="product-qty" name="quantity"
                   value="1" min="1" max="999" step="1" inputmode="numeric">
          </div>

          <button class="btn btn--primary"
                  type="button"
                  data-add-to-inquiry="<?= $productId ?>"
                  data-quantity-from="product-qty"
                  data-added-label="<?= e(t('cta.in_inquiry')) ?>"
                  data-cart-url="<?= e(url('inquiry.php')) ?>">
            <span data-add-label><?= e(t('cta.add_inquiry')) ?></span>
          </button>

          <a class="btn btn--ghost" href="<?= e(url('inquiry.php')) ?>">
            <?= e(t('cta.inquiry_cart')) ?>
          </a>
        </div>

        <p class="product__note">
          Adding a product does not place an order. You will review your list and send it
          to our team, who will reply with availability, pricing and shipping for your location.
        </p>

        <?php if ($supply !== []): ?>
          <dl class="product__supply">
            <?php foreach ($supply as $label => $value): ?>
              <dt><?= e($label) ?></dt>
              <dd><?= e($value) ?></dd>
            <?php endforeach; ?>
          </dl>
        <?php endif; ?>
      </div>

    </div>
  </div>
</section>

<?php if (!empty($product['description']) || $specs !== []): ?>
  <section class="section section--alt">
    <div class="container product__detail">

      <?php if (!empty($product['description'])): ?>
        <div class="product__description">
          <h2>Description</h2>
          <?php foreach (preg_split('/\n\s*\n/', (string) $product['description']) ?: [] as $paragraph): ?>
            <?php if (trim($paragraph) !== ''): ?>
              <p><?= nl2br(e(trim($paragraph))) ?></p>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($specs !== []): ?>
        <div class="product__specs">
          <h2>Specifications</h2>
          <table class="spec-table">
            <tbody>
              <?php foreach ($specs as $spec): ?>
                <tr>
                  <th scope="row"><?= e($spec['label']) ?></th>
                  <td><?= e($spec['value']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

    </div>
  </section>
<?php endif; ?>

<?php if ($videos !== []): ?>
  <section class="section">
    <div class="container">
      <div class="section__head"><h2>Videos</h2></div>

      <div class="video-grid">
        <?php foreach ($videos as $video): ?>
          <figure class="video">
            <?php if ($video['video_type'] === 'youtube'): ?>
              <?php $embed = youtube_embed_url((string) $video['source']); ?>
              <?php if ($embed !== null): ?>
                <div class="video__frame">
                  <iframe src="<?= e($embed) ?>"
                          title="<?= e($video['title'] ?: $product['name']) ?>"
                          loading="lazy" allowfullscreen
                          referrerpolicy="strict-origin-when-cross-origin"
                          allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                </div>
              <?php endif; ?>

            <?php elseif ($video['video_type'] === 'facebook'): ?>
              <?php /* Facebook's embed needs their SDK, which we do not load.
                        A direct link keeps the page fast and avoids a tracker. */ ?>
              <p class="video__link">
                <a href="<?= e($video['source']) ?>" target="_blank" rel="noopener noreferrer">
                  <?= e($video['title'] ?: 'Watch this video on Facebook') ?> &rarr;
                </a>
              </p>

            <?php else: ?>
              <div class="video__frame">
                <video controls preload="metadata"
                       <?= $product['main_image'] ? 'poster="' . e(upload_url($product['main_image'])) . '"' : '' ?>>
                  <source src="<?= e(url($video['source'])) ?>"
                          type="video/<?= e(pathinfo((string) $video['source'], PATHINFO_EXTENSION) === 'webm' ? 'webm' : 'mp4') ?>">
                  Your browser cannot play this video.
                </video>
              </div>
            <?php endif; ?>

            <?php if (!empty($video['title']) && $video['video_type'] !== 'facebook'): ?>
              <figcaption class="video__caption"><?= e($video['title']) ?></figcaption>
            <?php endif; ?>
          </figure>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php if ($related !== []): ?>
  <section class="section section--alt">
    <div class="container">
      <div class="section__head"><h2>Related equipment</h2></div>
      <div class="card-grid">
        <?php foreach ($related as $product): ?>
          <?php require __DIR__ . '/includes/product-card.php'; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

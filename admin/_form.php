<?php
/**
 * Veloura Tec — Admin form and table components.
 *
 * Small render helpers so every admin screen produces identical, already
 * escaped markup. Loaded by _layout.php, so any admin page can use them.
 */

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Render a labelled text-style input.
 *
 * @param array<string,string> $errors
 * @param array<string,mixed>  $options  type, hint, required, placeholder,
 *                                       maxlength, attrs
 */
function field_input(
    string $name,
    string $label,
    mixed $value,
    array $errors = [],
    array $options = []
): void {
    $type     = (string) ($options['type'] ?? 'text');
    $hint     = (string) ($options['hint'] ?? '');
    $error    = $errors[$name] ?? null;
    $required = !empty($options['required']);
    $id       = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);

    $attributes = '';
    foreach ((array) ($options['attrs'] ?? []) as $key => $attributeValue) {
        $attributes .= ' ' . e($key) . '="' . e($attributeValue) . '"';
    }
    if (isset($options['placeholder'])) {
        $attributes .= ' placeholder="' . e($options['placeholder']) . '"';
    }
    if (isset($options['maxlength'])) {
        $attributes .= ' maxlength="' . (int) $options['maxlength'] . '"';
    }
    ?>
    <div class="field<?= $error ? ' field--invalid' : '' ?>">
      <label class="field__label" for="<?= e($id) ?>">
        <?= e($label) ?><?= $required ? ' <span class="field__req">*</span>' : '' ?>
      </label>
      <input class="field__input" type="<?= e($type) ?>" id="<?= e($id) ?>"
             name="<?= e($name) ?>" value="<?= e($value) ?>"
             <?= $required ? 'required' : '' ?><?= $attributes ?>>
      <?php if ($hint !== ''): ?><span class="field__hint"><?= e($hint) ?></span><?php endif; ?>
      <?php if ($error): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
    </div>
    <?php
}

/**
 * @param array<string,string> $errors
 * @param array<string,mixed>  $options
 */
function field_textarea(
    string $name,
    string $label,
    mixed $value,
    array $errors = [],
    array $options = []
): void {
    $hint  = (string) ($options['hint'] ?? '');
    $rows  = (int) ($options['rows'] ?? 5);
    $error = $errors[$name] ?? null;
    $id    = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    ?>
    <div class="field<?= $error ? ' field--invalid' : '' ?>">
      <label class="field__label" for="<?= e($id) ?>"><?= e($label) ?></label>
      <textarea class="field__textarea" id="<?= e($id) ?>" name="<?= e($name) ?>"
                rows="<?= $rows ?>"
                <?= isset($options['maxlength']) ? 'maxlength="' . (int) $options['maxlength'] . '"' : '' ?>
      ><?= e($value) ?></textarea>
      <?php if ($hint !== ''): ?><span class="field__hint"><?= e($hint) ?></span><?php endif; ?>
      <?php if ($error): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
    </div>
    <?php
}

/**
 * @param array<int|string,string> $choices value => label
 * @param array<string,string>     $errors
 * @param array<string,mixed>      $options
 */
function field_select(
    string $name,
    string $label,
    mixed $value,
    array $choices,
    array $errors = [],
    array $options = []
): void {
    $hint     = (string) ($options['hint'] ?? '');
    $error    = $errors[$name] ?? null;
    $required = !empty($options['required']);
    $id       = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    ?>
    <div class="field<?= $error ? ' field--invalid' : '' ?>">
      <label class="field__label" for="<?= e($id) ?>">
        <?= e($label) ?><?= $required ? ' <span class="field__req">*</span>' : '' ?>
      </label>
      <select class="field__select" id="<?= e($id) ?>" name="<?= e($name) ?>"
              <?= $required ? 'required' : '' ?>>
        <?php if (isset($options['blank'])): ?>
          <option value=""><?= e($options['blank']) ?></option>
        <?php endif; ?>
        <?php foreach ($choices as $choiceValue => $choiceLabel): ?>
          <option value="<?= e($choiceValue) ?>"
            <?= (string) $choiceValue === (string) $value ? 'selected' : '' ?>>
            <?= e($choiceLabel) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if ($hint !== ''): ?><span class="field__hint"><?= e($hint) ?></span><?php endif; ?>
      <?php if ($error): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
    </div>
    <?php
}

/**
 * A checkbox with a paired hidden input, so an unchecked box still posts a
 * value and "off" is distinguishable from "not submitted".
 */
function field_checkbox(string $name, string $label, bool $checked, string $hint = ''): void
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    ?>
    <div class="field field--check">
      <input type="hidden" name="<?= e($name) ?>" value="0">
      <label class="check">
        <input type="checkbox" id="<?= e($id) ?>" name="<?= e($name) ?>" value="1"
               <?= $checked ? 'checked' : '' ?>>
        <span><?= e($label) ?></span>
      </label>
      <?php if ($hint !== ''): ?><span class="field__hint"><?= e($hint) ?></span><?php endif; ?>
    </div>
    <?php
}

/**
 * An image field: current preview, replace input, and an optional remove
 * checkbox.
 *
 * @param array<string,string> $errors
 */
function field_image(
    string $name,
    string $label,
    ?string $current,
    array $errors = [],
    string $hint = ''
): void {
    $error = $errors[$name] ?? null;
    $id    = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    $maxMb = (int) round((int) config('max_image_bytes', 4194304) / 1048576);
    ?>
    <div class="field<?= $error ? ' field--invalid' : '' ?>">
      <span class="field__label"><?= e($label) ?></span>

      <div class="image-field">
        <div class="image-field__preview">
          <img src="<?= e(upload_url($current)) ?>" alt="" data-preview-for="<?= e($id) ?>"
               width="120" height="120" loading="lazy">
        </div>

        <div class="image-field__controls">
          <input class="field__input" type="file" id="<?= e($id) ?>" name="<?= e($name) ?>"
                 accept="image/jpeg,image/png,image/webp" data-image-input>
          <span class="field__hint">
            JPG, PNG or WebP. Up to <?= $maxMb ?> MB. Large images are resized automatically.
            <?= $hint !== '' ? e(' ' . $hint) : '' ?>
          </span>

          <?php if ($current): ?>
            <label class="check check--sm">
              <input type="checkbox" name="<?= e($name) ?>_remove" value="1">
              <span>Remove the current image</span>
            </label>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($error): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
    </div>
    <?php
}

/**
 * A coloured status pill.
 */
function status_badge(string $text, string $tone = 'neutral'): string
{
    return '<span class="badge badge--' . e($tone) . '">' . e($text) . '</span>';
}

/**
 * A POST button that performs one operation on one record.
 *
 * Every destructive admin action goes through this: it carries a CSRF
 * token and uses POST, so nothing can be triggered by a link or an
 * <img> tag.
 *
 * @param array<string,string|int> $fields
 */
function action_button(
    string $action,
    string $op,
    array $fields,
    string $label,
    string $class = 'btn btn--ghost btn--xs',
    ?string $confirm = null
): void {
    ?>
    <form class="inline-form" method="post" action="<?= e(url('admin/actions/' . $action . '.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="<?= e($op) ?>">
      <?php foreach ($fields as $key => $value): ?>
        <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
      <?php endforeach; ?>
      <input type="hidden" name="return_to" value="<?= e(admin_return_to()) ?>">
      <button class="<?= e($class) ?>" type="submit"
              <?= $confirm !== null ? 'data-confirm="' . e($confirm) . '"' : '' ?>>
        <?= e($label) ?>
      </button>
    </form>
    <?php
}

/**
 * The current admin URL (path + query), used to return to the same
 * filtered list after an action.
 */
function admin_return_to(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/admin/index.php');

    return ltrim($uri, '/');
}

/**
 * Validate a return_to value from a request.
 *
 * Only paths inside /admin/ are accepted, so this can never be used as an
 * open redirect to another site.
 */
function admin_safe_return(?string $value, string $fallback = 'admin/index.php'): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return $fallback;
    }

    // Reject anything that looks like an absolute or protocol-relative URL.
    if (str_contains($value, '://') || str_starts_with($value, '//')) {
        return $fallback;
    }

    $value = ltrim($value, '/');

    return str_starts_with($value, 'admin/') ? $value : $fallback;
}

/**
 * Pagination control that preserves the current filters.
 *
 * @param array<string,mixed> $query
 */
function admin_pagination(int $page, int $pages, array $query = []): void
{
    if ($pages < 2) {
        return;
    }

    $link = static function (int $target) use ($query): string {
        $query['page'] = $target;

        return e('?' . http_build_query(array_filter(
            $query,
            static fn ($v) => $v !== '' && $v !== null
        )));
    };
    ?>
    <nav class="pager" aria-label="Pagination">
      <?php if ($page > 1): ?>
        <a class="pager__link" href="<?= $link($page - 1) ?>" rel="prev">&larr; Previous</a>
      <?php endif; ?>

      <span class="pager__status">Page <?= $page ?> of <?= $pages ?></span>

      <?php if ($page < $pages): ?>
        <a class="pager__link" href="<?= $link($page + 1) ?>" rel="next">Next &rarr;</a>
      <?php endif; ?>
    </nav>
    <?php
}

/**
 * Standard empty state for admin tables.
 */
function admin_empty(string $message, ?string $ctaLabel = null, ?string $ctaUrl = null): void
{
    ?>
    <div class="empty-state">
      <p><?= e($message) ?></p>
      <?php if ($ctaLabel !== null && $ctaUrl !== null): ?>
        <p><a class="btn btn--primary" href="<?= e($ctaUrl) ?>"><?= e($ctaLabel) ?></a></p>
      <?php endif; ?>
    </div>
    <?php
}

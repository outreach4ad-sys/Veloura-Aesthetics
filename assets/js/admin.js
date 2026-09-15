/**
 * Veloura Tec — Admin scripts.
 *
 * Vanilla JS, no dependencies, loaded with `defer`. Everything here is a
 * convenience layer: every feature still works with JavaScript disabled,
 * because validation and persistence happen server-side.
 */
(function () {
  'use strict';

  /* --------------------------------------------------- confirmations */
  function initConfirmations() {
    document.addEventListener('click', function (event) {
      var trigger = event.target.closest('[data-confirm]');
      if (!trigger) return;

      if (!window.confirm(trigger.getAttribute('data-confirm'))) {
        event.preventDefault();
        event.stopPropagation();
      }
    });
  }

  /* ------------------------------------------------- submit guarding */
  function initSubmitGuard() {
    document.addEventListener('submit', function (event) {
      var form = event.target;
      if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-guard')) return;

      var button = form.querySelector('button[type="submit"]');
      if (!button) return;

      // Disable after the browser has serialised the form, so the button's
      // own name/value still posts.
      window.setTimeout(function () {
        button.disabled = true;
        button.dataset.originalText = button.textContent;
        button.textContent = 'Working…';
      }, 0);

      // Re-enable if the page is restored from the back/forward cache.
      window.addEventListener('pageshow', function () {
        button.disabled = false;
        if (button.dataset.originalText) button.textContent = button.dataset.originalText;
      });
    });
  }

  /* ------------------------------------------------------ slug helper */
  function slugify(text) {
    return text
      .toLowerCase()
      .normalize('NFD')
      .replace(/[̀-ͯ]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  /**
   * Mirror the name field into an empty slug field while typing. Once the
   * slug has been edited by hand, mirroring stops.
   */
  function initSlugMirror() {
    document.querySelectorAll('[data-slug-source]').forEach(function (source) {
      var target = document.getElementById(source.getAttribute('data-slug-source'));
      if (!target) return;

      var touched = target.value.trim() !== '';

      target.addEventListener('input', function () { touched = true; });

      source.addEventListener('input', function () {
        if (!touched) target.value = slugify(source.value);
      });
    });
  }

  /* --------------------------------------------------- image previews */
  function initImagePreviews() {
    document.addEventListener('change', function (event) {
      var input = event.target;
      if (!input.matches('[data-image-input]')) return;

      var preview = document.querySelector('[data-preview-for="' + input.id + '"]');
      if (!preview) return;

      var file = input.files && input.files[0];
      if (!file) return;

      if (!/^image\//.test(file.type)) {
        window.alert('Please choose an image file.');
        input.value = '';
        return;
      }

      var url = URL.createObjectURL(file);
      preview.src = url;
      preview.addEventListener('load', function () { URL.revokeObjectURL(url); }, { once: true });
    });
  }

  /* ----------------------------------------------- specification rows */
  function initSpecRows() {
    var container = document.querySelector('[data-spec-rows]');
    var addButton = document.querySelector('[data-spec-add]');
    if (!container || !addButton) return;

    addButton.addEventListener('click', function () {
      var template = container.querySelector('.spec-row');
      if (!template) return;

      var row = template.cloneNode(true);
      row.querySelectorAll('input').forEach(function (input) { input.value = ''; });
      container.appendChild(row);
      var first = row.querySelector('input');
      if (first) first.focus();
    });

    container.addEventListener('click', function (event) {
      if (!event.target.matches('[data-spec-remove]')) return;

      var rows = container.querySelectorAll('.spec-row');
      var row = event.target.closest('.spec-row');
      if (!row) return;

      // Always leave one row so there is somewhere to type.
      if (rows.length > 1) {
        row.remove();
      } else {
        row.querySelectorAll('input').forEach(function (input) { input.value = ''; });
      }
    });
  }

  /* -------------------------------------- price / quote field linkage */
  function initPriceMode() {
    var mode = document.getElementById('f_price_mode');
    var price = document.getElementById('f_price');
    if (!mode || !price) return;

    var sync = function () {
      var showing = mode.value === 'show';
      price.required = showing;
      price.closest('.field').classList.toggle('is-dimmed', !showing);
    };

    mode.addEventListener('change', sync);
    sync();
  }

  /* -------------------------------------- video type field visibility */
  function initVideoType() {
    var type = document.getElementById('f_video_type');
    var urlField = document.getElementById('f_video_url');
    var fileField = document.getElementById('f_video_file');
    if (!type || !urlField || !fileField) return;

    var sync = function () {
      var isFile = type.value === 'file';
      urlField.closest('.field').hidden = isFile;
      fileField.closest('.field').hidden = !isFile;
    };

    type.addEventListener('change', sync);
    sync();
  }

  document.addEventListener('DOMContentLoaded', function () {
    initConfirmations();
    initSubmitGuard();
    initSlugMirror();
    initImagePreviews();
    initSpecRows();
    initPriceMode();
    initVideoType();
  });
})();

/**
 * Veloura Tec — Site scripts.
 *
 * Vanilla JS, no dependencies, loaded with `defer`. The inquiry cart lives
 * in cart.js; this file covers site chrome only.
 */
(function () {
  'use strict';

  /* ------------------------------------------------------ mobile nav */
  function initNav() {
    var toggle = document.querySelector('[data-nav-toggle]');
    var nav = document.getElementById('site-nav');

    if (!toggle || !nav) return;

    toggle.addEventListener('click', function () {
      var isOpen = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    nav.addEventListener('click', function (event) {
      if (event.target.closest('a')) {
        nav.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && nav.classList.contains('is-open')) {
        nav.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus();
      }
    });
  }

  /* ------------------------------------------- shop filter auto-submit */
  function initAutoSubmit() {
    document.querySelectorAll('[data-auto-submit]').forEach(function (control) {
      control.addEventListener('change', function () {
        var form = control.closest('form');
        if (form) form.submit();
      });
    });
  }

  /* ------------------------------------------------ product gallery */
  function initGallery() {
    var gallery = document.querySelector('[data-gallery]');
    if (!gallery) return;

    var main = gallery.querySelector('[data-gallery-main]');
    if (!main) return;

    gallery.addEventListener('click', function (event) {
      var thumb = event.target.closest('[data-gallery-src]');
      if (!thumb) return;

      event.preventDefault();
      main.src = thumb.getAttribute('data-gallery-src');
      main.alt = thumb.getAttribute('data-gallery-alt') || '';

      gallery.querySelectorAll('[data-gallery-src]').forEach(function (button) {
        button.setAttribute('aria-current', String(button === thumb));
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initNav();
    initAutoSubmit();
    initGallery();
  });
})();

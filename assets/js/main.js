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


  /* ------------------------------------------------------ hero slideshow */
  function initHero() {
    var hero = document.querySelector('[data-hero]');
    if (!hero) return;

    var slides = Array.prototype.slice.call(hero.querySelectorAll('[data-hero-slide]'));
    var dots = Array.prototype.slice.call(hero.querySelectorAll('[data-hero-dot]'));
    if (slides.length < 2) return;

    var interval = parseInt(hero.getAttribute('data-hero-interval'), 10) || 4000;
    var index = 0;
    var timer = null;
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function show(next) {
      next = (next + slides.length) % slides.length;
      slides[index].classList.remove('is-active');
      slides[index].setAttribute('aria-hidden', 'true');
      slides[next].classList.add('is-active');
      slides[next].removeAttribute('aria-hidden');
      if (dots[index]) { dots[index].classList.remove('is-active'); dots[index].setAttribute('aria-selected', 'false'); }
      if (dots[next]) { dots[next].classList.add('is-active'); dots[next].setAttribute('aria-selected', 'true'); }
      index = next;
    }

    function start() {
      if (reduce) return;
      stop();
      timer = window.setInterval(function () { show(index + 1); }, interval);
    }
    function stop() { if (timer) { window.clearInterval(timer); timer = null; } }

    dots.forEach(function (dot) {
      dot.addEventListener('click', function () {
        show(parseInt(dot.getAttribute('data-hero-dot'), 10) || 0);
        start();
      });
    });

    // Pause while the visitor is interacting or the tab is hidden.
    hero.addEventListener('mouseenter', stop);
    hero.addEventListener('mouseleave', start);
    hero.addEventListener('focusin', stop);
    hero.addEventListener('focusout', start);
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) { stop(); } else { start(); }
    });

    start();
  }

  document.addEventListener('DOMContentLoaded', function () {
    initNav();
    initHero();
    initAutoSubmit();
    initGallery();
  });
})();

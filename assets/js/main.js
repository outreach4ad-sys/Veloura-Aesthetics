/**
 * Veloura Tec — Site scripts (Phase 1).
 *
 * Vanilla JS, no dependencies, loaded with `defer`. Phase 1 covers the
 * mobile navigation and the inquiry-cart badge; the cart itself lands in
 * Phase 6 and will reuse the same storage key.
 */
(function () {
  'use strict';

  var CART_KEY = 'veloura_inquiry_cart';

  /* ------------------------------------------------------ mobile nav */
  function initNav() {
    var toggle = document.querySelector('[data-nav-toggle]');
    var nav = document.getElementById('site-nav');

    if (!toggle || !nav) return;

    toggle.addEventListener('click', function () {
      var isOpen = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    // Close the drawer when a link is followed or Escape is pressed.
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

  /* ------------------------------------------------- inquiry cart badge */
  function readCart() {
    try {
      var raw = window.localStorage.getItem(CART_KEY);
      var parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
      return [];
    }
  }

  function updateCartBadge() {
    var items = readCart();
    var count = items.reduce(function (sum, item) {
      return sum + (parseInt(item.quantity, 10) || 0);
    }, 0);

    document.querySelectorAll('[data-cart-count]').forEach(function (badge) {
      badge.textContent = String(count);
      badge.hidden = count === 0;
    });
  }

  // Keep the badge in sync across tabs.
  window.addEventListener('storage', function (event) {
    if (event.key === CART_KEY) updateCartBadge();
  });

  document.addEventListener('veloura:cart-changed', updateCartBadge);

  /* ---------------------------------------------------------- bootstrap */
  document.addEventListener('DOMContentLoaded', function () {
    initNav();
    updateCartBadge();
  });

  // Exposed so later phases (cart.js, shop.js) share one storage contract.
  window.Veloura = { CART_KEY: CART_KEY, readCart: readCart, updateCartBadge: updateCartBadge };
})();

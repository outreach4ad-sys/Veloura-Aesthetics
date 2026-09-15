/**
 * Veloura Tec — Inquiry cart.
 *
 * The cart is a list of {id, quantity} kept in localStorage. It never
 * stores names or prices: those are read back from the database through
 * api/cart.php every time the cart is displayed, so what the customer
 * sees always matches the catalog.
 *
 * This is an INQUIRY flow, not a checkout. Nothing here takes payment or
 * claims an order was placed.
 */
(function () {
  'use strict';

  var KEY = 'veloura_inquiry_cart';
  var MAX_LINES = 40;
  var MAX_QTY = 999;

  /* ------------------------------------------------------- storage */

  function read() {
    try {
      var raw = window.localStorage.getItem(KEY);
      var parsed = raw ? JSON.parse(raw) : [];
      if (!Array.isArray(parsed)) return [];

      return parsed
        .filter(function (item) { return item && parseInt(item.id, 10) > 0; })
        .map(function (item) {
          return {
            id: parseInt(item.id, 10),
            quantity: Math.min(MAX_QTY, Math.max(1, parseInt(item.quantity, 10) || 1))
          };
        })
        .slice(0, MAX_LINES);
    } catch (error) {
      return [];
    }
  }

  function write(items) {
    try {
      window.localStorage.setItem(KEY, JSON.stringify(items));
    } catch (error) {
      // Private browsing or a full quota: the cart simply does not persist.
    }
    document.dispatchEvent(new CustomEvent('veloura:cart-changed'));
  }

  function count() {
    return read().reduce(function (sum, item) { return sum + item.quantity; }, 0);
  }

  function add(id, quantity) {
    id = parseInt(id, 10);
    if (!(id > 0)) return false;

    quantity = Math.min(MAX_QTY, Math.max(1, parseInt(quantity, 10) || 1));

    var items = read();
    var existing = items.filter(function (item) { return item.id === id; })[0];

    if (existing) {
      existing.quantity = Math.min(MAX_QTY, existing.quantity + quantity);
    } else {
      if (items.length >= MAX_LINES) return false;
      items.push({ id: id, quantity: quantity });
    }

    write(items);
    return true;
  }

  function setQuantity(id, quantity) {
    id = parseInt(id, 10);
    quantity = Math.min(MAX_QTY, Math.max(1, parseInt(quantity, 10) || 1));

    var items = read().map(function (item) {
      if (item.id === id) item.quantity = quantity;
      return item;
    });

    write(items);
  }

  function remove(id) {
    id = parseInt(id, 10);
    write(read().filter(function (item) { return item.id !== id; }));
  }

  function clear() {
    try {
      window.localStorage.removeItem(KEY);
    } catch (error) { /* ignore */ }
    document.dispatchEvent(new CustomEvent('veloura:cart-changed'));
  }

  function has(id) {
    id = parseInt(id, 10);
    return read().some(function (item) { return item.id === id; });
  }

  /* --------------------------------------------------------- badge */

  function updateBadge() {
    var total = count();

    document.querySelectorAll('[data-cart-count]').forEach(function (badge) {
      badge.textContent = String(total);
      badge.hidden = total === 0;
    });
  }

  /* ------------------------------------------------- add buttons */

  function markAdded(button, added) {
    var label = button.querySelector('[data-add-label]') || button;
    var addedText = button.getAttribute('data-added-label') || 'In your inquiry';
    var idleText = button.getAttribute('data-idle-label') || label.textContent;

    if (!button.getAttribute('data-idle-label')) {
      button.setAttribute('data-idle-label', idleText);
    }

    button.classList.toggle('is-added', added);
    label.textContent = added ? addedText : button.getAttribute('data-idle-label');
  }

  function syncAddButtons() {
    document.querySelectorAll('[data-add-to-inquiry]').forEach(function (button) {
      markAdded(button, has(button.getAttribute('data-add-to-inquiry')));
    });
  }

  function initAddButtons() {
    document.addEventListener('click', function (event) {
      var button = event.target.closest('[data-add-to-inquiry]');
      if (!button) return;

      event.preventDefault();

      var id = button.getAttribute('data-add-to-inquiry');
      var qtyInput = button.getAttribute('data-quantity-from')
        ? document.getElementById(button.getAttribute('data-quantity-from'))
        : null;
      var quantity = qtyInput ? qtyInput.value : 1;

      if (has(id)) {
        // A second click opens the cart rather than silently stacking.
        window.location.href = button.getAttribute('data-cart-url') || 'inquiry.php';
        return;
      }

      if (!add(id, quantity)) {
        announce('Your inquiry list is full (' + MAX_LINES + ' products).');
        return;
      }

      markAdded(button, true);
      announce('Added to your inquiry list.');
    });
  }

  /* ------------------------------------------------ live announcer */

  var announcer = null;

  function announce(message) {
    if (!announcer) {
      announcer = document.createElement('div');
      announcer.className = 'cart-toast';
      announcer.setAttribute('role', 'status');
      announcer.setAttribute('aria-live', 'polite');
      document.body.appendChild(announcer);
    }

    announcer.textContent = message;
    announcer.classList.add('is-visible');

    window.clearTimeout(announce.timer);
    announce.timer = window.setTimeout(function () {
      announcer.classList.remove('is-visible');
    }, 2600);
  }

  /* ---------------------------------------------------------- init */

  window.addEventListener('storage', function (event) {
    if (event.key === KEY) {
      updateBadge();
      syncAddButtons();
    }
  });

  document.addEventListener('veloura:cart-changed', function () {
    updateBadge();
    syncAddButtons();
  });

  document.addEventListener('DOMContentLoaded', function () {
    initAddButtons();
    updateBadge();
    syncAddButtons();
  });

  // Public API, shared with inquiry.js.
  window.VelouraCart = {
    KEY: KEY,
    MAX_LINES: MAX_LINES,
    read: read,
    add: add,
    setQuantity: setQuantity,
    remove: remove,
    clear: clear,
    count: count,
    has: has,
    updateBadge: updateBadge,
    announce: announce
  };
})();

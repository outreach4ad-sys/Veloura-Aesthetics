/**
 * Veloura Tec — Inquiry cart page.
 *
 * Reads the cart from localStorage, asks the server for authoritative
 * product data, and renders the list. Quantities and removals write back
 * to localStorage and re-render.
 *
 * Nothing here decides a price. Every figure on screen comes from
 * api/cart.php, which reads the database.
 */
(function () {
  'use strict';

  var cart = window.VelouraCart;
  var root = document.getElementById('inquiry-root');

  /* ------------------------------------------------- success state */

  // Clear the cart once the inquiry has actually been saved server-side.
  var successPanel = document.querySelector('[data-clear-cart]');
  if (successPanel && cart) {
    cart.clear();

    // Give the browser a beat, then try to open WhatsApp. Pop-up blockers
    // stop automatic opens, which is why the button is the real path and
    // this is only a convenience.
    var link = document.getElementById('whatsapp-send');
    if (link) {
      window.setTimeout(function () {
        window.open(link.href, '_blank', 'noopener');
      }, 900);
    }
  }

  if (!root || !cart) return;

  var loading = document.getElementById('cart-loading');
  var empty = document.getElementById('cart-empty');
  var content = document.getElementById('cart-content');
  var list = document.getElementById('cart-list');
  var formWrap = document.getElementById('inquiry-form-wrap');
  var payload = document.getElementById('cart-payload');
  var unitsEl = document.getElementById('cart-units');
  var totalLine = document.getElementById('cart-total-line');
  var totalEl = document.getElementById('cart-total');
  var quoteNote = document.getElementById('cart-quote-note');
  var droppedEl = document.getElementById('cart-dropped');

  var busy = false;

  function show(el, visible) {
    if (el) el.hidden = !visible;
  }

  function showEmpty() {
    show(loading, false);
    show(content, false);
    show(formWrap, false);
    show(empty, true);
  }

  function render(data) {
    var lines = data.lines || [];

    if (lines.length === 0) {
      showEmpty();
      return;
    }

    list.textContent = '';

    lines.forEach(function (line) {
      list.appendChild(buildRow(line));
    });

    unitsEl.textContent = String(data.totals.units);

    if (data.totals.all_priced && data.totals.total) {
      totalEl.textContent = data.totals.total;
      show(totalLine, true);
      show(quoteNote, false);
    } else {
      show(totalLine, false);
      show(quoteNote, true);
    }

    // A product removed or unpublished since it was added.
    if (data.dropped && data.dropped.length) {
      droppedEl.textContent = data.dropped.length === 1
        ? 'One product was removed from your list because it is no longer available.'
        : data.dropped.length + ' products were removed from your list because they are no longer available.';
      show(droppedEl, true);

      // Drop them locally too, so the message does not reappear forever.
      var keep = cart.read().filter(function (item) {
        return data.dropped.indexOf(item.id) === -1;
      });
      window.localStorage.setItem(cart.KEY, JSON.stringify(keep));
      cart.updateBadge();
    } else {
      show(droppedEl, false);
    }

    payload.value = JSON.stringify(cart.read());

    show(loading, false);
    show(empty, false);
    show(content, true);
    show(formWrap, true);
  }

  function buildRow(line) {
    var li = document.createElement('li');
    li.className = 'cart-row';
    li.dataset.id = String(line.id);

    // --- image
    var media = document.createElement('a');
    media.className = 'cart-row__media';
    media.href = line.url;
    media.tabIndex = -1;
    media.setAttribute('aria-hidden', 'true');

    var img = document.createElement('img');
    img.src = line.image;
    img.alt = '';
    img.width = 120;
    img.height = 120;
    img.loading = 'lazy';
    media.appendChild(img);

    // --- text
    var body = document.createElement('div');
    body.className = 'cart-row__body';

    var category = document.createElement('p');
    category.className = 'cart-row__category';
    category.textContent = line.category;

    var title = document.createElement('h3');
    title.className = 'cart-row__title';
    var titleLink = document.createElement('a');
    titleLink.href = line.url;
    titleLink.textContent = line.name;
    title.appendChild(titleLink);

    var price = document.createElement('p');
    price.className = 'cart-row__price';
    price.textContent = line.price_label;

    body.appendChild(category);
    body.appendChild(title);
    body.appendChild(price);

    // --- controls
    var controls = document.createElement('div');
    controls.className = 'cart-row__controls';

    var stepper = document.createElement('div');
    stepper.className = 'stepper';

    var minus = document.createElement('button');
    minus.type = 'button';
    minus.className = 'stepper__btn';
    minus.textContent = '−';
    minus.setAttribute('aria-label', 'Decrease quantity of ' + line.name);

    var input = document.createElement('input');
    input.type = 'number';
    input.className = 'stepper__input';
    input.value = String(line.quantity);
    input.min = '1';
    input.max = '999';
    input.step = '1';
    input.inputMode = 'numeric';
    input.id = 'qty-' + line.id;
    input.setAttribute('aria-label', 'Quantity of ' + line.name);

    var plus = document.createElement('button');
    plus.type = 'button';
    plus.className = 'stepper__btn';
    plus.textContent = '+';
    plus.setAttribute('aria-label', 'Increase quantity of ' + line.name);

    minus.addEventListener('click', function () {
      update(line.id, Math.max(1, parseInt(input.value, 10) - 1));
    });
    plus.addEventListener('click', function () {
      update(line.id, Math.min(999, parseInt(input.value, 10) + 1));
    });
    input.addEventListener('change', function () {
      update(line.id, input.value);
    });

    stepper.appendChild(minus);
    stepper.appendChild(input);
    stepper.appendChild(plus);

    var remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'cart-row__remove';
    remove.textContent = 'Remove';
    remove.setAttribute('aria-label', 'Remove ' + line.name + ' from your inquiry');
    remove.addEventListener('click', function () {
      cart.remove(line.id);
      cart.announce(line.name + ' removed.');
      load();
    });

    controls.appendChild(stepper);
    controls.appendChild(remove);

    // --- line total
    if (line.line_total) {
      var lineTotal = document.createElement('p');
      lineTotal.className = 'cart-row__total';
      lineTotal.textContent = line.line_total;
      controls.appendChild(lineTotal);
    }

    li.appendChild(media);
    li.appendChild(body);
    li.appendChild(controls);
    return li;
  }

  function update(id, quantity) {
    cart.setQuantity(id, quantity);
    load();
  }

  function load() {
    var items = cart.read();

    if (items.length === 0) {
      showEmpty();
      return;
    }

    if (busy) return;
    busy = true;

    fetch('api/cart.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ items: items }),
      credentials: 'same-origin'
    })
      .then(function (response) {
        if (!response.ok) throw new Error('Request failed');
        return response.json();
      })
      .then(render)
      .catch(function () {
        show(loading, false);
        show(content, false);
        show(formWrap, false);
        show(empty, true);
        empty.innerHTML =
          '<p><strong>We could not load your inquiry list.</strong></p>' +
          '<p>Please check your connection and reload the page.</p>';
      })
      .finally(function () {
        busy = false;
      });
  }

  // Keep the submitted payload in step with any last-second edit.
  var form = document.getElementById('inquiry-form');
  if (form) {
    form.addEventListener('submit', function () {
      payload.value = JSON.stringify(cart.read());
    });
  }

  document.addEventListener('DOMContentLoaded', load);
  if (document.readyState !== 'loading') load();
})();

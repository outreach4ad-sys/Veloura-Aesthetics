/**
 * Veloura Tec — Contact form.
 *
 * Composes the visitor's message into a WhatsApp message (preferred) or an
 * email draft (fallback) and opens it. No data is posted to the server, so
 * there is no stored-message endpoint to secure or spam. Validation is
 * client-side because there is nothing to persist; the destination app is
 * where the message is actually reviewed and sent.
 */
(function () {
  'use strict';

  var form = document.getElementById('contact-form');
  if (!form) return;

  var button = form.querySelector('button[type=submit]');
  var errorEl = document.getElementById('c_error');
  var useWhatsApp = button.getAttribute('data-wa') === '1';
  var email = button.getAttribute('data-email') || '';

  function val(id) {
    var el = document.getElementById(id);
    return el ? el.value.trim() : '';
  }

  function showError(msg) {
    errorEl.textContent = msg;
    errorEl.hidden = false;
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    errorEl.hidden = true;

    var name = val('c_name');
    var message = val('c_message');

    if (name === '' || message === '') {
      showError('Please enter your name and a message.');
      return;
    }

    var company = val('c_company');
    var country = val('c_country');

    var lines = [
      'Message from the Veloura Tec website',
      '',
      'Name: ' + name
    ];
    if (company) lines.push('Company / clinic: ' + company);
    if (country) lines.push('Country: ' + country);
    lines.push('', 'Message:', message);

    var body = lines.join('\n');

    if (useWhatsApp && window.VelouraCart) {
      // Reuse the same wa.me link shape the inquiry flow uses. The number
      // is server-configured; the page exposed the link on the WhatsApp
      // channel, so we read it from there to avoid duplicating the number.
      var waLink = document.querySelector('.channel__value a[href*="wa.me/"]');
      if (waLink) {
        var base = waLink.href.split('?')[0];
        window.open(base + '?text=' + encodeURIComponent(body), '_blank', 'noopener');
        return;
      }
    }

    if (email) {
      var subject = 'Website enquiry from ' + name;
      window.location.href = 'mailto:' + email +
        '?subject=' + encodeURIComponent(subject) +
        '&body=' + encodeURIComponent(body);
      return;
    }

    showError('No contact channel is configured. Please try again later.');
  });
})();

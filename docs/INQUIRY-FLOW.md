# Inquiry cart and WhatsApp ordering

This is an **inquiry flow, not a checkout**. No payment is taken, no order is
placed, and no page claims otherwise. A customer builds a list, submits their
details, the request is saved to MySQL with a reference number, and WhatsApp
opens with a prepared message.

## SQL changes

**None.** Phase 1's `inquiries` and `inquiry_items` tables already carry every
column this flow writes. Import nothing; upload the files and it works.

## How it fits together

```
Product card / product page
  └─ [Add to Inquiry]  ──► localStorage: [{id, quantity}]   (cart.js)

inquiry.php
  ├─ inquiry.js reads localStorage
  ├─ POSTs the ids to api/cart.php
  ├─ api/cart.php → cart_resolve() → MySQL → authoritative names/prices
  └─ renders rows, quantity steppers, remove buttons

Submit
  ├─ CSRF check, honeypot, per-session throttle
  ├─ cart_resolve() runs AGAIN server-side (the POSTed payload is only ids)
  ├─ inquiry_validate()
  ├─ inquiry_create()  → inquiries + inquiry_items, inside a transaction
  ├─ inquiry_whatsapp_message() → wa.me link
  └─ redirect → success state → cart cleared → WhatsApp opens
```

**The browser never supplies a price.** It stores ids and quantities only.
Names, prices, URLs and totals are read from the database on every render and
again at submission, so editing localStorage changes nothing except which
products are requested and how many.

## The WhatsApp number

Configured once, in **Admin → Settings → `whatsapp_number`**, digits with the
country code (e.g. `970599123456`). Everything resolves it through
`whatsapp_number()` / `whatsapp_link()` in `app/settings.php` — it appears in no
other file. Until it is set, the inquiry still saves and the success page says
so plainly instead of producing a broken link.

## Reference numbers

Format `VT-2026-000042`: prefix from the `inquiry_prefix` setting, the year, and
the row's own auto-increment id, assigned inside a transaction. The row is
inserted with a temporary unique value and updated with the final reference in
the same transaction, so simultaneous submissions cannot collide. (Verified: six
concurrent submissions produced six distinct references and no leftovers.)

## Adding a payment gateway later

Nothing here would need rewriting:

- `cart_resolve()` already returns authoritative line prices and totals — that
  is exactly what a gateway needs to charge.
- `inquiry_create()` writes a header row plus line items: the shape an order
  takes. A gateway adds an `orders`/`payments` table alongside, not instead.
- The submission branch in `inquiry.php` is the single place a payment step
  would slot in, between validation and the success state.
- `INQUIRY_STATUSES` stays for quote requests; a payment flow adds its own set.

Keep the inquiry path working alongside it — quote-only products have no price
to charge.

## Files

| Path | Role |
|---|---|
| `api/cart.php` | Resolves cart ids to authoritative product data (JSON) |
| `assets/js/cart.js` | localStorage cart, Add buttons, badge, toast |
| `assets/js/inquiry.js` | Cart page rendering, steppers, submission payload |
| `inquiry.php` | Cart page, validation, submission, success state |
| `includes/product-card.php` | Card with Add to Inquiry — used by home, shop, category, related |
| `includes/pagination.php`, `includes/breadcrumbs.php` | Shared UI |
| `shop.php`, `category.php`, `product.php` | Built here because the flow needs somewhere to add from |

Modified: `app/repositories/inquiries.php` (cart resolution, validation,
creation, WhatsApp message), `app/repositories/products.php` (public listing,
related, Product schema), `app/lang/en.php`, `includes/footer.php`,
`assets/js/main.js`, `assets/css/base.css`, `index.php`.

## Limits

| Rule | Value | Where |
|---|---|---|
| Distinct products per inquiry | 40 | `CART_MAX_LINES` |
| Quantity per line | 999 | `CART_MAX_QUANTITY` |
| Notes length | 2000 characters | `inquiry_validate()` |
| Resubmission delay | 20 seconds per session | `inquiry_throttled()` |

## Spam handling

A honeypot field (`website`) sits off-screen. When a bot fills it the request
is accepted with the normal redirect but **nothing is written** — a bot gets no
signal that it was caught. A per-session throttle limits rapid resubmission.
Neither replaces a real rate limiter at the host level if abuse appears.

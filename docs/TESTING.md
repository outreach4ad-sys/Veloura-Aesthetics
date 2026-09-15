# Testing checklist

Run through this after deploying Phase 1. Every item was verified locally
against MariaDB 10.11 / PHP 8.4 before hand-off.

## Database

- [ ] `SHOW TABLES` lists 10 tables
- [ ] `SELECT COUNT(*) FROM site_settings` returns 23
- [ ] `SELECT COUNT(*) FROM categories` returns 9
- [ ] `SELECT COUNT(*) FROM hero_slides` returns 1
- [ ] Deleting a category that still has products fails with **error 1451** (RESTRICT)
- [ ] Deleting a product removes its `product_images` / `product_videos` rows (CASCADE)
- [ ] Deleting a product leaves `inquiry_items` intact with `product_id = NULL`
      and the snapshotted `product_name` (SET NULL)
- [ ] Deleting an inquiry removes its `inquiry_items` (CASCADE)
- [ ] A duplicate product slug is rejected with **error 1062**
- [ ] A product with `price = NULL, price_mode = 'quote'` inserts successfully
- [ ] `updated_at` changes on UPDATE, `created_at` does not

## Homepage

- [ ] `https://veloura-tec.com/` returns 200
- [ ] `<title>` reads `Veloura Tec — Professional Aesthetic Technology & Equipment`
- [ ] The `<h1>` comes from `hero_slides`, not from the PHP file
- [ ] All 9 categories render, each linking to `/category.php?slug=…`
- [ ] With no products published, the empty state appears instead of a broken grid
- [ ] A product with `price_mode = 'show'` renders a formatted price (`$7,900.00 USD`)
- [ ] A product with `price_mode = 'quote'` renders **Price on request**
- [ ] Turning off `show_prices` in settings hides every price site-wide
- [ ] A product with no image renders `placeholder.svg`, never a broken image
- [ ] No `[PLACEHOLDER: …]` text appears anywhere in the page source
- [ ] `<link rel="canonical">`, Open Graph and Twitter tags are present and not duplicated
- [ ] Organization JSON-LD validates at <https://validator.schema.org/>

## Authentication

- [ ] `/admin/index.php` while signed out redirects to `/admin/login.php`
- [ ] Wrong password shows **Incorrect email or password.**
- [ ] A non-existent email shows the *same* message (no account enumeration)
- [ ] Posting the login form without a CSRF token returns **419**
- [ ] 5 failed attempts lock the account; the correct password is then refused
      until the lockout expires (15 minutes)
- [ ] A successful login resets `failed_attempts` to 0 and sets `last_login_at`
- [ ] `SELECT password_hash FROM admins` shows a `$2y$` bcrypt hash, 60 chars
- [ ] Requesting `/admin/logout.php` by **GET** does **not** sign you out
- [ ] Posting logout with a valid CSRF token signs you out and the dashboard
      redirects to login afterwards
- [ ] Re-running `seed_admin.php` after the first admin exists is refused

## Security

- [ ] `https://veloura-tec.com/app/helpers.php` returns **403**
- [ ] `https://veloura-tec.com/app/config.php` returns **403**
- [ ] `https://veloura-tec.com/database/schema.sql` returns **403**
- [ ] `https://veloura-tec.com/includes/header.php` returns **403**
- [ ] Directory listing at `/uploads/` is refused
- [ ] Put a harmless `<?php echo "x";` file in `uploads/` — it must download or
      403, **never execute**. Delete it afterwards
- [ ] Response headers include `X-Content-Type-Options: nosniff`,
      `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`
- [ ] Admin pages send `X-Robots-Tag: noindex, nofollow`
- [ ] With `env => production`, a forced database error shows a generic message
      and no credentials; the detail lands in `storage/error.log`

## Front-end

- [ ] Layout is correct at 1440px, 1024px, 768px and 375px
- [ ] Below 840px the hamburger appears and opens the drawer
- [ ] The drawer closes on link click and on **Escape**
- [ ] `Tab` reaches every link and button with a visible copper focus ring
- [ ] The skip link appears on first `Tab` and jumps to `#main`
- [ ] The inquiry-cart badge is hidden while the cart is empty
- [ ] No JavaScript errors in the console
- [ ] `prefers-reduced-motion` disables transitions

---

# Phase 3 — admin dashboard and product CMS

Every item below was executed against MariaDB 10.11 / PHP 8.4 before hand-off.

## Access control

- [ ] All 11 admin pages redirect to the login page while signed out
- [ ] All 5 action endpoints redirect to login while signed out
- [ ] Every action endpoint returns **419** when posted without a CSRF token
- [ ] A forged 64-character token is rejected with 419
- [ ] A **GET** request to an action endpoint redirects and changes nothing
- [ ] `return_to=https://evil.example.com/steal` redirects back inside /admin/
- [ ] `return_to=//evil.example.com` and `../../etc/passwd` do the same

## Categories

- [ ] Create with name, slug, description, image, sort order, SEO fields
- [ ] Empty name shows "Please enter a category name."
- [ ] Slug `Bad Slug!` is rejected; an empty slug is generated from the name
- [ ] Publish / unpublish toggles the badge and the stored flag
- [ ] Deleting a category that holds products is refused with a count, and the
      category survives
- [ ] Deleting an empty category removes the row **and** its image file

## Products

- [ ] Create with main image, two gallery images and specifications
- [ ] Currency `usd` is normalised to `USD`
- [ ] Empty specification rows are dropped; filled rows store as JSON
- [ ] A duplicate name produces slug `…-2`
- [ ] Price mode *Show* with an empty price is rejected
- [ ] Currency `DOLLARS`, price `-5`, and category `9999` are each rejected
- [ ] Search matches name, slug and brand; a non-matching term returns 0
- [ ] Category, status and featured filters each narrow the list
- [ ] All 7 sort options return 200
- [ ] `sort=p.name;DROP TABLE products--` is ignored and the table survives
- [ ] Publish/draft and feature/unfeature toggles flip and flip back
- [ ] Related products: self-reference and non-existent ids are dropped
- [ ] Delete removes images, videos and related rows, deletes the files, and
      leaves `inquiry_items` intact with `product_id = NULL`
- [ ] Deleting from the edit screen redirects to the product list

## Gallery and videos

- [ ] *Make main* copies the gallery image path to `main_image`
- [ ] Deleting a gallery image removes the row and the file
- [ ] Deleting the image that is currently main clears `main_image` to NULL
- [ ] An image id belonging to a different product is refused
- [ ] YouTube `watch?v=`, `youtu.be` and a Facebook URL all store
- [ ] A non-YouTube URL under the YouTube type is rejected
- [ ] `https://fakebook.com/videos/1` is rejected as a Facebook video
- [ ] A PNG submitted as a video is rejected

## Uploads

- [ ] A PHP file renamed `.png` is rejected ("not a valid image")
- [ ] A real PNG renamed `.php` is rejected on extension
- [ ] A valid PNG with PHP appended uploads, and the payload is **gone** from
      the stored file (GD re-encode)
- [ ] A 2400x1200 image is stored at 1800x900
- [ ] Stored filenames are `YYYYMMDD-<16 hex chars>.<ext>` — the client
      filename never reaches disk
- [ ] Media delete refuses a file still referenced, naming what uses it
- [ ] Media delete removes an orphaned file
- [ ] `../app/config.php`, `uploads/../app/config.php`,
      `../../../../tmp/sentinel.txt` and `/etc/hostname` are all refused, and
      the refusal is written to `storage/error.log`

## Hero slider

- [ ] Create with headline, subtitle, both CTAs and a background image
- [ ] Empty headline is rejected
- [ ] A malformed absolute CTA URL is rejected; `/shop.php` is accepted
- [ ] Publish toggle and delete work; delete removes the image file

## Inquiries

- [ ] List shows reference, customer, country, item count, date and status
- [ ] Search matches reference, name, email, company, WhatsApp and country
- [ ] Status chips filter, and an empty result shows the empty state
- [ ] Detail view shows a total only when every line item has a price;
      otherwise it explains why there is none
- [ ] Status change persists; an invalid status leaves it unchanged
- [ ] Internal notes save and reload

## Settings

- [ ] Saving updates `site_settings` and the change appears site-wide
- [ ] An invalid email is rejected and the previous value survives
- [ ] `javascript:alert(1)` in a URL field is rejected
- [ ] A key not already in the table is ignored, not inserted
- [ ] Saving a real WhatsApp number makes `whatsapp_link()` return a wa.me URL

## Output escaping

- [ ] A product named `<script>alert(1)</script>` renders escaped in the list,
      in `value=""` on the edit form, and inside `data-confirm=""`
- [ ] An inquiry name of `<img src=x onerror=alert(3)>` renders escaped
- [ ] Zero unescaped occurrences of either payload in the HTML source

## Storefront regression

- [ ] The homepage still returns 200 and renders hero and categories
- [ ] No `[PLACEHOLDER: …]` text leaks to visitors

---

## Not in Phase 1 or 3

These are expected to 404 or be unstyled until later phases: `shop.php`,
`category.php`, `product.php`, `inquiry.php`, `about.php`, `solutions.php`,
`contact.php`, `faq.php`, `privacy.php`, `terms.php`, `shipping-returns.php`,
`sitemap.php`, and the admin modules for categories, products, media,
inquiries, hero and settings.

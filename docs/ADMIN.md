# Admin dashboard — setup and usage

## Setup

Phase 3 adds **no SQL changes**. The tables it uses were created by
`database/schema.sql` in Phase 1. If your database is already imported,
there is nothing to migrate — upload the files and sign in.

New files in this phase:

```
app/upload.php                     Secure upload / delete / optimise
app/repositories/inquiries.php     Inquiry queries
app/repositories/media.php         Media library
admin/_form.php                    Shared form + table components
admin/categories.php               Category list
admin/category-edit.php            Category create / edit
admin/products.php                 Product list (search, filter, sort, paginate)
admin/product-edit.php             Product create / edit + gallery + videos
admin/hero.php                     Hero slide list
admin/hero-edit.php                Hero slide create / edit
admin/media.php                    Media library
admin/inquiries.php                Inquiry list
admin/inquiry-view.php             Inquiry detail
admin/settings.php                 Site settings
admin/actions/categories.php       POST handlers
admin/actions/products.php
admin/actions/hero.php
admin/actions/inquiries.php
admin/actions/media.php
```

Modified: `app/bootstrap.php`, `app/repositories/categories.php`,
`app/repositories/products.php`, `app/repositories/hero.php`,
`admin/_auth.php`, `admin/_layout.php`, `admin/index.php`,
`assets/css/admin.css`, `assets/js/admin.js`.

### Folder permissions

`uploads/products`, `uploads/categories` and `uploads/hero` must be writable
(755). They are created automatically on first upload if the parent allows it.

### PHP limits on Hostinger

Videos need headroom. hPanel → **Advanced → PHP Configuration**:

| Option | Value |
|---|---|
| `upload_max_filesize` | 40M |
| `post_max_size` | 48M |
| `max_execution_time` | 120 |

The app's own caps live in `app/config.php` (`max_image_bytes`,
`max_video_bytes`) and should stay below the PHP limits.

## Using the dashboard

**Order of work for a new catalog:** create categories first (a product
cannot exist without one), then products, then mark the best ones featured
so they reach the homepage.

**Slugs** fill themselves in from the name while you type, and stop doing
that as soon as you edit the slug by hand. A slug that is already taken
gets `-2`, `-3` appended automatically.

**Pricing** has two modes. *Show the price* requires a price and displays
it. *Request a quote* hides it and the storefront shows "Price on request".
The global `show_prices` setting hides every price at once without editing
products.

**Gallery images** upload several at a time. Alt text and ordering save with
the product; *Make main* and *Delete* are separate buttons below the form
because HTML does not allow nested forms. Deleting an image that is
currently the main image clears the main image too, so nothing is left
pointing at a missing file.

**Videos** accept a YouTube link in any shape (`watch?v=`, `youtu.be`,
`/embed/`, `/shorts/`), a Facebook video URL, or an uploaded MP4/WebM. Long
videos belong on YouTube — uploads are capped.

**Deleting a category** is refused while it still holds products. Move or
delete those products first. This is enforced by the database
(`ON DELETE RESTRICT`), not just the UI.

**Deleting a product** removes its images, videos and related-product links,
and deletes the files from disk. Inquiries that referenced it keep working:
each line item stores the product name, URL and price as a snapshot.

**The media library** reads `uploads/` directly and marks each file *In use*
or *Not in use*. Only unused files can be deleted, so you cannot break a
product from this screen.

**Settings** are rendered from the `site_settings` table, so adding a row to
that table makes it editable here with no code change. Values still reading
`[PLACEHOLDER: …]` are flagged on the dashboard and hidden from visitors.

## Security notes

| Concern | Measure |
|---|---|
| Authentication | `password_hash()` / `password_verify()`, session regeneration on login, idle timeout, per-account lockout after 5 failures |
| CSRF | Token on every state-changing form; `csrf_guard()` on every POST handler; logout is POST-only |
| SQL | PDO prepared statements everywhere; `ORDER BY` chosen from a fixed whitelist, never built from input |
| XSS | `e()` on every echoed value, including `value=""` and `data-confirm=""` attributes |
| Uploads | Extension whitelist ∩ `finfo` MIME ∩ `getimagesize()`; images re-encoded through GD, which strips any smuggled payload; random filenames; the client filename is discarded |
| Path traversal | Deletion resolves `realpath()` and refuses anything outside `uploads/`; refusals are logged |
| Open redirect | `return_to` accepts only paths beginning `admin/` |
| Cross-record access | Gallery and video operations are scoped by product id, so an id belonging to another product is refused |
| Settings injection | Only keys already present in `site_settings` are writable |

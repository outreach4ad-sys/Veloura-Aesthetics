# Deployment — Hostinger shared hosting

Target domain: **https://veloura-tec.com**
Requirements: **PHP 8.1 or newer** (8.2 recommended) with `pdo_mysql`, `mbstring`,
`fileinfo` and `gd`; MySQL 5.7+ / MariaDB 10.3+.

> PHP 8.1 is the floor because the codebase uses the `never` return type.

---

## 1. Create the database

hPanel → **Databases → MySQL Databases**

1. Create a database (e.g. `u123456_veloura`).
2. Create a user and a strong password.
3. Grant the user all privileges on that database.
4. Write down database name, user and password — you need them in step 3.

## 2. Import the schema

hPanel → **phpMyAdmin** → select the database → **Import** tab →
upload `database/schema.sql` → **Go**.

You should end up with 10 tables, 23 settings rows, 9 categories and 1 hero slide.

> `schema.sql` starts with `DROP TABLE IF EXISTS`. Never re-run it against a
> populated database — it destroys existing data.

## 3. Upload the files

Upload **the contents of `veloura-tec/`** (not the folder itself) into `public_html`.
The fastest route is File Manager → upload a ZIP → *Extract*.

`public_html/` should then contain `index.php`, `admin/`, `app/`, `assets/`,
`includes/`, `uploads/`, `database/`, `storage/`, `.htaccess` and `robots.txt`.

Confirm the hidden `.htaccess` files uploaded — File Manager hides dotfiles by
default (Settings → *Show hidden files*). These four matter:

- `public_html/.htaccess`
- `public_html/uploads/.htaccess` ← **critical**: blocks code execution in uploads
- `public_html/app/.htaccess`
- `public_html/database/.htaccess`

## 4. Configure

Copy `app/config.sample.php` to `app/config.php` and edit:

```php
'env'      => 'production',
'base_url' => 'https://veloura-tec.com',
'db' => [
    'host' => 'localhost',
    'name' => 'u123456_veloura',
    'user' => 'u123456_veloura',
    'pass' => 'your-password',
],
```

`app/config.php` is git-ignored and must never be committed.

## 5. Create the first administrator

The safest route is the CLI (hPanel → **Advanced → SSH Access**, if enabled):

```bash
cd ~/public_html
php database/seed_admin.php "Your Name" "you@veloura-tec.com" "a-long-password"
```

If SSH is unavailable, temporarily comment out this line in `public_html/.htaccess`:

```apache
RewriteRule ^(app|database|storage|includes)/ - [F,L]
```

then open `https://veloura-tec.com/database/seed_admin.php`, fill the form,
**restore the line**, and delete the file.

**Delete `database/seed_admin.php` immediately after either route.** The script
refuses to run a second time, but removing it leaves nothing to probe.

## 6. Permissions

```
uploads/  and its subfolders : 755
app/config.php               : 644
storage/                     : 755
```

## 7. HTTPS

hPanel → **Security → SSL** → install the free Let's Encrypt certificate for
`veloura-tec.com` and enable *Force HTTPS*. Once you have confirmed HTTPS works,
uncomment the `Strict-Transport-Security` line in `.htaccess`.

## 8. PHP version

hPanel → **Advanced → PHP Configuration** → select **8.2**.
On the *PHP extensions* tab confirm `pdo_mysql`, `mbstring`, `fileinfo`, `gd`.

Recommended `PHP options`:

| Option | Value | Why |
|---|---|---|
| `upload_max_filesize` | 40M | product videos |
| `post_max_size` | 48M | must exceed upload_max_filesize |
| `max_execution_time` | 120 | large uploads |
| `display_errors` | Off | the app forces this too |

## 9. Brand assets

Upload the supplied logo to `public_html/assets/img/logo.png`, plus
`favicon.png`. See `assets/img/README.md` for sizes. The logo path is also
changeable from Admin → Settings (`site_logo`).

## 10. Finish the setup

Sign in at `https://veloura-tec.com/admin/login.php`. The dashboard lists every
setting still holding a `[PLACEHOLDER: …]` value — replace them with the real
WhatsApp number, contact details and policy text. Placeholders are hidden from
visitors until you do.

---

## Deployment checklist

- [ ] Database created and `schema.sql` imported (10 tables)
- [ ] Files in `public_html`, hidden `.htaccess` files present
- [ ] `app/config.php` created with real credentials and `env => production`
- [ ] First administrator created
- [ ] **`database/seed_admin.php` deleted**
- [ ] `.htaccess` rule for `app|database|storage|includes` restored if edited
- [ ] SSL issued, Force HTTPS on
- [ ] PHP 8.2 selected, extensions confirmed
- [ ] Logo and favicon uploaded
- [ ] Placeholder settings replaced
- [ ] `https://veloura-tec.com/app/config.php` returns **403**
- [ ] `https://veloura-tec.com/database/schema.sql` returns **403**

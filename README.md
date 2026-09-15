# Veloura Tec

Professional aesthetic technology and equipment — international e-commerce and
product inquiry website for **Optical Cargo**.

- **Domain:** https://veloura-tec.com
- **Stack:** PHP 8.1+, MySQL/MariaDB, PDO, HTML5, CSS3, vanilla JavaScript
- **Hosting:** standard Hostinger shared hosting — no Node.js, no build step

## Status

| Phase | Scope | State |
|---|---|---|
| 1 | Foundation, schema, PDO, config, includes, authentication | ✅ complete |
| 2 | Design system and visual language | pending |
| 3 | Admin dashboard: categories, products, media, videos, hero, settings, inquiries | ✅ complete |
| 5 | Storefront: shop, category, product detail | pending |
| 6 | Inquiry cart and WhatsApp integration | pending |
| 7 | Content pages | pending |
| 8 | SEO, performance, accessibility polish | pending |

## Structure

```
app/          Application core — config, PDO, helpers, security, settings, SEO
  repositories/  All SQL lives here; pages never write queries
  lang/          Translation dictionaries (en.php today)
includes/     Shared view partials: header, nav, footer, flash
assets/       css/ js/ img/ — no build step, served as-is
admin/        Protected dashboard
uploads/      User-uploaded media; code execution disabled by .htaccess
database/     schema.sql and the one-time admin seeder
storage/      Error log (not web-reachable)
docs/         DEPLOYMENT.md and TESTING.md
```

## Local setup

```bash
mysql -e "CREATE DATABASE veloura CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql veloura < database/schema.sql

cp app/config.sample.php app/config.php
# edit: env => 'development', base_url => 'http://localhost:8000', db credentials

php database/seed_admin.php "Your Name" "you@example.com" "a-long-password"
php -S localhost:8000
```

Open http://localhost:8000 and http://localhost:8000/admin/login.php.

> The PHP built-in server ignores `.htaccess`. Directory protection still holds
> because every file under `app/` refuses to run without the `VELOURA` constant,
> but clean URLs and security headers only apply under Apache.

## Conventions

- **Never** interpolate user input into SQL. Use `db_one()`, `db_all()`,
  `db_insert()` and friends — all bind parameters.
- **Never** echo a dynamic value without `e()`.
- **Never** hardcode a business value. Add it to `site_settings` and read it
  with `setting()`.
- Every state-changing form carries `csrf_field()`; its handler calls `csrf_guard()`.
- New queries belong in `app/repositories/` — the file is auto-loaded, no
  bootstrap edit required.
- New UI strings belong in `app/lang/en.php` and are read via `t()`.

See `docs/DEPLOYMENT.md` to deploy, `docs/ADMIN.md` for the dashboard, and
`docs/TESTING.md` for the QA checklist.

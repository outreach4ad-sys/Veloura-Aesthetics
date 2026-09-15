# Production readiness — Veloura Tec

This document records what was **actually reviewed and verified** for Phase 5,
what is confirmed working, and what still blocks a public launch. It does not
claim the site is secure or complete beyond what the checks below show.

Verified against MariaDB 10.11 / PHP 8.4 with a real Chromium browser.

---

## 1. What was verified working

### SEO
| Item | Status | Evidence |
|---|---|---|
| Dynamic `<title>` per page | ✅ | `Hydrafacial Treatment System — Veloura Tec` etc. |
| Meta description | ✅ | Per-page, falls back to the product short description |
| Canonical URL | ✅ | Absolute, present on every page |
| Open Graph tags | ✅ | type/site_name/title/description/url/image |
| Twitter card | ✅ | `summary_large_image` |
| XML sitemap | ✅ | `/sitemap.xml` → `sitemap.php`, well-formed, **excludes drafts** |
| robots.txt | ✅ | Disallows admin/app/database/storage/includes/inquiry |
| Organization schema | ✅ | On every page, only real fields |
| Product schema | ✅ | Priced products get an `offer`; **quote-only products do not** (no invented price) |
| Breadcrumb schema | ✅ | On shop, category, product |
| Single `<h1>` per page | ✅ | 404 and success branches are mutually exclusive |
| Image alt text | ✅ | Cards, product gallery, thumbnails |
| Clean URLs | ✅ | `.htaccess` rewrites `/product/slug`, `/category/slug` |

### Security
| Item | Status | Evidence |
|---|---|---|
| Admin routes require auth | ✅ | All 7 admin pages 302→login when logged out |
| Password hashing | ✅ | `password_hash`/`password_verify`, lockout (Phase 3) |
| Session hardening | ✅ | httponly, samesite=Lax, secure (on HTTPS), regenerate on login, idle timeout |
| CSRF | ✅ | Missing token → 419 on every state-changing POST |
| SQL injection | ✅ | PDO prepared statements; `ORDER BY` from a fixed whitelist; injection in `sort` ignored (Phase 3) |
| XSS | ✅ | `e()` on every echo incl. attributes; payloads render escaped (Phase 3) |
| File upload | ✅ | Extension + finfo MIME + getimagesize + GD re-encode; path traversal refused (Phase 3) |
| Config not web-readable | ✅ | `app/config.php` → 403 (VELOURA guard + `.htaccess`) |
| App internals not readable | ✅ | `app/`, `includes/`, `database/`, `storage/` → 403 |
| Security headers | ✅ | X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy |
| **Content-Security-Policy** | ✅ | Added Phase 5: nonce-based `script-src 'self'`, YouTube frame allowlist, `object-src 'none'`, `base-uri`/`form-action`/`frame-ancestors 'self'`. Zero violations in browser across public + admin |
| Production errors hidden | ✅ | Forced DB failure → `The site is temporarily unavailable.`; zero credential/path/SQLSTATE leaks |
| Errors logged safely | ✅ | Failure written to `storage/error.log`, which is not web-reachable |
| Admin not indexable | ✅ | `X-Robots-Tag: noindex`, `Cache-Control: no-store` |

### Performance
| Item | Status | Evidence |
|---|---|---|
| LCP image not lazy | ✅ | Product main image is `decoding="async"`, not `loading="lazy"` |
| Non-critical images lazy | ✅ | Cards and thumbnails `loading="lazy"` with width/height (no layout shift) |
| No JS libraries | ✅ | Vanilla only; largest JS file is 8 KB |
| CSS size | ✅ | base.css 26.5 KB, admin.css 15.7 KB (unminified, but gzipped by `.htaccess`) |
| Compression | ✅ | `mod_deflate` covers html/css/js/svg |
| Static caching | ✅ | `mod_expires` 1 year for css/js/images, with `?v=filemtime` cache-busting |
| Query indexes | ✅ | Storefront listing uses `idx_products_listing` (ref); slug lookup `const` on unique index |
| Pagination | ✅ | Shop, category, admin lists, inquiries |

---

## 2. Blockers that must be resolved before public launch

These are real and were **not** hidden:

1. ~~Seven footer links return 404.~~ **Resolved in Phase 7.** All seven
   content pages (About, Professional Solutions, Contact, FAQ, Privacy, Terms,
   Shipping & Returns) now exist, return 200, carry SEO metadata and are in the
   sitemap. The footer links resolve.

2. **The design system (Phase 2) is not built.** The storefront works and is
   responsive, but the visual treatment is the functional Phase 1 baseline, not
   the premium design in the brief. This is a completeness gap, not a defect.

3. **Legal pages need review and real details.** Privacy, Terms and
   Shipping & Returns ship as honest, working templates with clearly-marked
   `[PLACEHOLDER]` fields and a visible "Review required" note. The legal
   entity, jurisdiction, retention periods and confirmed shipping/returns
   terms must be completed — and the legal pages reviewed by counsel — before
   they are relied upon. Shipping & Returns reads from Admin → Settings.

4. **Placeholders must be replaced.** WhatsApp number, contact email, phone,
   address and shipping terms still read `[PLACEHOLDER: …]` until set in
   Admin → Settings. The dashboard flags these and they are hidden from
   visitors, but the site is not launch-ready with them unset.

5. **The logo file is not in the repo.** `assets/img/logo.png` must be uploaded;
   until then the header/footer fall back to `placeholder.svg`.

---

## 3. Things to enable at deploy time (documented, not automatic)

- **HSTS**: the `Strict-Transport-Security` header is commented out in
  `.htaccess`. Enable it only **after** confirming HTTPS works on the live
  domain, or you can lock users out of an unencrypted fallback.
- **HTTPS redirect**: the `.htaccess` forces HTTPS via `X-Forwarded-Proto`,
  which is how Hostinger signals TLS. Verify it after the certificate is issued.
- **`display_errors`**: the app forces it off in production regardless of the
  PHP setting, but set it off at the host level too as defense in depth.

---

## 4. Known limitations (accepted, by design or scope)

- CSP uses `frame-src` for YouTube; **Facebook videos render as a link**, not an
  embed, because the Facebook SDK would require loosening the CSP and adding a
  third-party tracker. This is intentional.
- No rate limiting beyond the per-session inquiry throttle and the login
  lockout. A determined attacker from many IPs is not stopped at the app layer;
  Hostinger's own protections apply. If abuse appears, add server-level limits.
- `admin.css` uses `@import "base.css"`, which serializes two requests on admin
  pages. Admin is not performance-critical, so this is left as is.

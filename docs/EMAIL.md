# Email & customer accounts — how to set it up

The site can send three kinds of email:

1. **Account confirmation code** — when a visitor registers, a 6-digit code is
   emailed from your noreply address; they enter it to activate the account.
2. **Inquiry confirmation** — when someone submits an inquiry, they get a
   confirmation email with their reference number (and you get a notification).
3. **Replies from the dashboard** — you can reply to an inquiry by email
   straight from Admin → Inquiries → (open one) → *Reply by email*. The reply
   is logged, and the customer's reply comes back to your contact email.

Until email is configured, everything still works — messages are written to
`storage/mail.log` and marked *logged* instead of sent, so nothing breaks. You
turn on real delivery in **Admin → Settings → Email & accounts**.

There are two ways to send. Pick one.

---

## Option A — SMTP with a Hostinger mailbox (recommended, no extra service)

1. In hPanel → **Emails → Email Accounts**, create a mailbox, e.g.
   `noreply@your-domain`. Note its password.
2. In hPanel that mailbox's **Connect / Configuration** shows the SMTP details.
   For Hostinger they are usually:
   - Host: `smtp.hostinger.com`
   - Port: `465`, security `SSL` (or port `587`, security `TLS`)
3. In **Admin → Settings → Email & accounts** set:
   - `mail_transport` = `smtp`
   - `mail_from_email` = `noreply@your-domain`
   - `smtp_host` = `smtp.hostinger.com`
   - `smtp_port` = `465`
   - `smtp_security` = `ssl`
   - `smtp_user` = `noreply@your-domain` (the full address)
   - `smtp_pass` = the mailbox password
4. Save, then create a test account or submit a test inquiry and confirm the
   email arrives.

For good deliverability, make sure your domain's **SPF** (and DKIM if Hostinger
offers it) records are set — hPanel → Emails → DNS/records usually does this
automatically for a Hostinger mailbox.

## Option B — Resend API (best deliverability, free tier)

1. Create an account at <https://resend.com>, add and verify your domain
   (they walk you through the DNS records), then create an API key.
2. In **Admin → Settings → Email & accounts** set:
   - `mail_transport` = `resend`
   - `mail_from_email` = `noreply@your-domain` (must be on the verified domain)
   - `resend_api_key` = your key
3. Save and send a test.

No SMTP settings are needed for Resend — it sends over HTTPS.

---

## Turning on customer accounts

Accounts are **off by default**. Turn them on in Settings with
`accounts_enabled = Yes`. Then a *Sign in / Create account* link appears in the
header, and `register.php`, `verify.php`, `account-login.php` and `account.php`
become active. Accounts must confirm their email before they can sign in, so
**email must be configured (Option A or B) for registration to work** — the
confirmation code is delivered by email.

If you leave accounts off, the storefront and the inquiry flow work exactly as
before; only the account pages are hidden.

## What is stored

- `customers` — the accounts (email, password hash, verified flag). A customer's
  "My inquiries" list is matched to their account by email address.
- `email_log` — every email the site tried to send, with its status. Useful for
  checking whether delivery is working.
- `inquiry_replies` — the replies you send from the dashboard, kept as a history
  on each inquiry.

## Notes on deliverability

Transactional email can land in spam if the domain isn't set up. The single most
important thing is that the **From address is on your own domain** and that the
domain's **SPF/DKIM** records authorise the sender (your Hostinger mailbox, or
Resend). A Gmail/Yahoo address as the From will not authenticate and will be
filtered — always send from `noreply@your-domain`.

# Affreso security model

## Built-in protections

**Version 3 additions**
- Calendar sync uses OAuth 2.0 with PKCE and a one-time state value. Access and refresh tokens are encrypted at rest. Only busy *times* are read from a person's calendar, never titles or details.
- API keys for Elvanto and Planning Center imports are used once and never stored.
- Self check-in kiosks:
  - Each kiosk holds a 256-bit device token in an HttpOnly, SameSite=Strict cookie, and only its SHA-256 hash is stored.
  - Pairing codes work once, expire after 30 minutes, and are rate-limited.
  - Families can only be found by the full mobile number of an adult, with 30 lookups per 5 minutes per kiosk.
  - The kiosk shows only first names and initials, and the family screen clears itself after inactivity.
  - Leaving kiosk mode needs the exit PIN (5 attempts per 15 minutes), and staff can switch a kiosk off remotely.
- Push notifications are encrypted end to end with the device's own keys (RFC 8291 aes128gcm), so the browser vendor's push service cannot read them. Set *What phone notifications show* to *Nothing* for content-free pushes.

**Accounts and sign-in**
- Passwords are hashed with PHP `password_hash` (bcrypt/argon2 depending on server) and must be at least 10 characters.
- Two-step verification (TOTP) with eight single-use recovery codes. It is **enforced** for anyone holding a sensitive permission (pastoral notes, safeguarding, giving, user/role/settings administration, data export) while *Security → Require two-step verification* is on.
- Brute-force throttling: 5 failed attempts per account, or 20 per IP address, in 15 minutes.
- Password reset and invitation links are single-use, time-limited and stored only as SHA-256 hashes.
- Sessions use HttpOnly and SameSite=Lax cookies (Secure on HTTPS). The session ID is regenerated at sign-in, idle sessions time out, and every session ends when the password changes.

**Access control**
- Granular, role-based permissions (`app/permissions.php`) checked on every route and inside controllers.
- Least privilege by default. For example, Course Creators can only open courses they're assigned to and cannot see member records. Ministry Leaders are scoped to the teams they lead.
- Only a Super Administrator can grant the Super Administrator role or change another Super Administrator's account. The last Super Administrator cannot be removed.
- Course approval requires a second person, so the author cannot approve their own course unless they are the only approver.

**Data protection**
- These are encrypted at rest:
  - confidential pastoral notes
  - WWCC and clearance numbers
  - children's allergy, medical and care information
  - incident reports
  - prayer requests
  - form answers
  - reimbursement bank details
  - two-step (MFA) secrets
  - SMTP, SMS, Stripe, Mailchimp and PrintNode credentials
  - push-notification keys

  They with libsodium (XSalsa20-Poly1305), or OpenSSL AES-256-GCM on servers without sodium. The key lives in `config/config.php`.
- Pastoral notes sit in a separate table and never appear in ordinary profiles, exports or the directory. Each view is written to the audit log.
- Uploaded files are stored with random names in `storage/uploads`, blocked from direct web access, and served only after a permission check. File types are checked by content, not by extension.

**Application security**
- CSRF tokens on every form (verified globally).
- All SQL uses prepared statements, and table and column identifiers are allow-listed.
- All output is HTML-escaped.
- Security headers: Content-Security-Policy (no inline scripts, no third-party scripts), X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy and HSTS on HTTPS.
- CSV exports neutralise spreadsheet formula injection.
- Public forms have a honeypot and per-IP rate limiting.
- The Stripe webhook verifies its HMAC signature and timestamp.
- Children are released only with the matching security code and an authorised collector. Overrides need a recorded reason, and both are logged.
- Push notification titles are encrypted end to end (RFC 8291) so only the person's own device can read them; churches can switch to content-free notifications instead.
- Form notification emails to staff contain a link, never the answers, so private details (health, allergies) stay in Affreso. Paid forms are only marked paid by a signed Stripe webhook, never by the return page.
- Pre-check-in arrival codes are random, single-use and only valid for that day's session. Labels sent to PrintNode leave your server as PDFs over HTTPS; allergy details print only when the leader checking in has children's medical access — otherwise, and always at kiosks, the label just shows a care-alert mark.
- Audit log of sign-ins, record views and changes, exports, permission changes, safeguarding actions and privacy actions.

## Your hardening checklist

- [ ] Serve the site **only over HTTPS**.
- [ ] Keep `'debug' => false` in `config/config.php`.
- [ ] Make `config/config.php` read-only after installation (`chmod 440` or `640`), and back it up offline.
- [ ] Confirm that private folders are blocked: `https://…/affreso/config/config.php` and `https://…/affreso/storage/logs/` must **not** load.
- [ ] Give people the **fewest roles they need**, and review users and roles every quarter.
- [ ] Keep two-step verification required for staff.
- [ ] Disable accounts as soon as staff or volunteers leave.
- [ ] Keep PHP and MySQL up to date (your host usually does this).
- [ ] Set up daily off-server backups and test a restore.
- [ ] Use a dedicated sending service or mailbox for email, with SPF and DKIM.
- [ ] Review the audit log regularly, especially `pastoral.*`, `privacy.*`, `*.exported` and `role.*`.

## Reporting a vulnerability

If you find a security problem, contact the person or organisation that provided your copy of Affreso. Please don't disclose it publicly until a fix is available.

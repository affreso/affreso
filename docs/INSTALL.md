# Installing Affreso

## 1. Requirements

| | Minimum |
|---|---|
| PHP | 8.1 or newer (8.2/8.3 recommended) |
| PHP extensions | pdo_mysql, openssl, curl, mbstring, fileinfo (sodium optional; intl recommended for translated dates; gd with FreeType for server-rendered check-in labels) |
| Database | MySQL 5.7+ or MariaDB 10.3+ (utf8mb4) |
| Web server | Apache (most cPanel hosts), LiteSpeed or nginx |
| HTTPS | Required for live use; most hosts offer free Let's Encrypt certificates |

Most Australian shared hosts (VentraIP, Crazy Domains, SiteGround, Hostinger and others) meet these requirements. The installer checks them for you.

## 2. Upload the files

1. Unzip `affreso.zip`.
2. Upload the `affreso` folder to your site with the hosting **File Manager** or an FTP program. Common locations:
   - `public_html/affreso` → `https://yourchurch.org.au/affreso`
   - or a subdomain such as `app.yourchurch.org.au`, with its document root pointing at the folder
3. Check that the web server can write to `config/` and `storage/`. Folders are usually 755, but some hosts need 775.

## 3. Create a database

In cPanel → **MySQL Databases**:

1. Create a database, for example `yourchurch_affreso`.
2. Create a user with a long random password.
3. Add the user to the database with **All Privileges**.

## 4. Run the installer

Visit the folder in your browser, for example `https://yourchurch.org.au/affreso/`. The installer:

- checks your server
- asks for your church name, state, time zone, database details and the first **Super Administrator** account
- can load **sample data** (demo families, teams, rosters and courses). Only use this on a test copy.

When it finishes, it writes `config/config.php`. **Download a copy of that file and store it somewhere safe and private.** It holds the encryption key for pastoral notes, safeguarding numbers and two-step secrets. Without it, that data cannot be recovered.

## 5. First sign-in

Sign in with the administrator account. Because the administrator can see sensitive data, Affreso asks you to turn on **two-step verification** with an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password and so on). Save the recovery codes.

## 6. Settings (Administration → Settings)

- **General**: church details, ABN (shown on receipts), and who receives new connect cards
- **Email**: SMTP details. Examples:
  - Microsoft 365: `smtp.office365.com`, port 587, STARTTLS, mailbox and password (SMTP AUTH must be enabled for the mailbox)
  - Google Workspace: `smtp.gmail.com`, port 587, STARTTLS, with an app password
  - Postmark, Mailgun, Amazon SES or SendGrid give the best deliverability for bulk messages
  - Use **Send a test email** to check the settings
- **SMS**: ClickSend (Australian) or Twilio
- **Online giving**: Stripe keys and the webhook (see below)
- **Security**: session timeout, two-step requirement, swap approvals and reminder timing
- **Privacy & retention**: privacy officer, retention periods and your privacy policy text

### Stripe webhook

In the Stripe Dashboard → Developers → Webhooks → **Add endpoint**:

- URL: shown in Affreso Settings → Online giving (it ends in `/stripe/webhook`)
- Events: `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `charge.refunded`, `invoice.paid`, `invoice.payment_failed`, `customer.subscription.deleted`
- Copy the **signing secret** (`whsec_…`) into Affreso.

The same webhook confirms payments for paid forms (version 4) and counts instalments (version 4.1) using `invoice.paid`, `invoice.payment_failed` and `customer.subscription.deleted`. Without it, people who pay for a camp are charged but their response stays *Not yet paid*, and instalment plans won't stop after the last payment.

## 7. Scheduled task (cron) — required

The scheduled task sends queued email and SMS, roster reminders, WWCC and training expiry alerts, and runs routine clean-up.

In cPanel → **Cron Jobs**, set it to run every 10 minutes:

```
*/10 * * * * php /home/YOURUSER/public_html/affreso/cron.php >/dev/null 2>&1
```

Calendar sync and repeating events also rely on this task.

If your host only supports web cron, use the URL shown in **Settings → Scheduled tasks**. It ends in `cron.php?key=…`.

**Administration → System health** shows when the task last ran.

## 8. Linking from your church website

- Public events: `…/affreso/index.php?r=/public/events`
- Newcomer connect card: `…/affreso/index.php?r=/connect`
- Online giving: `…/affreso/index.php?r=/give`
- Prayer requests: `…/affreso/index.php?r=/pray`
- Church calendar feed (iCal): `…/affreso/index.php?r=/ical/church`
- Member sign-in: `…/affreso/`

### Optional: shorter web addresses

On Apache with `mod_rewrite` (most hosts), set `'pretty_urls' => true` in `config/config.php`. Addresses then become `…/affreso/public/events`.

## 9. nginx

```nginx
location /affreso/ {
    try_files $uri $uri/ /affreso/index.php?$query_string;
}
location ~ ^/affreso/(app|config|storage|database|tools|lang|docs)/ { deny all; return 404; }
location ~ \.(sql|md|log)$ { deny all; }
location ~ \.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; fastcgi_pass unix:/run/php/php8.3-fpm.sock; }
```

## 10. Backups

Back up three things regularly, for example daily, kept for at least 30 days and stored off the server:

1. **The database** (cPanel → Backup, or `mysqldump`)
2. **`config/config.php`**, which holds the encryption key
3. **`storage/uploads/`** (course PDFs and documents)

Test a restore at least once a year.

## 11. Upgrading

1. Back up the database and files.
2. Upload the new version **over** the old one. Do **not** delete `config/config.php` or `storage/`.
3. Load any page. Affreso upgrades the database by itself (it runs the files in `database/migrations/`) and records this in the audit log. You don't need to run any SQL.

### Version 4.3 settings to review after upgrading

- Nothing needs changing. Gift refunds use the same Stripe keys and `charge.refunded` webhook as form refunds. If you use a restricted key, it needs *Write* access to Refunds (see below).
- Ticket types are off on every form until you add them in the form's settings.

### Version 4.2 settings to review after upgrading

- **Stripe webhook:** make sure `charge.refunded` is included (it's in the list above) so refunds made in the Stripe dashboard show in Affreso.
- **Stripe restricted keys:** if you use a restricted key (`rk_…`), give it *Write* access to Refunds and *Read* access to Checkout Sessions and Invoices.
- Events with a linked form now take registrations through that form. Check any forms you linked to events in version 4, because they now control those events' sign-ups.

### Version 4.1 settings to review after upgrading

- **Stripe webhook:** to offer instalments, make sure the webhook includes `invoice.paid`, `invoice.payment_failed` and `customer.subscription.deleted` (listed above).
- Nothing else needs changing. Instalments and profile links are off on every form until a form manager turns them on.

### Version 4 settings to review after upgrading

- **Roles & permissions:** two new permissions, *Build forms and see their responses* (`forms.manage`, given to Pastor and Church Administrator) and *Manage pledges and campaigns* (`campaigns.manage`, given to Finance Officer). Adjust them if you like.
- **Stripe webhook:** if you'll take payments on forms, check the webhook above is set up and includes `checkout.session.completed` and `charge.refunded`.
- **Settings → Integrations → Label printing:** optional. See *Direct label printing* below.
- **Languages:** Vietnamese, Arabic and Persian are now available in each person's Account & security page.
- The demo's sample data (forms, a building-fund campaign and a pre-checked family) only appears after **Reset demo now** — real churches don't get sample data on upgrade.

### Direct label printing (optional)

Check-in labels can always be printed from the browser. For silent printing straight to a label printer:

1. Create a free account at [printnode.com](https://www.printnode.com) and install the PrintNode client on the computer (Windows, Mac or Raspberry Pi) that the label printer is plugged into.
2. In PrintNode → Account → API keys, create a key and paste it into **Settings → Integrations → Label printing**, then save.
3. Choose the **default label printer** from the list and save again. Use **Print a test label** to check it.
4. Optional: give each kiosk its own printer under **Children's check-in → Self check-in kiosks**.

Labels are 62 × 100 mm PDFs, which suits Brother QL (62 mm continuous roll) and DYMO printers; set the printer's paper size to match in PrintNode. Labels need the PHP **gd** extension with FreeType (almost every host has it). The built-in font covers Latin, Greek and Cyrillic names, including Vietnamese. For Chinese, Korean or Japanese names, upload a font such as Noto Sans CJK under **Label font**. Names in Arabic or Persian script are shaped and printed right to left with the built-in font (version 4.1).

### Version 3 settings to review after upgrading

- **Roles & permissions:** two new permissions, *Record service attendance headcounts* and *Set up and manage self check-in kiosks*. They're added to Pastor, Church Administrator, Ministry Leader and Safeguarding Officer automatically. Adjust them if you like.
- **Settings → Integrations → Calendar sync:** optional. See *Calendar sync* below.
- **Settings → Integrations → Phone notifications:** choose whether notification titles show on phones.
- **Children's check-in → Self check-in kiosks:** set up a tablet for the foyer if you'd like families to check in themselves.

### Calendar sync (optional)

People can connect their own Google or Microsoft calendar from **Account & security**. Each church registers its own app, so tokens stay with you:

**Google:**
1. In Google Cloud Console, create a project and enable the **Google Calendar API**.
2. Go to **APIs & Services → OAuth consent screen**. Choose *External*, add your church name and the scope `.../auth/calendar.events`, then publish it.
3. Go to **Credentials → Create credentials → OAuth client ID → Web application**. Add the redirect address shown in **Settings → Integrations**. It ends in `/account/calendar/callback`.
4. Paste the client ID and secret into **Settings → Integrations**.

**Microsoft 365 / Outlook:**
1. In the Microsoft Entra admin centre, go to **App registrations → New registration**. Use the redirect type *Web* and the same redirect address.
2. Under **API permissions**, add the Microsoft Graph delegated permission **Calendars.ReadWrite**.
3. Under **Certificates & secrets**, create a client secret.
4. Paste the application (client) ID and secret into **Settings → Integrations**. Leave the tenant blank to allow both personal and work accounts.

The scheduled task keeps each connected calendar in sync about every 30 minutes, and people can press **Sync now** at any time. Calendar sync needs HTTPS.

### Self check-in kiosk

1. Go to **Children's check-in → Self check-in kiosks → Add a kiosk**. Give it a name and an exit PIN.
2. On the tablet, open `…/index.php?r=/kiosk/pair` and enter the pairing code shown.
3. Lock the tablet to the browser: **Guided Access** on iPad (Settings → Accessibility), or **screen pinning** on Android.
4. Each Sunday, open a check-in session as usual. The kiosk works only while today's session is open.

For label printing, set the tablet's default printer to your label printer (AirPrint on iPad), with 62 × 100 mm labels.

### Version 2 settings to review after upgrading

- **Roles & permissions:** check the new permissions are on the right roles. They cover check-in, children's medical information, incidents, services, library and reimbursements.
- **Settings → Campuses:** add campuses if you have more than one.
- **Settings → Integrations:** add your bank details for ABA reimbursement files, Xero account codes and Mailchimp.
- **Stripe webhook:** to accept regular (recurring) gifts, add these events to your Stripe webhook: `invoice.paid`, `invoice.payment_failed`, `customer.subscription.deleted`.
- **Push notifications:** need HTTPS. Each person turns them on from **Account & security → Turn on notifications on this device**.

## 12. Troubleshooting

| Problem | Fix |
|---|---|
| Blank page or "Something went wrong" | Check `storage/logs/php-errors.log`. For short-term diagnosis only, set `'debug' => true` in `config/config.php`. |
| Emails not arriving | Use Settings → Email → Send a test email. Check SPF/DKIM for your sending domain. Check that the cron job is running. |
| "Your form expired" | The page was open too long or cookies are blocked. Refresh and try again. |
| Locked out of two-step verification | Use a recovery code, or ask another Super Administrator to reset it (Users → user → Reset two-step verification). |
| Uploads fail | Check that `storage/uploads` is writable and that PHP `upload_max_filesize` is at least 20M. |

## 13. Setting up a public demo site

To show Affreso to other churches without a real congregation's data:

1. Install as normal, ideally on its own subdomain (for example `demo.affreso.com`) with its own database, and tick **"Make this a public demo site"**.
2. The sign-in page then shows one-click buttons for each role: Super Administrator, Pastor, Church Administrator, Ministry Leader, Safeguarding Officer, Volunteer and Member. No password or two-step verification is needed.
3. In demo mode:
   - a "Demo site" banner appears on every page
   - email, SMS and Stripe payments are switched off, so queued messages are marked *skipped*
   - file uploads are off
   - visitors can't change settings or role permissions, or change or lock the shared demo logins or your owner account
   - only you, the site owner, can use **Administration → Reset demo now**
4. **Nightly reset:** set up the normal cron job (section 7). The first run after midnight erases everything and reloads fresh sample data, with dates relative to the current week. Your own administrator login, password and two-step setup are kept. You can also reset from the command line: `php cron.php --demo-reset`.

To turn an existing install into a demo (or back), set `'demo_mode' => true` (or `false`) in `config/config.php`. Demo logins are created at the next reset.

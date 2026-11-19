# TerraLink CRM v1.5.0

A self-hosted CRM for TerraLink Systems, built with PHP 8.3 and MySQL 8.4 in the Backcountry Pro look. It runs on desktop and on phones in the field.

**Modules:** companies & contacts · pipeline · activity timeline · tasks · client sites & installed equipment · inventory (stock + consumables) · support tickets · quotes with PDF · website-lead API for n8n · users & roles.

---

## 1. Run it on your own computer (Docker Desktop)

```bash
APP_COMMIT=$(git rev-parse HEAD) docker compose -f docker-compose.yaml -f docker-compose.local.yml up -d
docker compose logs app        # copy the one-time setup link
```

Open the setup link with the host changed to `http://localhost:8080`, then create your admin account.

## 2. Production (Hostinger web hosting)

This runs on Hostinger **web hosting** (account `u300896537`) as the site **crm.terralinksystems.com**, next to the WordPress site.

- **Code:** hPanel Git deploy clones the **`hosting`** branch of this repo into `public_html/crm`, and `public_html/.htaccess` maps every request into `crm/public/`. That branch is `master` plus the `vendor/` folder, because shared hosting can't run composer. The root `.htaccess` maps every request into `public/`, so `src/`, `vendor/`, `sql/` and `.git` can never be served.
- **Settings and passwords** live in `/home/u300896537/domains/crm.terralinksystems.com/crm-config.php`. That's outside `public_html` and never in git. It holds the DB credentials, the n8n API key and the first-run setup token hash.
- **Database:** MySQL on the hosting account. Tables are created and updated automatically on the first request after a deploy.
- **First admin:** a one-time setup link (48 h). It's only usable while no users exist.
- **Updating:** commit to `master` → rebuild the `hosting` branch (`tools/make-hosting-branch.sh --push` in the dev workspace) → redeploy in hPanel. Data and the config file are untouched.
- **Backups:** handled by Hostinger's hosting backups, which cover files and databases. Check hPanel → Backups for the schedule.

The Docker files (`docker-compose.yaml`, `Dockerfile`) are kept for running it on your own computer or a VPS.

## 3. Website leads via n8n

The endpoint is `POST https://crm.terralinksystems.com/api/leads`.

- **Header:** `X-CRM-Key: <key>`. Admins can see the key under **Settings → Website leads API**. Store it in n8n as a Header Auth credential.
- **Body (JSON):** the same fields the website form already sends: `name`, `business`, `phone`, `email`, `message`, `page`.

What it does:
- Matches an existing contact by email or phone, otherwise creates the company and contact.
- Creates a **New** deal with source *Website*, and guesses the service from the page URL.
- Logs the activity and adds a "Call back" task for today.
- Ignores the same person resubmitting within 10 minutes and returns the existing deal.

In workflow `rutWGq9RGBX48gKp`, add an HTTP Request node on the **callback** branch only, not careers.

## 3b. Email from the CRM (n8n)
The CRM sends email (password resets, invites, and later quotes and reminders) by POSTing to the n8n workflow **"TerraLink CRM — Outbound Email"** (`o3mNCWGnxXkihG8j`). That workflow sends through the SMTP TERRALINK credential.

- **Webhook:** `https://terralinksystems.app.n8n.cloud/webhook/terralink-crm-mail`. You can change it in Settings.
- **Auth:** the same `X-CRM-Key` header that the leads API uses.
- **Body:** `{"messages":[{"to","subject","html","reply_to","attachment_b64","attachment_name","attachment_mime"}]}`.
- **Log:** each send is recorded in **Settings → Recent emails**. Set `MAIL_MODE=log` in a local `.env` to write emails to `/tmp/crm-mail.log` instead of sending them.

## 3c. Photos, files and backups
- **Where uploads are stored:** in `crm-uploads/`, next to `crm-config.php` (outside `public_html`). You can override this with `UPLOAD_DIR`. Locally they go to `storage/uploads`. `/api/health` shows `"storage": true` when that folder is writable.
- **Backups:** go to **Import & export** (avatar menu) and download the database (.sql.gz) and the photos and files (.zip). To restore, open hPanel → phpMyAdmin → Import.

## 4. Roles

| Role  | Can do |
|-------|--------|
| Admin | Everything, including users, settings, and deleting companies and tickets |
| Sales | Everything except admin pages; can delete quotes |
| Tech  | Everything except admin pages; can delete sites and equipment |

## 5. Settings to fill in after first login
Go to **Settings** and fill in the GST registration number and the **travel rate per km**. The "+ Travel (km)" button on quotes uses that rate, so it stays at $0 until you set it. You can also edit the default quote terms there.

PST is ticked by default on hardware and software quote lines, and every line can be changed. Check the defaults against your accountant's advice.

## Project layout
```
public/      web root (index.php front controller, assets, fonts)
src/         bootstrap, forms, handlers/ (one file per area), api
views/       PHP templates
sql/         schema (idempotent migrations)
bin/         migrate.php, create-admin.php, seed-demo.php (dev only)
```

**Local dev without Docker:** run `composer install`, then `php -S 127.0.0.1:8080 -t public public/dev-router.php`, using a `.env` with `APP_ENV=development`. Then run `php bin/seed-demo.php` for demo data.

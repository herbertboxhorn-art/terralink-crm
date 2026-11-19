# TerraLink CRM v1.3.1

A self-hosted CRM for TerraLink Systems, built with PHP 8.3 and MySQL 8.4 in the Backcountry Pro look. It runs on desktop and on phones in the field.

**Modules:** companies & contacts · pipeline · activity timeline · tasks · client sites & installed equipment · support tickets · quotes with PDF · website-lead API for n8n · users & roles.

---

## 1. Run it on your own computer (Docker Desktop)

```bash
docker compose -f docker-compose.yaml -f docker-compose.local.yml up -d --build
docker compose logs app        # copy the one-time setup link
```

Open the setup link with the host changed to `http://localhost:8080`, then create your admin account.

## 2. Production (Hostinger VPS)

This runs on VPS `srv1682079` (187.77.11.153) as Docker project **`terralink-crm`**, served at https://crm.terralinksystems.com through the existing Traefik proxy (`letsencrypt` resolver).

- **Code:** `docker-compose.yaml` builds the app straight from this repo's `master` branch.
- **Secrets:** generated on the server at first start and stored in the `crm-secrets` volume: the database passwords and the n8n API key. Nobody needs to type or store them.
- **First admin:** while no users exist, the app prints a one-time setup link (valid 48 h) in its log. View it in hPanel → VPS → Docker Manager → terralink-crm → Logs. Restarting the app container prints a fresh link.
- **Updating:** push to `master`, then redeploy the project in Docker Manager. Migrations run automatically, and data and secrets volumes are kept.

### Backups
The `backup` container writes `crm-YYYY-MM-DD.sql.gz` into the `crm-backups` volume every night at 02:30 Pacific and keeps 14 days.

**Restore:**
```bash
docker exec -i terralink-crm-db-1 sh -c 'mysql -uroot -p"$(cat /run/crm/db_root_pass)" terralink_crm' < crm-2026-11-19.sql
```

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

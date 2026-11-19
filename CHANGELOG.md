# TerraLink CRM changelog

## 1.6.0 — 2026-11-19
- **Look:** real TerraLink logo and app icons, login page in the website's style, light KPI cards (swipe strip on phones), owner avatars, **dark mode** (avatar menu → Theme)
- **My day:** one page per person with their open tickets (site access notes, phone, map) and tasks due; techs land here after login
- **Ticket response targets:** hours per priority in Settings (default 4 / 8 / 24 / 72); badges on tickets, an "Overdue" tab and a dashboard count
- **Tickets:** one Update box (note + status + time, saved together); time shown as "1 h 30 min"
- **Email:** forgot-password links and user invites (Users → Invite), sent through n8n; Settings shows recent emails and a test button
- Fixes: deal form makes you pick service and source; "UniFi UniFi" labels; dollar totals without stray cents; assignee dropdown no longer cut off; timeline "Hide automatic updates"; Settings no longer blanks fields the form didn't send

## 1.5.0 — 2026-11-19
- **Inventory:** equipment can be in stock without a site (location, supplier, purchase date, cost); Inventory page with Installed / In stock / Consumables tabs and stock value by category
- **Receive stock:** bulk-add one model by pasting/scanning serials (or a quantity)
- **Install** a stock item at a site from the item, or pick several from stock on the site page; **Return to stock** (optionally as faulty); deleting a site returns its gear to stock
- **Consumables:** count-only items (cable, connectors, mounts…) with receive / use at site / stock count, history, re-order levels, low-stock alerts on dashboard and menu
- Stock locations configurable in Settings; versioned migrations (`src/migrations.php`) run automatically and safely re-run

## 1.4.2 — 2026-11-19
- DB connection forces utf8mb4_unicode_ci (fixes "Illegal mix of collations" on Hostinger MySQL); leads duplicate check rewritten

## 1.4.1 — 2026-11-19
- Leads API: authenticated callers get the real error reason; rollback can no longer crash on a failed insert

## 1.4.0 — 2026-11-19
- Moved production to Hostinger web hosting (shared): `crm-config.php` outside the web root, root `.htaccess` → `public/`, automatic schema migration, setup token from config
- `hosting` branch carries vendor/ (no composer on shared hosting)
- `.htaccess` header rules made conditional (no 500 if mod_headers is missing)

## 1.3.1 — 2026-11-19
- Production stack generates DB passwords and the API key on the server (Docker volume), never in config files
- One-time first-admin setup link (printed to the app log, 48 h, single use)
- Settings shows the n8n API URL and key to admins
- Runs on stock images (Hostinger does not build): the app container fetches this repo at the pinned `APP_COMMIT` on start; `*_FILE` env support

## 1.3.0 — 2026-11-19 (first local build, not yet deployed)
- Core: companies, contacts, deals pipeline (New → Audit booked → Quoted → Won/Lost, drag & drop), activity timeline, tasks/follow-ups, global search
- Field: client sites (GPS, access notes, connectivity), installed equipment (UniFi, Starlink, phone lines… serial/MAC/IP/warranty), support tickets with time tracking
- Quotes: line items, Northern BC travel (km × rate), GST + per-line PST, PDF export, revisions; Accepted/Declined moves the linked deal to Won/Lost
- API: POST /api/leads for the n8n website callback form (header X-CRM-Key), duplicate guard, auto call-back task
- Users & roles (admin / sales / tech), login throttling, CSRF, self-hosted fonts
- Docker: PHP 8.3 + Apache, MySQL 8.4 (no public port), nightly backups (14 days), Traefik labels for crm.terralinksystems.com

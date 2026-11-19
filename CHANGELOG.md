# TerraLink CRM changelog

## 1.3.1 — 2026-11-19
- Production stack generates DB passwords and the API key on the server (Docker volume), never in config files
- One-time first-admin setup link (printed to the app log, 48 h, single use)
- Settings shows the n8n API URL and key to admins
- Builds straight from GitHub; `*_FILE` env support

## 1.3.0 — 2026-11-19 (first local build, not yet deployed)
- Core: companies, contacts, deals pipeline (New → Audit booked → Quoted → Won/Lost, drag & drop), activity timeline, tasks/follow-ups, global search
- Field: client sites (GPS, access notes, connectivity), installed equipment (UniFi, Starlink, phone lines… serial/MAC/IP/warranty), support tickets with time tracking
- Quotes: line items, Northern BC travel (km × rate), GST + per-line PST, PDF export, revisions; Accepted/Declined moves the linked deal to Won/Lost
- API: POST /api/leads for the n8n website callback form (header X-CRM-Key), duplicate guard, auto call-back task
- Users & roles (admin / sales / tech), login throttling, CSRF, self-hosted fonts
- Docker: PHP 8.3 + Apache, MySQL 8.4 (no public port), nightly backups (14 days), Traefik labels for crm.terralinksystems.com

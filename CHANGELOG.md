# TerraLink CRM changelog

## 1.18.2 — 2026-11-22
- Phones: the Home page count boxes are a compact 3-column grid (label + number only) instead of a sideways-swipe row. "Won in" uses the short month name.

## 1.18.1 — 2026-11-22
- Phones: a long email address, web link or word (e.g. in a website request message or contact details) no longer makes the page wider than the screen; it wraps inside its card. Applies to every page, not just deals.
- Phones under ~385 px wide: the account menu (avatar) no longer slides off the right edge of the top bar.

## 1.18.0 — 2026-11-22
- Installable app: the CRM and the client portal can be installed on phones and tablets (Android, iPhone/iPad) and desktops, and open full screen from their own icon.
- Service worker (`/sw.js`): keeps CSS/JS/icons on the device for faster loads; pages and data are never cached. Offline page (`/offline.html`) with Go back / Try again when there's no signal.
- Separate portal app (`/portal.webmanifest`, "TerraLink Client Portal", dark icon) so the two apps look different on a home screen. Proper maskable icons for Android.
- "Install app on this device" in the account menu and on the sign-in pages; iPhone/iPad get Add to Home Screen steps.
- App shortcuts (long-press the icon): My day, Tickets, New ticket (CRM); Open a ticket, My tickets (portal).

## 1.17.1 — 2026-11-19
- Fix: creating the 2FA key file used link(), which is disabled on Hostinger shared hosting and broke every page after 1.17.0. Now uses an exclusive create, and a key problem can never block the site.

## 1.17.0 — 2026-11-19 (security release)
- Client portal: two-step sign-in (authenticator app, recovery codes, trusted devices). Settings → Security → "Two-step sign-in (client portal)": optional, required for logins with Invoices or Manage users (default), or required for all. Staff can reset it on the portal login page.
- Client portal: a client's own admin can no longer change a colleague's login email (that allowed taking over a stronger login). When staff change it, the old password and sessions stop working; send a new login link.
- Signing out everywhere: a password change or reset, email or role change, deactivation or 2FA reset ends the person's other sessions (staff and portal).
- Login throttling also per account (10 tries an hour from any IP), on top of 8 per IP in 15 minutes. Settings shows the IP the CRM sees.
- 2FA secrets are now encrypted with their own key (crm-2fa.key next to crm-config.php, or TWOFA_KEY), not one derived from the API key. Existing secrets are re-encrypted automatically; nobody has to set up again.
- API keys can be split per job (LEADS_API_KEY, CRON_API_KEY, MAIL_WEBHOOK_KEY; all fall back to CRM_API_KEY). Settings shows keys masked only.
- Website leads always create a new company and contact, with a "possible existing client" note, instead of writing into an existing client's records.
- Techs can't open files attached to quotes.

## 1.16.0 — 2026-11-19
- **Two-step sign-in (2FA) for staff:** after the password, a 6-digit code from an authenticator app (Google/Microsoft Authenticator, 1Password, Authy, Bitwarden…). Set up under My account → Two-step sign-in by scanning a QR code.
- **You choose in Settings → Security:** optional for everyone, required for admins, or required for everyone. You can also require it for one person (Users → Edit). Anyone required without it is sent to the setup page until it's done.
- **8 recovery codes** (single use) for a lost phone, **"Trust this device for 30 days"**, and a list of trusted devices with "Forget all".
- **Admins can reset** someone's 2FA (Users → Edit) when they get a new phone.
- Security: codes can't be reused, 6 wrong codes locks the code step for 15 minutes, a password-reset link can't skip the code (and forgets trusted devices), secrets are encrypted in the database and backups, and users get an email whenever 2FA is turned on, off, reset or a recovery code is used.

## 1.15.0 — 2026-11-19 (audit release)
- **Client waiting:** when a client opens a ticket or replies in the portal, the ticket is flagged "Client waiting" until someone replies to the client, changes the status or closes it (internal notes don't clear it). New "Client waiting" filter on Tickets, a blue count next to Tickets in the menu, badges on tickets and My day, and the dashboard tile. Admins' morning email lists clients waiting on unassigned tickets.
- **Default assignee for portal tickets:** Settings → Client portal. New portal tickets go straight into that person's My day.
- **Overdue invoice reminders:** automatic polite reminder 3 days after the due date and a second one at 14 days, with the PDF attached and a portal link. Herb gets a one-line summary. On/off in Settings, and per company on its Edit page.
- **Closing emails clients:** closing a ticket emails the client who opened it (or the contact's portal login) that it's resolved, with a link to the work summary.
- **Portal links in emails:** quote emails get a "Review & accept online" button and invoice emails "View your invoices in the portal", when the company has a portal login with that access.
- **Site-limited portal logins:** a login can be limited to certain sites (e.g. a camp manager only sees that camp's tickets, gear and plans). A site-limited client admin can only give out their own sites.
- **Tech role tightened:** only admins can delete contacts, deals and equipment; techs no longer see quotes (menu, company tab, deal page, search, all quote pages).
- **Error log** (admin menu → Error log): crashes, fatal errors and PHP warnings on the live server are logged (same error counted, not repeated) with a stack trace; visitors get a friendly error page. Admins' morning email mentions new errors.
- **Audit fixes:** "Reply to client" is never pre-ticked (prevents internal notes reaching clients by accident); a company with invoices can't be deleted (it used to crash); old portal links and login attempts are cleaned up daily; HSTS and Permissions-Policy headers added.

## 1.14.0 — 2026-11-19
- **Client portal** at portal.terralinksystems.com: the same app and database on its own hostname, with its own logins and session. Staff pages are never served there.
- **Logins per company.** Each company can have several portal logins. Each login has ticks for: Tickets (own, or "see all company tickets"), Sites & equipment, Service plans, Quotes, Invoices & payments, Manage users. Plan fees and ticket charges only show with Invoices.
- **Staff:** a new **Client portal** tab on each company. Add a login from a contact or by name/email with a preset (Tickets only / Operations (no money) / Full access + manage users) or custom ticks. You can also email an invite (link valid 7 days), edit, disable or remove a login.
- **Client admins** (Manage users) add colleagues and set their access themselves, but can only give out access they have.
- **Tickets in the portal:** clients open tickets (site, urgency, photos), see their status, reply and add photos. Staff get an email; the client gets a confirmation. A client reply to "Waiting on client" puts the ticket back to Open. Closed tickets offer a follow-up ticket and a work summary PDF (no prices without Invoices; only client-visible notes).
- **Internal vs client-visible:** the ticket Update box has "Reply to client". Ticked, the note shows in the portal and is emailed to the client who opened the ticket (or the login linked to its contact). Otherwise notes stay internal. Ticket files: "share with client" toggle, client uploads are marked "From client".
- **Quotes:** clients see sent quotes with items, totals and PDF, and can accept (with an authorization tick and optional note/PO) or decline. Same effects as staff: deal Won/Lost, stock reserved/released. The quote shows who decided and when; the quote owner gets an email.
- **Invoices:** issued invoices (not drafts or void), balance owing, overdue flag, PDFs, and how to pay.
- **Sites & equipment** (serials, install dates, warranty status, "Report a problem" per device) and **service plans** (next visit, what's checked).

## 1.13.0 — 2026-11-19
- **Invoices** (new menu item, admin/sales). On the 1st of each month the daily job makes a **draft invoice per client** and emails Herb that they're ready to review. Nothing goes to clients until a draft is approved.
  - What goes on a draft: every closed, billable ticket not yet billed (grouped by ticket, with site and completion date), plus **service plan fees for the coming month** (billed in advance, fee ÷ months between visits).
  - Logged time nobody billed is added automatically at the field rate ($100/h managed rate for clients on a paid plan) and flagged "auto-billed time" for review.
- **Review:** change the billing email, terms or note; take a ticket off (it goes back to Ready to bill); remove plan or custom lines; add lines (a negative price makes a credit). "Make drafts now" runs month-end on demand.
- **Approve & email** (one at a time or ticked in bulk): gives the invoice its number (INV-0001…), sets the invoice and due dates, and emails the PDF to the client from the payment address. If the email fails it stays "Approved, not sent" with a Send button.
- **Invoice PDF:** groups lines by service plan and ticket, shows GST/PST, and has a "How to pay" box: Interac e-Transfer to payment@terralinksystems.com, EFT, credit card, and "We do not accept cheques."
- **Payments:** mark an invoice paid (date and method). Overdue invoices are flagged. Void sends a sent invoice's tickets back to Ready to bill; deleting a draft does the same.
- **Companies:** billing email and payment terms (empty = standard 15 days). **Settings → Invoices:** e-Transfer email, standard terms, who gets the review email, EFT and credit card text, footer note.
- CSV exports for invoices and invoice lines (for QuickBooks later). Money with a minus sign now shows as -$50.00.

## 1.12.0 — 2026-11-19
- **Charges on tickets:** a Charges card on every ticket. Add from the price list (mileage in km, labour in hours, installs, monthly items), override the price, or add a custom line. GST 5% / PST 7% totals are shown on the ticket and in its Details.
- **Bill logged time:** one button turns the time logged with "+ time…" into a labour line at the rate you pick (rounded up to the quarter hour). Billed time is tracked, so it's never billed twice.
- **Parts from stock:** price-list items linked to stock come off inventory when used on a ticket (consumables are deducted; equipment units are installed at the ticket's site, serials noted). Removing the charge puts the stock back.
- **Billable / no charge** switch per ticket. Tickets opened by a service plan start as no charge (covered by the plan).
- **Work summary PDF** per ticket: work done, time, charges and totals, with a client sign-off line.
- **Billing** page (admin/sales): "Ready to bill" lists closed, billable tickets with charges or unbilled time, with totals; tick and mark them billed. Billed tickets are locked (an admin can reopen) and can't be deleted.

## 1.11.0 — 2026-11-19
- **Plan templates** (Service plans → Templates): standard plans with a title, frequency, ticket lead time, priority, fee per visit, fee note and tech checklist. On a new plan, "Start from a template" fills everything in; you pick the company, site and first visit date. Editing a template doesn't change plans already made from it.
- Comes with TerraLink's eight standard plans: Network Care check, Managed IT site visit, Managed IT remote review, IT planning meeting, Backup restore test, Starlink seasonal check, Automation health review, Private AI Server maintenance.

## 1.10.0 — 2026-11-19
- **Price list import** (admins): "Import price list from CSV" on the Price list page adds new items and updates existing ones, matched by SKU, then by name. Uses the same columns as the price list export; stock links are kept.
- Price list CSV export now includes the Description column.

## 1.9.0 — 2026-11-19
- **Price list** (new menu item): standard items and rates with SKU, type, unit, sell price, optional cost (margin shown, never on quotes), PST default, and an optional link to stock (an equipment model or a consumable). Items used on quotes are hidden rather than deleted.
- **Quote picker:** "Add from price list" on the quote editor fills in the line (text, unit, price, PST) and shows the free stock under it, in red when there isn't enough
- **Stock on quotes:** a "Stock for this quote" card shows need / reserved / free / short for each stocked item, with **Reserve stock** and **Release** buttons. Stock is reserved automatically when a quote is accepted and released when it's declined, expires or is deleted; installing a reserved unit uses up its reservation.
- Inventory shows "Reserved · Q-…" on stock items and reserved/free amounts on consumables. "+ Add to price list" from a consumable. Price list CSV export.

## 1.8.0 — 2026-11-19
- **Email quotes:** "Email to client" on a quote sends a message with the quote PDF attached, from noreply@ with replies going to you, with an optional copy to yourself. A draft quote becomes Sent and its deal moves to Quoted; the email is logged on the quote.
- **Service plans** (new menu item): recurring visits (monthly to yearly) per company or site, with a checklist, assignee, priority and an optional fee. A ticket opens automatically N days before each visit, and plans keep their day of the month (the 31st stays at month end). "Open ticket now" is available on each plan. Shows recurring revenue per month; plan cards appear on company and site pages.
- **Morning summary email (6:30 am):** your tickets (overdue ones flagged), tasks due or overdue, new leads, quotes about to expire and low stock. It's skipped when there's nothing for you, and each person can turn it off under My account.
- New n8n workflow "TerraLink CRM — Daily Jobs" calls `POST /api/cron/daily` (X-CRM-Key) every morning; the run is safe to repeat (once per day)

## 1.7.0 — 2026-11-19
- **Photos & files** on companies, sites, deals, quotes, tickets and equipment: snap a photo on the phone or attach PDFs and Office files (25 MB each). Photos are turned upright, shrunk to 2560 px and get thumbnails. Files live outside the website folder and only open for signed-in users. The uploader or an admin can delete them, and they're removed when their record is deleted.
- **Import & export** (avatar menu, admins): CSV export of companies, contacts, deals, sites, equipment, consumables, tickets and quotes (Excel-safe); import companies and contacts from a template, Outlook or Google export, with a preview showing what will be created, added or skipped
- **Backup:** one-click database download (.sql.gz, restores with phpMyAdmin) and a zip of all photos and files; "last downloaded" date shown
- Clear message when an upload is bigger than the server allows; health check reports whether file storage is writable

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

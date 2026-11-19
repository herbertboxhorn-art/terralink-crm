<?php
// Versioned schema changes after the base schema (sql/001_schema.sql).
// Each step checks first, so a half-finished run can simply be repeated (DDL auto-commits in MySQL).

function col_exists(string $table, string $col): bool {
    return (bool)val('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$table, $col]);
}
function table_exists(string $table): bool {
    return (bool)val('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]);
}
function fk_rule(string $table, string $fk): ?string {
    $r = val('SELECT DELETE_RULE FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ?', [$table, $fk]);
    return $r === null ? null : (string)$r;
}

/** @return array<string, callable> version => migration */
function migrations(): array {
    return [
        // 1.5.0 — Inventory: equipment can be in stock (no site); consumables with stock movements
        '1.5.0' => function (): void {
            // equipment.site_id becomes optional; deleting a site returns its gear to stock instead of deleting it
            if (fk_rule('equipment', 'fk_equip_site') !== 'SET NULL') {
                if (fk_rule('equipment', 'fk_equip_site') !== null) db()->exec('ALTER TABLE equipment DROP FOREIGN KEY fk_equip_site');
                db()->exec('ALTER TABLE equipment MODIFY site_id INT UNSIGNED NULL');
                db()->exec('ALTER TABLE equipment ADD CONSTRAINT fk_equip_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL');
            }
            db()->exec("ALTER TABLE equipment MODIFY status ENUM('in_stock','active','spare','faulty','retired') NOT NULL DEFAULT 'active'");
            foreach ([
                'stock_location' => 'VARCHAR(80) NULL AFTER status',
                'supplier'       => 'VARCHAR(120) NULL AFTER stock_location',
                'purchased_at'   => 'DATE NULL AFTER supplier',
                'cost'           => 'DECIMAL(10,2) NULL AFTER purchased_at',
            ] as $c => $def) {
                if (!col_exists('equipment', $c)) db()->exec("ALTER TABLE equipment ADD COLUMN $c $def");
            }
            if (!table_exists('consumables')) db()->exec("CREATE TABLE consumables (
                id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name          VARCHAR(160) NOT NULL,
                category      VARCHAR(80)  NULL,
                sku           VARCHAR(80)  NULL,
                unit          VARCHAR(20)  NOT NULL DEFAULT 'ea',
                qty_on_hand   DECIMAL(10,2) NOT NULL DEFAULT 0,
                reorder_level DECIMAL(10,2) NULL,
                unit_cost     DECIMAL(10,2) NULL,
                location      VARCHAR(80)  NULL,
                supplier      VARCHAR(120) NULL,
                notes         TEXT NULL,
                active        TINYINT(1) NOT NULL DEFAULT 1,
                created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_cons_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            if (!table_exists('consumable_moves')) db()->exec("CREATE TABLE consumable_moves (
                id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                consumable_id INT UNSIGNED NOT NULL,
                qty_change    DECIMAL(10,2) NOT NULL,
                reason        ENUM('received','used','adjust') NOT NULL,
                site_id       INT UNSIGNED NULL,
                ticket_id     INT UNSIGNED NULL,
                note          VARCHAR(255) NULL,
                user_id       INT UNSIGNED NULL,
                created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_move_cons (consumable_id, created_at),
                CONSTRAINT fk_move_cons   FOREIGN KEY (consumable_id) REFERENCES consumables(id) ON DELETE CASCADE,
                CONSTRAINT fk_move_site   FOREIGN KEY (site_id)   REFERENCES sites(id)   ON DELETE SET NULL,
                CONSTRAINT fk_move_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
                CONSTRAINT fk_move_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            q("INSERT IGNORE INTO settings (k, v) VALUES ('stock_locations', 'Shop')");
        },
        // 1.6.0 — ticket response targets, password reset/invite tokens, outgoing mail log
        '1.6.0' => function (): void {
            if (!col_exists('tickets', 'first_response_at')) db()->exec('ALTER TABLE tickets ADD COLUMN first_response_at DATETIME NULL AFTER closed_at');
            // existing tickets that already moved on count as responded
            db()->exec("UPDATE tickets SET first_response_at = updated_at WHERE first_response_at IS NULL AND (status <> 'open' OR minutes_spent > 0)");
            if (!table_exists('password_resets')) db()->exec("CREATE TABLE password_resets (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL UNIQUE,
                kind ENUM('reset','invite') NOT NULL DEFAULT 'reset',
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            if (!table_exists('mail_log')) db()->exec("CREATE TABLE mail_log (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                to_addr VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                status ENUM('sent','failed') NOT NULL,
                error VARCHAR(500) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_mail_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            q("INSERT IGNORE INTO settings (k, v) VALUES ('sla_urgent','4'),('sla_high','8'),('sla_normal','24'),('sla_low','72'),
               ('mail_webhook_url','https://terralinksystems.app.n8n.cloud/webhook/terralink-crm-mail')");
        },
        // 1.7.0 — photos & files on records
        '1.7.0' => function (): void {
            if (!table_exists('files')) db()->exec("CREATE TABLE files (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                entity_type ENUM('company','site','ticket','deal','quote','equipment') NOT NULL,
                entity_id INT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                path VARCHAR(80) NOT NULL,
                mime VARCHAR(100) NOT NULL,
                size INT UNSIGNED NOT NULL DEFAULT 0,
                is_image TINYINT(1) NOT NULL DEFAULT 0,
                width INT UNSIGNED NULL,
                height INT UNSIGNED NULL,
                uploaded_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_file_entity (entity_type, entity_id),
                CONSTRAINT fk_file_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        },
        // 1.8.0 — recurring service plans, daily summary opt-out, quote email log
        '1.8.0' => function (): void {
            if (!table_exists('service_plans')) db()->exec("CREATE TABLE service_plans (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id INT UNSIGNED NOT NULL,
                site_id INT UNSIGNED NULL,
                title VARCHAR(190) NOT NULL,
                description TEXT NULL,
                every_months TINYINT UNSIGNED NOT NULL DEFAULT 3,
                next_due DATE NOT NULL,
                due_day TINYINT UNSIGNED NULL,
                lead_days SMALLINT UNSIGNED NOT NULL DEFAULT 7,
                priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
                assigned_to INT UNSIGNED NULL,
                fee DECIMAL(10,2) NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                last_ticket_id INT UNSIGNED NULL,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_plan_due (active, next_due),
                CONSTRAINT fk_plan_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                CONSTRAINT fk_plan_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL,
                CONSTRAINT fk_plan_user FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            if (!col_exists('service_plans', 'due_day')) db()->exec('ALTER TABLE service_plans ADD COLUMN due_day TINYINT UNSIGNED NULL AFTER next_due');
            if (!col_exists('tickets', 'plan_id')) db()->exec('ALTER TABLE tickets ADD COLUMN plan_id INT UNSIGNED NULL AFTER equipment_id, ADD INDEX idx_ticket_plan (plan_id)');
            if (!col_exists('users', 'daily_email')) db()->exec('ALTER TABLE users ADD COLUMN daily_email TINYINT(1) NOT NULL DEFAULT 1 AFTER active');
        },
        // 1.9.0 — price list, quote lines linked to it, stock reservations for quotes
        '1.9.0' => function (): void {
            if (!table_exists('catalog_items')) db()->exec("CREATE TABLE catalog_items (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                sku VARCHAR(60) NULL,
                name VARCHAR(190) NOT NULL,
                description VARCHAR(255) NULL,
                kind ENUM('labour','hardware','software','travel','other') NOT NULL DEFAULT 'hardware',
                unit VARCHAR(20) NULL,
                price DECIMAL(10,2) NOT NULL DEFAULT 0,
                cost DECIMAL(10,2) NULL,
                pst_applies TINYINT(1) NOT NULL DEFAULT 0,
                stock_type ENUM('none','equipment','consumable') NOT NULL DEFAULT 'none',
                eq_category VARCHAR(80) NULL,
                eq_make VARCHAR(80) NULL,
                eq_model VARCHAR(120) NULL,
                consumable_id INT UNSIGNED NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_cat_name (active, name),
                CONSTRAINT fk_cat_consumable FOREIGN KEY (consumable_id) REFERENCES consumables(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            if (!col_exists('quote_items', 'catalog_id')) db()->exec('ALTER TABLE quote_items ADD COLUMN catalog_id INT UNSIGNED NULL AFTER quote_id, ADD INDEX idx_qi_catalog (catalog_id)');
            if (!col_exists('equipment', 'reserved_quote_id')) db()->exec('ALTER TABLE equipment ADD COLUMN reserved_quote_id INT UNSIGNED NULL AFTER status, ADD INDEX idx_eq_reserved (reserved_quote_id),
                ADD CONSTRAINT fk_eq_reserved FOREIGN KEY (reserved_quote_id) REFERENCES quotes(id) ON DELETE SET NULL');
            if (!table_exists('consumable_reservations')) db()->exec("CREATE TABLE consumable_reservations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                quote_id INT UNSIGNED NOT NULL,
                consumable_id INT UNSIGNED NOT NULL,
                qty DECIMAL(10,2) NOT NULL,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_res (quote_id, consumable_id),
                CONSTRAINT fk_res_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
                CONSTRAINT fk_res_cons FOREIGN KEY (consumable_id) REFERENCES consumables(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        },
        // 1.11.0 — service plan templates (seeded with TerraLink's standard plans)
        '1.11.0' => function (): void {
            if (table_exists('plan_templates')) return;
            db()->exec("CREATE TABLE plan_templates (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                title VARCHAR(190) NOT NULL,
                description TEXT NULL,
                every_months TINYINT UNSIGNED NOT NULL DEFAULT 3,
                lead_days SMALLINT UNSIGNED NOT NULL DEFAULT 7,
                priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
                fee DECIMAL(10,2) NOT NULL DEFAULT 0,
                fee_note VARCHAR(190) NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            foreach (plan_template_seed() as $t) {
                q('INSERT INTO plan_templates (name, title, description, every_months, lead_days, priority, fee, fee_note) VALUES (?,?,?,?,?,?,?,?)', $t);
            }
        },
        // 1.12.0 — charges on tickets (labour, travel, parts from stock), billing status
        '1.12.0' => function (): void {
            if (!table_exists('ticket_charges')) db()->exec("CREATE TABLE ticket_charges (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ticket_id INT UNSIGNED NOT NULL,
                catalog_id INT UNSIGNED NULL,
                kind ENUM('labour','hardware','software','travel','other') NOT NULL DEFAULT 'labour',
                description VARCHAR(255) NOT NULL,
                qty DECIMAL(10,2) NOT NULL DEFAULT 1,
                unit VARCHAR(20) NULL,
                unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
                pst_applies TINYINT(1) NOT NULL DEFAULT 0,
                minutes INT UNSIGNED NULL,
                consumable_id INT UNSIGNED NULL,
                equipment_ids VARCHAR(255) NULL,
                invoice_id INT UNSIGNED NULL,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tc_ticket (ticket_id),
                INDEX idx_tc_invoice (invoice_id),
                CONSTRAINT fk_tc_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
                CONSTRAINT fk_tc_catalog FOREIGN KEY (catalog_id) REFERENCES catalog_items(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            if (!col_exists('tickets', 'billing')) db()->exec("ALTER TABLE tickets ADD COLUMN billing ENUM('billable','no_charge') NOT NULL DEFAULT 'billable' AFTER minutes_spent");
            if (!col_exists('tickets', 'minutes_billed')) db()->exec('ALTER TABLE tickets ADD COLUMN minutes_billed INT UNSIGNED NOT NULL DEFAULT 0 AFTER billing');
            if (!col_exists('tickets', 'billed_at')) db()->exec('ALTER TABLE tickets ADD COLUMN billed_at DATETIME NULL AFTER minutes_billed');
            if (!col_exists('tickets', 'invoice_id')) db()->exec('ALTER TABLE tickets ADD COLUMN invoice_id INT UNSIGNED NULL AFTER billed_at, ADD INDEX idx_ticket_billing (billing, billed_at)');
            // Visits opened by a service plan are covered by the plan fee
            db()->exec("UPDATE tickets SET billing = 'no_charge' WHERE plan_id IS NOT NULL");
        },
        // 1.13.0 — invoices (month-end drafts → approve → email), company payment terms
        '1.13.0' => function (): void {
            if (!table_exists('invoices')) db()->exec("CREATE TABLE invoices (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                number VARCHAR(30) NULL,
                company_id INT UNSIGNED NOT NULL,
                status ENUM('draft','approved','sent','paid','void') NOT NULL DEFAULT 'draft',
                period VARCHAR(7) NULL,
                issued_on DATE NULL,
                due_on DATE NULL,
                terms_days SMALLINT UNSIGNED NOT NULL DEFAULT 15,
                bill_to_email VARCHAR(190) NULL,
                gst_rate DECIMAL(5,3) NOT NULL DEFAULT 5,
                pst_rate DECIMAL(5,3) NOT NULL DEFAULT 7,
                subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
                gst DECIMAL(12,2) NOT NULL DEFAULT 0,
                pst DECIMAL(12,2) NOT NULL DEFAULT 0,
                total DECIMAL(12,2) NOT NULL DEFAULT 0,
                notes TEXT NULL,
                created_by INT UNSIGNED NULL,
                approved_by INT UNSIGNED NULL,
                approved_at DATETIME NULL,
                sent_at DATETIME NULL,
                sent_to VARCHAR(255) NULL,
                paid_on DATE NULL,
                paid_method VARCHAR(40) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_inv_number (number),
                INDEX idx_inv_status (status, due_on),
                INDEX idx_inv_company (company_id),
                CONSTRAINT fk_inv_company FOREIGN KEY (company_id) REFERENCES companies(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            if (!table_exists('invoice_items')) db()->exec("CREATE TABLE invoice_items (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                invoice_id INT UNSIGNED NOT NULL,
                position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                kind ENUM('labour','hardware','software','travel','other') NOT NULL DEFAULT 'other',
                description VARCHAR(255) NOT NULL,
                qty DECIMAL(10,2) NOT NULL DEFAULT 1,
                unit VARCHAR(20) NULL,
                unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
                pst_applies TINYINT(1) NOT NULL DEFAULT 0,
                ticket_id INT UNSIGNED NULL,
                charge_id INT UNSIGNED NULL,
                plan_id INT UNSIGNED NULL,
                plan_period VARCHAR(7) NULL,
                auto_added TINYINT(1) NOT NULL DEFAULT 0,
                INDEX idx_ii_invoice (invoice_id, position),
                INDEX idx_ii_plan (plan_id, plan_period),
                INDEX idx_ii_ticket (ticket_id),
                CONSTRAINT fk_ii_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            if (!col_exists('companies', 'payment_terms_days')) db()->exec('ALTER TABLE companies ADD COLUMN payment_terms_days SMALLINT UNSIGNED NULL AFTER province');
            if (!col_exists('companies', 'billing_email')) db()->exec('ALTER TABLE companies ADD COLUMN billing_email VARCHAR(190) NULL AFTER payment_terms_days');
            db()->exec("ALTER TABLE activities MODIFY entity_type ENUM('company','contact','deal','site','ticket','quote','invoice') NOT NULL");
            db()->exec("INSERT IGNORE INTO counters (name, value) VALUES ('invoice', 0)");
            // Defaults. The first automatic run is on the 1st of next month, not right after this update.
            $first = date('Y-m');
            foreach (['payment_email' => 'payment@terralinksystems.com', 'invoice_terms_days' => '15', 'invoice_last_month' => $first] as $k => $v) {
                q('INSERT IGNORE INTO settings (k, v) VALUES (?, ?)', [$k, $v]);
            }
        },
        // 1.14.0 — client portal: company logins with per-area permissions, client-visible ticket messages and files
        '1.14.0' => function (): void {
            if (!table_exists('portal_users')) db()->exec("CREATE TABLE portal_users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id INT UNSIGNED NOT NULL,
                contact_id INT UNSIGNED NULL,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(190) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                perm_tickets TINYINT(1) NOT NULL DEFAULT 1,
                perm_tickets_all TINYINT(1) NOT NULL DEFAULT 0,
                perm_sites TINYINT(1) NOT NULL DEFAULT 0,
                perm_plans TINYINT(1) NOT NULL DEFAULT 0,
                perm_quotes TINYINT(1) NOT NULL DEFAULT 0,
                perm_invoices TINYINT(1) NOT NULL DEFAULT 0,
                is_admin TINYINT(1) NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                last_login_at DATETIME NULL,
                invited_at DATETIME NULL,
                created_by_user INT UNSIGNED NULL,
                created_by_portal INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_portal_email (email),
                INDEX idx_portal_company (company_id),
                CONSTRAINT fk_pu_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                CONSTRAINT fk_pu_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            if (!table_exists('portal_resets')) db()->exec("CREATE TABLE portal_resets (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                portal_user_id INT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL UNIQUE,
                kind ENUM('reset','invite') NOT NULL DEFAULT 'reset',
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_pr_user FOREIGN KEY (portal_user_id) REFERENCES portal_users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            if (!col_exists('activities', 'is_public')) db()->exec('ALTER TABLE activities ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 0 AFTER body');
            if (!col_exists('activities', 'portal_user_id')) db()->exec('ALTER TABLE activities ADD COLUMN portal_user_id INT UNSIGNED NULL AFTER user_id');
            if (!col_exists('files', 'is_public')) db()->exec('ALTER TABLE files ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 0 AFTER height');
            if (!col_exists('files', 'portal_user_id')) db()->exec('ALTER TABLE files ADD COLUMN portal_user_id INT UNSIGNED NULL AFTER uploaded_by');
            if (!col_exists('tickets', 'portal_user_id')) db()->exec('ALTER TABLE tickets ADD COLUMN portal_user_id INT UNSIGNED NULL AFTER created_by, ADD INDEX idx_ticket_portal (portal_user_id)');
            if (!col_exists('quotes', 'client_decision_at')) db()->exec('ALTER TABLE quotes ADD COLUMN client_decision_at DATETIME NULL AFTER notes');
            if (!col_exists('quotes', 'client_decision_by')) db()->exec('ALTER TABLE quotes ADD COLUMN client_decision_by VARCHAR(190) NULL AFTER client_decision_at');
        },
        // 1.15.0 — audit: client-waiting flag, invoice reminders, site-restricted portal logins, error log
        '1.15.0' => function (): void {
            if (!col_exists('tickets', 'client_waiting_at')) db()->exec('ALTER TABLE tickets ADD COLUMN client_waiting_at DATETIME NULL AFTER portal_user_id, ADD INDEX idx_ticket_waiting (client_waiting_at)');
            // existing portal tickets where the client spoke last
            db()->exec("UPDATE tickets t SET client_waiting_at = (SELECT MAX(a.created_at) FROM activities a WHERE a.entity_type = 'ticket' AND a.entity_id = t.id AND a.portal_user_id IS NOT NULL)
                        WHERE t.status <> 'closed' AND t.client_waiting_at IS NULL AND t.portal_user_id IS NOT NULL AND t.first_response_at IS NULL");
            if (!col_exists('invoices', 'reminder_count')) db()->exec('ALTER TABLE invoices ADD COLUMN reminder_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER sent_to, ADD COLUMN reminded_at DATETIME NULL AFTER reminder_count');
            if (!col_exists('companies', 'invoice_reminders')) db()->exec('ALTER TABLE companies ADD COLUMN invoice_reminders TINYINT(1) NOT NULL DEFAULT 1 AFTER billing_email');
            if (!table_exists('portal_user_sites')) db()->exec("CREATE TABLE portal_user_sites (
                portal_user_id INT UNSIGNED NOT NULL,
                site_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (portal_user_id, site_id),
                CONSTRAINT fk_pus_user FOREIGN KEY (portal_user_id) REFERENCES portal_users(id) ON DELETE CASCADE,
                CONSTRAINT fk_pus_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            if (!table_exists('app_errors')) db()->exec("CREATE TABLE app_errors (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                fingerprint CHAR(40) NOT NULL,
                message VARCHAR(1000) NOT NULL,
                file VARCHAR(255) NULL,
                line INT UNSIGNED NULL,
                trace TEXT NULL,
                method VARCHAR(10) NULL,
                url VARCHAR(500) NULL,
                who VARCHAR(190) NULL,
                hits INT UNSIGNED NOT NULL DEFAULT 1,
                first_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_err_fp (fingerprint),
                INDEX idx_err_last (last_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            q("INSERT IGNORE INTO settings (k, v) VALUES ('invoice_reminders', '1'), ('portal_assignee', '')");
        },
        // 1.16.0 — two-factor sign-in (TOTP) for staff
        '1.16.0' => function (): void {
            foreach ([
                'totp_secret'      => 'VARCHAR(255) NULL AFTER password_hash',
                'totp_enabled_at'  => 'DATETIME NULL AFTER totp_secret',
                'totp_last_step'   => 'BIGINT UNSIGNED NULL AFTER totp_enabled_at',
                'totp_recovery'    => 'TEXT NULL AFTER totp_last_step',
                'totp_required'    => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_recovery',
            ] as $c => $def) if (!col_exists('users', $c)) db()->exec("ALTER TABLE users ADD COLUMN $c $def");
            if (!table_exists('trusted_devices')) db()->exec("CREATE TABLE trusted_devices (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL UNIQUE,
                label VARCHAR(190) NULL,
                expires_at DATETIME NOT NULL,
                last_used_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_td_user (user_id),
                CONSTRAINT fk_td_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            q("INSERT IGNORE INTO settings (k, v) VALUES ('twofa_policy', 'optional')");
        },
    ];
}

/** TerraLink's standard plans (Nov 2026 price list). Fees are per visit: monthly price × months between visits. */
function plan_template_seed(): array {
    return [
        ['Network Care check', 'Quarterly network check', "- Update UniFi firmware (gateway, switches, APs)\n- Download UniFi config backup and store it\n- Check AP and switch health, client counts, errors\n- Speed test and note the result\n- Camera NVR: storage days, all cameras recording\n- UPS: battery test and runtime\n- Note anything that needs a quote", 3, 7, 'normal', 297.00, 'Network Care $99/site/mo × 3 months'],
        ['Managed IT site visit', 'Monthly on-site IT visit', "- Walk-through with the office contact: open issues\n- Patch status on all computers\n- Review backup jobs (any failures this month?)\n- Check printers and shared devices\n- Antivirus/EDR alerts reviewed\n- Note hardware due for replacement", 1, 5, 'normal', 0.00, 'Set to users × per-user rate (Standard $105, Complete $150)'],
        ['Managed IT remote review', 'Quarterly remote IT review', "- Patch report for all computers\n- Antivirus/EDR alerts and quarantines\n- Disk health and free space\n- Licence check (Microsoft 365 / Google Workspace)\n- Send the client a short summary", 3, 7, 'normal', 0.00, 'Set to users × $65 × 3 months (Essentials)'],
        ['IT planning meeting', 'Quarterly IT planning meeting', "- Book a meeting with the owner\n- Review: hardware coming due, risks, incidents this quarter\n- Budget for next quarter\n- Ideas: automation / AI that could save time\n- Send notes and any quotes afterwards", 3, 14, 'low', 0.00, 'Included in Managed IT Complete — leave at $0'],
        ['Backup restore test', 'Yearly backup restore test', "- Restore a sample of real files to a test folder\n- Restore one mailbox item (M365 / Workspace)\n- Check the oldest restore point available\n- Write down what was restored, how long it took, any problems\n- Tell the client the result", 12, 14, 'normal', 0.00, 'Included in managed IT — leave at $0'],
        ['Starlink seasonal check', 'Starlink seasonal check (spring / fall)', "- Mount: tight, no movement, snow-shedding position clear\n- Cable entries sealed, cable not chafed\n- Obstruction map in the Starlink app\n- Failover test: unplug main line, confirm switch-over and back\n- Firmware up to date\n- Power draw OK on generator/solar sites\nSet the next visit date in April or October.", 6, 14, 'normal', 270.00, 'Starlink monitoring $45/site/mo × 6 months'],
        ['Automation health review', 'Monthly automation health review', "- Check n8n executions: any failed runs this month?\n- API keys / tokens about to expire\n- Workflows the client asked to change (4 hrs/month included)\n- Confirm monitoring alerts still reach us\n- Send a short monthly summary", 1, 3, 'normal', 1250.00, 'Automation Package $1,250/mo'],
        ['Private AI Server maintenance', 'Monthly Private AI Server maintenance', "- OS and security patches\n- Update models / runtime if a new version is approved\n- Disk space, GPU temperatures, fans\n- Backups of configuration and data\n- Test one prompt end to end", 1, 3, 'normal', 350.00, 'Private AI Server support $350/mo'],
    ];
}

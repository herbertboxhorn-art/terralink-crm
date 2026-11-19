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
    ];
}

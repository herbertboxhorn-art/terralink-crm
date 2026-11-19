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
    ];
}

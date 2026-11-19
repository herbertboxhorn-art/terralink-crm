-- TerraLink CRM schema v1.3.0
-- MySQL 8.0+ / utf8mb4. Safe to run on an empty database.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NOT NULL UNIQUE,
  phone         VARCHAR(40)  NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','sales','tech') NOT NULL DEFAULT 'tech',
  active        TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS companies (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(190) NOT NULL,
  industry   ENUM('Logging & Forestry','Construction & Excavation','Service Companies','Municipalities',
                  'Law Firms','First Nations & Band Offices','Clinics & Health Offices','Camps & Lodges','Other')
             NOT NULL DEFAULT 'Other',
  phone      VARCHAR(40)  NULL,
  email      VARCHAR(190) NULL,
  website    VARCHAR(190) NULL,
  address    VARCHAR(255) NULL,
  town       VARCHAR(120) NULL,
  province   VARCHAR(40)  NOT NULL DEFAULT 'BC',
  notes      TEXT NULL,
  owner_id   INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_company_name (name),
  CONSTRAINT fk_company_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contacts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NULL,
  first_name VARCHAR(80)  NOT NULL,
  last_name  VARCHAR(80)  NULL,
  title      VARCHAR(120) NULL,
  phone      VARCHAR(40)  NULL,
  mobile     VARCHAR(40)  NULL,
  email      VARCHAR(190) NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  notes      TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_contact_name (last_name, first_name),
  INDEX idx_contact_email (email),
  CONSTRAINT fk_contact_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS deals (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id     INT UNSIGNED NULL,
  contact_id     INT UNSIGNED NULL,
  title          VARCHAR(190) NOT NULL,
  service        ENUM('AI Automation','Business IT','Business Phones','Hardware & Networks','Starlink & Remote Sites','Other')
                 NOT NULL DEFAULT 'Other',
  stage          ENUM('new','audit_booked','quoted','won','lost') NOT NULL DEFAULT 'new',
  value          DECIMAL(12,2) NOT NULL DEFAULT 0,
  source         ENUM('website','referral','cold','repeat','event','other') NOT NULL DEFAULT 'other',
  owner_id       INT UNSIGNED NULL,
  expected_close DATE NULL,
  lost_reason    VARCHAR(255) NULL,
  message        TEXT NULL,
  stage_changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_deal_stage (stage),
  CONSTRAINT fk_deal_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
  CONSTRAINT fk_deal_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
  CONSTRAINT fk_deal_owner   FOREIGN KEY (owner_id)   REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- Timeline entries on any record (company, contact, deal, site, ticket, quote)
CREATE TABLE IF NOT EXISTS activities (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_type ENUM('company','contact','deal','site','ticket','quote') NOT NULL,
  entity_id   INT UNSIGNED NOT NULL,
  company_id  INT UNSIGNED NULL,
  user_id     INT UNSIGNED NULL,
  type        ENUM('note','call','email','visit','stage','system') NOT NULL DEFAULT 'note',
  body        TEXT NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_act_entity (entity_type, entity_id),
  INDEX idx_act_company (company_id, created_at),
  CONSTRAINT fk_act_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tasks (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(190) NOT NULL,
  notes       TEXT NULL,
  due_date    DATE NULL,
  assigned_to INT UNSIGNED NULL,
  created_by  INT UNSIGNED NULL,
  company_id  INT UNSIGNED NULL,
  deal_id     INT UNSIGNED NULL,
  ticket_id   INT UNSIGNED NULL,
  done_at     DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_task_open (done_at, due_date),
  CONSTRAINT fk_task_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_task_creator  FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_task_company  FOREIGN KEY (company_id)  REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_task_deal     FOREIGN KEY (deal_id)     REFERENCES deals(id) ON DELETE CASCADE,
  CONSTRAINT fk_task_ticket   FOREIGN KEY (ticket_id)   REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sites (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id   INT UNSIGNED NOT NULL,
  name         VARCHAR(190) NOT NULL,
  address      VARCHAR(255) NULL,
  town         VARCHAR(120) NULL,
  latitude     DECIMAL(9,6) NULL,
  longitude    DECIMAL(9,6) NULL,
  connectivity ENUM('Starlink','Fibre','Cable','DSL','Cellular','Satellite (other)','None','Other') NULL,
  access_notes TEXT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_site_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS equipment (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id        INT UNSIGNED NOT NULL,
  category       ENUM('UniFi','Starlink kit','Phone line','VoIP handset','Server','Workstation','Printer','UPS / Power','Other')
                 NOT NULL DEFAULT 'Other',
  make           VARCHAR(80)  NULL,
  model          VARCHAR(120) NULL,
  serial         VARCHAR(120) NULL,
  mac            VARCHAR(40)  NULL,
  ip_address     VARCHAR(64)  NULL,
  phone_number   VARCHAR(40)  NULL,
  installed_at   DATE NULL,
  warranty_until DATE NULL,
  status         ENUM('active','spare','faulty','retired') NOT NULL DEFAULT 'active',
  notes          TEXT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_equip_serial (serial),
  INDEX idx_equip_warranty (warranty_until),
  CONSTRAINT fk_equip_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quotes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  number      VARCHAR(20) NOT NULL UNIQUE,
  company_id  INT UNSIGNED NOT NULL,
  contact_id  INT UNSIGNED NULL,
  deal_id     INT UNSIGNED NULL,
  title       VARCHAR(190) NOT NULL,
  status      ENUM('draft','sent','accepted','declined','expired') NOT NULL DEFAULT 'draft',
  issued_on   DATE NOT NULL,
  valid_until DATE NULL,
  gst_rate    DECIMAL(5,3) NOT NULL DEFAULT 5.000,
  pst_rate    DECIMAL(5,3) NOT NULL DEFAULT 7.000,
  notes       TEXT NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_quote_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_quote_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
  CONSTRAINT fk_quote_deal    FOREIGN KEY (deal_id)    REFERENCES deals(id) ON DELETE SET NULL,
  CONSTRAINT fk_quote_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quote_items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quote_id    INT UNSIGNED NOT NULL,
  position    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  kind        ENUM('labour','hardware','software','travel','other') NOT NULL DEFAULT 'labour',
  description VARCHAR(255) NOT NULL,
  qty         DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit        VARCHAR(20) NULL,
  unit_price  DECIMAL(12,2) NOT NULL DEFAULT 0,
  pst_applies TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_qi_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tickets (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  number        VARCHAR(20) NOT NULL UNIQUE,
  company_id    INT UNSIGNED NOT NULL,
  site_id       INT UNSIGNED NULL,
  contact_id    INT UNSIGNED NULL,
  equipment_id  INT UNSIGNED NULL,
  subject       VARCHAR(190) NOT NULL,
  description   TEXT NULL,
  priority      ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  status        ENUM('open','in_progress','waiting','closed') NOT NULL DEFAULT 'open',
  assigned_to   INT UNSIGNED NULL,
  minutes_spent INT UNSIGNED NOT NULL DEFAULT 0,
  closed_at     DATETIME NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ticket_status (status, priority),
  CONSTRAINT fk_ticket_company  FOREIGN KEY (company_id)   REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_ticket_site     FOREIGN KEY (site_id)      REFERENCES sites(id) ON DELETE SET NULL,
  CONSTRAINT fk_ticket_contact  FOREIGN KEY (contact_id)   REFERENCES contacts(id) ON DELETE SET NULL,
  CONSTRAINT fk_ticket_equip    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE SET NULL,
  CONSTRAINT fk_ticket_assignee FOREIGN KEY (assigned_to)  REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_ticket_creator  FOREIGN KEY (created_by)   REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(64) PRIMARY KEY,
  v TEXT NULL
) ENGINE=InnoDB;

-- Simple counter table for Q-/T- numbering
CREATE TABLE IF NOT EXISTS counters (
  name  VARCHAR(32) PRIMARY KEY,
  value INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- Login throttling
CREATE TABLE IF NOT EXISTS login_attempts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip         VARCHAR(64) NOT NULL,
  email      VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_ip (ip, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS schema_version (
  version    VARCHAR(20) PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

INSERT IGNORE INTO settings (k, v) VALUES
  ('company_name', 'TerraLink Systems'),
  ('company_phone', '778-648-2400'),
  ('company_email', 'info@terralinksystems.com'),
  ('company_address', 'Houston, BC'),
  ('gst_number', ''),
  ('travel_rate_per_km', '0.00'),
  ('quote_valid_days', '30'),
  ('quote_terms', 'Prices in CAD. Quote valid for the period shown. Hardware subject to supplier availability.');

INSERT IGNORE INTO counters (name, value) VALUES ('quote', 0), ('ticket', 0);
INSERT IGNORE INTO schema_version (version) VALUES ('1.3.0');

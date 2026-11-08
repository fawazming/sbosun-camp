-- SB Osun Camp Portal — reference MySQL schema.
-- The PHP installer (install.php) creates the same schema programmatically.
-- Use this file only if you prefer to import it manually:
--   mysql -u user -p sbosun_camp < database/schema.mysql.sql

CREATE TABLE IF NOT EXISTS settings (
  skey VARCHAR(191) NOT NULL PRIMARY KEY,
  svalue TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS classes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL,
  eligible_categories TEXT NOT NULL,
  created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS houses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL,
  gender VARCHAR(16) NOT NULL,
  capacity INT NOT NULL DEFAULT 40,
  status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS participants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  registration_id VARCHAR(32) NOT NULL UNIQUE,
  token VARCHAR(64) NOT NULL UNIQUE,
  surname VARCHAR(191) NOT NULL,
  other_name VARCHAR(191) NOT NULL,
  gender VARCHAR(16) NOT NULL,
  age_group VARCHAR(32) NOT NULL,
  category VARCHAR(64) NOT NULL,
  class_id INT NULL,
  zone VARCHAR(64) NOT NULL,
  phone VARCHAR(32) NOT NULL,
  email VARCHAR(191) NOT NULL,
  health_challenges TEXT NULL,
  allergies TEXT NULL,
  nok_name VARCHAR(191) NULL,
  nok_phone VARCHAR(32) NULL,
  is_minor TINYINT NOT NULL DEFAULT 0,
  guardian_name VARCHAR(191) NULL,
  guardian_contact VARCHAR(191) NULL,
  payment_status VARCHAR(32) NOT NULL DEFAULT 'NOT_PAID',
  attendance_status VARCHAR(32) NOT NULL DEFAULT 'NOT_ARRIVED',
  house_id INT NULL,
  amount_owed INT NOT NULL DEFAULT 0,
  amount_paid INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  INDEX idx_participants_reg (registration_id),
  INDEX idx_participants_email (email),
  INDEX idx_participants_zone (zone),
  INDEX idx_participants_payment (payment_status),
  INDEX idx_participants_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  participant_id INT NOT NULL,
  reference VARCHAR(191) NOT NULL UNIQUE,
  provider_reference VARCHAR(191) NULL,
  amount INT NOT NULL,
  payment_type VARCHAR(16) NOT NULL,
  installment_number INT NULL,
  plan_rate VARCHAR(16) NOT NULL,
  provider VARCHAR(64) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'PENDING',
  virtual_account_number VARCHAR(32) NULL,
  account_name VARCHAR(191) NULL,
  bank_name VARCHAR(191) NULL,
  checkout_url VARCHAR(512) NULL,
  paid_at DATETIME NULL,
  verified_at DATETIME NULL,
  failure_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  INDEX idx_payments_participant (participant_id),
  INDEX idx_payments_status (status),
  INDEX idx_payments_account (virtual_account_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payment_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_reference VARCHAR(191) NOT NULL UNIQUE,
  payment_id INT NULL,
  amount INT NOT NULL DEFAULT 0,
  outcome VARCHAR(32) NOT NULL,
  raw TEXT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attendance (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  participant_id INT NOT NULL UNIQUE,
  status VARCHAR(16) NOT NULL DEFAULT 'NOT_ARRIVED',
  check_in_time DATETIME NULL,
  checked_in_by VARCHAR(191) NULL,
  corrected TINYINT NOT NULL DEFAULT 0,
  correction_reason VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL,
  email VARCHAR(191) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(32) NOT NULL DEFAULT 'VIEWER',
  zone VARCHAR(64) NULL,
  is_protected TINYINT NOT NULL DEFAULT 0,
  status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_id INT NULL,
  actor_name VARCHAR(191) NULL,
  action VARCHAR(64) NOT NULL,
  target_type VARCHAR(64) NULL,
  target_id VARCHAR(64) NULL,
  before_json TEXT NULL,
  after_json TEXT NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS newsletter (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(191) NOT NULL UNIQUE,
  name VARCHAR(191) NULL,
  source VARCHAR(64) NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'subscribed',
  token VARCHAR(64) NOT NULL UNIQUE,
  subscribed_at DATETIME NULL,
  unsubscribed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_newsletter_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(191) NULL,
  ip VARCHAR(45) NULL,
  success TINYINT NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL,
  INDEX idx_login_attempt (email, ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS throttle (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  action VARCHAR(32) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_throttle (action, ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed core settings (safe to re-run).
INSERT IGNORE INTO settings (skey, svalue) VALUES
('name','SB HIC 2026'),('year','2026'),('camp_start','2026-12-24'),('camp_end','2026-12-28'),
('venue_name','Oduduwa College'),('venue_address','Ile-Ife, Osun State'),
('early_bird_fee','8500'),('flat_fee','12000'),('early_bird_deadline','2026-11-30 23:59:59'),
('default_house_capacity','40'),('registration_open','1'),('maintenance_mode','0'),
('support_email','support@sbosun.org'),('support_phone','09053444178'),('payment_mode','mock');

INSERT IGNORE INTO classes (name, eligible_categories, created_at) VALUES
('Lower Class', '["JSS 1-3"]', NOW()),
('Upper Class', '["SS1- SSS 3"]', NOW()),
('Dawah Class', '["School Leaver","Undergraduate","Graduate","Others"]', NOW());

INSERT IGNORE INTO houses (name, gender, capacity, status, created_at) VALUES
('Ali House','MALE',40,'ACTIVE',NOW()),
('Abu Bakar House','MALE',40,'ACTIVE',NOW()),
('Khadijah House','FEMALE',40,'ACTIVE',NOW()),
('Aishah House','FEMALE',40,'ACTIVE',NOW());

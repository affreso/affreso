-- Affreso 4.0: pre-check-in, online forms, pledges & campaigns, direct label printing.

CREATE TABLE IF NOT EXISTS {p}prechecks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id INT UNSIGNED NOT NULL,
  child_id INT UNSIGNED NOT NULL,
  household_id INT UNSIGNED NULL,
  class_id INT UNSIGNED NULL,
  arrival_code CHAR(6) NOT NULL,
  dropped_off_by VARCHAR(160) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_precheck (session_id, child_id),
  KEY idx_precheck_code (session_id, arrival_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}forms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(160) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  intro TEXT NULL,
  fields MEDIUMTEXT NULL,
  audience ENUM('public','members') NOT NULL DEFAULT 'public',
  status ENUM('draft','open','closed') NOT NULL DEFAULT 'draft',
  opens_at DATETIME NULL,
  closes_at DATETIME NULL,
  capacity INT UNSIGNED NULL,
  one_per_person TINYINT(1) NOT NULL DEFAULT 0,
  price_cents INT UNSIGNED NOT NULL DEFAULT 0,
  confirmation TEXT NULL,
  notify_emails VARCHAR(500) NULL,
  campus_id INT UNSIGNED NULL,
  event_id INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_form_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}form_submissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  form_id INT UNSIGNED NOT NULL,
  person_id INT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NULL,
  answers MEDIUMTEXT NULL,
  status ENUM('submitted','awaiting_payment','paid','cancelled') NOT NULL DEFAULT 'submitted',
  amount_cents INT UNSIGNED NOT NULL DEFAULT 0,
  stripe_session_id VARCHAR(120) NULL,
  ip VARCHAR(45) NULL,
  staff_notes VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sub_form (form_id, created_at),
  KEY idx_sub_stripe (stripe_session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}campaigns (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  description TEXT NULL,
  goal_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  fund_id INT UNSIGNED NOT NULL,
  starts_on DATE NOT NULL,
  ends_on DATE NOT NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('active','closed') NOT NULL DEFAULT 'active',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}pledges (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campaign_id INT UNSIGNED NOT NULL,
  person_id INT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NULL,
  total_cents BIGINT UNSIGNED NOT NULL,
  frequency ENUM('once','weekly','fortnightly','monthly','quarterly','yearly') NOT NULL DEFAULT 'once',
  anonymous TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','cancelled') NOT NULL DEFAULT 'active',
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  KEY idx_pledge_campaign (campaign_id),
  KEY idx_pledge_person (person_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE {p}kiosks ADD COLUMN printer_id VARCHAR(40) NULL;
ALTER TABLE {p}checkins ADD COLUMN prechecked TINYINT(1) NOT NULL DEFAULT 0;

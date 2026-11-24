-- Affreso 2.0 — Phase 2 modules. Applied automatically to existing installations.

CREATE TABLE IF NOT EXISTS {p}campuses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  address VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE {p}people ADD COLUMN campus_id INT UNSIGNED NULL, ADD COLUMN giving_number VARCHAR(20) NULL;
ALTER TABLE {p}groups ADD COLUMN campus_id INT UNSIGNED NULL;
ALTER TABLE {p}events ADD COLUMN campus_id INT UNSIGNED NULL;
ALTER TABLE {p}rosters ADD COLUMN campus_id INT UNSIGNED NULL;
ALTER TABLE {p}rooms ADD COLUMN campus_id INT UNSIGNED NULL;
ALTER TABLE {p}users ADD COLUMN campus_id INT UNSIGNED NULL, ADD COLUMN language VARCHAR(10) NULL;
ALTER TABLE {p}notifications ADD COLUMN pushed_at DATETIME NULL;
ALTER TABLE {p}donations ADD COLUMN recurring_id INT UNSIGNED NULL, ADD COLUMN external_ref VARCHAR(120) NULL, ADD KEY idx_don_ext (external_ref);

-- Children's check-in --------------------------------------------------------
CREATE TABLE IF NOT EXISTS {p}child_profiles (
  person_id INT UNSIGNED PRIMARY KEY,
  allergies_enc TEXT NULL,
  medical_enc TEXT NULL,
  needs_enc TEXT NULL,
  emergency_enc TEXT NULL,
  photo_permission TINYINT(1) NOT NULL DEFAULT 0,
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}authorised_collectors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  child_id INT UNSIGNED NOT NULL,
  person_id INT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  relationship VARCHAR(60) NULL,
  phone VARCHAR(40) NULL,
  not_permitted TINYINT(1) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  KEY idx_ac_child (child_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}checkin_classes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  campus_id INT UNSIGNED NULL,
  room_id INT UNSIGNED NULL,
  group_id INT UNSIGNED NULL,
  min_age INT NULL,
  max_age INT NULL,
  ratio INT NOT NULL DEFAULT 8,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}checkin_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(160) NOT NULL,
  session_date DATE NOT NULL,
  start_time TIME NULL,
  campus_id INT UNSIGNED NULL,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}checkins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id INT UNSIGNED NOT NULL,
  child_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NULL,
  security_code CHAR(4) NOT NULL,
  checked_in_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  checked_in_by INT UNSIGNED NULL,
  dropped_off_by VARCHAR(160) NULL,
  checked_out_at DATETIME NULL,
  checked_out_by INT UNSIGNED NULL,
  collected_by VARCHAR(160) NULL,
  notes VARCHAR(255) NULL,
  KEY idx_ci_session (session_id),
  KEY idx_ci_child (child_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}checkin_leaders (
  session_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NOT NULL,
  person_id INT UNSIGNED NOT NULL,
  checked_in_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  checked_out_at DATETIME NULL,
  PRIMARY KEY (session_id, class_id, person_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Incident reporting -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS {p}incidents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  incident_type ENUM('child_safety','whs','behaviour','property','privacy','other') NOT NULL DEFAULT 'other',
  occurred_at DATETIME NOT NULL,
  location VARCHAR(200) NULL,
  campus_id INT UNSIGNED NULL,
  summary VARCHAR(255) NOT NULL,
  details_enc MEDIUMTEXT NULL,
  people_enc TEXT NULL,
  injury_enc TEXT NULL,
  action_enc TEXT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
  child_involved TINYINT(1) NOT NULL DEFAULT 0,
  reportable_conduct TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('reported','reviewing','actioned','closed') NOT NULL DEFAULT 'reported',
  reported_by INT UNSIGNED NULL,
  assigned_to INT UNSIGNED NULL,
  police_notified_on DATE NULL,
  child_protection_notified_on DATE NULL,
  regulator_notified_on DATE NULL,
  insurer_notified_on DATE NULL,
  outcome_enc TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}incident_updates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  incident_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  note_enc TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_iu (incident_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Prayer -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {p}prayer_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id INT UNSIGNED NULL,
  name VARCHAR(160) NULL,
  email VARCHAR(190) NULL,
  request_enc TEXT NOT NULL,
  visibility ENUM('church','team','pastors') NOT NULL DEFAULT 'team',
  status ENUM('open','answered','archived') NOT NULL DEFAULT 'open',
  prayed_count INT NOT NULL DEFAULT 0,
  answer_enc TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}prayer_log (
  request_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  prayed_on DATE NOT NULL,
  PRIMARY KEY (request_id, user_id, prayed_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resource library, policies & e-signatures ------------------------------------
CREATE TABLE IF NOT EXISTS {p}documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  category VARCHAR(80) NULL,
  description TEXT NULL,
  visibility ENUM('everyone','members','volunteers','staff','groups') NOT NULL DEFAULT 'members',
  group_ids VARCHAR(255) NULL,
  requires_ack TINYINT(1) NOT NULL DEFAULT 0,
  ack_renew_months INT NULL,
  current_version INT NOT NULL DEFAULT 1,
  archived TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}document_versions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id INT UNSIGNED NOT NULL,
  version INT NOT NULL,
  file_id INT UNSIGNED NULL,
  body MEDIUMTEXT NULL,
  content_hash CHAR(64) NOT NULL,
  notes VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_docver (document_id, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}acknowledgements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id INT UNSIGNED NOT NULL,
  version INT NOT NULL,
  person_id INT UNSIGNED NOT NULL,
  signed_name VARCHAR(160) NOT NULL,
  signature_png MEDIUMTEXT NULL,
  content_hash CHAR(64) NOT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  signed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ack (document_id, person_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}position_documents (
  position_id INT UNSIGNED NOT NULL,
  document_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (position_id, document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Service planning -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {p}songs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  author VARCHAR(200) NULL,
  ccli_number VARCHAR(20) NULL,
  default_key VARCHAR(10) NULL,
  tempo VARCHAR(20) NULL,
  link_url VARCHAR(500) NULL,
  notes TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}service_plans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  service_date DATE NOT NULL,
  start_time TIME NULL,
  campus_id INT UNSIGNED NULL,
  event_id INT UNSIGNED NULL,
  theme VARCHAR(200) NULL,
  status ENUM('draft','final') NOT NULL DEFAULT 'draft',
  notes TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sp_date (service_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}plan_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_id INT UNSIGNED NOT NULL,
  sort INT NOT NULL DEFAULT 0,
  item_type ENUM('song','reading','sermon','prayer','announcement','communion','offering','video','other') NOT NULL DEFAULT 'other',
  title VARCHAR(200) NOT NULL,
  song_id INT UNSIGNED NULL,
  song_key VARCHAR(10) NULL,
  duration_minutes INT NOT NULL DEFAULT 0,
  person_id INT UNSIGNED NULL,
  person_text VARCHAR(120) NULL,
  notes TEXT NULL,
  KEY idx_pi_plan (plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reimbursements ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {p}reimbursements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  amount_cents INT UNSIGNED NOT NULL,
  expense_date DATE NOT NULL,
  category VARCHAR(80) NULL,
  group_id INT UNSIGNED NULL,
  receipt_file_id INT UNSIGNED NULL,
  payee_name VARCHAR(160) NULL,
  bsb_enc TEXT NULL,
  account_enc TEXT NULL,
  status ENUM('submitted','approved','rejected','paid') NOT NULL DEFAULT 'submitted',
  approver_id INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  approver_note VARCHAR(255) NULL,
  paid_by INT UNSIGNED NULL,
  paid_at DATETIME NULL,
  payment_ref VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_reimb_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Recurring giving -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {p}recurring_gifts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id INT UNSIGNED NULL,
  donor_name VARCHAR(160) NULL,
  donor_email VARCHAR(190) NULL,
  amount_cents INT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'AUD',
  frequency ENUM('week','fortnight','month') NOT NULL DEFAULT 'month',
  fund_id INT UNSIGNED NULL,
  stripe_session_id VARCHAR(255) NULL,
  stripe_subscription_id VARCHAR(255) NULL,
  status ENUM('pending','active','cancelled','past_due') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cancelled_at DATETIME NULL,
  KEY idx_rg_sub (stripe_subscription_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Installable app push notifications -------------------------------------------
CREATE TABLE IF NOT EXISTS {p}push_subscriptions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  endpoint VARCHAR(700) NOT NULL,
  p256dh VARCHAR(200) NULL,
  auth_key VARCHAR(100) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME NULL,
  KEY idx_push_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

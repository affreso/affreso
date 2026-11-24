-- Affreso 3.0: recurring events, attendance headcounts, check-in kiosks, calendar sync, direct imports.

CREATE TABLE IF NOT EXISTS {p}event_series (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  freq ENUM('weekly','monthly_date','monthly_weekday') NOT NULL DEFAULT 'weekly',
  interval_n TINYINT UNSIGNED NOT NULL DEFAULT 1,
  weekdays VARCHAR(20) NULL,
  until_date DATE NULL,
  max_count SMALLINT UNSIGNED NULL,
  generated_until DATE NULL,
  skip_dates TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE {p}events ADD COLUMN series_id INT UNSIGNED NULL AFTER id;
ALTER TABLE {p}events ADD COLUMN series_changed TINYINT(1) NOT NULL DEFAULT 0 AFTER series_id;
ALTER TABLE {p}events ADD KEY idx_events_series (series_id, start_at);

CREATE TABLE IF NOT EXISTS {p}attendance_counts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campus_id INT UNSIGNED NULL,
  count_date DATE NOT NULL,
  service_label VARCHAR(120) NOT NULL,
  event_id INT UNSIGNED NULL,
  adults INT UNSIGNED NOT NULL DEFAULT 0,
  youth INT UNSIGNED NOT NULL DEFAULT 0,
  children INT UNSIGNED NOT NULL DEFAULT 0,
  online INT UNSIGNED NOT NULL DEFAULT 0,
  visitors INT UNSIGNED NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  recorded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  KEY idx_att_date (count_date),
  KEY idx_att_campus (campus_id, count_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}kiosks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  campus_id INT UNSIGNED NULL,
  token_hash CHAR(64) NULL,
  pair_hash CHAR(64) NULL,
  pair_expires DATETIME NULL,
  exit_pin_hash VARCHAR(255) NULL,
  print_labels TINYINT(1) NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  paired_at DATETIME NULL,
  last_seen_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_kiosk_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE {p}checkins ADD COLUMN kiosk_id INT UNSIGNED NULL;

CREATE TABLE IF NOT EXISTS {p}calendar_accounts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  provider ENUM('google','microsoft') NOT NULL,
  account_email VARCHAR(190) NULL,
  access_token TEXT NULL,
  refresh_token TEXT NULL,
  expires_at DATETIME NULL,
  calendar_id VARCHAR(255) NOT NULL DEFAULT 'primary',
  push_enabled TINYINT(1) NOT NULL DEFAULT 1,
  busy_enabled TINYINT(1) NOT NULL DEFAULT 1,
  last_sync_at DATETIME NULL,
  last_error VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cal_user_provider (user_id, provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}calendar_links (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_id INT UNSIGNED NOT NULL,
  item_key VARCHAR(40) NOT NULL,
  remote_id VARCHAR(255) NOT NULL,
  content_hash CHAR(40) NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cal_link (account_id, item_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}calendar_busy (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_id INT UNSIGNED NOT NULL,
  person_id INT UNSIGNED NOT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  KEY idx_busy_person (person_id, start_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE {p}people ADD COLUMN import_source VARCHAR(20) NULL;
ALTER TABLE {p}people ADD COLUMN import_ref VARCHAR(64) NULL;
ALTER TABLE {p}people ADD KEY idx_people_import (import_source, import_ref);
ALTER TABLE {p}households ADD COLUMN import_source VARCHAR(20) NULL;
ALTER TABLE {p}households ADD COLUMN import_ref VARCHAR(64) NULL;
ALTER TABLE {p}households ADD KEY idx_house_import (import_source, import_ref);
ALTER TABLE {p}groups ADD COLUMN import_source VARCHAR(20) NULL;
ALTER TABLE {p}groups ADD COLUMN import_ref VARCHAR(64) NULL;
ALTER TABLE {p}groups ADD KEY idx_groups_import (import_source, import_ref);

CREATE TABLE IF NOT EXISTS {p}import_runs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source VARCHAR(20) NOT NULL,
  status ENUM('running','done','failed') NOT NULL DEFAULT 'running',
  summary TEXT NULL,
  started_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

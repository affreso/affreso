-- Affreso Church Management System — database schema (MySQL 5.7+/MariaDB 10.3+)
-- The table prefix "{p}" is replaced by the installer.

SET NAMES utf8mb4;

CREATE TABLE {p}settings (
  k VARCHAR(100) PRIMARY KEY,
  v TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}households (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  address_line VARCHAR(200) NULL,
  suburb VARCHAR(100) NULL,
  state VARCHAR(50) NULL,
  postcode VARCHAR(12) NULL,
  phone VARCHAR(40) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}people (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  household_id INT UNSIGNED NULL,
  household_role ENUM('adult','child','other') NOT NULL DEFAULT 'adult',
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  preferred_name VARCHAR(80) NULL,
  email VARCHAR(190) NULL,
  mobile VARCHAR(40) NULL,
  birthdate DATE NULL,
  gender VARCHAR(20) NULL,
  address_line VARCHAR(200) NULL,
  suburb VARCHAR(100) NULL,
  state VARCHAR(50) NULL,
  postcode VARCHAR(12) NULL,
  membership_status ENUM('visitor','regular','member','inactive') NOT NULL DEFAULT 'visitor',
  first_visit DATE NULL,
  joined_on DATE NULL,
  directory_visible TINYINT(1) NOT NULL DEFAULT 0,
  notify_email TINYINT(1) NOT NULL DEFAULT 1,
  notify_sms TINYINT(1) NOT NULL DEFAULT 0,
  consent_contact TINYINT(1) NOT NULL DEFAULT 0,
  consent_photo TINYINT(1) NOT NULL DEFAULT 0,
  consent_updated_at DATETIME NULL,
  general_notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  KEY idx_people_household (household_id),
  KEY idx_people_email (email),
  KEY idx_people_status (membership_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}custom_fields (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(100) NOT NULL,
  field_type ENUM('text','date','select','checkbox') NOT NULL DEFAULT 'text',
  options TEXT NULL,
  sort INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}custom_field_values (
  person_id INT UNSIGNED NOT NULL,
  field_id INT UNSIGNED NOT NULL,
  value TEXT NULL,
  PRIMARY KEY (person_id, field_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission VARCHAR(60) NOT NULL,
  PRIMARY KEY (role_id, permission)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id INT UNSIGNED NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active','disabled','invited') NOT NULL DEFAULT 'active',
  mfa_secret VARCHAR(255) NULL,
  mfa_enabled TINYINT(1) NOT NULL DEFAULT 0,
  mfa_recovery TEXT NULL,
  ical_token VARCHAR(64) NULL,
  failed_logins INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login_at DATETIME NULL,
  password_changed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_users_person (person_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}user_roles (
  user_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  KEY idx_pr_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NULL,
  ip VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_la_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity VARCHAR(60) NULL,
  entity_id INT UNSIGNED NULL,
  details TEXT NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_entity (entity, entity_id),
  KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  link VARCHAR(255) NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notif_user (user_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}files (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(100) NOT NULL,
  mime VARCHAR(100) NOT NULL,
  size INT UNSIGNED NOT NULL,
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pastoral care ------------------------------------------------------------
CREATE TABLE {p}pastoral_notes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id INT UNSIGNED NOT NULL,
  author_id INT UNSIGNED NOT NULL,
  category VARCHAR(50) NOT NULL DEFAULT 'general',
  body_enc TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pn_person (person_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}care_tasks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id INT UNSIGNED NULL,
  task_type ENUM('newcomer','pastoral_contact','baptism','membership_class','prayer','hospital','other') NOT NULL DEFAULT 'other',
  title VARCHAR(200) NOT NULL,
  details TEXT NULL,
  is_confidential TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('open','in_progress','done') NOT NULL DEFAULT 'open',
  assigned_to INT UNSIGNED NULL,
  due_date DATE NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  KEY idx_ct_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ministries & groups ------------------------------------------------------
CREATE TABLE {p}groups (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  group_type ENUM('team','small_group','ministry') NOT NULL DEFAULT 'team',
  description TEXT NULL,
  meeting_schedule VARCHAR(150) NULL,
  location VARCHAR(150) NULL,
  is_open TINYINT(1) NOT NULL DEFAULT 0,
  child_related TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}group_members (
  group_id INT UNSIGNED NOT NULL,
  person_id INT UNSIGNED NOT NULL,
  member_role ENUM('member','assistant','leader') NOT NULL DEFAULT 'member',
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (group_id, person_id),
  KEY idx_gm_person (person_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}group_meetings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id INT UNSIGNED NOT NULL,
  meeting_date DATE NOT NULL,
  topic VARCHAR(200) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_gmt_group (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}group_attendance (
  meeting_id INT UNSIGNED NOT NULL,
  person_id INT UNSIGNED NOT NULL,
  present TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (meeting_id, person_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Courses & training -------------------------------------------------------
CREATE TABLE {p}courses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  summary TEXT NULL,
  category VARCHAR(80) NULL,
  status ENUM('draft','review','published','archived') NOT NULL DEFAULT 'draft',
  prerequisite_id INT UNSIGNED NULL,
  pass_mark INT NOT NULL DEFAULT 80,
  certificate_enabled TINYINT(1) NOT NULL DEFAULT 1,
  validity_months INT NULL,
  self_enrol TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  approved_by INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}course_editors (
  course_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (course_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}course_reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  reviewer_id INT UNSIGNED NOT NULL,
  decision ENUM('approved','changes_requested','submitted') NOT NULL,
  comment TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}lessons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  lesson_type ENUM('text','video','pdf','scripture') NOT NULL DEFAULT 'text',
  body MEDIUMTEXT NULL,
  video_url VARCHAR(500) NULL,
  file_id INT UNSIGNED NULL,
  sort INT NOT NULL DEFAULT 0,
  KEY idx_lessons_course (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}quiz_questions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  question TEXT NOT NULL,
  options TEXT NOT NULL,
  correct_index INT NOT NULL DEFAULT 0,
  sort INT NOT NULL DEFAULT 0,
  KEY idx_qq_course (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}enrolments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  person_id INT UNSIGNED NOT NULL,
  assigned_by INT UNSIGNED NULL,
  status ENUM('assigned','in_progress','completed','failed') NOT NULL DEFAULT 'assigned',
  score INT NULL,
  attempts INT NOT NULL DEFAULT 0,
  due_date DATE NULL,
  completed_at DATETIME NULL,
  expires_at DATE NULL,
  certificate_code VARCHAR(32) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_enrol (course_id, person_id),
  KEY idx_enrol_person (person_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}lesson_progress (
  enrolment_id INT UNSIGNED NOT NULL,
  lesson_id INT UNSIGNED NOT NULL,
  completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (enrolment_id, lesson_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Volunteers, safeguarding & rosters ---------------------------------------
CREATE TABLE {p}positions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  requires_wwcc TINYINT(1) NOT NULL DEFAULT 0,
  min_age INT NULL,
  max_per_month INT NOT NULL DEFAULT 3,
  description VARCHAR(255) NULL,
  KEY idx_pos_group (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}position_courses (
  position_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (position_id, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}volunteer_positions (
  person_id INT UNSIGNED NOT NULL,
  position_id INT UNSIGNED NOT NULL,
  approved_by INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  preference ENUM('preferred','willing') NOT NULL DEFAULT 'willing',
  PRIMARY KEY (person_id, position_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}volunteer_applications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id INT UNSIGNED NOT NULL,
  position_ids VARCHAR(255) NULL,
  skills TEXT NULL,
  availability TEXT NULL,
  experience TEXT NULL,
  status ENUM('submitted','reviewing','training','approved','declined') NOT NULL DEFAULT 'submitted',
  reviewer_id INT UNSIGNED NULL,
  reviewer_notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}safeguarding_checks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id INT UNSIGNED NOT NULL,
  check_type VARCHAR(60) NOT NULL DEFAULT 'WWCC',
  jurisdiction VARCHAR(10) NULL,
  reference_enc TEXT NULL,
  status ENUM('pending','verified','expired','barred','withdrawn') NOT NULL DEFAULT 'pending',
  verified_on DATE NULL,
  expires_on DATE NULL,
  verified_by INT UNSIGNED NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sg_person (person_id),
  KEY idx_sg_expiry (expires_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}unavailability (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id INT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  reason VARCHAR(200) NULL,
  KEY idx_un_person (person_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}rosters (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  service_date DATE NOT NULL,
  start_time TIME NULL,
  end_time TIME NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  notes TEXT NULL,
  created_by INT UNSIGNED NULL,
  published_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rost_date (service_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}roster_assignments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  roster_id INT UNSIGNED NOT NULL,
  position_id INT UNSIGNED NOT NULL,
  person_id INT UNSIGNED NULL,
  status ENUM('open','pending','accepted','declined','swap_requested') NOT NULL DEFAULT 'open',
  responded_at DATETIME NULL,
  reminded_at DATETIME NULL,
  KEY idx_ra_roster (roster_id),
  KEY idx_ra_person (person_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}swap_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT UNSIGNED NOT NULL,
  requested_by INT UNSIGNED NOT NULL,
  replacement_id INT UNSIGNED NULL,
  reason VARCHAR(255) NULL,
  status ENUM('open','accepted','approved','cancelled') NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Events, rooms & bookings -------------------------------------------------
CREATE TABLE {p}rooms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  capacity INT NULL,
  description VARCHAR(255) NULL,
  is_equipment TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  category VARCHAR(60) NULL,
  location VARCHAR(200) NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 1,
  registration_enabled TINYINT(1) NOT NULL DEFAULT 0,
  registration_closes DATETIME NULL,
  capacity INT NULL,
  joining_instructions TEXT NULL,
  status ENUM('scheduled','cancelled') NOT NULL DEFAULT 'scheduled',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_events_start (start_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}event_registrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id INT UNSIGNED NOT NULL,
  person_id INT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  guests INT NOT NULL DEFAULT 0,
  status ENUM('registered','waitlisted','cancelled','attended','no_show') NOT NULL DEFAULT 'registered',
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_er_event (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}room_bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id INT UNSIGNED NOT NULL,
  event_id INT UNSIGNED NULL,
  title VARCHAR(200) NOT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  status ENUM('requested','approved','declined','cancelled') NOT NULL DEFAULT 'requested',
  requested_by INT UNSIGNED NULL,
  approved_by INT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rb_room (room_id, start_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Communications -----------------------------------------------------------
CREATE TABLE {p}messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  channel ENUM('email','sms') NOT NULL,
  subject VARCHAR(200) NULL,
  body TEXT NOT NULL,
  audience VARCHAR(255) NOT NULL,
  sent_by INT UNSIGNED NULL,
  recipient_count INT NOT NULL DEFAULT 0,
  status ENUM('queued','sending','sent','failed') NOT NULL DEFAULT 'queued',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}message_recipients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  message_id INT UNSIGNED NULL,
  person_id INT UNSIGNED NULL,
  channel ENUM('email','sms') NOT NULL,
  address VARCHAR(190) NOT NULL,
  subject VARCHAR(200) NULL,
  body TEXT NULL,
  status ENUM('queued','sent','failed','skipped') NOT NULL DEFAULT 'queued',
  error VARCHAR(255) NULL,
  attempts INT NOT NULL DEFAULT 0,
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}announcements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  audience VARCHAR(60) NOT NULL DEFAULT 'all',
  publish_on DATE NULL,
  expires_on DATE NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Giving -------------------------------------------------------------------
CREATE TABLE {p}funds (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}donations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id INT UNSIGNED NULL,
  fund_id INT UNSIGNED NULL,
  donor_name VARCHAR(160) NULL,
  donor_email VARCHAR(190) NULL,
  amount_cents INT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'AUD',
  method ENUM('stripe','cash','eft','cheque','other') NOT NULL DEFAULT 'stripe',
  status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  stripe_session_id VARCHAR(255) NULL,
  receipt_no VARCHAR(30) NULL,
  received_on DATE NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_don_session (stripe_session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Privacy & compliance -----------------------------------------------------
CREATE TABLE {p}consent_records (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id INT UNSIGNED NOT NULL,
  consent_type VARCHAR(60) NOT NULL,
  granted TINYINT(1) NOT NULL,
  recorded_by INT UNSIGNED NULL,
  method VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cr_person (person_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}privacy_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id INT UNSIGNED NULL,
  request_type ENUM('access','correction','deletion','complaint') NOT NULL,
  details TEXT NULL,
  status ENUM('open','in_progress','closed') NOT NULL DEFAULT 'open',
  received_on DATE NOT NULL,
  due_on DATE NULL,
  outcome TEXT NULL,
  handled_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}breach_register (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  discovered_at DATETIME NOT NULL,
  summary VARCHAR(255) NOT NULL,
  details TEXT NULL,
  data_involved TEXT NULL,
  people_affected INT NULL,
  stage ENUM('contain','assess','notify','review','closed') NOT NULL DEFAULT 'contain',
  likely_serious_harm ENUM('unknown','yes','no') NOT NULL DEFAULT 'unknown',
  assessment_due DATE NULL,
  oaic_notified_on DATE NULL,
  individuals_notified_on DATE NULL,
  actions_taken TEXT NULL,
  lessons_learned TEXT NULL,
  recorded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {p}jobs_lock (
  name VARCHAR(60) PRIMARY KEY,
  ran_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

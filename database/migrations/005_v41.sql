-- Version 4.1: paying for forms in instalments, and form answers that update profiles.

ALTER TABLE {p}forms ADD COLUMN instalments TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER price_cents;

ALTER TABLE {p}form_submissions MODIFY status ENUM('submitted','awaiting_payment','part_paid','overdue','paid','cancelled') NOT NULL DEFAULT 'submitted';
ALTER TABLE {p}form_submissions ADD COLUMN instalments TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER amount_cents;
ALTER TABLE {p}form_submissions ADD COLUMN paid_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER instalments;
ALTER TABLE {p}form_submissions ADD COLUMN stripe_subscription_id VARCHAR(120) NULL AFTER stripe_session_id;
ALTER TABLE {p}form_submissions ADD COLUMN profile_applied_at DATETIME NULL;
ALTER TABLE {p}form_submissions ADD KEY idx_sub_subscription (stripe_subscription_id);

CREATE TABLE IF NOT EXISTS {p}form_payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  submission_id INT UNSIGNED NOT NULL,
  stripe_ref VARCHAR(120) NULL,
  amount_cents INT UNSIGNED NOT NULL,
  method VARCHAR(20) NOT NULL DEFAULT 'card',
  paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_form_pay_ref (stripe_ref),
  KEY idx_form_pay_sub (submission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Existing paid responses: record what they paid so the new payment history adds up.
UPDATE {p}form_submissions SET paid_cents = amount_cents WHERE status = 'paid';
INSERT INTO {p}form_payments (submission_id, stripe_ref, amount_cents, method, paid_at)
  SELECT id, stripe_session_id, amount_cents, 'card', created_at FROM {p}form_submissions WHERE status = 'paid' AND amount_cents > 0;

-- Forms can update the respondent's own profile automatically (off unless a form manager turns it on).
ALTER TABLE {p}forms ADD COLUMN profile_auto TINYINT(1) NOT NULL DEFAULT 0 AFTER one_per_person;

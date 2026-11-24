-- Version 4.2: refunds for form payments, and paid event registration through a linked form.

ALTER TABLE {p}form_payments ADD COLUMN stripe_pi VARCHAR(120) NULL AFTER stripe_ref;
ALTER TABLE {p}form_payments ADD COLUMN refunded_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER amount_cents;
ALTER TABLE {p}form_payments ADD KEY idx_form_pay_pi (stripe_pi);
ALTER TABLE {p}form_submissions ADD COLUMN refunded_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER paid_cents;

ALTER TABLE {p}event_registrations ADD COLUMN form_submission_id INT UNSIGNED NULL;
ALTER TABLE {p}event_registrations ADD KEY idx_reg_form_sub (form_submission_id);

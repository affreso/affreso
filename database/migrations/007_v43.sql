-- Version 4.3: refunds for gifts, and ticket types (family tickets) on forms.

ALTER TABLE {p}donations ADD COLUMN refunded_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER amount_cents;
ALTER TABLE {p}donations ADD COLUMN stripe_pi VARCHAR(120) NULL AFTER stripe_session_id;
ALTER TABLE {p}donations ADD KEY idx_don_pi (stripe_pi);
-- Gifts already marked refunded were refunded in full.
UPDATE {p}donations SET refunded_cents = amount_cents WHERE status = 'refunded';

ALTER TABLE {p}forms ADD COLUMN tickets TEXT NULL AFTER price_cents;
ALTER TABLE {p}form_submissions ADD COLUMN tickets TEXT NULL AFTER answers;
ALTER TABLE {p}form_submissions ADD COLUMN places SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER tickets;

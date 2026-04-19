ALTER TABLE answers
    ADD COLUMN verified_at DATETIME NULL AFTER is_accepted,
    ADD COLUMN verified_by BIGINT UNSIGNED NULL AFTER verified_at,
    ADD COLUMN review_note VARCHAR(240) NOT NULL DEFAULT '' AFTER verified_by,
    ADD KEY idx_answers_verified (verified_at),
    ADD CONSTRAINT fk_answers_verified_by FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL;

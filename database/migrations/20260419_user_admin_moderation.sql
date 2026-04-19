ALTER TABLE users
    ADD COLUMN banned_at DATETIME NULL AFTER email_verified_at,
    ADD COLUMN deleted_at DATETIME NULL AFTER banned_at,
    ADD KEY idx_users_status (deleted_at, banned_at);

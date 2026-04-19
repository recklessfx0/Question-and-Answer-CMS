ALTER TABLE users
    ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(64) NOT NULL DEFAULT '' AFTER email_verified_at,
    ADD COLUMN IF NOT EXISTS two_factor_enabled_at DATETIME NULL AFTER two_factor_secret;

INSERT INTO settings (`key`, value, updated_at) VALUES
('smtp_enabled', '0', NOW()),
('smtp_host', '', NOW()),
('smtp_port', '587', NOW()),
('smtp_encryption', 'tls', NOW()),
('smtp_username', '', NOW()),
('smtp_password', '', NOW()),
('smtp_from_email', '', NOW()),
('smtp_from_name', '', NOW())
ON DUPLICATE KEY UPDATE value = value;

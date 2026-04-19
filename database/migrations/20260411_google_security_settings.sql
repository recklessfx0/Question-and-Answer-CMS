INSERT INTO settings (`key`, value, updated_at) VALUES
('recaptcha_enabled', '0', NOW()),
('recaptcha_site_key', '', NOW()),
('recaptcha_secret_key', '', NOW()),
('google_auth_enabled', '0', NOW()),
('google_client_id', '', NOW()),
('google_client_secret', '', NOW())
ON DUPLICATE KEY UPDATE value = value, updated_at = updated_at;

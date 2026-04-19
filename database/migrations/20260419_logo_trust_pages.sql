INSERT INTO settings (`key`, value, updated_at) VALUES
('site_logo_path', '', NOW())
ON DUPLICATE KEY UPDATE value = value;

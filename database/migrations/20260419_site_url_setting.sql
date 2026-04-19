INSERT INTO settings (`key`, value, updated_at) VALUES
('site_url', 'https://example.com', NOW())
ON DUPLICATE KEY UPDATE value = value;

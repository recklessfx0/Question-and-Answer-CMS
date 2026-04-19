CREATE TABLE IF NOT EXISTS languages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(80) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_languages_active (is_active),
    KEY idx_languages_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS translations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    language_id BIGINT UNSIGNED NOT NULL,
    translation_key VARCHAR(120) NOT NULL,
    value TEXT NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_translations_language_key (language_id, translation_key),
    CONSTRAINT fk_translations_language FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO languages (code, name, is_active, is_default, created_at, updated_at) VALUES
('en', 'English', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name), is_active = VALUES(is_active), is_default = VALUES(is_default), updated_at = NOW();

CREATE TABLE IF NOT EXISTS gamification_levels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level_number TINYINT UNSIGNED NOT NULL,
    title VARCHAR(80) NOT NULL,
    min_reputation INT UNSIGNED NOT NULL,
    description VARCHAR(240) NOT NULL DEFAULT '',
    icon_path VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_gamification_levels_number (level_number),
    KEY idx_gamification_levels_active_min (is_active, min_reputation, level_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO gamification_levels (level_number, title, min_reputation, description, icon_path, is_active, created_at, updated_at)
WITH RECURSIVE level_seed(level_number) AS (
    SELECT 1
    UNION ALL
    SELECT level_number + 1 FROM level_seed WHERE level_number < 100
)
SELECT
    level_number,
    CASE
        WHEN level_number >= 90 THEN 'Legend'
        WHEN level_number >= 75 THEN 'Architect'
        WHEN level_number >= 60 THEN 'Sage'
        WHEN level_number >= 45 THEN 'Expert'
        WHEN level_number >= 30 THEN 'Mentor'
        WHEN level_number >= 15 THEN 'Contributor'
        ELSE 'Newcomer'
    END,
    POW(level_number - 1, 2) * 10,
    CONCAT('Reached level ', level_number, '.'),
    NULL,
    1,
    NOW(),
    NOW()
FROM level_seed
ON DUPLICATE KEY UPDATE level_number = level_number;

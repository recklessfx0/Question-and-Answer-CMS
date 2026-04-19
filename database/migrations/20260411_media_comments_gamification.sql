ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL AFTER bio;
ALTER TABLE comments ADD COLUMN parent_id BIGINT UNSIGNED NULL AFTER answer_id;
ALTER TABLE comments ADD COLUMN votes INT NOT NULL DEFAULT 0 AFTER body;
CREATE INDEX idx_comments_parent ON comments (parent_id, created_at);
ALTER TABLE comments ADD CONSTRAINT fk_comments_parent FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE;
ALTER TABLE votes MODIFY post_type ENUM('question','answer','comment') NOT NULL;

CREATE TABLE IF NOT EXISTS reputation_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    points INT NOT NULL,
    reason VARCHAR(80) NOT NULL,
    source_type VARCHAR(40) NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_reputation_events_user_created (user_id, created_at),
    KEY idx_reputation_events_source (source_type, source_id),
    CONSTRAINT fk_reputation_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS badges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(240) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_badges_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_badges (
    user_id BIGINT UNSIGNED NOT NULL,
    badge_id BIGINT UNSIGNED NOT NULL,
    earned_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, badge_id),
    CONSTRAINT fk_user_badges_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_badges_badge FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

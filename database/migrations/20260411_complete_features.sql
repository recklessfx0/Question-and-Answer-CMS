CREATE TABLE IF NOT EXISTS categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NULL,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description VARCHAR(240) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_categories_slug (slug),
    KEY idx_categories_parent_name (parent_id, name),
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE questions
    ADD COLUMN category_id BIGINT UNSIGNED NULL AFTER user_id;

CREATE INDEX idx_questions_category_created ON questions (category_id, created_at);

ALTER TABLE questions
    ADD CONSTRAINT fk_questions_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS user_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(40) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_user_tokens_hash (token_hash),
    KEY idx_user_tokens_user_type (user_id, type),
    KEY idx_user_tokens_expires (expires_at),
    CONSTRAINT fk_user_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id BIGINT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    target_type VARCHAR(40) NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    payload JSON NOT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_audit_logs_created (created_at),
    KEY idx_audit_logs_target (target_type, target_id),
    CONSTRAINT fk_audit_logs_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reaction_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    icon_path VARCHAR(255) NULL,
    reputation_delta INT NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_reaction_types_slug (slug),
    KEY idx_reaction_types_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_reactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    reaction_type_id BIGINT UNSIGNED NOT NULL,
    post_type VARCHAR(20) NOT NULL,
    post_id BIGINT UNSIGNED NOT NULL,
    target_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_post_reactions_user_target (user_id, reaction_type_id, post_type, post_id),
    KEY idx_post_reactions_target (post_type, post_id, reaction_type_id),
    KEY idx_post_reactions_target_user (target_user_id, created_at),
    CONSTRAINT fk_post_reactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_post_reactions_type FOREIGN KEY (reaction_type_id) REFERENCES reaction_types(id) ON DELETE CASCADE,
    CONSTRAINT fk_post_reactions_target_user FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO reaction_types (name, slug, icon_path, reputation_delta, sort_order, is_active, created_at, updated_at) VALUES
('Useful', 'useful', NULL, 2, 10, 1, NOW(), NOW()),
('Thanks', 'thanks', NULL, 1, 20, 1, NOW(), NOW()),
('Insightful', 'insightful', NULL, 3, 30, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name), reputation_delta = VALUES(reputation_delta), sort_order = VALUES(sort_order), is_active = VALUES(is_active), updated_at = NOW();

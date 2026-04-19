CREATE DATABASE IF NOT EXISTS qa_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE qa_platform;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(40) NOT NULL,
    email VARCHAR(180) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user','moderator','administrator') NOT NULL DEFAULT 'user',
    reputation INT NOT NULL DEFAULT 1,
    bio TEXT NULL,
    website_url VARCHAR(255) NOT NULL DEFAULT '',
    expertise VARCHAR(240) NOT NULL DEFAULT '',
    credentials VARCHAR(240) NOT NULL DEFAULT '',
    avatar_path VARCHAR(255) NULL,
    email_verified_at DATETIME NULL,
    two_factor_secret VARCHAR(64) NOT NULL DEFAULT '',
    two_factor_enabled_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role),
    KEY idx_users_reputation (reputation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NULL,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description VARCHAR(240) NOT NULL DEFAULT '',
    seo_title VARCHAR(120) NOT NULL DEFAULT '',
    seo_description VARCHAR(180) NOT NULL DEFAULT '',
    intro TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_categories_slug (slug),
    KEY idx_categories_parent_name (parent_id, name),
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE questions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NULL,
    accepted_answer_id BIGINT UNSIGNED NULL,
    duplicate_of_id BIGINT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(220) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    meta_description VARCHAR(180) NOT NULL,
    votes INT NOT NULL DEFAULT 0,
    views INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    closed_at DATETIME NULL,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_questions_slug (slug),
    KEY idx_questions_user_created (user_id, created_at),
    KEY idx_questions_category_created (category_id, created_at),
    KEY idx_questions_duplicate_of (duplicate_of_id),
    KEY idx_questions_created (created_at),
    KEY idx_questions_deleted_created (deleted_at, created_at),
    FULLTEXT KEY ft_questions_title_body (title, body),
    CONSTRAINT fk_questions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_questions_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    body MEDIUMTEXT NOT NULL,
    votes INT NOT NULL DEFAULT 0,
    is_accepted TINYINT(1) NOT NULL DEFAULT 0,
    verified_at DATETIME NULL,
    verified_by BIGINT UNSIGNED NULL,
    review_note VARCHAR(240) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    KEY idx_answers_question_rank (question_id, is_accepted, votes, created_at),
    KEY idx_answers_verified (verified_at),
    KEY idx_answers_user_created (user_id, created_at),
    CONSTRAINT fk_answers_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    CONSTRAINT fk_answers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_answers_verified_by FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE questions
    ADD CONSTRAINT fk_questions_accepted_answer FOREIGN KEY (accepted_answer_id) REFERENCES answers(id) ON DELETE SET NULL;

ALTER TABLE questions
    ADD CONSTRAINT fk_questions_duplicate_of FOREIGN KEY (duplicate_of_id) REFERENCES questions(id) ON DELETE SET NULL;

CREATE TABLE comments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id BIGINT UNSIGNED NULL,
    answer_id BIGINT UNSIGNED NULL,
    parent_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    votes INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    KEY idx_comments_question (question_id, created_at),
    KEY idx_comments_answer (answer_id, created_at),
    KEY idx_comments_parent (parent_id, created_at),
    CONSTRAINT fk_comments_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_answer FOREIGN KEY (answer_id) REFERENCES answers(id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_parent FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_comments_target CHECK ((question_id IS NOT NULL AND answer_id IS NULL) OR (question_id IS NULL AND answer_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description VARCHAR(240) NOT NULL DEFAULT '',
    seo_title VARCHAR(120) NOT NULL DEFAULT '',
    seo_description VARCHAR(180) NOT NULL DEFAULT '',
    intro TEXT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_tags_slug (slug),
    KEY idx_tags_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE question_tags (
    question_id BIGINT UNSIGNED NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (question_id, tag_id),
    KEY idx_question_tags_tag (tag_id, question_id),
    CONSTRAINT fk_question_tags_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    CONSTRAINT fk_question_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE votes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    post_type ENUM('question','answer','comment') NOT NULL,
    post_id BIGINT UNSIGNED NOT NULL,
    value TINYINT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_votes_user_post (user_id, post_type, post_id),
    KEY idx_votes_post (post_type, post_id),
    CONSTRAINT fk_votes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_votes_value CHECK (value IN (-1, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE flags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    post_type ENUM('question','answer','comment','user') NOT NULL,
    post_id BIGINT UNSIGNED NOT NULL,
    reason VARCHAR(240) NOT NULL,
    status ENUM('open','resolved','rejected') NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL,
    resolved_at DATETIME NULL,
    KEY idx_flags_status_created (status, created_at),
    KEY idx_flags_post (post_type, post_id),
    CONSTRAINT fk_flags_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(80) NOT NULL,
    payload JSON NOT NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    KEY idx_notifications_user_read (user_id, read_at, created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_tokens (
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

CREATE TABLE audit_logs (
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

CREATE TABLE settings (
    `key` VARCHAR(80) PRIMARY KEY,
    value TEXT NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE languages (
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

CREATE TABLE translations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    language_id BIGINT UNSIGNED NOT NULL,
    translation_key VARCHAR(120) NOT NULL,
    value TEXT NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_translations_language_key (language_id, translation_key),
    CONSTRAINT fk_translations_language FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reputation_events (
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

CREATE TABLE badges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(240) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_badges_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_badges (
    user_id BIGINT UNSIGNED NOT NULL,
    badge_id BIGINT UNSIGNED NOT NULL,
    earned_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, badge_id),
    CONSTRAINT fk_user_badges_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_badges_badge FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gamification_levels (
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

CREATE TABLE reaction_types (
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

CREATE TABLE post_reactions (
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

INSERT INTO settings (`key`, value, updated_at) VALUES
('site_title', 'Question And Answer Platform', NOW()),
('site_logo_path', '', NOW()),
('site_url', 'https://example.com', NOW()),
('site_description', 'A fast, secure community question and answer platform.', NOW()),
('recaptcha_enabled', '0', NOW()),
('recaptcha_site_key', '', NOW()),
('recaptcha_secret_key', '', NOW()),
('google_auth_enabled', '0', NOW()),
('google_client_id', '', NOW()),
('google_client_secret', '', NOW()),
('smtp_enabled', '0', NOW()),
('smtp_host', '', NOW()),
('smtp_port', '587', NOW()),
('smtp_encryption', 'tls', NOW()),
('smtp_username', '', NOW()),
('smtp_password', '', NOW()),
('smtp_from_email', '', NOW()),
('smtp_from_name', '', NOW()),
('about_description', 'Learn about the community, moderation standards, and public question-and-answer content.', NOW()),
('about_body', 'This community publishes public questions, answers, clarifications, profiles, categories, and tags so people can find helpful discussions and cite canonical question pages.', NOW()),
('contact_description', 'Contact the site team about account, moderation, privacy, or community questions.', NOW()),
('contact_body', 'Use the contact information configured by the site owner for account, moderation, privacy, and community requests.', NOW()),
('privacy_description', 'Privacy information for community accounts, public posts, media, and moderation records.', NOW()),
('privacy_body', 'Public posts, answers, clarifications, profile details, uploaded media, votes, reactions, reports, and moderation records may be processed to operate the community and protect the site.', NOW()),
('terms_description', 'Terms for using the community question-and-answer platform.', NOW()),
('terms_body', 'By using the community, members agree to post lawful, respectful, original content and understand that public contributions may be indexed by search engines and AI retrieval systems.', NOW()),
('guidelines_description', 'Community guidelines for useful questions, answers, clarifications, citations, and moderation.', NOW()),
('guidelines_body', 'Ask clear questions, share complete answers, disclose uncertainty, avoid spam, respect other members, and report content that needs moderator attention.', NOW())
ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW();

INSERT INTO languages (code, name, is_active, is_default, created_at, updated_at) VALUES
('en', 'English', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name), is_active = VALUES(is_active), is_default = VALUES(is_default), updated_at = NOW();

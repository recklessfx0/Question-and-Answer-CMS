<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use PDOException;
use RuntimeException;
use Throwable;

final class UserModel
{
    private ?bool $approvalColumnAvailable = null;

    public function __construct(private Database $db) {}

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return $this->db->fetch('SELECT * FROM users WHERE email = :email LIMIT 1', [':email' => $email]);
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->db->fetch('SELECT id, username, email, role, reputation, bio, website_url, expertise, credentials, avatar_path, email_verified_at, banned_at, deleted_at, two_factor_secret, two_factor_enabled_at, created_at FROM users WHERE id = :id LIMIT 1', [':id' => $id]);
    }

    /** @return array<string, mixed>|null */
    public function findByIdWithSecurity(int $id): ?array
    {
        return $this->db->fetch('SELECT id, username, email, role, password_hash, banned_at, deleted_at, two_factor_secret, two_factor_enabled_at FROM users WHERE id = :id LIMIT 1', [':id' => $id]);
    }

    /** @return array<string, mixed>|null */
    public function findSessionStateById(int $id): ?array
    {
        $row = $this->db->fetch(
            'SELECT id, username, email, role, reputation, bio, website_url, expertise, credentials, avatar_path, email_verified_at, banned_at, deleted_at, created_at, password_hash
             FROM users
             WHERE id = :id
             LIMIT 1',
            [':id' => $id]
        );
        if ($row === null) {
            return null;
        }

        $row['auth_fingerprint'] = hash('sha256', (string) ($row['password_hash'] ?? '') . '|' . (string) ($row['banned_at'] ?? '') . '|' . (string) ($row['deleted_at'] ?? '') . '|' . (string) ($row['role'] ?? ''));
        unset($row['password_hash']);

        return $row;
    }

    /** @return array<string, mixed>|null */
    public function findByUsername(string $username): ?array
    {
        return $this->db->fetch('SELECT id, username, email, role, reputation, bio, website_url, expertise, credentials, avatar_path, email_verified_at, banned_at, deleted_at, created_at FROM users WHERE username = :username LIMIT 1', [':username' => $username]);
    }

    /** @return array<string, mixed>|null */
    public function findMentionableByUsername(string $username): ?array
    {
        return $this->db->fetch(
            'SELECT id, username, avatar_path, reputation
             FROM users
             WHERE username = :username
               AND deleted_at IS NULL
               AND banned_at IS NULL
             LIMIT 1',
            [':username' => $username]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function suggestMentions(string $query, int $limit = 6): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $prefix = mb_substr($query, 0, 32);
        $contains = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix) . '%';

        return $this->db->fetchAll(
            'SELECT id, username, avatar_path, reputation
             FROM users
             WHERE deleted_at IS NULL
               AND banned_at IS NULL
               AND username LIKE :contains
             ORDER BY
               CASE WHEN username LIKE :prefix THEN 0 ELSE 1 END,
               reputation DESC,
               created_at DESC
             LIMIT :limit',
            [':contains' => $contains, ':prefix' => $prefix . '%', ':limit' => max(1, min(10, $limit))]
        );
    }

    public function create(string $username, string $email, string $passwordHash, string $role = 'user'): int
    {
        $this->db->execute(
            'INSERT INTO users (username, email, password_hash, role, reputation, created_at, updated_at) VALUES (:username, :email, :hash, :role, 1, NOW(), NOW())',
            [':username' => $username, ':email' => $email, ':hash' => $passwordHash, ':role' => $role]
        );

        return $this->db->lastInsertId();
    }

    public function createWithBootstrapRole(string $username, string $email, string $passwordHash): int
    {
        $lockName = 'users:first-admin-bootstrap';
        $attempts = 0;

        beginning:
        $pdo = $this->db->pdo();
        $lockAcquired = false;

        try {
            $lock = $this->db->fetch('SELECT GET_LOCK(:lock_name, 5) AS acquired', [':lock_name' => $lockName]);
            if ((int) ($lock['acquired'] ?? 0) !== 1) {
                throw new RuntimeException('Unable to acquire bootstrap role lock.');
            }

            $lockAcquired = true;
            $pdo->beginTransaction();

            $count = $this->db->fetch('SELECT COUNT(*) AS total FROM users');
            $role = (int) ($count['total'] ?? 0) === 0 ? 'administrator' : 'user';

            $this->db->execute(
                'INSERT INTO users (username, email, password_hash, role, reputation, created_at, updated_at) VALUES (:username, :email, :hash, :role, 1, NOW(), NOW())',
                [':username' => $username, ':email' => $email, ':hash' => $passwordHash, ':role' => $role]
            );

            $userId = $this->db->lastInsertId();
            $pdo->commit();

            return $userId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $retryable = $e instanceof PDOException
                && (str_contains($e->getMessage(), 'Deadlock') || str_contains($e->getMessage(), 'Lock wait timeout'));
            if ($retryable && ++$attempts < 3) {
                usleep(100000 * $attempts);
                goto beginning;
            }

            throw $e;
        } finally {
            if ($lockAcquired) {
                try {
                    $this->db->fetch('SELECT RELEASE_LOCK(:lock_name) AS released', [':lock_name' => $lockName]);
                } catch (Throwable) {
                }
            }
        }
    }

    public function uniqueUsername(string $username): string
    {
        $base = mb_substr(trim($username) !== '' ? trim($username) : 'member', 0, 32);
        $candidate = $base;
        $counter = 2;

        while ($this->findByUsername($candidate) !== null) {
            $suffix = '-' . $counter++;
            $candidate = mb_substr($base, 0, 40 - mb_strlen($suffix)) . $suffix;
        }

        return $candidate;
    }

    public function count(string $query = '', bool $includeDeleted = false): int
    {
        $params = [];
        $where = $this->adminSearchWhere($query, $params, $includeDeleted);
        $row = $this->db->fetch('SELECT COUNT(*) AS total FROM users' . $where, $params);
        return (int) ($row['total'] ?? 0);
    }

    public function publicCount(): int
    {
        $row = $this->db->fetch('SELECT COUNT(*) AS total FROM users WHERE deleted_at IS NULL AND banned_at IS NULL');

        return (int) ($row['total'] ?? 0);
    }

    /** @return array{top_reputation: int, total_answers: int, total_questions: int} */
    public function directorySummary(): array
    {
        $row = $this->db->fetch(
            'SELECT
                COALESCE(MAX(u.reputation), 0) AS top_reputation,
                (SELECT COUNT(*) FROM answers a JOIN questions q ON q.id = a.question_id WHERE a.deleted_at IS NULL AND q.deleted_at IS NULL' . $this->approvalFilter('q') . ') AS total_answers,
                (SELECT COUNT(*) FROM questions q WHERE q.deleted_at IS NULL' . $this->approvalFilter('q') . ') AS total_questions
             FROM users u
             WHERE u.deleted_at IS NULL
               AND u.banned_at IS NULL'
        );

        return [
            'top_reputation' => (int) ($row['top_reputation'] ?? 0),
            'total_answers' => (int) ($row['total_answers'] ?? 0),
            'total_questions' => (int) ($row['total_questions'] ?? 0),
        ];
    }

    public function updateLastLogin(int $id): void
    {
        $this->db->execute('UPDATE users SET last_login_at = NOW(), updated_at = NOW() WHERE id = :id', [':id' => $id]);
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $this->db->execute('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id', [':hash' => $hash, ':id' => $id]);
    }

    public function updateProfile(int $id, string $username, string $bio, string $websiteUrl = '', string $expertise = '', string $credentials = ''): void
    {
        $this->db->execute(
            'UPDATE users SET username = :username, bio = :bio, website_url = :website_url, expertise = :expertise, credentials = :credentials, updated_at = NOW() WHERE id = :id',
            [':username' => $username, ':bio' => $bio, ':website_url' => $websiteUrl, ':expertise' => $expertise, ':credentials' => $credentials, ':id' => $id]
        );
    }

    public function updateAvatar(int $id, string $avatarPath): void
    {
        $this->db->execute('UPDATE users SET avatar_path = :avatar, updated_at = NOW() WHERE id = :id', [':avatar' => $avatarPath, ':id' => $id]);
    }

    public function saveTwoFactorSecret(int $id, string $secret): void
    {
        $this->db->execute('UPDATE users SET two_factor_secret = :secret, two_factor_enabled_at = NULL, updated_at = NOW() WHERE id = :id', [':secret' => $secret, ':id' => $id]);
    }

    public function enableTwoFactor(int $id): void
    {
        $this->db->execute('UPDATE users SET two_factor_enabled_at = NOW(), updated_at = NOW() WHERE id = :id AND two_factor_secret <> ""', [':id' => $id]);
    }

    public function disableTwoFactor(int $id): void
    {
        $this->db->execute('UPDATE users SET two_factor_secret = "", two_factor_enabled_at = NULL, updated_at = NOW() WHERE id = :id', [':id' => $id]);
    }

    public function markEmailVerified(int $id): void
    {
        $this->db->execute('UPDATE users SET email_verified_at = NOW(), updated_at = NOW() WHERE id = :id', [':id' => $id]);
    }

    public function createToken(int $userId, string $type, int $ttlSeconds = 3600): string
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);

        $this->db->transaction(function () use ($userId, $type, $hash, $expiresAt): void {
            $user = $this->db->fetch(
                'SELECT id
                 FROM users
                 WHERE id = :id AND deleted_at IS NULL
                 LIMIT 1
                 FOR UPDATE',
                [':id' => $userId]
            );
            if ($user === null) {
                throw new RuntimeException('Unable to create token for missing user.');
            }

            $this->db->execute('DELETE FROM user_tokens WHERE user_id = :user AND type = :type', [':user' => $userId, ':type' => $type]);
            $this->db->execute(
                'INSERT INTO user_tokens (user_id, type, token_hash, expires_at, created_at)
                 VALUES (:user, :type, :hash, :expires_at, NOW())',
                [':user' => $userId, ':type' => $type, ':hash' => $hash, ':expires_at' => $expiresAt]
            );
        });

        return $token;
    }

    /** @return array<string, mixed>|null */
    public function findValidToken(string $type, string $token): ?array
    {
        return $this->db->fetch(
            'SELECT t.*, u.email, u.username, u.email_verified_at
             FROM user_tokens t
             JOIN users u ON u.id = t.user_id
             WHERE t.type = :type AND t.token_hash = :hash AND t.expires_at > NOW() AND u.deleted_at IS NULL AND u.banned_at IS NULL
             LIMIT 1',
            [':type' => $type, ':hash' => hash('sha256', $token)]
        );
    }

    public function consumeToken(int $tokenId): void
    {
        $this->db->execute('DELETE FROM user_tokens WHERE id = :id', [':id' => $tokenId]);
    }

    public function resetPasswordWithToken(string $token, string $hash): bool
    {
        return $this->db->transaction(function () use ($token, $hash): bool {
            $record = $this->db->fetch(
                'SELECT t.id, t.user_id
                 FROM user_tokens t
                 JOIN users u ON u.id = t.user_id
                 WHERE t.type = :type AND t.token_hash = :hash AND t.expires_at > NOW()
                   AND u.deleted_at IS NULL AND u.banned_at IS NULL
                 LIMIT 1
                 FOR UPDATE',
                [':type' => 'password_reset', ':hash' => hash('sha256', $token)]
            );
            if ($record === null) {
                return false;
            }

            $this->db->execute(
                'UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id',
                [':hash' => $hash, ':id' => (int) $record['user_id']]
            );
            $this->db->execute('DELETE FROM user_tokens WHERE id = :id', [':id' => (int) $record['id']]);

            return true;
        });
    }

    public function verifyEmailWithToken(string $token): ?int
    {
        return $this->db->transaction(function () use ($token): ?int {
            $record = $this->db->fetch(
                'SELECT t.id, t.user_id
                 FROM user_tokens t
                 JOIN users u ON u.id = t.user_id
                 WHERE t.type = :type AND t.token_hash = :hash AND t.expires_at > NOW()
                   AND u.deleted_at IS NULL AND u.banned_at IS NULL
                 LIMIT 1
                 FOR UPDATE',
                [':type' => 'email_verification', ':hash' => hash('sha256', $token)]
            );
            if ($record === null) {
                return null;
            }

            $userId = (int) $record['user_id'];
            $this->db->execute('UPDATE users SET email_verified_at = NOW(), updated_at = NOW() WHERE id = :id', [':id' => $userId]);
            $this->db->execute('DELETE FROM user_tokens WHERE id = :id', [':id' => (int) $record['id']]);

            return $userId;
        });
    }

    public function setRole(int $id, string $role): bool
    {
        if (!in_array($role, ['user', 'moderator', 'administrator'], true)) {
            return false;
        }

        return $this->db->transaction(function () use ($id, $role): bool {
            $user = $this->db->fetch(
                'SELECT id, role
                 FROM users
                 WHERE id = :id
                 LIMIT 1
                 FOR UPDATE',
                [':id' => $id]
            );
            if ($user === null) {
                return false;
            }

            $currentRole = (string) ($user['role'] ?? '');
            if ($currentRole === 'administrator' && $role !== 'administrator') {
                $otherAdmin = $this->db->fetch(
                    'SELECT id
                     FROM users
                     WHERE role = :role AND deleted_at IS NULL AND banned_at IS NULL AND id <> :id
                     LIMIT 1
                     FOR UPDATE',
                    [':role' => 'administrator', ':id' => $id]
                );
                if ($otherAdmin === null) {
                    return false;
                }
            }

            $this->db->execute('UPDATE users SET role = :role, updated_at = NOW() WHERE id = :id', [':role' => $role, ':id' => $id]);

            return true;
        });
    }

    public function transaction(callable $callback): mixed
    {
        return $this->db->transaction($callback);
    }

    public function adjustReputation(int $id, int $delta): void
    {
        $this->db->execute('UPDATE users SET reputation = GREATEST(0, reputation + :delta), updated_at = NOW() WHERE id = :id', [':delta' => $delta, ':id' => $id]);
    }

    public function recordReputationEvent(int $userId, int $points, string $reason, string $sourceType, int $sourceId): bool
    {
        return $this->db->query(
            'INSERT IGNORE INTO reputation_events (user_id, points, reason, source_type, source_id, created_at) VALUES (:user, :points, :reason, :source_type, :source_id, NOW())',
            [':user' => $userId, ':points' => $points, ':reason' => $reason, ':source_type' => $sourceType, ':source_id' => $sourceId]
        )->rowCount() > 0;
    }

    /** @return array<string, mixed>|null */
    public function findReputationEvent(int $userId, string $sourceType, int $sourceId, string $reason): ?array
    {
        return $this->db->fetch(
            'SELECT id, points FROM reputation_events WHERE user_id = :user AND source_type = :source_type AND source_id = :source_id AND reason = :reason LIMIT 1',
            [':user' => $userId, ':source_type' => $sourceType, ':source_id' => $sourceId, ':reason' => $reason]
        );
    }

    public function setReputationEventPoints(int $userId, int $points, string $reason, string $sourceType, int $sourceId): int
    {
        $existing = $this->findReputationEvent($userId, $sourceType, $sourceId, $reason);
        $currentPoints = (int) ($existing['points'] ?? 0);

        if ($existing === null) {
            if ($points === 0) {
                return 0;
            }

            $this->db->execute(
                'INSERT INTO reputation_events (user_id, points, reason, source_type, source_id, created_at) VALUES (:user, :points, :reason, :source_type, :source_id, NOW())',
                [':user' => $userId, ':points' => $points, ':reason' => $reason, ':source_type' => $sourceType, ':source_id' => $sourceId]
            );

            return $points;
        }

        if ($points === $currentPoints) {
            return 0;
        }

        if ($points === 0) {
            $this->db->execute('DELETE FROM reputation_events WHERE id = :id', [':id' => (int) $existing['id']]);
        } else {
            $this->db->execute(
                'UPDATE reputation_events SET points = :points WHERE id = :id',
                [':points' => $points, ':id' => (int) $existing['id']]
            );
        }

        return $points - $currentPoints;
    }

    public function grantBadge(int $userId, string $name, string $description): void
    {
        $this->db->execute(
            'INSERT INTO badges (name, description, created_at) VALUES (:name, :description, NOW()) ON DUPLICATE KEY UPDATE description = VALUES(description)',
            [':name' => $name, ':description' => $description]
        );
        $badge = $this->db->fetch('SELECT id FROM badges WHERE name = :name LIMIT 1', [':name' => $name]);
        if ($badge !== null) {
            $this->db->execute('INSERT IGNORE INTO user_badges (user_id, badge_id, earned_at) VALUES (:user, :badge, NOW())', [':user' => $userId, ':badge' => (int) $badge['id']]);
        }
    }

    /** @param array<int, array{name: string, description: string, earned: bool}> $definitions */
    public function syncLevelBadges(int $userId, array $definitions): void
    {
        foreach ($definitions as $definition) {
            $name = trim((string) ($definition['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $description = (string) ($definition['description'] ?? '');
            $this->db->execute(
                'INSERT INTO badges (name, description, created_at) VALUES (:name, :description, NOW()) ON DUPLICATE KEY UPDATE description = VALUES(description)',
                [':name' => $name, ':description' => $description]
            );

            $badge = $this->db->fetch('SELECT id FROM badges WHERE name = :name LIMIT 1', [':name' => $name]);
            if ($badge === null) {
                continue;
            }

            $badgeId = (int) $badge['id'];
            if (($definition['earned'] ?? false) === true) {
                $this->db->execute('INSERT IGNORE INTO user_badges (user_id, badge_id, earned_at) VALUES (:user, :badge, NOW())', [':user' => $userId, ':badge' => $badgeId]);
                continue;
            }

            $this->db->execute('DELETE FROM user_badges WHERE user_id = :user AND badge_id = :badge', [':user' => $userId, ':badge' => $badgeId]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function badges(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT b.*, ub.earned_at FROM badges b JOIN user_badges ub ON ub.badge_id = b.id WHERE ub.user_id = :user ORDER BY ub.earned_at DESC',
            [':user' => $userId]
        );
    }

    /** @return array{questions: int, answers: int, comments: int} */
    public function activityStats(int $userId): array
    {
        $questions = $this->db->fetch('SELECT COUNT(*) AS total FROM questions q WHERE q.user_id = :user AND q.deleted_at IS NULL' . $this->approvalFilter('q'), [':user' => $userId]);
        $answers = $this->db->fetch(
            'SELECT COUNT(*) AS total
             FROM answers a
             JOIN questions q ON q.id = a.question_id
             WHERE a.user_id = :user AND a.deleted_at IS NULL AND q.deleted_at IS NULL' . $this->approvalFilter('q'),
            [':user' => $userId]
        );
        $comments = $this->db->fetch(
            'SELECT COUNT(*) AS total
             FROM comments c
             LEFT JOIN answers a ON a.id = c.answer_id
             LEFT JOIN questions q ON q.id = COALESCE(c.question_id, a.question_id)
             WHERE c.user_id = :user' . $this->commentDeletedFilter('c') . ' AND q.deleted_at IS NULL' . $this->approvalFilter('q') . ' AND (c.answer_id IS NULL OR a.id IS NOT NULL)',
            [':user' => $userId]
        );

        return [
            'questions' => (int) ($questions['total'] ?? 0),
            'answers' => (int) ($answers['total'] ?? 0),
            'comments' => (int) ($comments['total'] ?? 0),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function recentQuestions(int $userId, int $limit = 10): array
    {
        return $this->db->fetchAll(
            'SELECT q.*, COUNT(DISTINCT a.id) AS answer_count
             FROM questions q
             LEFT JOIN answers a ON a.question_id = q.id AND a.deleted_at IS NULL
             WHERE q.user_id = :user AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
             GROUP BY q.id
             ORDER BY q.created_at DESC
             LIMIT :limit',
            [':user' => $userId, ':limit' => $limit]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function recentAnswers(int $userId, int $limit = 10): array
    {
        return $this->db->fetchAll(
            'SELECT a.id, a.body, a.votes, a.is_accepted, a.created_at, q.id AS question_id, q.title AS question_title, q.slug AS question_slug
             FROM answers a
             JOIN questions q ON q.id = a.question_id
             WHERE a.user_id = :user AND a.deleted_at IS NULL AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
             ORDER BY a.created_at DESC
             LIMIT :limit',
            [':user' => $userId, ':limit' => $limit]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function recentActivity(int $userId, int $limit = 10): array
    {
        $approvalFilter = $this->approvalFilter('q');
        $commentFilter = $this->commentDeletedFilter('c');
        return $this->db->fetchAll(
            "SELECT *
             FROM (
                SELECT 'question' AS type, 'Asked' AS label, q.id AS question_id, q.title AS title, q.slug AS slug, q.body AS summary, q.created_at AS created_at
                FROM questions q
                WHERE q.user_id = :question_user AND q.deleted_at IS NULL{$approvalFilter}
                UNION ALL
                SELECT 'answer' AS type, 'Answered' AS label, q.id AS question_id, q.title AS title, q.slug AS slug, a.body AS summary, a.created_at AS created_at
                FROM answers a
                JOIN questions q ON q.id = a.question_id
                WHERE a.user_id = :answer_user AND a.deleted_at IS NULL AND q.deleted_at IS NULL{$approvalFilter}
                UNION ALL
                SELECT 'comment' AS type, 'Commented' AS label, q.id AS question_id, q.title AS title, q.slug AS slug, c.body AS summary, c.created_at AS created_at
                FROM comments c
                LEFT JOIN answers a ON a.id = c.answer_id
                LEFT JOIN questions q ON q.id = COALESCE(c.question_id, a.question_id)
                WHERE c.user_id = :comment_user{$commentFilter} AND q.deleted_at IS NULL{$approvalFilter} AND (c.answer_id IS NULL OR a.id IS NOT NULL)
             ) activity
             ORDER BY created_at DESC
             LIMIT :limit",
            [':question_user' => $userId, ':answer_user' => $userId, ':comment_user' => $userId, ':limit' => $limit]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function topTopics(int $userId, int $limit = 8): array
    {
        return $this->db->fetchAll(
            'SELECT topic_type, slug, name, SUM(score) AS score
             FROM (
                SELECT "tag" AS topic_type, t.slug, t.name, COUNT(*) AS score
                FROM tags t
                JOIN question_tags qt ON qt.tag_id = t.id
                JOIN questions q ON q.id = qt.question_id
                WHERE q.user_id = :question_user
                  AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
                GROUP BY t.id, t.slug, t.name
                UNION ALL
                SELECT "tag" AS topic_type, t.slug, t.name, COUNT(*) AS score
                FROM tags t
                JOIN question_tags qt ON qt.tag_id = t.id
                JOIN questions q ON q.id = qt.question_id
                JOIN answers a ON a.question_id = q.id
                WHERE a.user_id = :answer_user
                  AND a.deleted_at IS NULL
                  AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
                GROUP BY t.id, t.slug, t.name
                UNION ALL
                SELECT "category" AS topic_type, c.slug, c.name, COUNT(*) AS score
                FROM categories c
                JOIN questions q ON q.category_id = c.id
                WHERE q.user_id = :question_category_user
                  AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
                GROUP BY c.id, c.slug, c.name
                UNION ALL
                SELECT "category" AS topic_type, c.slug, c.name, COUNT(*) AS score
                FROM categories c
                JOIN questions q ON q.category_id = c.id
                JOIN answers a ON a.question_id = q.id
                WHERE a.user_id = :answer_category_user
                  AND a.deleted_at IS NULL
                  AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
                GROUP BY c.id, c.slug, c.name
             ) topics
             GROUP BY topic_type, slug, name
             ORDER BY score DESC, topic_type ASC, name ASC
             LIMIT :limit',
            [
                ':question_user' => $userId,
                ':answer_user' => $userId,
                ':question_category_user' => $userId,
                ':answer_category_user' => $userId,
                ':limit' => $limit,
            ]
        );
    }

    /** @param array<int, int> $questionIds @return array<int, array<int, array<string, mixed>>> */
    public function participantsForQuestions(array $questionIds, int $limitPerQuestion = 6): array
    {
        $questionIds = array_values(array_unique(array_filter($questionIds, static fn (int $id): bool => $id > 0)));
        if ($questionIds === []) {
            return [];
        }

        $params = [];
        $questionIn = $this->inParams($questionIds, 'participant_question', $params);
        $answerIn = $this->inParams($questionIds, 'participant_answer', $params);
        $commentQuestionIn = $this->inParams($questionIds, 'participant_comment_question', $params);
        $commentAnswerIn = $this->inParams($questionIds, 'participant_comment_answer', $params);
        $approvalFilter = $this->approvalFilter('q');
        $commentFilter = $this->commentDeletedFilter('c');
        $rows = $this->db->fetchAll(
            "SELECT activity.question_id, activity.user_id, u.username, u.avatar_path, MAX(activity.last_activity) AS last_activity
             FROM (
                SELECT q.id AS question_id, q.user_id, q.created_at AS last_activity
                FROM questions q
                WHERE q.id IN ({$questionIn}) AND q.deleted_at IS NULL{$approvalFilter}
                UNION ALL
                SELECT a.question_id, a.user_id, a.created_at AS last_activity
                FROM answers a
                JOIN questions q ON q.id = a.question_id
                WHERE a.question_id IN ({$answerIn}) AND a.deleted_at IS NULL AND q.deleted_at IS NULL{$approvalFilter}
                UNION ALL
                SELECT COALESCE(c.question_id, a.question_id) AS question_id, c.user_id, c.created_at AS last_activity
                FROM comments c
                LEFT JOIN answers a ON a.id = c.answer_id
                LEFT JOIN questions q ON q.id = COALESCE(c.question_id, a.question_id)
                WHERE (c.question_id IN ({$commentQuestionIn}) OR a.question_id IN ({$commentAnswerIn}))
                  {$commentFilter}
                  AND q.deleted_at IS NULL{$approvalFilter}
                  AND (c.answer_id IS NULL OR a.id IS NOT NULL)
             ) activity
             JOIN users u ON u.id = activity.user_id AND u.deleted_at IS NULL AND u.banned_at IS NULL
             WHERE activity.question_id IS NOT NULL
             GROUP BY activity.question_id, activity.user_id, u.username, u.avatar_path
             ORDER BY activity.question_id, last_activity DESC",
            $params
        );

        $participants = [];
        foreach ($rows as $row) {
            $questionId = (int) $row['question_id'];
            $participants[$questionId] ??= [];
            if (count($participants[$questionId]) >= $limitPerQuestion) {
                continue;
            }

            $participants[$questionId][] = $row;
        }

        return $participants;
    }

    /** @param array<int, int> $ids @param array<string, mixed> $params */
    private function inParams(array $ids, string $prefix, array &$params): string
    {
        $placeholders = [];
        foreach ($ids as $index => $id) {
            $key = ':' . $prefix . $index;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        return implode(',', $placeholders);
    }

    /** @return array<int, array<string, mixed>> */
    public function all(int $limit = 50, int $offset = 0, string $query = ''): array
    {
        $params = [':limit' => $limit, ':offset' => $offset];
        $where = $this->adminSearchWhere($query, $params, true);

        return $this->db->fetchAll(
            'SELECT id, username, email, role, reputation, banned_at, deleted_at, created_at FROM users' . $where . ' ORDER BY id DESC LIMIT :limit OFFSET :offset',
            $params
        );
    }

    public function setBanned(int $id, bool $banned): bool
    {
        if ($id < 1) {
            return false;
        }

        $updated = $this->db->query(
            'UPDATE users SET banned_at = ' . ($banned ? 'COALESCE(banned_at, NOW())' : 'NULL') . ', updated_at = NOW() WHERE id = :id AND deleted_at IS NULL',
            [':id' => $id]
        )->rowCount();

        return $updated > 0;
    }

    public function removeEngagementFootprint(int $userId): void
    {
        if ($userId < 1) {
            return;
        }

        $this->db->transaction(function () use ($userId): void {
            $this->removeUserVotes($userId);
            $this->db->execute('DELETE FROM post_reactions WHERE user_id = :user', [':user' => $userId]);
            $this->deleteInteractionReputationEventsByActor($userId);
        });
    }

    /** @return array<string, mixed>|null */
    public function adminDelete(int $id, bool $keepContent): ?array
    {
        if ($id < 1) {
            return null;
        }

        return $this->db->transaction(function () use ($id, $keepContent): ?array {
            $user = $this->db->fetch('SELECT id, username, email, role, deleted_at FROM users WHERE id = :id LIMIT 1 FOR UPDATE', [':id' => $id]);
            if ($user === null || !empty($user['deleted_at'])) {
                return null;
            }

            if (!$keepContent) {
                $this->removeUserContent($id);
            }

            $deletedUsername = $this->uniqueDeletedUsername($id);
            $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]);

            $this->db->execute('DELETE FROM user_tokens WHERE user_id = :user', [':user' => $id]);
            $this->db->execute('DELETE FROM notifications WHERE user_id = :user', [':user' => $id]);
            $this->removeUserVotes($id);
            $this->db->execute('DELETE FROM post_reactions WHERE user_id = :user OR target_user_id = :user', [':user' => $id]);
            $this->deleteInteractionReputationEventsByActor($id);
            $this->db->execute('DELETE FROM flags WHERE user_id = :user', [':user' => $id]);
            $this->db->execute('DELETE FROM reputation_events WHERE user_id = :user', [':user' => $id]);
            $this->db->execute('DELETE FROM user_badges WHERE user_id = :user', [':user' => $id]);
            $this->db->execute(
                'UPDATE users
                 SET username = :username,
                     email = :email,
                     password_hash = :password_hash,
                     role = "user",
                     reputation = 0,
                     bio = NULL,
                     website_url = "",
                     expertise = "",
                     credentials = "",
                     avatar_path = NULL,
                     email_verified_at = NULL,
                     banned_at = NULL,
                     deleted_at = NOW(),
                     two_factor_secret = "",
                     two_factor_enabled_at = NULL,
                     updated_at = NOW()
                 WHERE id = :id',
                [
                    ':username' => $deletedUsername,
                    ':email' => $deletedUsername . '@deleted.local',
                    ':password_hash' => $hash,
                    ':id' => $id,
                ]
            );

            return [
                'id' => $id,
                'username' => (string) $user['username'],
                'deleted_username' => $deletedUsername,
                'kept_content' => $keepContent,
            ];
        });
    }

    public function hasOtherAdministrator(int $id): bool
    {
        return $this->db->fetch(
            'SELECT id FROM users WHERE role = "administrator" AND deleted_at IS NULL AND banned_at IS NULL AND id <> :id LIMIT 1',
            [':id' => $id]
        ) !== null;
    }

    /** @return array<int, array<string, mixed>> */
    public function publicUsers(int $limit = 100, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT u.id, u.username, u.role, u.reputation, u.avatar_path, u.created_at,
                    COUNT(DISTINCT q.id) AS question_count,
                    COUNT(DISTINCT CASE WHEN aq.id IS NOT NULL THEN a.id END) AS answer_count
             FROM users u
             LEFT JOIN questions q ON q.user_id = u.id AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
             LEFT JOIN answers a ON a.user_id = u.id AND a.deleted_at IS NULL
             LEFT JOIN questions aq ON aq.id = a.question_id AND aq.deleted_at IS NULL' . $this->approvalFilter('aq') . '
             WHERE u.deleted_at IS NULL
               AND u.banned_at IS NULL
             GROUP BY u.id, u.username, u.role, u.reputation, u.avatar_path, u.created_at
             ORDER BY u.reputation DESC, u.created_at DESC
             LIMIT :limit OFFSET :offset',
            [':limit' => $limit, ':offset' => $offset]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function sitemapUsers(): array
    {
        return $this->db->fetchAll(
            'SELECT u.username, COALESCE(MAX(q.updated_at), MAX(CASE WHEN aq.id IS NOT NULL THEN a.updated_at END), u.updated_at, u.created_at) AS updated_at
             FROM users u
             LEFT JOIN questions q ON q.user_id = u.id AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
             LEFT JOIN answers a ON a.user_id = u.id AND a.deleted_at IS NULL
             LEFT JOIN questions aq ON aq.id = a.question_id AND aq.deleted_at IS NULL' . $this->approvalFilter('aq') . '
             WHERE u.deleted_at IS NULL
               AND u.banned_at IS NULL
             GROUP BY u.id, u.username, u.role, u.reputation, u.avatar_path, u.created_at
             HAVING COUNT(DISTINCT q.id) > 0
                OR COUNT(DISTINCT CASE WHEN aq.id IS NOT NULL THEN a.id END) > 0
                 OR TRIM(MAX(COALESCE(u.bio, ""))) <> ""
                 OR TRIM(MAX(COALESCE(u.expertise, ""))) <> ""
                 OR TRIM(MAX(COALESCE(u.credentials, ""))) <> ""
                 OR TRIM(MAX(COALESCE(u.website_url, ""))) <> ""
                 OR MAX(COALESCE(u.avatar_path, "")) <> ""
             ORDER BY updated_at DESC
             LIMIT 50000'
        );
    }

    /** @param array<string, mixed> $params */
    private function adminSearchWhere(string $query, array &$params, bool $includeDeleted): string
    {
        $conditions = [];
        if (!$includeDeleted) {
            $conditions[] = 'deleted_at IS NULL';
        }

        $query = trim($query);
        if ($query !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
            $params[':admin_user_query_username'] = '%' . $escaped . '%';
            $params[':admin_user_query_email'] = '%' . $escaped . '%';
            $conditions[] = '(username LIKE :admin_user_query_username OR email LIKE :admin_user_query_email)';
        }

        return $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
    }

    private function uniqueDeletedUsername(int $id): string
    {
        do {
            $candidate = 'deleted' . $id . random_int(1000, 999999);
        } while ($this->db->fetch('SELECT id FROM users WHERE username = :username LIMIT 1', [':username' => $candidate]) !== null);

        return $candidate;
    }

    private function removeUserContent(int $userId): void
    {
        $questionIds = $this->idsFromRows($this->db->fetchAll('SELECT id FROM questions WHERE user_id = :user AND deleted_at IS NULL', [':user' => $userId]));
        $answerIds = array_values(array_unique(array_merge(
            $this->idsFromRows($this->db->fetchAll('SELECT id FROM answers WHERE user_id = :user AND deleted_at IS NULL', [':user' => $userId])),
            $this->answerIdsForQuestions($questionIds)
        )));
        $commentIds = array_values(array_unique(array_merge(
            $this->idsFromRows($this->db->fetchAll('SELECT id FROM comments WHERE user_id = :user', [':user' => $userId])),
            $this->commentIdsForQuestions($questionIds),
            $this->commentIdsForAnswers($answerIds)
        )));

        $this->deleteReputationFootprint($questionIds, $answerIds, $commentIds);

        if ($questionIds !== []) {
            $params = [];
            $in = $this->inParams($questionIds, 'delete_user_question', $params);
            $this->db->execute("UPDATE questions SET deleted_at = NOW(), accepted_answer_id = NULL, updated_at = NOW() WHERE id IN ({$in})", $params);
        }

        if ($answerIds !== []) {
            $params = [];
            $in = $this->inParams($answerIds, 'delete_user_answer', $params);
            $this->db->execute("UPDATE answers SET deleted_at = NOW(), is_accepted = 0, updated_at = NOW() WHERE id IN ({$in})", $params);
            $this->db->execute("UPDATE questions SET accepted_answer_id = NULL, updated_at = NOW() WHERE accepted_answer_id IN ({$in})", $params);
        }

        if ($commentIds !== []) {
            $params = [];
            $in = $this->inParams($commentIds, 'delete_user_comment', $params);
            $this->db->execute("DELETE FROM comments WHERE id IN ({$in})", $params);
        }

        $this->deletePostReferences('votes', 'question', $questionIds, 'delete_user_question_vote');
        $this->deletePostReferences('votes', 'answer', $answerIds, 'delete_user_answer_vote');
        $this->deletePostReferences('votes', 'comment', $commentIds, 'delete_user_comment_vote');
        $this->deletePostReferences('flags', 'question', $questionIds, 'delete_user_question_flag');
        $this->deletePostReferences('flags', 'answer', $answerIds, 'delete_user_answer_flag');
        $this->deletePostReferences('flags', 'comment', $commentIds, 'delete_user_comment_flag');
        $this->deletePostReferences('post_reactions', 'question', $questionIds, 'delete_user_question_reaction');
        $this->deletePostReferences('post_reactions', 'answer', $answerIds, 'delete_user_answer_reaction');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, int>
     */
    private function idsFromRows(array $rows): array
    {
        return array_values(array_unique(array_map(static fn (array $row): int => (int) $row['id'], $rows)));
    }

    /** @param array<int, int> $questionIds @return array<int, int> */
    private function answerIdsForQuestions(array $questionIds): array
    {
        if ($questionIds === []) {
            return [];
        }

        $params = [];
        $in = $this->inParams($questionIds, 'delete_user_question_answer', $params);

        return $this->idsFromRows($this->db->fetchAll("SELECT id FROM answers WHERE question_id IN ({$in})", $params));
    }

    /** @param array<int, int> $questionIds @return array<int, int> */
    private function commentIdsForQuestions(array $questionIds): array
    {
        if ($questionIds === []) {
            return [];
        }

        $params = [];
        $in = $this->inParams($questionIds, 'delete_user_question_comment', $params);

        return $this->idsFromRows($this->db->fetchAll("SELECT id FROM comments WHERE question_id IN ({$in})", $params));
    }

    /** @param array<int, int> $answerIds @return array<int, int> */
    private function commentIdsForAnswers(array $answerIds): array
    {
        if ($answerIds === []) {
            return [];
        }

        $params = [];
        $in = $this->inParams($answerIds, 'delete_user_answer_comment', $params);

        return $this->idsFromRows($this->db->fetchAll("SELECT id FROM comments WHERE answer_id IN ({$in})", $params));
    }

    /**
     * @param array<int, int> $questionIds
     * @param array<int, int> $answerIds
     * @param array<int, int> $commentIds
     */
    private function deleteReputationFootprint(array $questionIds, array $answerIds, array $commentIds): void
    {
        $conditions = [];
        $params = [];

        if ($questionIds !== []) {
            $questionIn = $this->inParams($questionIds, 'delete_user_rep_question', $params);
            $conditions[] = "(source_type = 'question' AND source_id IN ({$questionIn}))";
        }

        if ($answerIds !== []) {
            $answerIn = $this->inParams($answerIds, 'delete_user_rep_answer', $params);
            $conditions[] = "(source_type = 'answer' AND source_id IN ({$answerIn}))";
        }

        if ($commentIds !== []) {
            $commentIn = $this->inParams($commentIds, 'delete_user_rep_comment', $params);
            $conditions[] = "(source_type = 'comment' AND source_id IN ({$commentIn}))";
        }

        if ($conditions === []) {
            return;
        }

        $where = implode(' OR ', $conditions);
        $affectedUsers = $this->idsFromRows($this->db->fetchAll("SELECT DISTINCT user_id AS id FROM reputation_events WHERE {$where}", $params));
        $this->db->execute("DELETE FROM reputation_events WHERE {$where}", $params);
        $this->recalculateReputation($affectedUsers);
    }

    /** @param array<int, int> $ids */
    private function deletePostReferences(string $table, string $postType, array $ids, string $prefix): void
    {
        if ($ids === [] || !in_array($table, ['votes', 'flags', 'post_reactions'], true)) {
            return;
        }

        $params = [':post_type' => $postType];
        $in = $this->inParams($ids, $prefix, $params);
        $this->db->execute("DELETE FROM {$table} WHERE post_type = :post_type AND post_id IN ({$in})", $params);
    }

    /** @param array<int, int> $userIds */
    private function recalculateReputation(array $userIds): void
    {
        foreach (array_values(array_unique($userIds)) as $userId) {
            $total = $this->db->fetch('SELECT COALESCE(SUM(points), 0) AS total FROM reputation_events WHERE user_id = :user', [':user' => $userId]);
            $this->db->execute(
                'UPDATE users SET reputation = GREATEST(0, :reputation), updated_at = NOW() WHERE id = :id',
                [':reputation' => (int) ($total['total'] ?? 0), ':id' => $userId]
            );
        }
    }

    private function removeUserVotes(int $userId): void
    {
        $rows = $this->db->fetchAll(
            'SELECT post_type, post_id FROM votes WHERE user_id = :user',
            [':user' => $userId]
        );

        $this->db->execute('DELETE FROM votes WHERE user_id = :user', [':user' => $userId]);

        $seen = [];
        foreach ($rows as $row) {
            $postType = (string) $row['post_type'];
            $postId = (int) $row['post_id'];
            $key = $postType . ':' . $postId;
            if ($postId < 1 || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $table = match ($postType) {
                'question' => 'questions',
                'answer' => 'answers',
                'comment' => 'comments',
                default => null,
            };

            if ($table === null) {
                continue;
            }

            $this->db->execute(
                "UPDATE {$table}
                 SET votes = (
                     SELECT COALESCE(SUM(v.value), 0)
                     FROM votes v
                     WHERE v.post_type = :post_type AND v.post_id = :sum_post_id
                 )
                 WHERE id = :target_post_id",
                [
                    ':post_type' => $postType,
                    ':sum_post_id' => $postId,
                    ':target_post_id' => $postId,
                ]
            );
        }
    }

    private function deleteInteractionReputationEventsByActor(int $actorUserId): void
    {
        if ($actorUserId < 1) {
            return;
        }

        $pattern = '%_actor_' . $actorUserId;
        $affectedUsers = $this->idsFromRows($this->db->fetchAll(
            'SELECT DISTINCT user_id AS id
             FROM reputation_events
             WHERE reason LIKE :pattern',
            [':pattern' => $pattern]
        ));
        if ($affectedUsers === []) {
            return;
        }

        $this->db->execute(
            'DELETE FROM reputation_events WHERE reason LIKE :pattern',
            [':pattern' => $pattern]
        );
        $this->recalculateReputation($affectedUsers);
    }

    private function approvalFilter(string $alias): string
    {
        if ($this->approvalColumnAvailable !== null) {
            return $this->approvalColumnAvailable ? ' AND ' . $alias . '.approval_status = "approved"' : '';
        }

        try {
            $this->approvalColumnAvailable = $this->db->fetch(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'questions' AND COLUMN_NAME = 'approval_status' LIMIT 1"
            ) !== null;
        } catch (Throwable) {
            $this->approvalColumnAvailable = false;
        }

        return $this->approvalColumnAvailable ? ' AND ' . $alias . '.approval_status = "approved"' : '';
    }

    private function commentDeletedFilter(string $alias): string
    {
        static $available = null;
        if ($available === null) {
            try {
                $available = $this->db->fetch(
                    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comments' AND COLUMN_NAME = 'deleted_at' LIMIT 1"
                ) !== null;
            } catch (Throwable) {
                $available = false;
            }
        }

        return $available ? ' AND ' . $alias . '.deleted_at IS NULL' : '';
    }
}

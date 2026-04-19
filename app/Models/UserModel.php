<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use PDOException;
use RuntimeException;
use Throwable;

final readonly class UserModel
{
    public function __construct(private Database $db) {}

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return $this->db->fetch('SELECT * FROM users WHERE email = :email LIMIT 1', [':email' => $email]);
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->db->fetch('SELECT id, username, email, role, reputation, bio, website_url, expertise, credentials, avatar_path, email_verified_at, created_at FROM users WHERE id = :id LIMIT 1', [':id' => $id]);
    }

    /** @return array<string, mixed>|null */
    public function findSessionStateById(int $id): ?array
    {
        $row = $this->db->fetch(
            'SELECT id, username, email, role, reputation, bio, website_url, expertise, credentials, avatar_path, email_verified_at, created_at, password_hash
             FROM users
             WHERE id = :id
             LIMIT 1',
            [':id' => $id]
        );
        if ($row === null) {
            return null;
        }

        $row['auth_fingerprint'] = hash('sha256', (string) ($row['password_hash'] ?? ''));
        unset($row['password_hash']);

        return $row;
    }

    /** @return array<string, mixed>|null */
    public function findByUsername(string $username): ?array
    {
        return $this->db->fetch('SELECT id, username, email, role, reputation, bio, website_url, expertise, credentials, avatar_path, email_verified_at, created_at FROM users WHERE username = :username LIMIT 1', [':username' => $username]);
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

    public function count(): int
    {
        $row = $this->db->fetch('SELECT COUNT(*) AS total FROM users');
        return (int) ($row['total'] ?? 0);
    }

    /** @return array{top_reputation: int, total_answers: int, total_questions: int} */
    public function directorySummary(): array
    {
        $row = $this->db->fetch(
            'SELECT
                COALESCE(MAX(u.reputation), 0) AS top_reputation,
                (SELECT COUNT(*) FROM answers a JOIN questions q ON q.id = a.question_id WHERE a.deleted_at IS NULL AND q.deleted_at IS NULL) AS total_answers,
                (SELECT COUNT(*) FROM questions q WHERE q.deleted_at IS NULL) AS total_questions
             FROM users u'
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
                 WHERE id = :id
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
            'SELECT t.*, u.email, u.username
             FROM user_tokens t
             JOIN users u ON u.id = t.user_id
             WHERE t.type = :type AND t.token_hash = :hash AND t.expires_at > NOW()
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
                'SELECT id, user_id
                 FROM user_tokens
                 WHERE type = :type AND token_hash = :hash AND expires_at > NOW()
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
                'SELECT id, user_id
                 FROM user_tokens
                 WHERE type = :type AND token_hash = :hash AND expires_at > NOW()
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
                     WHERE role = :role AND id <> :id
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
            'SELECT id FROM reputation_events WHERE user_id = :user AND source_type = :source_type AND source_id = :source_id AND reason = :reason LIMIT 1',
            [':user' => $userId, ':source_type' => $sourceType, ':source_id' => $sourceId, ':reason' => $reason]
        );
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
        $questions = $this->db->fetch('SELECT COUNT(*) AS total FROM questions WHERE user_id = :user AND deleted_at IS NULL', [':user' => $userId]);
        $answers = $this->db->fetch(
            'SELECT COUNT(*) AS total
             FROM answers a
             JOIN questions q ON q.id = a.question_id
             WHERE a.user_id = :user AND a.deleted_at IS NULL AND q.deleted_at IS NULL',
            [':user' => $userId]
        );
        $comments = $this->db->fetch(
            'SELECT COUNT(*) AS total
             FROM comments c
             LEFT JOIN answers a ON a.id = c.answer_id
             LEFT JOIN questions q ON q.id = COALESCE(c.question_id, a.question_id)
             WHERE c.user_id = :user AND q.deleted_at IS NULL AND (c.answer_id IS NULL OR a.deleted_at IS NULL)',
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
             WHERE q.user_id = :user AND q.deleted_at IS NULL
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
             WHERE a.user_id = :user AND a.deleted_at IS NULL AND q.deleted_at IS NULL
             ORDER BY a.created_at DESC
             LIMIT :limit',
            [':user' => $userId, ':limit' => $limit]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function recentActivity(int $userId, int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT *
             FROM (
                SELECT 'question' AS type, 'Asked' AS label, q.id AS question_id, q.title AS title, q.slug AS slug, q.body AS summary, q.created_at AS created_at
                FROM questions q
                WHERE q.user_id = :question_user AND q.deleted_at IS NULL
                UNION ALL
                SELECT 'answer' AS type, 'Answered' AS label, q.id AS question_id, q.title AS title, q.slug AS slug, a.body AS summary, a.created_at AS created_at
                FROM answers a
                JOIN questions q ON q.id = a.question_id
                WHERE a.user_id = :answer_user AND a.deleted_at IS NULL AND q.deleted_at IS NULL
                UNION ALL
                SELECT 'comment' AS type, 'Commented' AS label, q.id AS question_id, q.title AS title, q.slug AS slug, c.body AS summary, c.created_at AS created_at
                FROM comments c
                LEFT JOIN answers a ON a.id = c.answer_id
                LEFT JOIN questions q ON q.id = COALESCE(c.question_id, a.question_id)
                WHERE c.user_id = :comment_user AND q.deleted_at IS NULL AND (c.answer_id IS NULL OR a.deleted_at IS NULL)
             ) activity
             ORDER BY created_at DESC
             LIMIT :limit",
            [':question_user' => $userId, ':answer_user' => $userId, ':comment_user' => $userId, ':limit' => $limit]
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
        $rows = $this->db->fetchAll(
            "SELECT activity.question_id, activity.user_id, u.username, u.avatar_path, MAX(activity.last_activity) AS last_activity
             FROM (
                SELECT q.id AS question_id, q.user_id, q.created_at AS last_activity
                FROM questions q
                WHERE q.id IN ({$questionIn}) AND q.deleted_at IS NULL
                UNION ALL
                SELECT a.question_id, a.user_id, a.created_at AS last_activity
                FROM answers a
                JOIN questions q ON q.id = a.question_id
                WHERE a.question_id IN ({$answerIn}) AND a.deleted_at IS NULL AND q.deleted_at IS NULL
                UNION ALL
                SELECT COALESCE(c.question_id, a.question_id) AS question_id, c.user_id, c.created_at AS last_activity
                FROM comments c
                LEFT JOIN answers a ON a.id = c.answer_id
                LEFT JOIN questions q ON q.id = COALESCE(c.question_id, a.question_id)
                WHERE (c.question_id IN ({$commentQuestionIn}) OR a.question_id IN ({$commentAnswerIn}))
                  AND q.deleted_at IS NULL
                  AND (c.answer_id IS NULL OR a.deleted_at IS NULL)
             ) activity
             JOIN users u ON u.id = activity.user_id
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
    public function all(int $limit = 50): array
    {
        return $this->db->fetchAll('SELECT id, username, email, role, reputation, created_at FROM users ORDER BY id DESC LIMIT :limit', [':limit' => $limit]);
    }

    /** @return array<int, array<string, mixed>> */
    public function publicUsers(int $limit = 100, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT u.id, u.username, u.role, u.reputation, u.avatar_path, u.created_at,
                    COUNT(DISTINCT q.id) AS question_count,
                    COUNT(DISTINCT a.id) AS answer_count
             FROM users u
             LEFT JOIN questions q ON q.user_id = u.id AND q.deleted_at IS NULL
             LEFT JOIN answers a ON a.user_id = u.id AND a.deleted_at IS NULL
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
            'SELECT u.username, COALESCE(MAX(q.updated_at), MAX(a.updated_at), u.updated_at, u.created_at) AS updated_at
             FROM users u
             LEFT JOIN questions q ON q.user_id = u.id AND q.deleted_at IS NULL
             LEFT JOIN answers a ON a.user_id = u.id AND a.deleted_at IS NULL
             GROUP BY u.id, u.username, u.role, u.reputation, u.avatar_path, u.created_at
             HAVING COUNT(DISTINCT q.id) > 0
                 OR COUNT(DISTINCT a.id) > 0
                 OR TRIM(MAX(COALESCE(u.bio, ""))) <> ""
                 OR TRIM(MAX(COALESCE(u.expertise, ""))) <> ""
                 OR TRIM(MAX(COALESCE(u.credentials, ""))) <> ""
                 OR TRIM(MAX(COALESCE(u.website_url, ""))) <> ""
                 OR MAX(COALESCE(u.avatar_path, "")) <> ""
             ORDER BY updated_at DESC
             LIMIT 50000'
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use PDOException;

final class ReactionModel
{
    private bool $schemaReady = false;

    public function __construct(private readonly Database $db) {}

    /** @return array<int, array<string, mixed>> */
    public function allTypes(): array
    {
        $this->ensureSchema();

        return $this->db->fetchAll('SELECT * FROM reaction_types ORDER BY sort_order ASC, id ASC');
    }

    /** @return array<int, array<string, mixed>> */
    public function activeTypes(): array
    {
        $this->ensureSchema();

        return $this->db->fetchAll('SELECT * FROM reaction_types WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
    }

    public function createType(string $name, string $slug, ?string $iconPath, int $reputationDelta, int $sortOrder, bool $active): bool
    {
        $this->ensureSchema();
        try {
            $this->db->execute(
                'INSERT INTO reaction_types (name, slug, icon_path, reputation_delta, sort_order, is_active, created_at, updated_at)
                 VALUES (:name, :slug, :icon, :delta, :sort, :active, NOW(), NOW())',
                [':name' => $name, ':slug' => $slug, ':icon' => $iconPath, ':delta' => $reputationDelta, ':sort' => $sortOrder, ':active' => $active ? 1 : 0]
            );
        } catch (PDOException) {
            return false;
        }

        return true;
    }

    public function updateType(int $id, string $name, string $slug, ?string $iconPath, int $reputationDelta, int $sortOrder, bool $active): bool
    {
        $this->ensureSchema();
        $current = $this->db->fetch('SELECT icon_path FROM reaction_types WHERE id = :id LIMIT 1', [':id' => $id]);
        if ($current === null) {
            return false;
        }

        try {
            $this->db->execute(
                'UPDATE reaction_types
                 SET name = :name, slug = :slug, icon_path = :icon, reputation_delta = :delta, sort_order = :sort, is_active = :active, updated_at = NOW()
                 WHERE id = :id',
                [
                    ':id' => $id,
                    ':name' => $name,
                    ':slug' => $slug,
                    ':icon' => $iconPath ?? $current['icon_path'],
                    ':delta' => $reputationDelta,
                    ':sort' => $sortOrder,
                    ':active' => $active ? 1 : 0,
                ]
            );
        } catch (PDOException) {
            return false;
        }

        return true;
    }

    public function deleteType(int $id): bool
    {
        $this->ensureSchema();
        try {
            return $this->db->query('DELETE FROM reaction_types WHERE id = :id', [':id' => $id])->rowCount() > 0;
        } catch (PDOException) {
            return false;
        }
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function forTargets(array $targets, int $userId): array
    {
        $this->ensureSchema();
        $types = $this->activeTypes();
        $result = [];
        foreach ($targets as $target) {
            $postType = (string) ($target['type'] ?? '');
            $postId = (int) ($target['id'] ?? 0);
            if (!$this->validPostType($postType) || $postId < 1) {
                continue;
            }

            $key = $this->targetKey($postType, $postId);
            foreach ($types as $type) {
                $result[$key][(int) $type['id']] = array_merge($type, ['count' => 0, 'reacted' => false]);
            }
        }

        if ($result === []) {
            return [];
        }

        foreach (['question', 'answer'] as $postType) {
            $ids = array_values(array_unique(array_map(
                static fn (array $target): int => (string) ($target['type'] ?? '') === $postType ? (int) ($target['id'] ?? 0) : 0,
                $targets
            )));
            $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
            if ($ids === []) {
                continue;
            }

            $params = [':post_type' => $postType, ':user' => $userId];            $in = $this->inParams($ids, 'reaction_target', $params);
            $rows = $this->db->fetchAll(
                "SELECT pr.post_type, pr.post_id, pr.reaction_type_id, COUNT(*) AS total,
                        MAX(CASE WHEN pr.user_id = :user THEN 1 ELSE 0 END) AS reacted
                 FROM post_reactions pr
                 JOIN reaction_types rt ON rt.id = pr.reaction_type_id AND rt.is_active = 1
                 WHERE pr.post_type = :post_type AND pr.post_id IN ({$in})
                 GROUP BY pr.post_type, pr.post_id, pr.reaction_type_id",
                $params
            );

            foreach ($rows as $row) {
                $key = $this->targetKey((string) $row['post_type'], (int) $row['post_id']);
                $reactionId = (int) $row['reaction_type_id'];
                if (!isset($result[$key][$reactionId])) {
                    continue;
                }

                $result[$key][$reactionId]['count'] = (int) $row['total'];
                $result[$key][$reactionId]['reacted'] = (int) $row['reacted'] === 1;
            }
        }

        return array_map(static fn (array $items): array => array_values($items), $result);
    }

    /** @return array{id: int, slug: string, name: string, reputation_delta: int}|null */
    public function reactionType(int $id): ?array
    {
        $this->ensureSchema();
        $row = $this->db->fetch('SELECT id, slug, name, reputation_delta FROM reaction_types WHERE id = :id AND is_active = 1 LIMIT 1', [':id' => $id]);

        return $row === null ? null : ['id' => (int) $row['id'], 'slug' => (string) $row['slug'], 'name' => (string) $row['name'], 'reputation_delta' => (int) $row['reputation_delta']];
    }

    public function hasReaction(int $userId, string $postType, int $postId, int $reactionTypeId): bool
    {
        $this->ensureSchema();
        $row = $this->db->fetch(
            'SELECT id FROM post_reactions WHERE user_id = :user AND post_type = :post_type AND post_id = :post AND reaction_type_id = :reaction LIMIT 1',
            [':user' => $userId, ':post_type' => $postType, ':post' => $postId, ':reaction' => $reactionTypeId]
        );

        return $row !== null;
    }

    public function addReaction(int $userId, string $postType, int $postId, int $reactionTypeId, int $targetUserId): void
    {
        $this->ensureSchema();
        $this->db->execute(
            'INSERT IGNORE INTO post_reactions (user_id, reaction_type_id, post_type, post_id, target_user_id, created_at, updated_at)
             VALUES (:user, :reaction, :post_type, :post, :target_user, NOW(), NOW())',
            [':user' => $userId, ':reaction' => $reactionTypeId, ':post_type' => $postType, ':post' => $postId, ':target_user' => $targetUserId]
        );
    }

    public function removeReaction(int $userId, string $postType, int $postId, int $reactionTypeId): void
    {
        $this->ensureSchema();
        $this->db->execute(
            'DELETE FROM post_reactions WHERE user_id = :user AND post_type = :post_type AND post_id = :post AND reaction_type_id = :reaction',
            [':user' => $userId, ':post_type' => $postType, ':post' => $postId, ':reaction' => $reactionTypeId]
        );
    }

    public function targetUserId(string $postType, int $postId): ?int
    {
        $row = $this->reactionTargetRow($postType, $postId);

        return $row === null ? null : (int) $row['user_id'];
    }

    /** @return array{target_user_id: int, reputation_delta: int, slug: string}|null */
    public function toggleForUser(int $userId, string $postType, int $postId, int $reactionTypeId): ?array
    {
        $this->ensureSchema();
        if ($userId < 1 || !$this->validPostType($postType) || $postId < 1 || $reactionTypeId < 1) {
            return null;
        }

        return $this->db->transaction(function () use ($userId, $postType, $postId, $reactionTypeId): ?array {
            $type = $this->db->fetch(
                'SELECT id, slug, reputation_delta
                 FROM reaction_types
                 WHERE id = :id AND is_active = 1
                 LIMIT 1 FOR UPDATE',
                [':id' => $reactionTypeId]
            );
            if ($type === null) {
                return null;
            }

            $target = $this->reactionTargetRow($postType, $postId, true);
            if ($target === null) {
                return null;
            }

            $targetUserId = (int) $target['user_id'];
            if ($targetUserId === $userId) {
                return null;
            }

            $existing = $this->db->fetch(
                'SELECT id
                 FROM post_reactions
                 WHERE user_id = :user AND post_type = :post_type AND post_id = :post AND reaction_type_id = :reaction
                 LIMIT 1 FOR UPDATE',
                [':user' => $userId, ':post_type' => $postType, ':post' => $postId, ':reaction' => $reactionTypeId]
            );

            $delta = 0;
            if ($existing !== null) {
                $delta = $this->db->query(
                    'DELETE FROM post_reactions WHERE id = :id',
                    [':id' => (int) $existing['id']]
                )->rowCount() > 0 ? -1 * (int) $type['reputation_delta'] : 0;
            } else {
                $delta = $this->db->query(
                    'INSERT INTO post_reactions (user_id, reaction_type_id, post_type, post_id, target_user_id, created_at, updated_at)
                     VALUES (:user, :reaction, :post_type, :post, :target_user, NOW(), NOW())',
                    [':user' => $userId, ':reaction' => $reactionTypeId, ':post_type' => $postType, ':post' => $postId, ':target_user' => $targetUserId]
                )->rowCount() > 0 ? (int) $type['reputation_delta'] : 0;
            }

            if ($delta === 0) {
                return null;
            }

            return [
                'target_user_id' => $targetUserId,
                'reputation_delta' => $delta,
                'slug' => (string) $type['slug'],
            ];
        });
    }

    public function targetKey(string $postType, int $postId): string
    {
        return $postType . ':' . $postId;
    }

    private function validPostType(string $postType): bool
    {
        return in_array($postType, ['question', 'answer'], true);
    }

    /** @return array<string, mixed>|null */
    private function reactionTargetRow(string $postType, int $postId, bool $forUpdate = false): ?array
    {
        $lock = $forUpdate ? ' FOR UPDATE' : '';

        return match ($postType) {
            'question' => $this->db->fetch('SELECT user_id FROM questions WHERE id = :id AND deleted_at IS NULL LIMIT 1' . $lock, [':id' => $postId]),
            'answer' => $this->db->fetch('SELECT a.user_id FROM answers a JOIN questions q ON q.id = a.question_id WHERE a.id = :id AND a.deleted_at IS NULL AND q.deleted_at IS NULL LIMIT 1' . $lock, [':id' => $postId]),
            default => null,
        };
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

    private function ensureSchema(): void
    {
        if ($this->schemaReady) {
            return;
        }

        try {
            $this->db->execute(
                'CREATE TABLE IF NOT EXISTS reaction_types (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $this->db->execute(
                'CREATE TABLE IF NOT EXISTS post_reactions (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $count = $this->db->fetch('SELECT COUNT(*) AS total FROM reaction_types');
            if ((int) ($count['total'] ?? 0) === 0) {
                foreach ([['Useful', 'useful', 2, 10], ['Thanks', 'thanks', 1, 20], ['Insightful', 'insightful', 3, 30]] as $seed) {
                    $this->db->execute(
                        'INSERT INTO reaction_types (name, slug, icon_path, reputation_delta, sort_order, is_active, created_at, updated_at)
                         VALUES (:name, :slug, NULL, :delta, :sort, 1, NOW(), NOW())',
                        [':name' => $seed[0], ':slug' => $seed[1], ':delta' => $seed[2], ':sort' => $seed[3]]
                    );
                }
            }
        } catch (PDOException) {
        }

        $this->schemaReady = true;
    }
}

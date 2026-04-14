<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use PDOException;

final readonly class GamificationLevelModel
{
    public function __construct(private Database $db) {}

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        try {
            return $this->db->fetchAll('SELECT * FROM gamification_levels ORDER BY level_number ASC, min_reputation ASC');
        } catch (PDOException) {
            return [];
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function active(): array
    {
        try {
            return $this->db->fetchAll('SELECT * FROM gamification_levels WHERE is_active = 1 ORDER BY min_reputation ASC, level_number ASC');
        } catch (PDOException) {
            return [];
        }
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        try {
            return $this->db->fetch('SELECT * FROM gamification_levels WHERE id = :id LIMIT 1', [':id' => $id]);
        } catch (PDOException) {
            return null;
        }
    }

    public function create(int $level, string $title, int $minReputation, string $description, ?string $iconPath, bool $active): bool
    {
        try {
            $this->db->execute(
                'INSERT INTO gamification_levels (level_number, title, min_reputation, description, icon_path, is_active, created_at, updated_at)
                 VALUES (:level, :title, :min_reputation, :description, :icon_path, :active, NOW(), NOW())',
                [
                    ':level' => $level,
                    ':title' => $title,
                    ':min_reputation' => $minReputation,
                    ':description' => $description,
                    ':icon_path' => $iconPath,
                    ':active' => $active ? 1 : 0,
                ]
            );
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    public function update(int $id, int $level, string $title, int $minReputation, string $description, ?string $iconPath, bool $active): bool
    {
        $current = $this->find($id);
        if ($current === null) {
            return false;
        }

        try {
            $this->db->execute(
                'UPDATE gamification_levels
                 SET level_number = :level, title = :title, min_reputation = :min_reputation, description = :description, icon_path = :icon_path, is_active = :active, updated_at = NOW()
                 WHERE id = :id',
                [
                    ':id' => $id,
                    ':level' => $level,
                    ':title' => $title,
                    ':min_reputation' => $minReputation,
                    ':description' => $description,
                    ':icon_path' => $iconPath ?? $current['icon_path'],
                    ':active' => $active ? 1 : 0,
                ]
            );
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    public function delete(int $id): bool
    {
        try {
            $this->db->execute('DELETE FROM gamification_levels WHERE id = :id', [':id' => $id]);
            return true;
        } catch (PDOException) {
            return false;
        }
    }
}

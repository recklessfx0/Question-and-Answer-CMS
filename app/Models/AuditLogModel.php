<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class AuditLogModel
{
    public function __construct(private Database $db) {}

    /** @param array<string, mixed> $payload */
    public function record(int $actorId, string $action, string $targetType, int $targetId, array $payload = []): void
    {
        $this->db->execute(
            'INSERT INTO audit_logs (actor_id, action, target_type, target_id, payload, created_at)
             VALUES (:actor, :action, :target_type, :target_id, :payload, NOW())',
            [
                ':actor' => $actorId,
                ':action' => $action,
                ':target_type' => $targetType,
                ':target_id' => $targetId,
                ':payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            ]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function latest(int $limit = 100, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT a.*, u.username
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.actor_id
             ORDER BY a.created_at DESC LIMIT :limit OFFSET :offset',
            [':limit' => $limit, ':offset' => max(0, $offset)]
        );
    }

    public function count(): int
    {
        $row = $this->db->fetch('SELECT COUNT(*) AS total FROM audit_logs');

        return (int) ($row['total'] ?? 0);
    }
}

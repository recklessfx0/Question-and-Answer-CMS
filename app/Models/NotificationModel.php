<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final readonly class NotificationModel
{
    public function __construct(private Database $db) {}

    /** @param array<string, mixed> $payload */
    public function create(int $userId, string $type, array $payload): void
    {
        $this->db->execute(
            'INSERT INTO notifications (user_id, type, payload, created_at) VALUES (:user, :type, :payload, NOW())',
            [':user' => $userId, ':type' => $type, ':payload' => json_encode($payload, JSON_THROW_ON_ERROR)]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function unreadForUser(int $userId, int $limit = 25): array
    {
        return $this->normalizeRows($this->db->fetchAll(
            'SELECT * FROM notifications WHERE user_id = :user AND read_at IS NULL ORDER BY created_at DESC LIMIT :limit',
            [':user' => $userId, ':limit' => $limit]
        ));
    }

    /** @return array<int, array<string, mixed>> */
    public function forUser(int $userId, int $limit = 50): array
    {
        return $this->normalizeRows($this->db->fetchAll(
            'SELECT * FROM notifications WHERE user_id = :user ORDER BY created_at DESC LIMIT :limit',
            [':user' => $userId, ':limit' => $limit]
        ));
    }

    public function markAllRead(int $userId): void
    {
        $this->db->execute('UPDATE notifications SET read_at = NOW() WHERE user_id = :user AND read_at IS NULL', [':user' => $userId]);
    }

    /** @param array<int, array<string, mixed>> $rows @return array<int, array<string, mixed>> */
    private function normalizeRows(array $rows): array
    {
        foreach ($rows as &$row) {
            $payload = json_decode((string) ($row['payload'] ?? ''), true);
            $payload = is_array($payload) ? $payload : [];
            $payload['message'] = isset($payload['message']) && is_scalar($payload['message'])
                ? (string) $payload['message']
                : 'You have an update.';
            $payload['url'] = isset($payload['url']) && is_scalar($payload['url'])
                ? safe_internal_path((string) $payload['url'])
                : '';
            $row['payload_data'] = $payload;
        }
        unset($row);

        return $rows;
    }
}

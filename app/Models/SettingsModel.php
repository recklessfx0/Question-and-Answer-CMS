<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class SettingsModel
{
    public function __construct(private Database $db) {}

    public function get(string $key, string $default = ''): string
    {
        $row = $this->db->fetch('SELECT value FROM settings WHERE `key` = :key LIMIT 1', [':key' => $key]);
        return $row === null ? $default : (string) $row['value'];
    }

    public function set(string $key, string $value): void
    {
        $this->db->execute('INSERT INTO settings (`key`, value, updated_at) VALUES (:key, :value, NOW()) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()', [':key' => $key, ':value' => $value]);
    }

    /** @return array<string, string> */
    public function all(): array
    {
        $rows = $this->db->fetchAll('SELECT `key`, value FROM settings ORDER BY `key`');
        $settings = [];
        foreach ($rows as $row) {
            $settings[(string) $row['key']] = (string) $row['value'];
        }

        return $settings;
    }

    /** @param array<int, string> $keys @return array<string, string> */
    public function updatedAtByKeys(array $keys): array
    {
        $keys = array_values(array_unique(array_filter(array_map('strval', $keys), static fn (string $key): bool => $key !== '')));
        if ($keys === []) {
            return [];
        }

        $params = [];
        $placeholders = [];
        foreach ($keys as $index => $key) {
            $param = ':key' . $index;
            $params[$param] = $key;
            $placeholders[] = $param;
        }

        $rows = $this->db->fetchAll(
            'SELECT `key`, updated_at
             FROM settings
             WHERE `key` IN (' . implode(',', $placeholders) . ')',
            $params
        );

        $timestamps = [];
        foreach ($rows as $row) {
            $timestamps[(string) $row['key']] = (string) ($row['updated_at'] ?? '');
        }

        return $timestamps;
    }
}

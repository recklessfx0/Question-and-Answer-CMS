<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class SettingsModel
{
    /** @var array<string, string>|null */
    private ?array $snapshot = null;

    public function __construct(private Database $db, private bool $requestScoped = false) {}

    public function get(string $key, string $default = ''): string
    {
        if ($this->snapshot !== null) {
            return $this->snapshot[$key] ?? $default;
        }
        $row = $this->db->fetch('SELECT value FROM settings WHERE `key` = :key LIMIT 1', [':key' => $key]);
        return $row === null ? $default : (string) $row['value'];
    }

    public function set(string $key, string $value): void
    {
        $this->db->execute('INSERT INTO settings (`key`, value, updated_at) VALUES (:key, :value, NOW()) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()', [':key' => $key, ':value' => $value]);
        $this->snapshot = null;
    }

    /** @return array<string, string> */
    public function all(): array
    {
        if ($this->snapshot !== null) {
            return $this->snapshot;
        }
        $rows = $this->db->fetchAll('SELECT `key`, value FROM settings ORDER BY `key`');
        $settings = [];
        foreach ($rows as $row) {
            $settings[(string) $row['key']] = (string) $row['value'];
        }
        if ($this->requestScoped) {
            $this->snapshot = $settings;
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

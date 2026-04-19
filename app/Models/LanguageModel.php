<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final readonly class LanguageModel
{
    public function __construct(private Database $db) {}

    /** @return array<int, array<string, mixed>> */
    public function all(bool $activeOnly = false): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM languages ' . ($activeOnly ? 'WHERE is_active = 1 ' : '') . 'ORDER BY is_default DESC, name ASC'
        );
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        return $this->db->fetch('SELECT * FROM languages WHERE code = :code LIMIT 1', [':code' => $this->normalizeCode($code)]);
    }

    /** @return array<string, mixed>|null */
    public function defaultLanguage(): ?array
    {
        return $this->db->fetch('SELECT * FROM languages WHERE is_default = 1 LIMIT 1')
            ?? $this->db->fetch('SELECT * FROM languages WHERE code = "en" LIMIT 1')
            ?? $this->db->fetch('SELECT * FROM languages ORDER BY id ASC LIMIT 1');
    }

    public function create(string $code, string $name, bool $active, bool $default): bool
    {
        $code = $this->normalizeCode($code);
        $name = trim($name);
        if (!preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$/', $code) || mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            return false;
        }

        return $this->db->transaction(function () use ($code, $name, $active, $default): bool {
            if ($default) {
                $this->db->execute('UPDATE languages SET is_default = 0');
                $active = true;
            }
            $this->db->execute(
                'INSERT INTO languages (code, name, is_active, is_default, created_at, updated_at)
                 VALUES (:code, :name, :active, :default, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE name = VALUES(name), is_active = VALUES(is_active), is_default = VALUES(is_default), updated_at = NOW()',
                [':code' => $code, ':name' => $name, ':active' => $active ? 1 : 0, ':default' => $default ? 1 : 0]
            );

            return true;
        });
    }

    public function update(int $id, string $name, bool $active, bool $default): bool
    {
        $name = trim($name);
        if ($id < 1 || mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            return false;
        }

        return $this->db->transaction(function () use ($id, $name, $active, $default): bool {
            if ($default) {
                $this->db->execute('UPDATE languages SET is_default = 0');
                $active = true;
            }
            $this->db->execute(
                'UPDATE languages SET name = :name, is_active = :active, is_default = :default, updated_at = NOW() WHERE id = :id',
                [':name' => $name, ':active' => $active ? 1 : 0, ':default' => $default ? 1 : 0, ':id' => $id]
            );

            return true;
        });
    }

    /** @return array<string, string> */
    public function translationsForCode(string $code): array
    {
        $language = $this->findByCode($code);
        if ($language === null) {
            return [];
        }

        return $this->translationsForLanguage((int) $language['id']);
    }

    /** @return array<string, string> */
    public function translationsForLanguage(int $languageId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT translation_key, value FROM translations WHERE language_id = :language ORDER BY translation_key ASC',
            [':language' => $languageId]
        );
        $translations = [];
        foreach ($rows as $row) {
            $translations[(string) $row['translation_key']] = (string) $row['value'];
        }

        return $translations;
    }

    /** @param array<string, string> $translations */
    public function saveTranslations(int $languageId, array $translations): void
    {
        foreach ($translations as $key => $value) {
            if (!preg_match('/^[a-z0-9_.-]{2,120}$/', $key)) {
                continue;
            }
            $this->db->execute(
                'INSERT INTO translations (language_id, translation_key, value, updated_at)
                 VALUES (:language, :key, :value, NOW())
                 ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()',
                [':language' => $languageId, ':key' => $key, ':value' => trim($value)]
            );
        }
    }

    /** @param array<string, string> $defaults */
    public function seedDefaults(array $defaults): void
    {
        $hasDefault = $this->db->fetch('SELECT id FROM languages WHERE is_default = 1 LIMIT 1') !== null;
        $english = $this->findByCode('en');
        if ($english === null) {
            $this->create('en', 'English', true, !$hasDefault);
            $english = $this->findByCode('en');
        }
        if (!$hasDefault && $english !== null) {
            $this->update((int) $english['id'], (string) $english['name'], true, true);
        }

        if ($english !== null) {
            foreach ($defaults as $key => $value) {
                $this->db->execute(
                    'INSERT IGNORE INTO translations (language_id, translation_key, value, updated_at)
                     VALUES (:language, :key, :value, NOW())',
                    [':language' => (int) $english['id'], ':key' => $key, ':value' => $value]
                );
            }
        }
    }

    private function normalizeCode(string $code): string
    {
        return strtolower(str_replace('_', '-', trim($code)));
    }
}

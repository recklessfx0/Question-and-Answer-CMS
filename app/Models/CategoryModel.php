<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use PDOException;
use Throwable;

final class CategoryModel
{
    private ?bool $approvalColumnAvailable = null;
    private ?bool $duplicateColumnAvailable = null;

    public function __construct(private Database $db) {}

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT c.*, p.name AS parent_name
             FROM categories c
             LEFT JOIN categories p ON p.id = c.parent_id
             ORDER BY COALESCE(c.parent_id, c.id), c.parent_id IS NOT NULL, c.name'
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function parents(): array
    {
        return $this->db->fetchAll('SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name');
    }

    /** @return array<int, array<string, mixed>> */
    public function children(int $parentId): array
    {
        return $this->db->fetchAll('SELECT * FROM categories WHERE parent_id = :parent ORDER BY name', [':parent' => $parentId]);
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->fetch('SELECT * FROM categories WHERE slug = :slug LIMIT 1', [':slug' => $slug]);
    }

    public function create(?int $parentId, string $name, string $slug, string $description, string $seoTitle = '', string $seoDescription = '', string $intro = ''): bool
    {
        if (!$this->validParentId($parentId)) {
            return false;
        }

        try {
            $this->db->execute(
                'INSERT INTO categories (parent_id, name, slug, description, seo_title, seo_description, intro, created_at, updated_at)
                 VALUES (:parent_id, :name, :slug, :description, :seo_title, :seo_description, :intro, NOW(), NOW())',
                [':parent_id' => $parentId, ':name' => $name, ':slug' => $slug, ':description' => $description, ':seo_title' => $seoTitle, ':seo_description' => $seoDescription, ':intro' => $intro]
            );
        } catch (PDOException) {
            return false;
        }

        return true;
    }

    public function update(int $id, ?int $parentId, string $name, string $slug, string $description, string $seoTitle = '', string $seoDescription = '', string $intro = ''): bool
    {
        if ($parentId === $id) {
            return false;
        }

        if (!$this->validParentId($parentId) || $this->createsCycle($id, $parentId)) {
            return false;
        }

        try {
            $this->db->execute(
                'UPDATE categories SET parent_id = :parent_id, name = :name, slug = :slug, description = :description, seo_title = :seo_title, seo_description = :seo_description, intro = :intro, updated_at = NOW() WHERE id = :id',
                [':parent_id' => $parentId, ':name' => $name, ':slug' => $slug, ':description' => $description, ':seo_title' => $seoTitle, ':seo_description' => $seoDescription, ':intro' => $intro, ':id' => $id]
            );
        } catch (PDOException) {
            return false;
        }

        return true;
    }

    public function delete(int $id): bool
    {
        if ($this->db->fetch('SELECT id FROM categories WHERE id = :id LIMIT 1', [':id' => $id]) === null) {
            return false;
        }

        try {
            $this->db->execute('UPDATE questions SET category_id = NULL WHERE category_id = :id', [':id' => $id]);
            $this->db->execute('UPDATE categories SET parent_id = NULL WHERE parent_id = :id', [':id' => $id]);
            $this->db->execute('DELETE FROM categories WHERE id = :id', [':id' => $id]);
        } catch (PDOException) {
            return false;
        }

        return true;
    }

    /** @return array<int, array<string, mixed>> */
    public function sitemapCategories(): array
    {
        return $this->db->fetchAll(
            'SELECT c.slug, COALESCE(MAX(q.updated_at), c.updated_at, c.created_at) AS updated_at
             FROM categories c
             LEFT JOIN categories child ON child.parent_id = c.id
             JOIN questions q ON q.deleted_at IS NULL' . $this->approvalFilter('q') . $this->canonicalQuestionFilter('q') . ' AND (q.category_id = c.id OR q.category_id = child.id)
             GROUP BY c.id, c.slug, c.updated_at, c.created_at
             ORDER BY updated_at DESC
             LIMIT 50000'
        );
    }

    public function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = $slug;
        $counter = 2;

        while ($this->slugExists($slug, $ignoreId)) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $ignoreId): bool
    {
        if ($ignoreId === null) {
            return $this->db->fetch('SELECT id FROM categories WHERE slug = :slug LIMIT 1', [':slug' => $slug]) !== null;
        }

        return $this->db->fetch('SELECT id FROM categories WHERE slug = :slug AND id <> :id LIMIT 1', [':slug' => $slug, ':id' => $ignoreId]) !== null;
    }

    private function validParentId(?int $parentId): bool
    {
        if ($parentId === null) {
            return true;
        }

        return $this->db->fetch('SELECT id FROM categories WHERE id = :id LIMIT 1', [':id' => $parentId]) !== null;
    }

    private function createsCycle(int $id, ?int $parentId): bool
    {
        if ($parentId === null || $id < 1) {
            return false;
        }

        $current = $parentId;
        while ($current !== null) {
            if ($current === $id) {
                return true;
            }

            $row = $this->db->fetch('SELECT parent_id FROM categories WHERE id = :id LIMIT 1', [':id' => $current]);
            if ($row === null) {
                return false;
            }

            $current = isset($row['parent_id']) ? ((int) $row['parent_id'] > 0 ? (int) $row['parent_id'] : null) : null;
        }

        return false;
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

    private function canonicalQuestionFilter(string $alias): string
    {
        if ($this->duplicateColumnAvailable !== null) {
            return $this->duplicateColumnAvailable ? ' AND ' . $alias . '.duplicate_of_id IS NULL' : '';
        }

        try {
            $this->duplicateColumnAvailable = $this->db->fetch(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'questions' AND COLUMN_NAME = 'duplicate_of_id' LIMIT 1"
            ) !== null;
        } catch (Throwable) {
            $this->duplicateColumnAvailable = false;
        }

        return $this->duplicateColumnAvailable ? ' AND ' . $alias . '.duplicate_of_id IS NULL' : '';
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Throwable;

final class QuestionModel
{
    public const MAX_QUESTION_TAGS = 8;

    private ?bool $approvalColumnsAvailable = null;
    private ?bool $commentDeletedColumnAvailable = null;

    public function __construct(private Database $db) {}

    /** @return array<int, array<string, mixed>> */
    public function latest(int $limit, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT q.*, u.username, c.name AS category_name, c.slug AS category_slug, COUNT(DISTINCT a.id) AS answer_count
             FROM questions q
             JOIN users u ON u.id = q.user_id
             LEFT JOIN categories c ON c.id = q.category_id
             LEFT JOIN answers a ON a.question_id = q.id AND a.deleted_at IS NULL
             WHERE q.deleted_at IS NULL' . $this->approvalFilter('q') . '
             GROUP BY q.id
             ORDER BY q.created_at DESC
             LIMIT :limit OFFSET :offset',
            [':limit' => $limit, ':offset' => $offset]
        );
    }

    public function countLatest(): int
    {
        $row = $this->db->fetch('SELECT COUNT(*) AS total FROM questions q WHERE q.deleted_at IS NULL' . $this->approvalFilter('q'));
        return (int) ($row['total'] ?? 0);
    }

    /** @param array<int, array<string, mixed>> $questions @return array<int, array<string, mixed>> */
    public function withCardMeta(array $questions): array
    {
        $ids = array_values(array_filter(array_map(static fn (array $question): int => (int) ($question['id'] ?? 0), $questions), static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return $questions;
        }
        $commentFilter = $this->commentDeletedFilter('c');

        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $key = ':id' . $index;
            $placeholders[] = $key;
            $params[$key] = $id;
        }
        $in = implode(',', $placeholders);

        $tagsByQuestion = [];
        $tagRows = $this->db->fetchAll(
            "SELECT qt.question_id, t.name, t.slug
             FROM question_tags qt
             JOIN tags t ON t.id = qt.tag_id
             WHERE qt.question_id IN ({$in})
             ORDER BY t.name",
            $params
        );
        foreach ($tagRows as $row) {
            $tagsByQuestion[(int) $row['question_id']][] = ['name' => (string) $row['name'], 'slug' => (string) $row['slug']];
        }

        $answerCounts = [];
        $answerRows = $this->db->fetchAll(
            "SELECT question_id, COUNT(*) AS answer_count
             FROM answers
             WHERE deleted_at IS NULL AND question_id IN ({$in})
             GROUP BY question_id",
            $params
        );
        foreach ($answerRows as $row) {
            $answerCounts[(int) $row['question_id']] = (int) $row['answer_count'];
        }

        $voteTotalsByQuestion = [];
        $voteParams = [];
        $voteQuestionIn = $this->inParams($ids, 'vote_question', $voteParams);
        $voteAnswerIn = $this->inParams($ids, 'vote_answer', $voteParams);
        $voteCommentQuestionIn = $this->inParams($ids, 'vote_comment_question', $voteParams);
        $voteCommentAnswerIn = $this->inParams($ids, 'vote_comment_answer', $voteParams);
        $voteRows = $this->db->fetchAll(
            "SELECT question_id, SUM(vote_total) AS total_votes
             FROM (
                SELECT q.id AS question_id, COALESCE(q.votes, 0) AS vote_total
                FROM questions q
                WHERE q.id IN ({$voteQuestionIn})
                UNION ALL
                SELECT a.question_id, COALESCE(a.votes, 0) AS vote_total
                FROM answers a
                WHERE a.deleted_at IS NULL AND a.question_id IN ({$voteAnswerIn})
                UNION ALL
                SELECT COALESCE(c.question_id, a.question_id) AS question_id, COALESCE(c.votes, 0) AS vote_total
                FROM comments c
                LEFT JOIN answers a ON a.id = c.answer_id AND a.deleted_at IS NULL
                WHERE ((c.question_id IN ({$voteCommentQuestionIn}) OR a.question_id IN ({$voteCommentAnswerIn})){$commentFilter})
             ) vote_totals
             WHERE question_id IS NOT NULL
             GROUP BY question_id",
            $voteParams
        );
        foreach ($voteRows as $row) {
            $voteTotalsByQuestion[(int) $row['question_id']] = (int) ($row['total_votes'] ?? 0);
        }

        $activityByQuestion = [];
        $activityParams = [];
        $questionIn = $this->inParams($ids, 'activity_question', $activityParams);
        $answerIn = $this->inParams($ids, 'activity_answer', $activityParams);
        $commentQuestionIn = $this->inParams($ids, 'activity_comment_question', $activityParams);
        $commentAnswerIn = $this->inParams($ids, 'activity_comment_answer', $activityParams);
        $activityRows = $this->db->fetchAll(
            "SELECT activity.question_id, activity.user_id, u.username, u.avatar_path, MAX(activity.last_activity) AS last_activity
             FROM (
                SELECT q.id AS question_id, q.user_id, q.created_at AS last_activity
                FROM questions q
                WHERE q.id IN ({$questionIn})
                UNION ALL
                SELECT a.question_id, a.user_id, a.created_at AS last_activity
                FROM answers a
                WHERE a.deleted_at IS NULL AND a.question_id IN ({$answerIn})
                UNION ALL
                SELECT COALESCE(c.question_id, a.question_id) AS question_id, c.user_id, c.created_at AS last_activity
                FROM comments c
                LEFT JOIN answers a ON a.id = c.answer_id AND a.deleted_at IS NULL
                WHERE ((c.question_id IN ({$commentQuestionIn}) OR a.question_id IN ({$commentAnswerIn})){$commentFilter})
             ) activity
             JOIN users u ON u.id = activity.user_id AND u.deleted_at IS NULL AND u.banned_at IS NULL
             WHERE activity.question_id IS NOT NULL
             GROUP BY activity.question_id, activity.user_id, u.username, u.avatar_path
             ORDER BY activity.question_id, last_activity DESC",
            $activityParams
        );
        foreach ($activityRows as $row) {
            $questionId = (int) $row['question_id'];
            $activityByQuestion[$questionId] ??= [];
            if (count($activityByQuestion[$questionId]) >= 9) {
                continue;
            }
            $activityByQuestion[$questionId][] = [
                'username' => (string) $row['username'],
                'avatar_path' => (string) ($row['avatar_path'] ?? ''),
                'last_activity' => (string) $row['last_activity'],
            ];
        }

        $mediaByQuestion = [];
        $mediaParams = [];
        $mediaQuestionIn = $this->inParams($ids, 'media_question', $mediaParams);
        $mediaAnswerIn = $this->inParams($ids, 'media_answer', $mediaParams);
        $mediaCommentQuestionIn = $this->inParams($ids, 'media_comment_question', $mediaParams);
        $mediaCommentAnswerIn = $this->inParams($ids, 'media_comment_answer', $mediaParams);
        $mediaRows = $this->db->fetchAll(
            "SELECT question_id, body, created_at
             FROM (
                SELECT q.id AS question_id, q.body, q.created_at
                FROM questions q
                WHERE q.id IN ({$mediaQuestionIn})
                UNION ALL
                SELECT a.question_id, a.body, a.created_at
                FROM answers a
                WHERE a.deleted_at IS NULL AND a.question_id IN ({$mediaAnswerIn})
                UNION ALL
                SELECT COALESCE(c.question_id, a.question_id) AS question_id, c.body, c.created_at
                FROM comments c
                LEFT JOIN answers a ON a.id = c.answer_id AND a.deleted_at IS NULL
                WHERE ((c.question_id IN ({$mediaCommentQuestionIn}) OR a.question_id IN ({$mediaCommentAnswerIn})){$commentFilter})
             ) media
             ORDER BY created_at DESC",
            $mediaParams
        );
        foreach ($mediaRows as $row) {
            $questionId = (int) $row['question_id'];
            $mediaByQuestion[$questionId] ??= [];
            if (count($mediaByQuestion[$questionId]) >= 4) {
                continue;
            }

            preg_match_all('/(?:!\[[^\]]*\]|\[uploaded video\])\((\/public-assets\/images\/[a-zA-Z0-9\/._-]+)\)/', (string) $row['body'], $matches);
            foreach ($matches[1] ?? [] as $path) {
                if (count($mediaByQuestion[$questionId]) >= 4) {
                    break;
                }
                $mediaByQuestion[$questionId][] = $path;
            }
        }

        $activityDaysByQuestion = [];
        $since = date('Y-m-d 00:00:00', strtotime('-30 days'));
        $dayParams = [':activity_since_question' => $since, ':activity_since_answer' => $since, ':activity_since_comment' => $since];
        $dayQuestionIn = $this->inParams($ids, 'day_question', $dayParams);
        $dayAnswerIn = $this->inParams($ids, 'day_answer', $dayParams);
        $dayCommentQuestionIn = $this->inParams($ids, 'day_comment_question', $dayParams);
        $dayCommentAnswerIn = $this->inParams($ids, 'day_comment_answer', $dayParams);
        $dayRows = $this->db->fetchAll(
            "SELECT question_id, activity_day, COUNT(*) AS total
             FROM (
                SELECT q.id AS question_id, DATE(q.created_at) AS activity_day
                FROM questions q
                WHERE q.id IN ({$dayQuestionIn}) AND q.created_at >= :activity_since_question
                UNION ALL
                SELECT a.question_id, DATE(a.created_at) AS activity_day
                FROM answers a
                WHERE a.deleted_at IS NULL AND a.question_id IN ({$dayAnswerIn}) AND a.created_at >= :activity_since_answer
                UNION ALL
                SELECT COALESCE(c.question_id, a.question_id) AS question_id, DATE(c.created_at) AS activity_day
                FROM comments c
                LEFT JOIN answers a ON a.id = c.answer_id AND a.deleted_at IS NULL
                WHERE ((c.question_id IN ({$dayCommentQuestionIn}) OR a.question_id IN ({$dayCommentAnswerIn})){$commentFilter}) AND c.created_at >= :activity_since_comment
             ) activity_days
             GROUP BY question_id, activity_day",
            $dayParams
        );
        foreach ($dayRows as $row) {
            $activityDaysByQuestion[(int) $row['question_id']][(string) $row['activity_day']] = (int) $row['total'];
        }

        foreach ($questions as &$question) {
            $id = (int) ($question['id'] ?? 0);
            $question['card_tags'] = $tagsByQuestion[$id] ?? [];
            $question['activity_users'] = $activityByQuestion[$id] ?? [];
            $question['latest_media'] = $mediaByQuestion[$id] ?? [];
            $question['activity_days'] = $activityDaysByQuestion[$id] ?? [];
            $question['total_vote_count'] = $voteTotalsByQuestion[$id] ?? (int) ($question['votes'] ?? 0);
            if (!isset($question['answer_count'])) {
                $question['answer_count'] = $answerCounts[$id] ?? 0;
            }
        }
        unset($question);

        return $questions;
    }

    /** @return array<int, array<string, mixed>> */
    public function search(string $query, int $limit = 20, int $offset = 0): array
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
        $pattern = '%' . $escaped . '%';
        return $this->db->fetchAll(
            'SELECT q.*, u.username, c.name AS category_name, c.slug AS category_slug
             FROM questions q
             JOIN users u ON u.id = q.user_id
             LEFT JOIN categories c ON c.id = q.category_id
             WHERE q.deleted_at IS NULL' . $this->approvalFilter('q') . ' AND (q.title LIKE :query_title OR q.body LIKE :query_body)
             ORDER BY q.created_at DESC LIMIT :limit OFFSET :offset',
            [':query_title' => $pattern, ':query_body' => $pattern, ':limit' => $limit, ':offset' => $offset]
        );
    }

    public function countSearch(string $query): int
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
        $pattern = '%' . $escaped . '%';
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS total
             FROM questions q
             WHERE q.deleted_at IS NULL' . $this->approvalFilter('q') . ' AND (q.title LIKE :query_title OR q.body LIKE :query_body)',
            [':query_title' => $pattern, ':query_body' => $pattern]
        );

        return (int) ($row['total'] ?? 0);
    }

    /** @return array<int, array<string, mixed>> */
    public function similarTitles(string $title, int $limit = 5): array
    {
        $plain = trim(preg_replace('/[^a-z0-9\s]+/i', ' ', $title) ?? '');
        $tokens = array_values(array_filter(array_unique(preg_split('/\s+/', mb_strtolower($plain)) ?: []), static fn (string $token): bool => mb_strlen($token) >= 3));
        $tokens = array_slice($tokens, 0, 6);
        if ($tokens === []) {
            return [];
        }

        $conditions = [];
        $params = [':limit' => $limit];
        foreach ($tokens as $index => $token) {
            $key = ':token' . $index;
            $conditions[] = 'q.title LIKE ' . $key;
            $params[$key] = '%' . $token . '%';
        }

        return $this->db->fetchAll(
            'SELECT q.title, q.slug, q.views, q.votes, COUNT(DISTINCT a.id) AS answer_count
             FROM questions q
             LEFT JOIN answers a ON a.question_id = q.id AND a.deleted_at IS NULL
             WHERE q.deleted_at IS NULL' . $this->approvalFilter('q') . ' AND (' . implode(' OR ', $conditions) . ')
             GROUP BY q.id, q.title, q.slug, q.views, q.votes
             ORDER BY answer_count DESC, q.votes DESC, q.views DESC, q.created_at DESC
             LIMIT :limit',
            $params
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function unanswered(int $limit = 30, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT q.*, u.username, c.name AS category_name, c.slug AS category_slug, 0 AS answer_count
             FROM questions q
             JOIN users u ON u.id = q.user_id
             LEFT JOIN categories c ON c.id = q.category_id
             LEFT JOIN answers a ON a.question_id = q.id AND a.deleted_at IS NULL
             WHERE q.deleted_at IS NULL' . $this->approvalFilter('q') . '
             GROUP BY q.id
             HAVING COUNT(a.id) = 0
             ORDER BY q.created_at DESC LIMIT :limit OFFSET :offset',
            [':limit' => $limit, ':offset' => $offset]
        );
    }

    public function countUnanswered(): int
    {
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS total
             FROM questions q
             WHERE q.deleted_at IS NULL' . $this->approvalFilter('q') . '
               AND NOT EXISTS (SELECT 1 FROM answers a WHERE a.question_id = q.id AND a.deleted_at IS NULL)'
        );

        return (int) ($row['total'] ?? 0);
    }

    /** @return array<int, array<string, mixed>> */
    public function popularTags(int $limit = 100, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT t.id, t.name, t.slug, t.description, t.seo_title, t.seo_description, t.intro, COUNT(DISTINCT q.id) AS question_count
             FROM tags t
             LEFT JOIN question_tags qt ON qt.tag_id = t.id
             LEFT JOIN questions q ON q.id = qt.question_id AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
             GROUP BY t.id, t.name, t.slug, t.description, t.seo_title, t.seo_description, t.intro
             ORDER BY question_count DESC, t.name ASC LIMIT :limit OFFSET :offset',
            [':limit' => $limit, ':offset' => $offset]
        );
    }

    public function countTags(): int
    {
        $row = $this->db->fetch('SELECT COUNT(*) AS total FROM tags');
        return (int) ($row['total'] ?? 0);
    }

    /** @return array<string, mixed>|null */
    public function tagBySlug(string $slug): ?array
    {
        return $this->db->fetch(
            'SELECT t.*,
                    (SELECT COUNT(DISTINCT q.id)
                     FROM question_tags qt
                     JOIN questions q ON q.id = qt.question_id AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
                     WHERE qt.tag_id = t.id) AS question_count,
                    (SELECT MAX(q.updated_at)
                     FROM question_tags qt
                     JOIN questions q ON q.id = qt.question_id AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
                     WHERE qt.tag_id = t.id) AS updated_at
             FROM tags t
             WHERE t.slug = :slug
             LIMIT 1',
            [':slug' => $slug]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function relatedTags(string $slug, int $limit = 12): array
    {
        return $this->db->fetchAll(
            'SELECT t2.name, t2.slug, COUNT(DISTINCT qt2.question_id) AS overlap
             FROM tags t1
             JOIN question_tags qt1 ON qt1.tag_id = t1.id
             JOIN question_tags qt2 ON qt2.question_id = qt1.question_id
             JOIN tags t2 ON t2.id = qt2.tag_id
             JOIN questions q ON q.id = qt2.question_id AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
             WHERE t1.slug = :slug AND t2.slug <> :exclude_slug
             GROUP BY t2.id, t2.name, t2.slug
             ORDER BY overlap DESC, t2.name ASC
             LIMIT :limit',
            [':slug' => $slug, ':exclude_slug' => $slug, ':limit' => $limit]
        );
    }

    public function updateTagSeo(int $id, string $name, string $description, string $seoTitle, string $seoDescription, string $intro): bool
    {
        if ($id < 1 || trim($name) === '') {
            return false;
        }

        $slug = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(trim($name))) ?? '';
        $slug = trim($slug, '-');
        if ($slug === '') {
            return false;
        }
        $base = $slug;
        $counter = 2;
        while ($this->db->fetch('SELECT id FROM tags WHERE slug = :slug AND id <> :id LIMIT 1', [':slug' => $slug, ':id' => $id]) !== null) {
            $slug = $base . '-' . $counter++;
        }

        if ($this->db->fetch('SELECT id FROM tags WHERE id = :id LIMIT 1', [':id' => $id]) === null) {
            return false;
        }

        try {
            $this->db->execute(
                'UPDATE tags SET name = :name, slug = :slug, description = :description, seo_title = :seo_title, seo_description = :seo_description, intro = :intro, updated_at = NOW() WHERE id = :id',
                [':name' => $name, ':slug' => $slug, ':description' => $description, ':seo_title' => $seoTitle, ':seo_description' => $seoDescription, ':intro' => $intro, ':id' => $id]
            );
            return true;
        } catch (\PDOException) {
            return false;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function popularTagsForCategory(int $categoryId, int $limit = 12): array
    {
        return $this->db->fetchAll(
            'SELECT t.name, t.slug, COUNT(DISTINCT q.id) AS question_count
             FROM tags t
             JOIN question_tags qt ON qt.tag_id = t.id
             JOIN questions q ON q.id = qt.question_id
             WHERE q.deleted_at IS NULL' . $this->approvalFilter('q') . '
               AND (q.category_id = :category OR q.category_id IN (SELECT id FROM categories WHERE parent_id = :child_parent))
             GROUP BY t.id, t.name, t.slug
             ORDER BY question_count DESC, t.name ASC
             LIMIT :limit',
            [':category' => $categoryId, ':child_parent' => $categoryId, ':limit' => $limit]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function byTag(string $slug, int $limit = 30, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT q.*, u.username, c.name AS category_name, c.slug AS category_slug
             FROM questions q
             JOIN question_tags qt ON qt.question_id = q.id
             JOIN tags t ON t.id = qt.tag_id
             JOIN users u ON u.id = q.user_id
             LEFT JOIN categories c ON c.id = q.category_id
             WHERE t.slug = :slug AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
             ORDER BY q.created_at DESC LIMIT :limit OFFSET :offset',
            [':slug' => $slug, ':limit' => $limit, ':offset' => $offset]
        );
    }

    public function countByTag(string $slug): int
    {
        $row = $this->db->fetch(
            'SELECT COUNT(DISTINCT q.id) AS total
             FROM questions q
             JOIN question_tags qt ON qt.question_id = q.id
             JOIN tags t ON t.id = qt.tag_id
             WHERE t.slug = :slug AND q.deleted_at IS NULL' . $this->approvalFilter('q'),
            [':slug' => $slug]
        );

        return (int) ($row['total'] ?? 0);
    }

    /** @return array<int, array<string, mixed>> */
    public function byUser(int $userId, int $limit = 30): array
    {
        return $this->db->fetchAll('SELECT * FROM questions q WHERE q.user_id = :user AND q.deleted_at IS NULL' . $this->approvalFilter('q') . ' ORDER BY q.created_at DESC LIMIT :limit', [':user' => $userId, ':limit' => $limit]);
    }

    /** @return array<int, array<string, mixed>> */
    public function byCategory(int $categoryId, int $limit = 30, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT q.*, u.username, c.name AS category_name, c.slug AS category_slug
             FROM questions q
             JOIN users u ON u.id = q.user_id
             LEFT JOIN categories c ON c.id = q.category_id
             WHERE q.deleted_at IS NULL' . $this->approvalFilter('q') . ' AND (q.category_id = :category OR q.category_id IN (SELECT id FROM categories WHERE parent_id = :child_parent))
             ORDER BY q.created_at DESC LIMIT :limit OFFSET :offset',
            [':category' => $categoryId, ':child_parent' => $categoryId, ':limit' => $limit, ':offset' => $offset]
        );
    }

    public function countByCategory(int $categoryId): int
    {
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS total
             FROM questions q
             WHERE q.deleted_at IS NULL' . $this->approvalFilter('q') . ' AND (q.category_id = :category OR q.category_id IN (SELECT id FROM categories WHERE parent_id = :child_parent))',
            [':category' => $categoryId, ':child_parent' => $categoryId]
        );

        return (int) ($row['total'] ?? 0);
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        if ($this->duplicateColumnAvailable()) {
            return $this->db->fetch(
                'SELECT q.*, u.username, u.reputation, u.avatar_path, c.name AS category_name, c.slug AS category_slug,
                        dq.slug AS duplicate_slug, dq.title AS duplicate_title
                 FROM questions q
                 JOIN users u ON u.id = q.user_id
                 LEFT JOIN categories c ON c.id = q.category_id
                 LEFT JOIN questions dq ON dq.id = q.duplicate_of_id AND dq.deleted_at IS NULL' . $this->approvalFilter('dq') . '
                 WHERE q.slug = :slug AND q.deleted_at IS NULL' . $this->approvalFilter('q') . ' LIMIT 1',
                [':slug' => $slug]
            );
        }

        return $this->db->fetch(
            'SELECT q.*, u.username, u.reputation, u.avatar_path, c.name AS category_name, c.slug AS category_slug,
                    NULL AS duplicate_of_id, NULL AS duplicate_slug, NULL AS duplicate_title
             FROM questions q
             JOIN users u ON u.id = q.user_id
             LEFT JOIN categories c ON c.id = q.category_id
             WHERE q.slug = :slug AND q.deleted_at IS NULL' . $this->approvalFilter('q') . ' LIMIT 1',
            [':slug' => $slug]
        );
    }

    public function incrementViews(int $questionId): void
    {
        $this->db->execute('UPDATE questions SET views = views + 1 WHERE id = :id AND deleted_at IS NULL', [':id' => $questionId]);
    }

    public function isQuestionOpen(int $questionId): bool
    {
        $row = $this->db->fetch('SELECT id FROM questions q WHERE q.id = :id AND q.deleted_at IS NULL' . $this->approvalFilter('q') . ' AND q.closed_at IS NULL LIMIT 1', [':id' => $questionId]);
        return $row !== null;
    }

    /** @return array<string, mixed>|null */
    public function findSummaryById(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT id, user_id, title, slug
             FROM questions q
             WHERE q.id = :id AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
             LIMIT 1',
            [':id' => $id]
        );
    }

    public function create(int $userId, ?int $categoryId, string $title, string $slug, string $body, string $metaDescription, string $approvalStatus = 'approved'): int
    {
        $approvalStatus = $approvalStatus === 'pending' ? 'pending' : 'approved';
        if ($this->approvalColumnsAvailable()) {
            $this->db->execute(
                'INSERT INTO questions (user_id, category_id, title, slug, body, meta_description, votes, approval_status, approved_at, created_at, updated_at)
                 VALUES (:user_id, :category_id, :title, :slug, :body, :meta, 0, :approval_status, ' . ($approvalStatus === 'approved' ? 'NOW()' : 'NULL') . ', NOW(), NOW())',
                [':user_id' => $userId, ':category_id' => $categoryId, ':title' => $title, ':slug' => $slug, ':body' => $body, ':meta' => $metaDescription, ':approval_status' => $approvalStatus]
            );

            return $this->db->lastInsertId();
        }

        $this->db->execute(
            'INSERT INTO questions (user_id, category_id, title, slug, body, meta_description, votes, created_at, updated_at) VALUES (:user_id, :category_id, :title, :slug, :body, :meta, 0, NOW(), NOW())',
            [':user_id' => $userId, ':category_id' => $categoryId, ':title' => $title, ':slug' => $slug, ':body' => $body, ':meta' => $metaDescription]
        );

        return $this->db->lastInsertId();
    }

    public function uniqueSlug(string $slug): string
    {
        $base = $slug;
        $counter = 2;
        while ($this->db->fetch('SELECT id FROM questions WHERE slug = :slug LIMIT 1', [':slug' => $slug]) !== null) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }

    public function attachTags(int $questionId, array $tags): void
    {
        $attached = 0;
        $seenSlugs = [];
        foreach ($tags as $tag) {
            $name = trim(mb_strtolower((string) $tag));
            if ($name === '') {
                continue;
            }

            $slug = preg_replace('/[^a-z0-9]+/', '-', $name) ?? '';
            $slug = trim($slug, '-');
            if ($slug === '') {
                continue;
            }

            if (isset($seenSlugs[$slug])) {
                continue;
            }
            $seenSlugs[$slug] = true;

            $this->db->execute('INSERT INTO tags (name, slug, created_at) VALUES (:name, :slug, NOW()) ON DUPLICATE KEY UPDATE id = id', [':name' => $name, ':slug' => $slug]);
            $tagRow = $this->db->fetch('SELECT id FROM tags WHERE slug = :slug LIMIT 1', [':slug' => $slug]);
            if ($tagRow !== null) {
                $this->db->execute('INSERT IGNORE INTO question_tags (question_id, tag_id) VALUES (:question, :tag)', [':question' => $questionId, ':tag' => (int) $tagRow['id']]);
                $attached++;
                if ($attached >= self::MAX_QUESTION_TAGS) {
                    break;
                }
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function tags(int $questionId): array
    {
        return $this->db->fetchAll('SELECT t.* FROM tags t JOIN question_tags qt ON qt.tag_id = t.id WHERE qt.question_id = :id ORDER BY t.name', [':id' => $questionId]);
    }

    /** @return array<int, array<string, mixed>> */
    public function answers(int $questionId): array
    {
        return $this->db->fetchAll(
            "SELECT a.*, q.slug AS question_slug, u.username, u.reputation, u.avatar_path,
                    reviewer.username AS verified_by_username, reviewer.avatar_path AS verified_by_avatar_path,
                    COALESCE(ar.reaction_count, 0) AS reaction_count,
                    COALESCE(ar.reaction_score, 0) AS reaction_score,
                    COALESCE(a.votes, 0) + COALESCE(ar.reaction_score, 0) AS community_score
             FROM answers a
             JOIN questions q ON q.id = a.question_id
             JOIN users u ON u.id = a.user_id
             LEFT JOIN users reviewer ON reviewer.id = a.verified_by
             LEFT JOIN (
                 SELECT pr.post_id,
                        COUNT(*) AS reaction_count,
                        COALESCE(SUM(rt.reputation_delta), 0) AS reaction_score
                 FROM post_reactions pr
                 JOIN answers ra ON ra.id = pr.post_id AND ra.question_id = :reaction_question_id AND ra.deleted_at IS NULL
                 JOIN reaction_types rt ON rt.id = pr.reaction_type_id AND rt.is_active = 1
                 WHERE pr.post_type = 'answer'
                 GROUP BY pr.post_id
             ) ar ON ar.post_id = a.id
             WHERE a.question_id = :id AND a.deleted_at IS NULL
             ORDER BY a.is_accepted DESC, community_score DESC, a.verified_at IS NULL, a.votes DESC, a.created_at ASC",
            [':id' => $questionId, ':reaction_question_id' => $questionId]
        );
    }

    public function addAnswer(int $questionId, int $userId, string $body): int
    {
        $this->db->execute('INSERT INTO answers (question_id, user_id, body, votes, is_accepted, created_at, updated_at) VALUES (:question, :user, :body, 0, 0, NOW(), NOW())', [':question' => $questionId, ':user' => $userId, ':body' => $body]);
        return $this->db->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findAnswer(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT a.*, q.slug AS question_slug, q.title AS question_title
             FROM answers a
             JOIN questions q ON q.id = a.question_id
             WHERE a.id = :id AND a.deleted_at IS NULL AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
             LIMIT 1',
            [':id' => $id]
        );
    }

    public function comment(string $type, int $postId, int $userId, string $body, ?int $parentId = null): int
    {
        if (!in_array($type, ['answer', 'question'], true)) {
            throw new \InvalidArgumentException("Invalid comment type: {$type}");
        }
        $column = $type === 'answer' ? 'answer_id' : 'question_id';
        $this->db->execute("INSERT INTO comments ({$column}, parent_id, user_id, body, votes, created_at) VALUES (:post, :parent, :user, :body, 0, NOW())", [':post' => $postId, ':parent' => $parentId, ':user' => $userId, ':body' => $body]);
        return $this->db->lastInsertId();
    }

    public function commentParentBelongsTo(string $type, int $postId, int $parentId): bool
    {
        $commentFilter = $this->commentDeletedFilter();

        if ($type === 'answer') {
            $row = $this->db->fetch('SELECT id FROM comments WHERE id = :parent AND answer_id = :post' . $commentFilter . ' LIMIT 1', [':parent' => $parentId, ':post' => $postId]);
            return $row !== null;
        }

        if ($type === 'question') {
            $row = $this->db->fetch('SELECT id FROM comments WHERE id = :parent AND question_id = :post' . $commentFilter . ' LIMIT 1', [':parent' => $parentId, ':post' => $postId]);
            return $row !== null;
        }

        return false;
    }

    /** @return array<int, array<string, mixed>> */
    public function commentsForQuestion(int $questionId): array
    {
        return $this->db->fetchAll('SELECT c.*, u.username, u.avatar_path FROM comments c JOIN users u ON u.id = c.user_id WHERE c.question_id = :id' . $this->commentDeletedFilter('c') . ' ORDER BY c.created_at ASC', [':id' => $questionId]);
    }

    /** @return array<int, array<string, mixed>> */
    public function commentsForAnswers(int $questionId): array
    {
        return $this->db->fetchAll('SELECT c.*, u.username, u.avatar_path FROM comments c JOIN answers a ON a.id = c.answer_id AND a.deleted_at IS NULL JOIN users u ON u.id = c.user_id WHERE a.question_id = :id' . $this->commentDeletedFilter('c') . ' ORDER BY c.answer_id, c.created_at ASC', [':id' => $questionId]);
    }

    /** @return array<string, mixed>|null */
    public function findComment(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT c.*, q.slug AS question_slug, aq.slug AS answer_question_slug
             FROM comments c
             LEFT JOIN questions q ON q.id = c.question_id
             LEFT JOIN answers a ON a.id = c.answer_id
             LEFT JOIN questions aq ON aq.id = a.question_id
             WHERE c.id = :id' . $this->commentDeletedFilter('c') . '
               AND COALESCE(q.deleted_at, aq.deleted_at) IS NULL
               AND (c.answer_id IS NULL OR a.id IS NOT NULL)
             LIMIT 1',
            [':id' => $id]
        );
    }

    public function vote(string $type, int $postId, int $userId, int $value): void
    {
        $this->applyVote($type, $postId, $userId, $value);
    }

    /** @return array{user_id: int, reputation_delta: int, votes: int, current_vote: int}|null */
    public function applyVote(string $type, int $postId, int $userId, int $value): ?array
    {
        $value = $value > 0 ? 1 : -1;
        $points = match ($type) {
            'answer' => 10,
            'comment' => 2,
            'question' => 5,
            default => 0,
        };
        if ($points === 0) {
            return null;
        }

        return $this->db->transaction(function () use ($type, $postId, $userId, $value, $points): ?array {
            $target = $this->voteTargetRow($type, $postId, $userId, true);
            if ($target === null) {
                return null;
            }

            $ownerUserId = (int) $target['user_id'];
            $currentVote = (int) $target['current_vote'];
            if ($ownerUserId === $userId || $currentVote === $value) {
                return null;
            }

            $this->db->execute(
                'INSERT INTO votes (user_id, post_type, post_id, value, created_at, updated_at) VALUES (:user, :type, :post, :value, NOW(), NOW()) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()',
                [':user' => $userId, ':type' => $type, ':post' => $postId, ':value' => $value]
            );

            $table = match ($type) {
                'answer' => 'answers',
                'comment' => 'comments',
                default => 'questions',
            };
            $total = $this->db->fetch('SELECT COALESCE(SUM(value), 0) AS total FROM votes WHERE post_type = :type AND post_id = :post', [':type' => $type, ':post' => $postId]);
            $updatedAt = $table === 'comments' ? '' : ', updated_at = NOW()';
            $this->db->execute("UPDATE {$table} SET votes = :votes{$updatedAt} WHERE id = :id", [':votes' => (int) ($total['total'] ?? 0), ':id' => $postId]);

            return [
                'user_id' => $ownerUserId,
                'reputation_delta' => ($value - $currentVote) * $points,
                'votes' => (int) ($total['total'] ?? 0),
                'current_vote' => $value,
            ];
        });
    }

    /** @return array{user_id: int, current_vote: int}|null */
    public function voteTarget(string $type, int $postId, int $voterId): ?array
    {
        $row = $this->voteTargetRow($type, $postId, $voterId);

        return $row === null ? null : ['user_id' => (int) $row['user_id'], 'current_vote' => (int) $row['current_vote']];
    }

    /** @return array{accepted: bool, newly_accepted: bool, answer_user_id: int}|null */
    public function acceptAnswer(int $answerId, int $questionOwnerId): ?array
    {
        return $this->db->transaction(function () use ($answerId, $questionOwnerId): ?array {
            $answer = $this->db->fetch(
                'SELECT a.id, a.question_id, a.user_id, a.is_accepted, q.user_id AS question_owner_id
                 FROM answers a
                 JOIN questions q ON q.id = a.question_id
                 WHERE a.id = :id AND a.deleted_at IS NULL AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
                 LIMIT 1 FOR UPDATE',
                [':id' => $answerId]
            );
            if ($answer === null || (int) $answer['question_owner_id'] !== $questionOwnerId) {
                return null;
            }

            $questionId = (int) $answer['question_id'];
            $this->db->execute('UPDATE answers SET is_accepted = 0 WHERE question_id = :question', [':question' => $questionId]);
            $this->db->execute('UPDATE answers SET is_accepted = 1 WHERE id = :id', [':id' => (int) $answer['id']]);
            $this->db->execute('UPDATE questions SET accepted_answer_id = :answer, updated_at = NOW() WHERE id = :question', [':answer' => (int) $answer['id'], ':question' => $questionId]);

            return [
                'accepted' => true,
                'newly_accepted' => (int) ($answer['is_accepted'] ?? 0) !== 1,
                'answer_user_id' => (int) $answer['user_id'],
            ];
        });
    }

    /** @return array<string, mixed>|null */
    private function voteTargetRow(string $type, int $postId, int $voterId, bool $forUpdate = false): ?array
    {
        $lock = $forUpdate ? ' FOR UPDATE' : '';

        return match ($type) {
            'answer' => $this->db->fetch(
                'SELECT p.user_id, COALESCE(v.value, 0) AS current_vote
                 FROM answers p
                 JOIN questions q ON q.id = p.question_id
                 LEFT JOIN votes v ON v.post_type = :vote_type AND v.post_id = p.id AND v.user_id = :voter
                 WHERE p.id = :id AND p.deleted_at IS NULL AND q.deleted_at IS NULL' . $this->approvalFilter('q') . ' LIMIT 1' . $lock,
                [':vote_type' => $type, ':voter' => $voterId, ':id' => $postId]
            ),
            'comment' => $this->db->fetch(
                'SELECT p.user_id, COALESCE(v.value, 0) AS current_vote
                 FROM comments p
                 LEFT JOIN answers a ON a.id = p.answer_id
                 LEFT JOIN questions q ON q.id = COALESCE(p.question_id, a.question_id)
                 LEFT JOIN votes v ON v.post_type = :vote_type AND v.post_id = p.id AND v.user_id = :voter
                 WHERE p.id = :id
                   ' . $this->commentDeletedFilter('p') . '
                   AND q.deleted_at IS NULL
                   AND (p.answer_id IS NULL OR a.id IS NOT NULL)' . $this->approvalFilter('q') . '
                 LIMIT 1' . $lock,
                [':vote_type' => $type, ':voter' => $voterId, ':id' => $postId]
            ),
            'question' => $this->db->fetch(
                'SELECT p.user_id, COALESCE(v.value, 0) AS current_vote
                 FROM questions p
                 LEFT JOIN votes v ON v.post_type = :vote_type AND v.post_id = p.id AND v.user_id = :voter
                 WHERE p.id = :id AND p.deleted_at IS NULL' . $this->approvalFilter('p') . ' LIMIT 1' . $lock,
                [':vote_type' => $type, ':voter' => $voterId, ':id' => $postId]
            ),
            default => null,
        };
    }

    public function verifyAnswer(int $answerId, int $moderatorId, string $note): bool
    {
        if ($answerId < 1 || $moderatorId < 1) {
            return false;
        }

        $answer = $this->db->fetch(
            'SELECT a.id
             FROM answers a
             JOIN questions q ON q.id = a.question_id
             WHERE a.id = :id AND a.deleted_at IS NULL AND q.deleted_at IS NULL
             LIMIT 1',
            [':id' => $answerId]
        );
        if ($answer === null) {
            return false;
        }

        $this->db->execute(
            'UPDATE answers SET verified_at = NOW(), verified_by = :moderator, review_note = :note, updated_at = NOW() WHERE id = :id',
            [':moderator' => $moderatorId, ':note' => excerpt($note, 240), ':id' => $answerId]
        );

        return true;
    }

    public function unverifyAnswer(int $answerId): bool
    {
        if ($answerId < 1) {
            return false;
        }

        $updated = $this->db->query(
            'UPDATE answers a
             JOIN questions q ON q.id = a.question_id
             SET a.verified_at = NULL, a.verified_by = NULL, a.review_note = "", a.updated_at = NOW()
             WHERE a.id = :id AND a.deleted_at IS NULL AND q.deleted_at IS NULL',
            [':id' => $answerId]
        )->rowCount();

        return $updated > 0;
    }

    public function flag(string $type, int $postId, int $userId, string $reason): void
    {
        $updated = $this->db->query(
            'UPDATE flags
             SET reason = :reason, created_at = NOW(), resolved_at = NULL
             WHERE user_id = :user AND post_type = :type AND post_id = :post AND status = "open"',
            [':user' => $userId, ':type' => $type, ':post' => $postId, ':reason' => $reason]
        )->rowCount();

        if ($updated > 0) {
            return;
        }

        $this->db->execute(
            'INSERT INTO flags (user_id, post_type, post_id, reason, status, created_at)
             VALUES (:user, :type, :post, :reason, "open", NOW())',
            [':user' => $userId, ':type' => $type, ':post' => $postId, ':reason' => $reason]
        );
    }

    public function flagTargetExists(string $type, int $postId): bool
    {
        $row = match ($type) {
            'question' => $this->db->fetch('SELECT id FROM questions q WHERE q.id = :id AND q.deleted_at IS NULL' . $this->approvalFilter('q') . ' LIMIT 1', [':id' => $postId]),
            'answer' => $this->db->fetch('SELECT a.id FROM answers a JOIN questions q ON q.id = a.question_id WHERE a.id = :id AND a.deleted_at IS NULL AND q.deleted_at IS NULL' . $this->approvalFilter('q') . ' LIMIT 1', [':id' => $postId]),
            'comment' => $this->db->fetch(
                'SELECT c.id
                 FROM comments c
                 LEFT JOIN answers a ON a.id = c.answer_id
                 LEFT JOIN questions q ON q.id = COALESCE(c.question_id, a.question_id)
                 WHERE c.id = :id' . $this->commentDeletedFilter('c') . ' AND q.deleted_at IS NULL AND (c.answer_id IS NULL OR a.id IS NOT NULL)' . $this->approvalFilter('q') . '
                 LIMIT 1',
                [':id' => $postId]
            ),
            'user' => $this->db->fetch('SELECT id FROM users WHERE id = :id AND deleted_at IS NULL AND banned_at IS NULL LIMIT 1', [':id' => $postId]),
            default => null,
        };

        return $row !== null;
    }

    /** @return array<int, array<string, mixed>> */
    public function related(int $questionId, int $limit = 4): array
    {
        $duplicateFilter = $this->duplicateColumnAvailable() ? 'AND q.duplicate_of_id IS NULL' : '';

        return $this->db->fetchAll(
            'SELECT q.id, q.title, q.slug,
                    COALESCE(q.votes, 0) AS votes,
                    (SELECT COUNT(*) FROM answers a WHERE a.question_id = q.id AND a.deleted_at IS NULL) AS answer_count,
                    EXISTS(SELECT 1 FROM answers a WHERE a.question_id = q.id AND a.is_accepted = 1 AND a.deleted_at IS NULL) AS has_accepted,
                    COUNT(DISTINCT qt.tag_id) AS tag_overlap
             FROM questions q
             JOIN questions current_question ON current_question.id = :id
             LEFT JOIN question_tags qt ON qt.question_id = q.id AND qt.tag_id IN (SELECT tag_id FROM question_tags WHERE question_id = :tag_source_id)
             WHERE (
                    qt.tag_id IS NOT NULL
                    OR (current_question.category_id IS NOT NULL AND q.category_id = current_question.category_id)
               )
               AND q.id <> :exclude_id AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
               ' . $duplicateFilter . '
             GROUP BY q.id, q.title, q.slug, q.votes, q.created_at
             ORDER BY tag_overlap DESC, has_accepted DESC, answer_count DESC, q.votes DESC, q.created_at DESC
             LIMIT :limit',
            [':id' => $questionId, ':tag_source_id' => $questionId, ':exclude_id' => $questionId, ':limit' => $limit]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function sitemapQuestions(): array
    {
        $duplicateFilter = $this->duplicateColumnAvailable() ? 'AND q.duplicate_of_id IS NULL' : '';

        return $this->db->fetchAll(
            'SELECT q.slug, q.body, q.updated_at
             FROM questions q
             WHERE q.deleted_at IS NULL' . $this->approvalFilter('q') . '
               ' . $duplicateFilter . '
               AND (
                   CHAR_LENGTH(TRIM(q.body)) >= 180
                   OR EXISTS (
                       SELECT 1
                       FROM answers a
                       WHERE a.question_id = q.id
                         AND a.deleted_at IS NULL
                   )
               )
             ORDER BY q.updated_at DESC
             LIMIT 50000'
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function sitemapTags(): array
    {
        return $this->db->fetchAll(
            'SELECT t.slug, COALESCE(MAX(q.updated_at), MAX(t.created_at), NOW()) AS updated_at
             FROM tags t
             JOIN question_tags qt ON qt.tag_id = t.id
             JOIN questions q ON q.id = qt.question_id AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
             GROUP BY t.id, t.slug
             ORDER BY updated_at DESC
             LIMIT 50000'
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function openFlags(int $limit = 100, int $offset = 0): array
    {
        $approvalQuestion = $this->approvalFilter('q');
        $approvalAnswerQuestion = $this->approvalFilter('aq');
        $approvalCommentQuestion = $this->approvalFilter('cq');
        $approvalCommentAnswerQuestion = $this->approvalFilter('caq');
        $commentFilter = $this->commentDeletedFilter('c');

        return $this->db->fetchAll(
            "SELECT f.*, reporter.username,
                    CASE
                        WHEN f.post_type = 'question' THEN q.title
                        WHEN f.post_type = 'answer' THEN aq.title
                        WHEN f.post_type = 'comment' THEN COALESCE(cq.title, caq.title)
                        WHEN f.post_type = 'user' THEN target_user.username
                        ELSE ''
                    END AS target_title,
                    CASE
                        WHEN f.post_type = 'question' THEN q.slug
                        WHEN f.post_type = 'answer' THEN aq.slug
                        WHEN f.post_type = 'comment' THEN COALESCE(cq.slug, caq.slug)
                        ELSE ''
                    END AS target_slug,
                    c.body AS comment_body,
                    target_user.username AS target_username
             FROM flags f
             JOIN users reporter ON reporter.id = f.user_id
             LEFT JOIN questions q ON f.post_type = 'question' AND q.id = f.post_id AND q.deleted_at IS NULL{$approvalQuestion}
             LEFT JOIN answers ans ON f.post_type = 'answer' AND ans.id = f.post_id AND ans.deleted_at IS NULL
             LEFT JOIN questions aq ON aq.id = ans.question_id AND aq.deleted_at IS NULL{$approvalAnswerQuestion}
             LEFT JOIN comments c ON f.post_type = 'comment' AND c.id = f.post_id{$commentFilter}
             LEFT JOIN questions cq ON cq.id = c.question_id AND cq.deleted_at IS NULL{$approvalCommentQuestion}
             LEFT JOIN answers ca ON ca.id = c.answer_id AND ca.deleted_at IS NULL
             LEFT JOIN questions caq ON caq.id = ca.question_id AND caq.deleted_at IS NULL{$approvalCommentAnswerQuestion}
             LEFT JOIN users target_user ON f.post_type = 'user' AND target_user.id = f.post_id AND target_user.deleted_at IS NULL AND target_user.banned_at IS NULL
             WHERE f.status = 'open'
               AND (
                    (f.post_type = 'question' AND q.id IS NOT NULL)
                    OR (f.post_type = 'answer' AND ans.id IS NOT NULL AND aq.id IS NOT NULL)
                    OR (
                        f.post_type = 'comment'
                        AND c.id IS NOT NULL
                        AND (
                            (c.answer_id IS NULL AND cq.id IS NOT NULL)
                            OR (c.answer_id IS NOT NULL AND ca.id IS NOT NULL AND caq.id IS NOT NULL)
                        )
                    )
                    OR (f.post_type = 'user' AND target_user.id IS NOT NULL)
               )
             ORDER BY f.created_at DESC LIMIT :limit OFFSET :offset",
            [':limit' => $limit, ':offset' => $offset]
        );
    }

    public function countOpenFlags(): int
    {
        $approvalQuestion = $this->approvalFilter('q');
        $approvalAnswerQuestion = $this->approvalFilter('aq');
        $approvalCommentQuestion = $this->approvalFilter('cq');
        $approvalCommentAnswerQuestion = $this->approvalFilter('caq');
        $commentFilter = $this->commentDeletedFilter('c');

        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total
             FROM flags f
             LEFT JOIN questions q ON f.post_type = 'question' AND q.id = f.post_id AND q.deleted_at IS NULL{$approvalQuestion}
             LEFT JOIN answers ans ON f.post_type = 'answer' AND ans.id = f.post_id AND ans.deleted_at IS NULL
             LEFT JOIN questions aq ON aq.id = ans.question_id AND aq.deleted_at IS NULL{$approvalAnswerQuestion}
             LEFT JOIN comments c ON f.post_type = 'comment' AND c.id = f.post_id{$commentFilter}
             LEFT JOIN questions cq ON cq.id = c.question_id AND cq.deleted_at IS NULL{$approvalCommentQuestion}
             LEFT JOIN answers ca ON ca.id = c.answer_id AND ca.deleted_at IS NULL
             LEFT JOIN questions caq ON caq.id = ca.question_id AND caq.deleted_at IS NULL{$approvalCommentAnswerQuestion}
             LEFT JOIN users target_user ON f.post_type = 'user' AND target_user.id = f.post_id AND target_user.deleted_at IS NULL AND target_user.banned_at IS NULL
             WHERE f.status = 'open'
               AND (
                    (f.post_type = 'question' AND q.id IS NOT NULL)
                    OR (f.post_type = 'answer' AND ans.id IS NOT NULL AND aq.id IS NOT NULL)
                    OR (
                        f.post_type = 'comment'
                        AND c.id IS NOT NULL
                        AND (
                            (c.answer_id IS NULL AND cq.id IS NOT NULL)
                            OR (c.answer_id IS NOT NULL AND ca.id IS NOT NULL AND caq.id IS NOT NULL)
                        )
                    )
                    OR (f.post_type = 'user' AND target_user.id IS NOT NULL)
               )"
        );
        return (int) ($row['total'] ?? 0);
    }

    public function supportsQuestionApproval(): bool
    {
        return $this->approvalColumnsAvailable();
    }

    public function ensureQuestionApprovalSchema(): bool
    {
        if ($this->approvalColumnsAvailable()) {
            return true;
        }

        try {
            if (!$this->columnExists('approval_status')) {
                $this->db->execute('ALTER TABLE questions ADD COLUMN approval_status VARCHAR(20) NOT NULL DEFAULT "approved" AFTER deleted_at');
            }
            if (!$this->columnExists('approved_at')) {
                $this->db->execute('ALTER TABLE questions ADD COLUMN approved_at DATETIME NULL AFTER approval_status');
            }
            if (!$this->columnExists('approved_by')) {
                $this->db->execute('ALTER TABLE questions ADD COLUMN approved_by BIGINT UNSIGNED NULL AFTER approved_at');
            }
            $this->approvalColumnsAvailable = null;

            return $this->approvalColumnsAvailable();
        } catch (Throwable) {
            $this->approvalColumnsAvailable = false;
            return false;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function pendingQuestions(int $limit = 10, int $offset = 0): array
    {
        if (!$this->approvalColumnsAvailable()) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT q.id, q.title, q.body, q.slug, q.created_at, u.username, c.name AS category_name
             FROM questions q
             JOIN users u ON u.id = q.user_id
             LEFT JOIN categories c ON c.id = q.category_id
             WHERE q.deleted_at IS NULL AND q.approval_status = "pending"
             ORDER BY q.created_at ASC
             LIMIT :limit OFFSET :offset',
            [':limit' => $limit, ':offset' => $offset]
        );
    }

    public function countPendingQuestions(): int
    {
        if (!$this->approvalColumnsAvailable()) {
            return 0;
        }

        $row = $this->db->fetch('SELECT COUNT(*) AS total FROM questions WHERE deleted_at IS NULL AND approval_status = "pending"');
        return (int) ($row['total'] ?? 0);
    }

    /** @return array{question_id: int, user_id: int, title: string, slug: string, body: string}|null */
    public function moderatePendingQuestion(int $questionId, int $moderatorId, string $decision): ?array
    {
        if ($questionId < 1 || $moderatorId < 1 || !$this->approvalColumnsAvailable() || !in_array($decision, ['approved', 'declined'], true)) {
            return null;
        }

        return $this->db->transaction(function () use ($questionId, $moderatorId, $decision): ?array {
            $question = $this->db->fetch(
                'SELECT id, user_id, title, slug, body
                 FROM questions
                 WHERE id = :id AND deleted_at IS NULL AND approval_status = "pending"
                 LIMIT 1 FOR UPDATE',
                [':id' => $questionId]
            );
            if ($question === null) {
                return null;
            }

            if ($decision === 'approved') {
                $this->db->execute(
                    'UPDATE questions SET approval_status = "approved", approved_at = NOW(), approved_by = :moderator, updated_at = NOW() WHERE id = :id',
                    [':moderator' => $moderatorId, ':id' => $questionId]
                );
            } else {
                $this->db->execute(
                    'UPDATE questions SET approval_status = "declined", approved_by = :moderator, deleted_at = NOW(), updated_at = NOW() WHERE id = :id',
                    [':moderator' => $moderatorId, ':id' => $questionId]
                );
            }

            return [
                'question_id' => (int) $question['id'],
                'user_id' => (int) $question['user_id'],
                'title' => (string) $question['title'],
                'slug' => (string) $question['slug'],
                'body' => (string) $question['body'],
            ];
        });
    }

    public function resolveFlag(int $flagId, string $status): bool
    {
        if ($flagId < 1 || !in_array($status, ['resolved', 'rejected'], true)) {
            return false;
        }

        $updated = $this->db->query(
            'UPDATE flags SET status = :status, resolved_at = NOW() WHERE id = :id AND status = "open"',
            [':status' => $status, ':id' => $flagId]
        )->rowCount();

        return $updated > 0;
    }

    /** @return array<int, int> */
    public function softDelete(string $type, int $id): array
    {
        if ($type === 'comment') {
            return $this->db->transaction(function () use ($id): array {
                $commentIds = $this->commentThreadIds($id);
                if ($commentIds === []) {
                    return [];
                }

                $affectedUserIds = $this->deleteReputationFootprint([], [], $commentIds);
                $commentParams = [];
                $commentIn = $this->inParams($commentIds, 'comment_delete', $commentParams);
                $this->db->execute("DELETE FROM comments WHERE id IN ({$commentIn})", $commentParams);

                $voteParams = [':post_type' => 'comment'];
                $voteIn = $this->inParams($commentIds, 'comment_vote_delete', $voteParams);
                $this->db->execute("DELETE FROM votes WHERE post_type = :post_type AND post_id IN ({$voteIn})", $voteParams);

                $flagParams = [':post_type' => 'comment'];
                $flagIn = $this->inParams($commentIds, 'comment_flag_delete', $flagParams);
                $this->db->execute("DELETE FROM flags WHERE post_type = :post_type AND post_id IN ({$flagIn})", $flagParams);

                return $affectedUserIds;
            });
        }

        if ($type === 'answer') {
            return $this->db->transaction(function () use ($id): array {
                $answerIds = [$id];
                $commentIds = $this->commentIdsForAnswers($answerIds);
                $affectedUserIds = $this->deleteReputationFootprint([], $answerIds, $commentIds);

                $this->db->execute(
                    'UPDATE answers SET deleted_at = NOW(), updated_at = NOW(), is_accepted = 0 WHERE id = :id AND deleted_at IS NULL',
                    [':id' => $id]
                );
                $this->db->execute(
                    'UPDATE questions SET accepted_answer_id = NULL, updated_at = NOW() WHERE accepted_answer_id = :id',
                    [':id' => $id]
                );

                $commentParams = [];
                if ($commentIds !== []) {
                    $commentIn = $this->inParams($commentIds, 'answer_comment_delete', $commentParams);
                    $this->db->execute("DELETE FROM comments WHERE id IN ({$commentIn})", $commentParams);
                }

                $voteParams = [':post_type' => 'answer'];
                $voteIn = $this->inParams($answerIds, 'answer_vote_delete', $voteParams);
                $this->db->execute("DELETE FROM votes WHERE post_type = :post_type AND post_id IN ({$voteIn})", $voteParams);
                if ($commentIds !== []) {
                    $commentVoteParams = [':post_type' => 'comment'];
                    $commentVoteIn = $this->inParams($commentIds, 'answer_comment_vote_delete', $commentVoteParams);
                    $this->db->execute("DELETE FROM votes WHERE post_type = :post_type AND post_id IN ({$commentVoteIn})", $commentVoteParams);
                }

                $reactionParams = [':post_type' => 'answer'];
                $reactionIn = $this->inParams($answerIds, 'answer_reaction_delete', $reactionParams);
                $this->db->execute("DELETE FROM post_reactions WHERE post_type = :post_type AND post_id IN ({$reactionIn})", $reactionParams);

                $flagParams = [':post_type' => 'answer'];
                $flagIn = $this->inParams($answerIds, 'answer_flag_delete', $flagParams);
                $this->db->execute("DELETE FROM flags WHERE post_type = :post_type AND post_id IN ({$flagIn})", $flagParams);
                if ($commentIds !== []) {
                    $commentFlagParams = [':post_type' => 'comment'];
                    $commentFlagIn = $this->inParams($commentIds, 'answer_comment_flag_delete', $commentFlagParams);
                    $this->db->execute("DELETE FROM flags WHERE post_type = :post_type AND post_id IN ({$commentFlagIn})", $commentFlagParams);
                }

                return $affectedUserIds;
            });
        }

        return $this->db->transaction(function () use ($id): array {
            $questionIds = [$id];
            $answerIds = $this->answerIdsForQuestions($questionIds);
            $commentIds = array_values(array_unique(array_merge(
                $this->commentIdsForQuestions($questionIds),
                $this->commentIdsForAnswers($answerIds)
            )));
            $affectedUserIds = $this->deleteReputationFootprint($questionIds, $answerIds, $commentIds);

            $this->db->execute('UPDATE questions SET deleted_at = NOW(), accepted_answer_id = NULL, updated_at = NOW() WHERE id = :id', [':id' => $id]);
            if ($answerIds !== []) {
                $answerParams = [];
                $answerIn = $this->inParams($answerIds, 'question_answer_delete', $answerParams);
                $this->db->execute("UPDATE answers SET deleted_at = NOW(), updated_at = NOW(), is_accepted = 0 WHERE id IN ({$answerIn})", $answerParams);
            }

            if ($commentIds !== []) {
                $commentParams = [];
                $commentIn = $this->inParams($commentIds, 'question_comment_delete', $commentParams);
                $this->db->execute("DELETE FROM comments WHERE id IN ({$commentIn})", $commentParams);
            }

            $questionVoteParams = [':post_type' => 'question'];
            $questionVoteIn = $this->inParams($questionIds, 'question_vote_delete', $questionVoteParams);
            $this->db->execute("DELETE FROM votes WHERE post_type = :post_type AND post_id IN ({$questionVoteIn})", $questionVoteParams);

            if ($answerIds !== []) {
                $answerVoteParams = [':post_type' => 'answer'];
                $answerVoteIn = $this->inParams($answerIds, 'question_answer_vote_delete', $answerVoteParams);
                $this->db->execute("DELETE FROM votes WHERE post_type = :post_type AND post_id IN ({$answerVoteIn})", $answerVoteParams);
            }

            if ($commentIds !== []) {
                $commentVoteParams = [':post_type' => 'comment'];
                $commentVoteIn = $this->inParams($commentIds, 'question_comment_vote_delete', $commentVoteParams);
                $this->db->execute("DELETE FROM votes WHERE post_type = :post_type AND post_id IN ({$commentVoteIn})", $commentVoteParams);
            }

            $questionReactionParams = [':post_type' => 'question'];
            $questionReactionIn = $this->inParams($questionIds, 'question_reaction_delete', $questionReactionParams);
            $this->db->execute("DELETE FROM post_reactions WHERE post_type = :post_type AND post_id IN ({$questionReactionIn})", $questionReactionParams);

            if ($answerIds !== []) {
                $answerReactionParams = [':post_type' => 'answer'];
                $answerReactionIn = $this->inParams($answerIds, 'question_answer_reaction_delete', $answerReactionParams);
                $this->db->execute("DELETE FROM post_reactions WHERE post_type = :post_type AND post_id IN ({$answerReactionIn})", $answerReactionParams);
            }

            $questionFlagParams = [':post_type' => 'question'];
            $questionFlagIn = $this->inParams($questionIds, 'question_flag_delete', $questionFlagParams);
            $this->db->execute("DELETE FROM flags WHERE post_type = :post_type AND post_id IN ({$questionFlagIn})", $questionFlagParams);

            if ($answerIds !== []) {
                $answerFlagParams = [':post_type' => 'answer'];
                $answerFlagIn = $this->inParams($answerIds, 'question_answer_flag_delete', $answerFlagParams);
                $this->db->execute("DELETE FROM flags WHERE post_type = :post_type AND post_id IN ({$answerFlagIn})", $answerFlagParams);
            }

            if ($commentIds !== []) {
                $commentFlagParams = [':post_type' => 'comment'];
                $commentFlagIn = $this->inParams($commentIds, 'question_comment_flag_delete', $commentFlagParams);
                $this->db->execute("DELETE FROM flags WHERE post_type = :post_type AND post_id IN ({$commentFlagIn})", $commentFlagParams);
            }

            return $affectedUserIds;
        });
    }

    public function closeQuestion(int $id): bool
    {
        $updated = $this->db->query(
            'UPDATE questions q SET q.closed_at = COALESCE(q.closed_at, NOW()), q.updated_at = NOW() WHERE q.id = :id AND q.deleted_at IS NULL' . $this->approvalFilter('q'),
            [':id' => $id]
        )->rowCount();

        return $updated > 0;
    }

    /** @return array<string, mixed>|null */
    public function findPost(string $type, int $id): ?array
    {
        if ($type === 'question') {
            if ($this->duplicateColumnAvailable()) {
                return $this->db->fetch(
                    'SELECT q.id, q.title, q.body, q.slug, q.closed_at, q.duplicate_of_id,
                            dq.slug AS duplicate_slug, dq.title AS duplicate_title
                     FROM questions q
                     LEFT JOIN questions dq ON dq.id = q.duplicate_of_id AND dq.deleted_at IS NULL' . $this->approvalFilter('dq') . '
                     WHERE q.id = :id AND q.deleted_at IS NULL' . $this->approvalFilter('q') . ' LIMIT 1',
                    [':id' => $id]
                );
            }

            return $this->db->fetch(
                'SELECT id, title, body, slug, closed_at, NULL AS duplicate_of_id, NULL AS duplicate_slug, NULL AS duplicate_title
                 FROM questions q
                 WHERE q.id = :id AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
                 LIMIT 1',
                [':id' => $id]
            );
        }

        if ($type === 'answer') {
            return $this->db->fetch(
                'SELECT a.id, "" AS title, a.body, q.slug
                 FROM answers a
                 JOIN questions q ON q.id = a.question_id
                 WHERE a.id = :id AND a.deleted_at IS NULL AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
                 LIMIT 1',
                [':id' => $id]
            );
        }

        if ($type === 'comment') {
            return $this->db->fetch(
                'SELECT c.id, "" AS title, c.body, COALESCE(q.slug, aq.slug) AS slug
                 FROM comments c
                 LEFT JOIN questions q ON q.id = c.question_id
                 LEFT JOIN answers a ON a.id = c.answer_id
                 LEFT JOIN questions aq ON aq.id = a.question_id
                 WHERE c.id = :id' . $this->commentDeletedFilter('c') . ' AND COALESCE(q.deleted_at, aq.deleted_at) IS NULL
                   AND (c.answer_id IS NULL OR a.id IS NOT NULL)
                   AND ((c.question_id IS NOT NULL' . $this->approvalFilter('q') . ')
                        OR (c.answer_id IS NOT NULL' . $this->approvalFilter('aq') . '))
                 LIMIT 1',
                [':id' => $id]
            );
        }

        return null;
    }

    public function updatePost(string $type, int $id, string $title, string $body): void
    {
        if ($type === 'question') {
            $this->db->execute('UPDATE questions SET title = :title, body = :body, meta_description = :meta, updated_at = NOW() WHERE id = :id', [':title' => $title, ':body' => $body, ':meta' => excerpt($body, 155), ':id' => $id]);
            return;
        }

        if ($type === 'answer') {
            $this->db->execute('UPDATE answers SET body = :body, updated_at = NOW() WHERE id = :id', [':body' => $body, ':id' => $id]);
            return;
        }

        if ($type === 'comment') {
            $this->db->execute('UPDATE comments SET body = :body WHERE id = :id', [':body' => $body, ':id' => $id]);
        }
    }

    public function setDuplicateOf(int $questionId, ?int $duplicateOfId): bool
    {
        if ($questionId < 1 || !$this->duplicateColumnAvailable()) {
            return false;
        }

        if ($duplicateOfId === null) {
            $updated = $this->db->query(
                'UPDATE questions q SET q.duplicate_of_id = NULL, q.updated_at = NOW() WHERE q.id = :id AND q.deleted_at IS NULL' . $this->approvalFilter('q'),
                [':id' => $questionId]
            )->rowCount();

            return $updated > 0;
        }

        if ($duplicateOfId < 1 || $duplicateOfId === $questionId) {
            return false;
        }

        $target = $this->db->fetch(
            'SELECT q.id FROM questions q WHERE q.id = :id AND q.deleted_at IS NULL' . $this->approvalFilter('q') . ' AND (q.duplicate_of_id IS NULL OR q.duplicate_of_id <> :question_id) LIMIT 1',
            [':id' => $duplicateOfId, ':question_id' => $questionId]
        );
        if ($target === null) {
            return false;
        }

        $this->db->execute(
            'UPDATE questions q SET q.duplicate_of_id = :duplicate_of_id, q.closed_at = COALESCE(q.closed_at, NOW()), q.updated_at = NOW() WHERE q.id = :id AND q.deleted_at IS NULL' . $this->approvalFilter('q'),
            [':duplicate_of_id' => $duplicateOfId, ':id' => $questionId]
        );

        return $this->db->fetch(
            'SELECT q.id FROM questions q WHERE q.id = :id AND q.deleted_at IS NULL' . $this->approvalFilter('q') . ' AND q.duplicate_of_id = :duplicate_of_id LIMIT 1',
            [':id' => $questionId, ':duplicate_of_id' => $duplicateOfId]
        ) !== null;
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        $questions = $this->db->fetch(
            'SELECT COUNT(*) AS total
             FROM questions q
             WHERE q.deleted_at IS NULL' . $this->approvalFilter('q')
        );
        $answers = $this->db->fetch(
            'SELECT COUNT(*) AS total
             FROM answers a
             JOIN questions q ON q.id = a.question_id
             WHERE a.deleted_at IS NULL
               AND q.deleted_at IS NULL' . $this->approvalFilter('q')
        );
        $users = $this->db->fetch(
            'SELECT COUNT(*) AS total
             FROM users
             WHERE deleted_at IS NULL
               AND banned_at IS NULL'
        );

        return [
            'questions' => (int) ($questions['total'] ?? 0),
            'answers' => (int) ($answers['total'] ?? 0),
            'users' => (int) ($users['total'] ?? 0),
        ];
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

    /** @param array<int, int> $questionIds @return array<int, int> */
    private function answerIdsForQuestions(array $questionIds): array
    {
        if ($questionIds === []) {
            return [];
        }

        $params = [];
        $in = $this->inParams($questionIds, 'footprint_question_answer', $params);
        $rows = $this->db->fetchAll("SELECT id FROM answers WHERE question_id IN ({$in})", $params);

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /** @param array<int, int> $questionIds @return array<int, int> */
    private function commentIdsForQuestions(array $questionIds): array
    {
        if ($questionIds === []) {
            return [];
        }

        $params = [];
        $in = $this->inParams($questionIds, 'footprint_question_comment', $params);
        $rows = $this->db->fetchAll("SELECT id FROM comments WHERE question_id IN ({$in})", $params);

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /** @param array<int, int> $answerIds @return array<int, int> */
    private function commentIdsForAnswers(array $answerIds): array
    {
        if ($answerIds === []) {
            return [];
        }

        $params = [];
        $in = $this->inParams($answerIds, 'footprint_answer_comment', $params);
        $rows = $this->db->fetchAll("SELECT id FROM comments WHERE answer_id IN ({$in})", $params);

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * @param array<int, int> $questionIds
     * @param array<int, int> $answerIds
     * @param array<int, int> $commentIds
     * @return array<int, int>
     */
    private function deleteReputationFootprint(array $questionIds, array $answerIds, array $commentIds): array
    {
        $conditions = [];
        $params = [];

        if ($questionIds !== []) {
            $questionIn = $this->inParams($questionIds, 'rep_question', $params);
            $conditions[] = "(source_type = 'question' AND source_id IN ({$questionIn}))";
        }

        if ($answerIds !== []) {
            $answerIn = $this->inParams($answerIds, 'rep_answer', $params);
            $conditions[] = "(source_type = 'answer' AND source_id IN ({$answerIn}))";
        }

        if ($commentIds !== []) {
            $commentIn = $this->inParams($commentIds, 'rep_comment', $params);
            $conditions[] = "(source_type = 'comment' AND source_id IN ({$commentIn}))";
        }

        if ($conditions === []) {
            return [];
        }

        $where = implode(' OR ', $conditions);
        $users = $this->db->fetchAll("SELECT DISTINCT user_id FROM reputation_events WHERE {$where}", $params);
        $userIds = array_map(static fn (array $row): int => (int) $row['user_id'], $users);

        $this->db->execute("DELETE FROM reputation_events WHERE {$where}", $params);

        foreach ($userIds as $userId) {
            $total = $this->db->fetch('SELECT COALESCE(SUM(points), 0) AS total FROM reputation_events WHERE user_id = :user', [':user' => $userId]);
            $this->db->execute(
                'UPDATE users SET reputation = GREATEST(0, :reputation), updated_at = NOW() WHERE id = :id',
                [':reputation' => (int) ($total['total'] ?? 0), ':id' => $userId]
            );
        }

        return $userIds;
    }

    /** @return array<int, int> */
    private function commentThreadIds(int $rootId): array
    {
        if ($rootId < 1) {
            return [];
        }

        $seen = [];
        $frontier = [$rootId];

        while ($frontier !== []) {
            $current = [];
            foreach ($frontier as $candidate) {
                if ($candidate > 0 && !isset($seen[$candidate])) {
                    $seen[$candidate] = true;
                    $current[] = $candidate;
                }
            }

            if ($current === []) {
                break;
            }

            $params = [];
            $in = $this->inParams($current, 'comment_children', $params);
            $children = $this->db->fetchAll("SELECT id FROM comments WHERE parent_id IN ({$in})", $params);
            $frontier = array_map(static fn (array $row): int => (int) $row['id'], $children);
        }

        return array_map('intval', array_keys($seen));
    }

    private function duplicateColumnAvailable(): bool
    {
        static $available = null;
        if ($available !== null) {
            return $available;
        }

        try {
            $row = $this->db->fetch(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'questions' AND COLUMN_NAME = 'duplicate_of_id' LIMIT 1"
            );
            $available = $row !== null;
        } catch (Throwable) {
            $available = false;
        }

        return $available;
    }

    private function commentDeletedFilter(string $alias = 'comments'): string
    {
        if ($this->commentDeletedColumnAvailable !== null) {
            return $this->commentDeletedColumnAvailable ? ' AND ' . $alias . '.deleted_at IS NULL' : '';
        }

        try {
            $this->commentDeletedColumnAvailable = $this->db->fetch(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comments' AND COLUMN_NAME = 'deleted_at' LIMIT 1"
            ) !== null;
        } catch (Throwable) {
            $this->commentDeletedColumnAvailable = false;
        }

        return $this->commentDeletedColumnAvailable ? ' AND ' . $alias . '.deleted_at IS NULL' : '';
    }

    private function approvalFilter(string $alias = 'q'): string
    {
        if (!$this->approvalColumnsAvailable()) {
            return '';
        }

        return ' AND ' . $alias . '.approval_status = "approved"';
    }

    private function approvalColumnsAvailable(): bool
    {
        if ($this->approvalColumnsAvailable !== null) {
            return $this->approvalColumnsAvailable;
        }

        try {
            $this->approvalColumnsAvailable = $this->columnExists('approval_status')
                && $this->columnExists('approved_at')
                && $this->columnExists('approved_by');
        } catch (Throwable) {
            $this->approvalColumnsAvailable = false;
        }

        return $this->approvalColumnsAvailable;
    }

    private function columnExists(string $column): bool
    {
        return $this->db->fetch(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'questions' AND COLUMN_NAME = :column LIMIT 1",
            [':column' => $column]
        ) !== null;
    }
}

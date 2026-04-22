<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SettingsModel;
use Core\Database;
use JsonException;

final class WidgetService
{
    private const SETTINGS_KEY = 'dynamic_widgets_config';
    private const MAX_LIMIT = 12;
    private const DEVICES = ['desktop', 'phone'];

    private ?bool $approvalColumnAvailable = null;
    private ?bool $commentDeletedColumnAvailable = null;

    /** @return array<string, array<string, string>> */
    public function types(): array
    {
        return [
            'categories' => ['label' => 'Categories', 'description' => 'Category links with public question counts.'],
            'last_registered_users' => ['label' => 'Last Registered Users', 'description' => 'Newest public members with avatars.'],
            'popular_questions' => ['label' => 'Most Popular Questions', 'description' => 'Questions ranked by views, votes, and answers.'],
            'accepted_answers' => ['label' => 'Best Accepted Answers', 'description' => 'Accepted answers ranked by community response.'],
            'community_answers' => ['label' => 'Community Chosen Answers', 'description' => 'Answers ranked by vote and reaction score.'],
            'top_users' => ['label' => 'Top Users By Activity', 'description' => 'Active members ranked by public contributions.'],
            'platform_stats' => ['label' => 'Platform Statistics', 'description' => 'Public counts for questions, answers, members, and clarifications.'],
        ];
    }

    public function __construct(private SettingsModel $settings, private Database $db) {}

    /** @return array<string, mixed> */
    public function adminPage(): array
    {
        return [
            'widgetConfig' => $this->config(),
            'widgetTypes' => $this->types(),
            'widgetZones' => ['left' => 'Left column', 'right' => 'Right column'],
            'widgetPages' => ['landing' => 'Landing page', 'question' => 'Question pages'],
            'widgetDevices' => ['desktop' => 'Desktop', 'phone' => 'Phone'],
        ];
    }

    /** @return array<string, mixed> */
    public function page(string $page, bool $guest): array
    {
        $config = $this->config();
        $result = [
            'enabled' => !empty($config['enabled']),
            'devices' => $this->cleanDevices(is_array($config['devices'] ?? null) ? $config['devices'] : self::DEVICES),
            'welcome' => null,
            'zones' => ['left' => [], 'right' => []],
            'has_widgets' => false,
        ];

        if ($guest && !empty($config['welcome']['enabled']) && in_array($page, $config['welcome']['pages'], true)) {
            $result['welcome'] = [
                'title' => (string) $config['welcome']['title'],
                'message' => (string) $config['welcome']['message'],
                'button_label' => (string) $config['welcome']['button_label'],
            ];
        }

        if (empty($config['enabled']) || $result['devices'] === []) {
            return $result;
        }

        foreach ($config['widgets'] as $type => $widget) {
            $devices = $this->cleanDevices(is_array($widget['devices'] ?? null) ? $widget['devices'] : self::DEVICES);
            if (empty($widget['enabled']) || $devices === [] || !in_array($page, $widget['pages'], true)) {
                continue;
            }

            $items = $this->itemsFor($type, (int) $widget['limit']);
            if ($items === []) {
                continue;
            }

            $zone = (string) $widget['zone'];
            $result['zones'][$zone][] = [
                'type' => $type,
                'title' => (string) $widget['title'],
                'devices' => $devices,
                'items' => $items,
            ];
            $result['has_widgets'] = true;
        }

        return $result;
    }

    /** @param array<string, mixed> $input */
    public function save(array $input): void
    {
        $config = $this->sanitize($input);
        $this->settings->set(self::SETTINGS_KEY, json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    public function config(): array
    {
        $raw = $this->settings->get(self::SETTINGS_KEY, '');
        if ($raw === '') {
            return $this->defaultConfig();
        }

        try {
            $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->defaultConfig();
        }

        return $this->mergeConfig(is_array($decoded) ? $decoded : []);
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function sanitize(array $input): array
    {
        $defaults = $this->defaultConfig();
        $widgetsInput = is_array($input['widgets'] ?? null) ? $input['widgets'] : [];
        $welcomeInput = is_array($input['guest_welcome'] ?? null) ? $input['guest_welcome'] : [];
        $config = [
            'enabled' => !empty($input['widgets_enabled']),
            'devices' => $this->cleanDevices(is_array($input['widget_devices'] ?? null) ? $input['widget_devices'] : []),
            'welcome' => [
                'enabled' => !empty($welcomeInput['enabled']),
                'pages' => $this->cleanPages(is_array($welcomeInput['pages'] ?? null) ? $welcomeInput['pages'] : []),
                'title' => $this->cleanText((string) ($welcomeInput['title'] ?? $defaults['welcome']['title']), 90),
                'message' => $this->cleanText((string) ($welcomeInput['message'] ?? $defaults['welcome']['message']), 220),
                'button_label' => $this->cleanText((string) ($welcomeInput['button_label'] ?? $defaults['welcome']['button_label']), 40),
            ],
            'widgets' => [],
        ];

        $config['welcome']['title'] = $config['welcome']['title'] !== '' ? $config['welcome']['title'] : (string) $defaults['welcome']['title'];
        $config['welcome']['message'] = $config['welcome']['message'] !== '' ? $config['welcome']['message'] : (string) $defaults['welcome']['message'];
        $config['welcome']['button_label'] = $config['welcome']['button_label'] !== '' ? $config['welcome']['button_label'] : (string) $defaults['welcome']['button_label'];

        foreach ($this->types() as $type => $meta) {
            $widgetInput = is_array($widgetsInput[$type] ?? null) ? $widgetsInput[$type] : [];
            $defaultWidget = $defaults['widgets'][$type];
            $zone = (string) ($widgetInput['zone'] ?? $defaultWidget['zone']);
            $title = $this->cleanText((string) ($widgetInput['title'] ?? $meta['label']), 80);
            $config['widgets'][$type] = [
                'enabled' => !empty($widgetInput['enabled']),
                'title' => $title !== '' ? $title : (string) $meta['label'],
                'zone' => in_array($zone, ['left', 'right'], true) ? $zone : (string) $defaultWidget['zone'],
                'pages' => $this->cleanPages(is_array($widgetInput['pages'] ?? null) ? $widgetInput['pages'] : []),
                'devices' => $this->cleanDevices(is_array($widgetInput['devices'] ?? null) ? $widgetInput['devices'] : []),
                'limit' => max(1, min(self::MAX_LIMIT, (int) ($widgetInput['limit'] ?? $defaultWidget['limit']))),
            ];
        }

        return $config;
    }

    /** @param array<string, mixed> $stored @return array<string, mixed> */
    private function mergeConfig(array $stored): array
    {
        $defaults = $this->defaultConfig();
        $merged = $defaults;
        $merged['enabled'] = (bool) ($stored['enabled'] ?? $defaults['enabled']);
        $merged['devices'] = $this->cleanDevices(is_array($stored['devices'] ?? null) ? $stored['devices'] : $defaults['devices']);

        if (is_array($stored['welcome'] ?? null)) {
            $welcome = $stored['welcome'];
            $merged['welcome'] = [
                'enabled' => (bool) ($welcome['enabled'] ?? $defaults['welcome']['enabled']),
                'pages' => $this->cleanPages(is_array($welcome['pages'] ?? null) ? $welcome['pages'] : $defaults['welcome']['pages']),
                'title' => $this->cleanText((string) ($welcome['title'] ?? $defaults['welcome']['title']), 90),
                'message' => $this->cleanText((string) ($welcome['message'] ?? $defaults['welcome']['message']), 220),
                'button_label' => $this->cleanText((string) ($welcome['button_label'] ?? $defaults['welcome']['button_label']), 40),
            ];
        }

        $storedWidgets = is_array($stored['widgets'] ?? null) ? $stored['widgets'] : [];
        foreach ($this->types() as $type => $meta) {
            if (!is_array($storedWidgets[$type] ?? null)) {
                continue;
            }
            $widget = $storedWidgets[$type];
            $zone = (string) ($widget['zone'] ?? $defaults['widgets'][$type]['zone']);
            $title = $this->cleanText((string) ($widget['title'] ?? $meta['label']), 80);
            $merged['widgets'][$type] = [
                'enabled' => (bool) ($widget['enabled'] ?? $defaults['widgets'][$type]['enabled']),
                'title' => $title !== '' ? $title : (string) $meta['label'],
                'zone' => in_array($zone, ['left', 'right'], true) ? $zone : (string) $defaults['widgets'][$type]['zone'],
                'pages' => $this->cleanPages(is_array($widget['pages'] ?? null) ? $widget['pages'] : $defaults['widgets'][$type]['pages']),
                'devices' => $this->cleanDevices(is_array($widget['devices'] ?? null) ? $widget['devices'] : $defaults['widgets'][$type]['devices']),
                'limit' => max(1, min(self::MAX_LIMIT, (int) ($widget['limit'] ?? $defaults['widgets'][$type]['limit']))),
            ];
        }

        return $merged;
    }

    /** @return array<string, mixed> */
    private function defaultConfig(): array
    {
        return [
            'enabled' => false,
            'devices' => self::DEVICES,
            'welcome' => [
                'enabled' => false,
                'pages' => ['landing', 'question'],
                'title' => 'Join the community',
                'message' => 'Create an account to ask questions, answer discussions, vote, and follow your activity.',
                'button_label' => 'Register',
            ],
            'widgets' => [
                'categories' => ['enabled' => true, 'title' => 'Categories', 'zone' => 'left', 'pages' => ['landing', 'question'], 'devices' => self::DEVICES, 'limit' => 8],
                'last_registered_users' => ['enabled' => true, 'title' => 'New Members', 'zone' => 'right', 'pages' => ['landing'], 'devices' => self::DEVICES, 'limit' => 6],
                'popular_questions' => ['enabled' => true, 'title' => 'Popular Questions', 'zone' => 'right', 'pages' => ['landing', 'question'], 'devices' => self::DEVICES, 'limit' => 5],
                'accepted_answers' => ['enabled' => false, 'title' => 'Accepted Answers', 'zone' => 'left', 'pages' => ['question'], 'devices' => self::DEVICES, 'limit' => 5],
                'community_answers' => ['enabled' => false, 'title' => 'Community Chosen', 'zone' => 'right', 'pages' => ['landing', 'question'], 'devices' => self::DEVICES, 'limit' => 5],
                'top_users' => ['enabled' => false, 'title' => 'Top Users', 'zone' => 'left', 'pages' => ['landing', 'question'], 'devices' => self::DEVICES, 'limit' => 6],
                'platform_stats' => ['enabled' => true, 'title' => 'Platform Stats', 'zone' => 'left', 'pages' => ['landing'], 'devices' => self::DEVICES, 'limit' => 4],
            ],
        ];
    }

    /** @param array<int, mixed> $pages @return array<int, string> */
    private function cleanPages(array $pages): array
    {
        return array_values(array_unique(array_intersect(['landing', 'question'], array_map('strval', $pages))));
    }

    /** @param array<int, mixed> $devices @return array<int, string> */
    private function cleanDevices(array $devices): array
    {
        return array_values(array_unique(array_intersect(self::DEVICES, array_map('strval', $devices))));
    }

    private function cleanText(string $value, int $length): string
    {
        $value = trim(str_replace("\0", '', $value));
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        return mb_substr($value, 0, $length);
    }

    /** @return array<int, array<string, mixed>> */
    private function itemsFor(string $type, int $limit): array
    {
        return match ($type) {
            'categories' => $this->categories($limit),
            'last_registered_users' => $this->lastRegisteredUsers($limit),
            'popular_questions' => $this->popularQuestions($limit),
            'accepted_answers' => $this->acceptedAnswers($limit),
            'community_answers' => $this->communityAnswers($limit),
            'top_users' => $this->topUsers($limit),
            'platform_stats' => $this->platformStats(),
            default => [],
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function categories(int $limit): array
    {
        return $this->db->fetchAll(
            'SELECT c.name, c.slug, COUNT(DISTINCT q.id) AS count
             FROM categories c
             JOIN questions q ON q.category_id = c.id AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
             GROUP BY c.id, c.name, c.slug
             ORDER BY count DESC, c.name ASC
             LIMIT :limit',
            [':limit' => $limit]
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function lastRegisteredUsers(int $limit): array
    {
        return $this->db->fetchAll(
            'SELECT username, avatar_path
             FROM users
             WHERE deleted_at IS NULL
               AND banned_at IS NULL
             ORDER BY created_at DESC
             LIMIT :limit',
            [':limit' => $limit]
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function popularQuestions(int $limit): array
    {
        return $this->db->fetchAll(
            'SELECT q.title, q.slug, q.views, q.votes, COUNT(DISTINCT a.id) AS answer_count
             FROM questions q
             LEFT JOIN answers a ON a.question_id = q.id AND a.deleted_at IS NULL
             WHERE q.deleted_at IS NULL' . $this->approvalFilter('q') . '
             GROUP BY q.id, q.title, q.slug, q.views, q.votes
             ORDER BY (q.views + (q.votes * 4) + (COUNT(DISTINCT a.id) * 8)) DESC, q.created_at DESC
             LIMIT :limit',
            [':limit' => $limit]
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function acceptedAnswers(int $limit): array
    {
        return $this->db->fetchAll(
            'SELECT a.id, a.body, a.votes, q.title AS question_title, q.slug AS question_slug, u.username
             FROM answers a
             JOIN questions q ON q.id = a.question_id AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
             JOIN users u ON u.id = a.user_id AND u.deleted_at IS NULL AND u.banned_at IS NULL
             WHERE a.deleted_at IS NULL AND a.is_accepted = 1
             ORDER BY a.votes DESC, a.created_at DESC
             LIMIT :limit',
            [':limit' => $limit]
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function communityAnswers(int $limit): array
    {
        return $this->db->fetchAll(
            'SELECT a.id, a.body, a.votes, q.title AS question_title, q.slug AS question_slug, u.username,
                    COALESCE(reactions.reaction_count, 0) AS reaction_count,
                    COALESCE(reactions.reaction_score, 0) AS reaction_score,
                    COALESCE(a.votes, 0) + COALESCE(reactions.reaction_score, 0) AS score
             FROM answers a
             JOIN questions q ON q.id = a.question_id AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
             JOIN users u ON u.id = a.user_id AND u.deleted_at IS NULL AND u.banned_at IS NULL
             LEFT JOIN (
                SELECT pr.post_id, COUNT(*) AS reaction_count, COALESCE(SUM(rt.reputation_delta), 0) AS reaction_score
                FROM post_reactions pr
                JOIN reaction_types rt ON rt.id = pr.reaction_type_id AND rt.is_active = 1
                WHERE pr.post_type = \'answer\'
                GROUP BY pr.post_id
             ) reactions ON reactions.post_id = a.id
             WHERE a.deleted_at IS NULL
               AND COALESCE(a.votes, 0) + COALESCE(reactions.reaction_score, 0) > 0
               AND NOT EXISTS (
                    SELECT 1
                    FROM answers competing_answer
                    LEFT JOIN (
                        SELECT pr.post_id, COUNT(*) AS reaction_count, COALESCE(SUM(rt.reputation_delta), 0) AS reaction_score
                        FROM post_reactions pr
                        JOIN reaction_types rt ON rt.id = pr.reaction_type_id AND rt.is_active = 1
                        WHERE pr.post_type = \'answer\'
                        GROUP BY pr.post_id
                    ) competing_reactions ON competing_reactions.post_id = competing_answer.id
                    WHERE competing_answer.question_id = a.question_id
                      AND competing_answer.deleted_at IS NULL
                      AND (
                            COALESCE(competing_answer.votes, 0) + COALESCE(competing_reactions.reaction_score, 0) > COALESCE(a.votes, 0) + COALESCE(reactions.reaction_score, 0)
                         OR (
                            COALESCE(competing_answer.votes, 0) + COALESCE(competing_reactions.reaction_score, 0) = COALESCE(a.votes, 0) + COALESCE(reactions.reaction_score, 0)
                            AND COALESCE(competing_reactions.reaction_count, 0) > COALESCE(reactions.reaction_count, 0)
                         )
                         OR (
                            COALESCE(competing_answer.votes, 0) + COALESCE(competing_reactions.reaction_score, 0) = COALESCE(a.votes, 0) + COALESCE(reactions.reaction_score, 0)
                            AND COALESCE(competing_reactions.reaction_count, 0) = COALESCE(reactions.reaction_count, 0)
                            AND competing_answer.created_at < a.created_at
                         )
                         OR (
                            COALESCE(competing_answer.votes, 0) + COALESCE(competing_reactions.reaction_score, 0) = COALESCE(a.votes, 0) + COALESCE(reactions.reaction_score, 0)
                            AND COALESCE(competing_reactions.reaction_count, 0) = COALESCE(reactions.reaction_count, 0)
                            AND competing_answer.created_at = a.created_at
                            AND competing_answer.id < a.id
                         )
                      )
               )
             ORDER BY score DESC, reaction_count DESC, a.created_at ASC, a.id ASC
             LIMIT :limit',
            [':limit' => $limit]
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function topUsers(int $limit): array
    {
        $commentFilter = $this->commentDeletedFilter('c');

        return $this->db->fetchAll(
            'SELECT u.username, u.avatar_path, u.reputation,
                    COUNT(DISTINCT q.id) AS question_count,
                    COUNT(DISTINCT CASE WHEN aq.id IS NOT NULL THEN a.id END) AS answer_count,
                    COUNT(DISTINCT CASE WHEN cq.id IS NOT NULL OR caq.id IS NOT NULL THEN c.id END) AS comment_count,
                    (COUNT(DISTINCT q.id) * 3 + COUNT(DISTINCT CASE WHEN aq.id IS NOT NULL THEN a.id END) * 2 + COUNT(DISTINCT CASE WHEN cq.id IS NOT NULL OR caq.id IS NOT NULL THEN c.id END)) AS activity_score
             FROM users u
             LEFT JOIN questions q ON q.user_id = u.id AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
             LEFT JOIN answers a ON a.user_id = u.id AND a.deleted_at IS NULL
             LEFT JOIN questions aq ON aq.id = a.question_id AND aq.deleted_at IS NULL' . $this->approvalFilter('aq') . '
             LEFT JOIN comments c ON c.user_id = u.id' . $commentFilter . '
             LEFT JOIN questions cq ON cq.id = c.question_id AND cq.deleted_at IS NULL' . $this->approvalFilter('cq') . '
             LEFT JOIN answers ca ON ca.id = c.answer_id AND ca.deleted_at IS NULL
             LEFT JOIN questions caq ON caq.id = ca.question_id AND caq.deleted_at IS NULL' . $this->approvalFilter('caq') . '
             WHERE u.deleted_at IS NULL
               AND u.banned_at IS NULL
             GROUP BY u.id, u.username, u.avatar_path, u.reputation
             HAVING activity_score > 0
             ORDER BY activity_score DESC, u.reputation DESC, u.created_at DESC
             LIMIT :limit',
            [':limit' => $limit]
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function platformStats(): array
    {
        $commentFilter = $this->commentDeletedFilter('c');

        $row = $this->db->fetch(
            'SELECT
                (SELECT COUNT(*) FROM questions q WHERE q.deleted_at IS NULL' . $this->approvalFilter('q') . ') AS questions,
                (SELECT COUNT(*) FROM answers a JOIN questions q ON q.id = a.question_id AND q.deleted_at IS NULL' . $this->approvalFilter('q') . ' WHERE a.deleted_at IS NULL) AS answers,
                (SELECT COUNT(*)
                 FROM comments c
                 LEFT JOIN answers a ON a.id = c.answer_id AND a.deleted_at IS NULL
                 JOIN questions q ON q.id = COALESCE(c.question_id, a.question_id) AND q.deleted_at IS NULL' . $this->approvalFilter('q') . '
                 WHERE (c.answer_id IS NULL OR a.id IS NOT NULL)' . $commentFilter . ') AS clarifications,
                (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND banned_at IS NULL) AS users'
        ) ?? [];

        return [
            ['label' => 'Questions', 'value' => (int) ($row['questions'] ?? 0)],
            ['label' => 'Answers', 'value' => (int) ($row['answers'] ?? 0)],
            ['label' => 'Clarifications', 'value' => (int) ($row['clarifications'] ?? 0)],
            ['label' => 'Members', 'value' => (int) ($row['users'] ?? 0)],
        ];
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
        } catch (\Throwable) {
            $this->approvalColumnAvailable = false;
        }

        return $this->approvalColumnAvailable ? ' AND ' . $alias . '.approval_status = "approved"' : '';
    }

    private function commentDeletedFilter(string $alias): string
    {
        if ($this->commentDeletedColumnAvailable !== null) {
            return $this->commentDeletedColumnAvailable ? ' AND ' . $alias . '.deleted_at IS NULL' : '';
        }

        try {
            $this->commentDeletedColumnAvailable = $this->db->fetch(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comments' AND COLUMN_NAME = 'deleted_at' LIMIT 1"
            ) !== null;
        } catch (\Throwable) {
            $this->commentDeletedColumnAvailable = false;
        }

        return $this->commentDeletedColumnAvailable ? ' AND ' . $alias . '.deleted_at IS NULL' : '';
    }
}

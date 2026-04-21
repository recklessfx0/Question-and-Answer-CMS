<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\QuestionModel;
use App\Models\AuditLogModel;
use App\Models\NotificationModel;
use App\Models\UserModel;
use Core\Session;
use Core\Queue;
use Core\Logger;

final class ModerationService
{
    private const PENDING_PAGE_SIZE = 10;
    private const REPORTS_PAGE_SIZE = 10;
    private const AUDIT_PAGE_SIZE = 15;

    public function __construct(
        private QuestionModel $questions,
        private AuditLogModel $audit,
        private NotificationModel $notifications,
        private GamificationService $gamification,
        private Session $session,
        private UserModel $users,
        private Queue $queue,
        private Logger $logger,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function flags(): array
    {
        return $this->questions->openFlags();
    }

    /** @return array<string, mixed> */
    public function dashboard(int $pendingPage, int $reportsPage, int $auditPage): array
    {
        $pendingTotal = $this->questions->countPendingQuestions();
        $pendingPage = $this->boundedPage($pendingPage, $pendingTotal, self::PENDING_PAGE_SIZE);
        $pendingOffset = ($pendingPage - 1) * self::PENDING_PAGE_SIZE;

        $reportsTotal = $this->questions->countOpenFlags();
        $reportsPage = $this->boundedPage($reportsPage, $reportsTotal, self::REPORTS_PAGE_SIZE);
        $reportsOffset = ($reportsPage - 1) * self::REPORTS_PAGE_SIZE;

        $auditTotal = $this->audit->count();
        $auditPage = $this->boundedPage($auditPage, $auditTotal, self::AUDIT_PAGE_SIZE);
        $auditOffset = ($auditPage - 1) * self::AUDIT_PAGE_SIZE;

        return [
            'pendingQuestions' => $this->questions->pendingQuestions(self::PENDING_PAGE_SIZE, $pendingOffset),
            'flags' => $this->questions->openFlags(self::REPORTS_PAGE_SIZE, $reportsOffset),
            'auditLog' => $this->audit->latest(self::AUDIT_PAGE_SIZE, $auditOffset),
            'moderatorStats' => [
                'pending_questions' => $pendingTotal,
                'open_reports' => $reportsTotal,
                'audit_entries' => $auditTotal,
                'pending_page_size' => self::PENDING_PAGE_SIZE,
                'report_page_size' => self::REPORTS_PAGE_SIZE,
                'audit_page_size' => self::AUDIT_PAGE_SIZE,
            ],
            'pendingPagination' => $this->pagination(
                'pending_page',
                $pendingTotal,
                $pendingPage,
                self::PENDING_PAGE_SIZE,
                ['reports_page' => $reportsPage, 'audit_page' => $auditPage]
            ),
            'reportsPagination' => $this->pagination(
                'reports_page',
                $reportsTotal,
                $reportsPage,
                self::REPORTS_PAGE_SIZE,
                ['pending_page' => $pendingPage, 'audit_page' => $auditPage]
            ),
            'auditPagination' => $this->pagination(
                'audit_page',
                $auditTotal,
                $auditPage,
                self::AUDIT_PAGE_SIZE,
                ['pending_page' => $pendingPage, 'reports_page' => $reportsPage]
            ),
        ];
    }

    public function moderateQuestion(int $questionId, string $decision): bool
    {
        $moderatorId = (int) $this->session->get('user_id');
        $result = $this->questions->moderatePendingQuestion($questionId, $moderatorId, $decision);
        if ($result === null) {
            return false;
        }

        if ($decision === 'approved') {
            $this->gamification->award((int) $result['user_id'], 3, 'question_created', 'question', (int) $result['question_id']);
            $this->notifyApprovedQuestionMentions(
                (string) ($result['body'] ?? ''),
                (int) $result['user_id'],
                (string) ($result['title'] ?? ''),
                (string) ($result['slug'] ?? '')
            );
        }

        $this->notifyQuestionModerationDecision($moderatorId, $result, $decision);
        $this->audit->record($moderatorId, 'question_' . $decision, 'question', (int) $result['question_id']);
        return true;
    }

    public function delete(string $type, int $id): void
    {
        if (in_array($type, ['question', 'answer', 'comment'], true)) {
            $affectedUserIds = $this->questions->softDelete($type, $id);
            $this->gamification->recalculateUsers($affectedUserIds);
            $this->audit->record((int) $this->session->get('user_id'), 'delete', $type, $id);
        }
    }

    /** @return array<string, mixed>|null */
    public function findPost(string $type, int $id): ?array
    {
        return $this->questions->findPost($type, $id);
    }

    public function update(string $type, int $id, string $title, string $body): bool
    {
        $minimumLength = $type === 'comment' ? 3 : 10;
        if (!in_array($type, ['question', 'answer', 'comment'], true) || $id < 1 || mb_strlen($body) < $minimumLength) {
            return false;
        }

        if ($this->questions->findPost($type, $id) === null) {
            return false;
        }

        $this->questions->updatePost($type, $id, $title, $body);
        $this->audit->record((int) $this->session->get('user_id'), 'edit', $type, $id);

        return true;
    }

    public function closeQuestion(int $questionId): void
    {
        if ($questionId < 1) {
            return;
        }

        $this->questions->closeQuestion($questionId);
        $this->audit->record((int) $this->session->get('user_id'), 'close', 'question', $questionId);
    }

    public function markDuplicate(int $questionId, ?int $duplicateOfId): bool
    {
        if (!$this->questions->setDuplicateOf($questionId, $duplicateOfId)) {
            return false;
        }

        $this->audit->record((int) $this->session->get('user_id'), $duplicateOfId === null ? 'clear_duplicate' : 'mark_duplicate', 'question', $questionId);
        return true;
    }

    public function verifyAnswer(int $answerId, string $note): bool
    {
        $moderatorId = (int) $this->session->get('user_id');
        if ($this->questions->verifyAnswer($answerId, $moderatorId, $note)) {
            $this->audit->record($moderatorId, 'verify', 'answer', $answerId);
            return true;
        }

        return false;
    }

    public function unverifyAnswer(int $answerId): bool
    {
        if ($this->questions->unverifyAnswer($answerId)) {
            $this->audit->record((int) $this->session->get('user_id'), 'unverify', 'answer', $answerId);
            return true;
        }

        return false;
    }

    public function resolveFlag(int $flagId, string $status): bool
    {
        if (!$this->questions->resolveFlag($flagId, $status)) {
            return false;
        }

        $this->audit->record((int) $this->session->get('user_id'), 'flag_' . $status, 'flag', $flagId);
        return true;
    }

    /** @return array<int, array<string, mixed>> */
    public function auditLog(): array
    {
        return $this->audit->latest();
    }

    private function boundedPage(int $page, int $total, int $perPage): int
    {
        $totalPages = max(1, (int) ceil($total / $perPage));

        return min(max(1, $page), $totalPages);
    }

    /** @param array<string, int> $preserved */
    private function pagination(string $pageKey, int $total, int $page, int $perPage, array $preserved): array
    {
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $totalPages);
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        if ($end - $start < 4) {
            $start = max(1, min($start, $end - 4));
            $end = min($totalPages, max($end, $start + 4));
        }

        $url = static function (int $target) use ($pageKey, $preserved): string {
            $query = [];
            foreach ($preserved as $key => $value) {
                if ($value > 1) {
                    $query[$key] = $value;
                }
            }
            if ($target > 1) {
                $query[$pageKey] = $target;
            }

            return '/moderate' . ($query === [] ? '' : '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
        };

        return [
            'current' => $page,
            'total_pages' => $totalPages,
            'total_items' => $total,
            'window' => range($start, $end),
            'previous' => $page > 1 ? $url($page - 1) : null,
            'next' => $page < $totalPages ? $url($page + 1) : null,
            'href' => $url,
        ];
    }

    private function notifyApprovedQuestionMentions(string $body, int $authorId, string $title, string $slug): void
    {
        foreach ($this->mentionedUsers($body, $authorId) as $user) {
            try {
                $this->queue->push('MentionNotification', [
                    'user_id' => (int) $user['id'],
                    'type' => 'Mention',
                    'message' => 'You were mentioned in ' . $title,
                    'url' => '/question/' . $slug,
                ]);
            } catch (\Throwable $e) {
                $this->logger->error($e);
            }
        }
    }

    /** @param array{question_id: int, user_id: int, title: string, slug: string, body: string} $result */
    private function notifyQuestionModerationDecision(int $moderatorId, array $result, string $decision): void
    {
        $targetUserId = (int) ($result['user_id'] ?? 0);
        if ($targetUserId < 1 || $targetUserId === $moderatorId) {
            return;
        }

        $questionTitle = (string) ($result['title'] ?? 'your question');
        $questionSlug = (string) ($result['slug'] ?? '');
        $payload = $decision === 'approved'
            ? [
                'message' => 'Your question "' . $questionTitle . '" was approved.',
                'url' => $questionSlug !== '' ? '/question/' . $questionSlug : '/notifications',
            ]
            : [
                'message' => 'Your question "' . $questionTitle . '" was declined.',
                'url' => '/ask',
            ];

        $type = $decision === 'approved' ? 'Question approved' : 'Question declined';
        $this->notifications->createUniqueRecent($targetUserId, $type, $payload, 7 * 86400);
    }

    /** @return array<int, array<string, mixed>> */
    private function mentionedUsers(string $body, int $authorId): array
    {
        $text = preg_replace('/`[^`]+`/', ' ', $body) ?? $body;
        $text = preg_replace('/!\[[^\]]*\]\((?:\/public-assets\/images\/[a-zA-Z0-9\/._-]+|https?:\/\/[^\s)]+)\)/i', ' ', $text) ?? $text;
        $text = preg_replace('/\[[^\]]+\]\(https?:\/\/[^\s)]+\)/i', ' ', $text) ?? $text;
        preg_match_all('/(^|[\s>(])@([a-z0-9][a-z0-9_-]{2,31})\b/i', $text, $matches);

        $users = [];
        $seenNames = [];
        foreach ($matches[2] ?? [] as $username) {
            $key = strtolower((string) $username);
            if (isset($seenNames[$key])) {
                continue;
            }
            $seenNames[$key] = true;

            $user = $this->users->findByUsername((string) $username);
            if ($user === null || (int) ($user['id'] ?? 0) === $authorId) {
                continue;
            }

            $users[(int) $user['id']] = $user;
        }

        return array_values($users);
    }
}

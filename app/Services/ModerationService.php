<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\QuestionModel;
use App\Models\AuditLogModel;
use Core\Session;

final class ModerationService
{
    private const REPORTS_PAGE_SIZE = 10;
    private const AUDIT_PAGE_SIZE = 15;

    public function __construct(
        private QuestionModel $questions,
        private AuditLogModel $audit,
        private GamificationService $gamification,
        private Session $session,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function flags(): array
    {
        return $this->questions->openFlags();
    }

    /** @return array<string, mixed> */
    public function dashboard(int $reportsPage, int $auditPage): array
    {
        $reportsTotal = $this->questions->countOpenFlags();
        $reportsPage = $this->boundedPage($reportsPage, $reportsTotal, self::REPORTS_PAGE_SIZE);
        $reportsOffset = ($reportsPage - 1) * self::REPORTS_PAGE_SIZE;

        $auditTotal = $this->audit->count();
        $auditPage = $this->boundedPage($auditPage, $auditTotal, self::AUDIT_PAGE_SIZE);
        $auditOffset = ($auditPage - 1) * self::AUDIT_PAGE_SIZE;

        return [
            'flags' => $this->questions->openFlags(self::REPORTS_PAGE_SIZE, $reportsOffset),
            'auditLog' => $this->audit->latest(self::AUDIT_PAGE_SIZE, $auditOffset),
            'moderatorStats' => [
                'open_reports' => $reportsTotal,
                'audit_entries' => $auditTotal,
                'report_page_size' => self::REPORTS_PAGE_SIZE,
                'audit_page_size' => self::AUDIT_PAGE_SIZE,
            ],
            'reportsPagination' => $this->pagination(
                'reports_page',
                $reportsTotal,
                $reportsPage,
                self::REPORTS_PAGE_SIZE,
                ['audit_page' => $auditPage]
            ),
            'auditPagination' => $this->pagination(
                'audit_page',
                $auditTotal,
                $auditPage,
                self::AUDIT_PAGE_SIZE,
                ['reports_page' => $reportsPage]
            ),
        ];
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

    public function update(string $type, int $id, string $title, string $body): void
    {
        $minimumLength = $type === 'comment' ? 3 : 10;
        if (in_array($type, ['question', 'answer', 'comment'], true) && mb_strlen($body) >= $minimumLength) {
            $this->questions->updatePost($type, $id, $title, $body);
            $this->audit->record((int) $this->session->get('user_id'), 'edit', $type, $id);
        }
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

    public function verifyAnswer(int $answerId, string $note): void
    {
        $moderatorId = (int) $this->session->get('user_id');
        if ($this->questions->verifyAnswer($answerId, $moderatorId, $note)) {
            $this->audit->record($moderatorId, 'verify', 'answer', $answerId);
        }
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

            return '/moderator' . ($query === [] ? '' : '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
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
}

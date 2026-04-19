<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\QuestionModel;
use App\Models\AuditLogModel;
use Core\Session;

final class ModerationService
{
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
}

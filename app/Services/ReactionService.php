<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReactionModel;
use Core\Session;

final readonly class ReactionService
{
    public function __construct(
        private ReactionModel $reactions,
        private Session $session,
        private GamificationService $gamification,
    ) {}

    public function toggle(string $postType, int $postId, int $reactionTypeId): void
    {
        $userId = (int) $this->session->get('user_id');
        $result = $this->reactions->toggleForUser($userId, $postType, $postId, $reactionTypeId);
        if ($result === null || (int) ($result['reputation_delta'] ?? 0) === 0) {
            return;
        }

        $reason = (int) $result['reputation_delta'] > 0 ? 'reaction_' . (string) $result['slug'] : 'reaction_removed_' . (string) $result['slug'];
        $this->gamification->award((int) $result['target_user_id'], (int) $result['reputation_delta'], $reason, $postType, $postId);
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function forTargets(array $targets): array
    {
        return $this->reactions->forTargets($targets, (int) $this->session->get('user_id'));
    }
}

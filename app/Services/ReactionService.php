<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReactionModel;
use Core\Queue;
use Core\Session;

final readonly class ReactionService
{
    public function __construct(
        private ReactionModel $reactions,
        private Session $session,
        private GamificationService $gamification,
        private Queue $queue,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function toggle(string $postType, int $postId, int $reactionTypeId): array
    {
        $userId = (int) $this->session->get('user_id');
        if ($userId < 1) {
            return [];
        }

        $result = $this->reactions->toggleForUser($userId, $postType, $postId, $reactionTypeId);
        if ($result !== null && (int) ($result['reputation_delta'] ?? 0) !== 0) {
            $reason = (int) $result['reputation_delta'] > 0 ? 'reaction_' . (string) $result['slug'] : 'reaction_removed_' . (string) $result['slug'];
            $this->gamification->award((int) $result['target_user_id'], (int) $result['reputation_delta'], $reason, $postType, $postId);
        }
        if ($result !== null && !empty($result['added'])) {
            $url = '/question/' . (string) $result['question_slug'] . ($postType === 'answer' ? '#answer-' . $postId : '');
            $this->queue->push('MentionNotification', [
                'user_id' => (int) $result['target_user_id'],
                'type' => 'Reaction',
                'message' => 'Your ' . $postType . ' received a ' . (string) $result['slug'] . ' reaction on ' . (string) $result['post_title'],
                'url' => $url,
            ]);
        }

        return $this->forTargets([['type' => $postType, 'id' => $postId]])[$postType . ':' . $postId] ?? [];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function forTargets(array $targets): array
    {
        return $this->reactions->forTargets($targets, (int) $this->session->get('user_id'));
    }
}

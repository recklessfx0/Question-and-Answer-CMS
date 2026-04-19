<?php

declare(strict_types=1);

namespace App\Services;

final readonly class CommunityChoiceService
{
    /** @param array<int, array<string, mixed>> $answers @return array<int, array<string, mixed>> */
    public function annotateAnswers(array $answers): array
    {
        $bestIndex = null;
        $best = null;

        foreach ($answers as $index => $answer) {
            $votes = (int) ($answer['votes'] ?? 0);
            $reactionScore = (int) ($answer['reaction_score'] ?? 0);
            $reactionCount = (int) ($answer['reaction_count'] ?? 0);
            $score = $votes + $reactionScore;

            $answers[$index]['reaction_score'] = $reactionScore;
            $answers[$index]['reaction_count'] = $reactionCount;
            $answers[$index]['community_score'] = $score;
            $answers[$index]['is_community_chosen'] = false;

            if ($score <= 0) {
                continue;
            }

            $candidate = [
                'score' => $score,
                'votes' => $votes,
                'reaction_count' => $reactionCount,
                'created_at' => strtotime((string) ($answer['created_at'] ?? '')) ?: PHP_INT_MAX,
                'id' => (int) ($answer['id'] ?? 0),
            ];

            if ($best === null || $this->isBetterChoice($candidate, $best)) {
                $best = $candidate;
                $bestIndex = $index;
            }
        }

        if ($bestIndex !== null) {
            $answers[$bestIndex]['is_community_chosen'] = true;
        }

        return $answers;
    }

    /** @param array<string, int> $candidate @param array<string, int> $current */
    private function isBetterChoice(array $candidate, array $current): bool
    {
        foreach (['score', 'votes', 'reaction_count'] as $field) {
            if ($candidate[$field] !== $current[$field]) {
                return $candidate[$field] > $current[$field];
            }
        }

        if ($candidate['created_at'] !== $current['created_at']) {
            return $candidate['created_at'] < $current['created_at'];
        }

        return $candidate['id'] < $current['id'];
    }
}

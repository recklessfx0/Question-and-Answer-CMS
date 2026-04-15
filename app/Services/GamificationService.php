<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GamificationLevelModel;
use App\Models\UserModel;

final readonly class GamificationService
{
    /** @var array<int, string> */
    private const LEVEL_BADGES = [
        5 => 'Level 5',
        10 => 'Level 10',
        25 => 'Level 25',
        50 => 'Level 50',
        75 => 'Level 75',
        100 => 'Level 100',
    ];

    public function __construct(private UserModel $users, private GamificationLevelModel $levels) {}

    public function award(int $userId, int $points, string $reason, string $sourceType, int $sourceId): void
    {
        if ($userId < 1 || $points === 0) {
            return;
        }

        $this->users->transaction(function () use ($userId, $points, $reason, $sourceType, $sourceId): void {
            // INSERT IGNORE returns 0 rows affected when the unique key already exists,
            // preventing double-awards in concurrent requests without a separate SELECT.
            $inserted = $this->users->recordReputationEvent($userId, $points, $reason, $sourceType, $sourceId);
            if (!$inserted) {
                return;
            }
            $this->users->adjustReputation($userId, $points);
            $this->syncBadges($userId);
        });
    }

    /** @param array<int, int> $userIds */
    public function recalculateUsers(array $userIds): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        foreach ($userIds as $userId) {
            $this->users->transaction(function () use ($userId): void {
                $this->syncBadges($userId);
            });
        }
    }

    /** @return array{level: int, title: string, current: int, next: int, progress: int, icon_path: string} */
    public function rank(int $reputation): array
    {
        $reputation = max(0, $reputation);
        $custom = $this->customRank($reputation);
        if ($custom !== null) {
            return $custom;
        }

        $level = min(100, max(1, (int) floor(sqrt($reputation / 10)) + 1));
        $current = $this->thresholdForLevel($level);
        $next = $level >= 100 ? $current : $this->thresholdForLevel($level + 1);
        $range = max(1, $next - $current);
        $progress = $level >= 100 ? 100 : min(100, max(0, (int) floor((($reputation - $current) / $range) * 100)));

        return [
            'level' => $level,
            'title' => $this->title($level),
            'current' => $current,
            'next' => $next,
            'progress' => $progress,
            'icon_path' => '',
        ];
    }

    /** @return array{level: int, title: string, current: int, next: int, progress: int, icon_path: string}|null */
    private function customRank(int $reputation): ?array
    {
        $levels = $this->levels->active();
        if ($levels === []) {
            return null;
        }

        if ($reputation < (int) ($levels[0]['min_reputation'] ?? 0)) {
            return null;
        }

        $current = $levels[0];
        $next = null;
        foreach ($levels as $level) {
            if ((int) $level['min_reputation'] <= $reputation) {
                $current = $level;
                continue;
            }

            $next = $level;
            break;
        }

        $currentThreshold = (int) $current['min_reputation'];
        $nextThreshold = is_array($next) ? (int) $next['min_reputation'] : $currentThreshold;
        $range = max(1, $nextThreshold - $currentThreshold);

        return [
            'level' => (int) $current['level_number'],
            'title' => (string) $current['title'],
            'current' => $currentThreshold,
            'next' => $nextThreshold,
            'progress' => $next === null ? 100 : min(100, max(0, (int) floor((($reputation - $currentThreshold) / $range) * 100))),
            'icon_path' => (string) ($current['icon_path'] ?? ''),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function badges(int $userId): array
    {
        return $this->users->badges($userId);
    }

    private function thresholdForLevel(int $level): int
    {
        return (int) pow(max(1, $level - 1), 2) * 10;
    }

    private function title(int $level): string
    {
        return match (true) {
            $level >= 90 => 'Legend',
            $level >= 75 => 'Architect',
            $level >= 60 => 'Sage',
            $level >= 45 => 'Expert',
            $level >= 30 => 'Mentor',
            $level >= 15 => 'Contributor',
            default => 'Newcomer',
        };
    }

    private function syncBadges(int $userId): void
    {
        $user = $this->users->findById($userId);
        if ($user === null) {
            return;
        }

        $level = $this->rank((int) $user['reputation'])['level'];
        $definitions = [];
        foreach (self::LEVEL_BADGES as $required => $name) {
            $definitions[] = [
                'name' => $name,
                'description' => 'Reached level ' . $required . '.',
                'earned' => $level >= $required,
            ];
        }

        $this->users->syncLevelBadges($userId, $definitions);
    }
}

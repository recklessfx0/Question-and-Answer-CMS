<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\QuestionModel;
use App\Models\AuditLogModel;
use App\Models\CategoryModel;
use App\Models\GamificationLevelModel;
use App\Models\ReactionModel;
use App\Models\SettingsModel;
use App\Models\UserModel;
use Core\Slugger;
use RuntimeException;

final readonly class AdminService
{
    public function __construct(
        private UserModel $users,
        private QuestionModel $questions,
        private CategoryModel $categories,
        private GamificationLevelModel $levels,
        private GamificationService $gamification,
        private ReactionModel $reactions,
        private SettingsModel $settings,
        private AuditLogModel $audit,
        private UploadService $uploads,
        private Slugger $slugger,
    ) {}

    /** @return array{users: array<int, array<string, mixed>>, stats: array<string, int>, settings: array<string, string>, categories: array<int, array<string, mixed>>, tags: array<int, array<string, mixed>>, gamificationLevels: array<int, array<string, mixed>>, reactions: array<int, array<string, mixed>>, reports: array<int, array<string, mixed>>} */
    public function dashboard(): array
    {
        return [
            'users' => $this->users->all(),
            'stats' => $this->questions->stats(),
            'settings' => $this->settings->all(),
            'categories' => $this->categories->all(),
            'tags' => $this->questions->popularTags(200),
            'gamificationLevels' => $this->levels->all(),
            'reactions' => $this->reactions->allTypes(),
            'reports' => $this->questions->openFlags(),
        ];
    }

    public function setRole(int $userId, string $role): bool
    {
        return $this->users->setRole($userId, $role);
    }

    /** @param array<string, string> $input */
    public function saveSettings(array $input): void
    {
        $this->settings->set('site_title', excerpt($input['site_title'] ?? '', 80));
        $this->settings->set('site_description', excerpt($input['site_description'] ?? '', 160));
        $this->settings->set('recaptcha_enabled', ($input['recaptcha_enabled'] ?? '0') === '1' ? '1' : '0');
        $this->settings->set('recaptcha_site_key', excerpt($input['recaptcha_site_key'] ?? '', 255));
        if (trim($input['recaptcha_secret_key'] ?? '') !== '') {
            $this->settings->set('recaptcha_secret_key', excerpt($input['recaptcha_secret_key'] ?? '', 255));
        }

        $this->settings->set('google_auth_enabled', ($input['google_auth_enabled'] ?? '0') === '1' ? '1' : '0');
        $this->settings->set('google_client_id', excerpt($input['google_client_id'] ?? '', 255));
        if (trim($input['google_client_secret'] ?? '') !== '') {
            $this->settings->set('google_client_secret', excerpt($input['google_client_secret'] ?? '', 255));
        }
    }

    public function createCategory(int $parentId, string $name, string $description, string $seoTitle = '', string $seoDescription = '', string $intro = ''): bool
    {
        $name = trim($name);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            return false;
        }

        $parent = $parentId > 0 ? $parentId : null;
        $slug = $this->categories->uniqueSlug($this->slugger->slug($name));
        return $this->categories->create($parent, $name, $slug, excerpt($description, 240), excerpt($seoTitle, 120), excerpt($seoDescription, 180), excerpt($intro, 4000));
    }

    public function updateCategory(int $id, int $parentId, string $name, string $description, string $seoTitle = '', string $seoDescription = '', string $intro = ''): bool
    {
        $name = trim($name);
        if ($id < 1 || mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            return false;
        }

        $parent = $parentId > 0 ? $parentId : null;
        $slug = $this->categories->uniqueSlug($this->slugger->slug($name), $id);
        return $this->categories->update($id, $parent, $name, $slug, excerpt($description, 240), excerpt($seoTitle, 120), excerpt($seoDescription, 180), excerpt($intro, 4000));
    }

    public function updateTag(int $id, string $name, string $description, string $seoTitle, string $seoDescription, string $intro): void
    {
        $name = trim($name);
        if ($id < 1 || mb_strlen($name) < 1 || mb_strlen($name) > 80) {
            return;
        }

        $this->questions->updateTagSeo($id, $name, excerpt($description, 240), excerpt($seoTitle, 120), excerpt($seoDescription, 180), excerpt($intro, 4000));
    }

    public function deleteCategory(int $id): bool
    {
        if ($id > 0) {
            return $this->categories->delete($id);
        }

        return false;
    }

    /** @param array<string, mixed>|null $icon */
    public function createLevel(int $level, string $title, int $minReputation, string $description, bool $active, ?array $icon): bool
    {
        $title = trim($title);
        if ($level < 1 || $level > 100 || mb_strlen($title) < 2 || mb_strlen($title) > 80 || $minReputation < 0) {
            return false;
        }

        return $this->levels->create($level, $title, $minReputation, excerpt($description, 240), $this->storeIcon($icon), $active);
    }

    /** @param array<string, mixed>|null $icon */
    public function updateLevel(int $id, int $level, string $title, int $minReputation, string $description, bool $active, ?array $icon): bool
    {
        $title = trim($title);
        if ($id < 1 || $level < 1 || $level > 100 || mb_strlen($title) < 2 || mb_strlen($title) > 80 || $minReputation < 0) {
            return false;
        }

        return $this->levels->update($id, $level, $title, $minReputation, excerpt($description, 240), $this->storeIcon($icon), $active);
    }

    public function deleteLevel(int $id): bool
    {
        if ($id > 0) {
            return $this->levels->delete($id);
        }

        return false;
    }

    /** @param array<string, mixed>|null $icon */
    public function createReaction(string $name, int $reputationDelta, int $sortOrder, bool $active, ?array $icon): bool
    {
        $name = trim($name);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 60 || $reputationDelta < -10 || $reputationDelta > 25) {
            return false;
        }

        return $this->reactions->createType($name, $this->slugger->slug($name), $this->storeReactionIcon($icon), $reputationDelta, $sortOrder, $active);
    }

    /** @param array<string, mixed>|null $icon */
    public function updateReaction(int $id, string $name, int $reputationDelta, int $sortOrder, bool $active, ?array $icon): bool
    {
        $name = trim($name);
        if ($id < 1 || mb_strlen($name) < 2 || mb_strlen($name) > 60 || $reputationDelta < -10 || $reputationDelta > 25) {
            return false;
        }

        return $this->reactions->updateType($id, $name, $this->slugger->slug($name), $this->storeReactionIcon($icon), $reputationDelta, $sortOrder, $active);
    }

    public function deleteReaction(int $id): bool
    {
        if ($id > 0) {
            return $this->reactions->deleteType($id);
        }

        return false;
    }

    public function resolveReport(int $reportId, string $status, int $adminId): void
    {
        $this->questions->resolveFlag($reportId, $status);
        $this->audit->record($adminId, 'report_' . $status, 'flag', $reportId);
    }

    public function deleteReportedContent(string $type, int $id, int $adminId): void
    {
        if (!in_array($type, ['question', 'answer', 'comment'], true)) {
            return;
        }

        $affectedUserIds = $this->questions->softDelete($type, $id);
        $this->gamification->recalculateUsers($affectedUserIds);
        $this->audit->record($adminId, 'delete', $type, $id);
    }

    public function closeQuestion(int $questionId, int $adminId): void
    {
        $this->questions->closeQuestion($questionId);
        $this->audit->record($adminId, 'close', 'question', $questionId);
    }

    /** @param array<string, mixed>|null $icon */
    private function storeIcon(?array $icon): ?string
    {
        if ($icon === null || (int) ($icon['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        try {
            return $this->uploads->storeImage($icon, 'public-assets/images/levels');
        } catch (RuntimeException) {
            return null;
        }
    }

    /** @param array<string, mixed>|null $icon */
    private function storeReactionIcon(?array $icon): ?string
    {
        if ($icon === null || (int) ($icon['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        try {
            return $this->uploads->storeImage($icon, 'public-assets/images/reactions');
        } catch (RuntimeException) {
            return null;
        }
    }
}

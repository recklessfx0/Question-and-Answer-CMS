<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\QuestionModel;
use App\Models\AuditLogModel;
use App\Models\CategoryModel;
use App\Models\GamificationLevelModel;
use App\Models\LanguageModel;
use App\Models\ReactionModel;
use App\Models\SettingsModel;
use App\Models\UserModel;
use Core\Slugger;
use RuntimeException;

final class AdminService
{
    private const ADMIN_PAGE_SIZE = 12;

    public function __construct(
        private UserModel $users,
        private QuestionModel $questions,
        private CategoryModel $categories,
        private GamificationLevelModel $levels,
        private LanguageModel $languages,
        private GamificationService $gamification,
        private ReactionModel $reactions,
        private SettingsModel $settings,
        private AuditLogModel $audit,
        private UploadService $uploads,
        private Slugger $slugger,
    ) {}

    /** @return array{users: array<int, array<string, mixed>>, stats: array<string, int>, settings: array<string, string>, categories: array<int, array<string, mixed>>, tags: array<int, array<string, mixed>>, gamificationLevels: array<int, array<string, mixed>>, languages: array<int, array<string, mixed>>, reactions: array<int, array<string, mixed>>, reports: array<int, array<string, mixed>>} */
    public function dashboard(): array
    {
        return [
            'users' => $this->users->all(),
            'stats' => $this->questions->stats(),
            'settings' => $this->settings->all(),
            'categories' => $this->categories->all(),
            'tags' => $this->questions->popularTags(200),
            'gamificationLevels' => $this->levels->all(),
            'languages' => $this->languages->all(),
            'reactions' => $this->reactions->allTypes(),
            'reports' => $this->questions->openFlags(),
        ];
    }

    /** @return array{stats: array<string, int>, settings: array<string, string>} */
    public function shell(): array
    {
        return [
            'stats' => $this->questions->stats(),
            'settings' => $this->settings->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function managementPage(string $section, int $page, string $query = ''): array
    {
        $page = max(1, $page);
        $perPage = self::ADMIN_PAGE_SIZE;
        $offset = ($page - 1) * $perPage;

        return match ($section) {
            'categories' => $this->categoryManagementPage($page, $perPage),
            'levels' => $this->paginatedArray('gamificationLevels', '/admin/levels', $this->levels->all(), $page, $perPage),
            'reactions' => $this->paginatedArray('reactions', '/admin/reactions', $this->reactions->allTypes(), $page, $perPage),
            'users' => [
                'users' => $this->users->all($perPage, $offset, $query),
                'userSearch' => $query,
                'adminPagination' => $this->pagination($query !== '' ? '/admin/users?q=' . rawurlencode($query) : '/admin/users', $this->users->count($query, true), $page, $perPage, $query !== ''),
            ],
            'tags' => [
                'tags' => $this->questions->popularTags($perPage, $offset),
                'adminPagination' => $this->pagination('/admin/tags', $this->questions->countTags(), $page, $perPage),
            ],
            'reports' => [
                'reports' => $this->questions->openFlags($perPage, $offset),
                'adminPagination' => $this->pagination('/admin/reports', $this->questions->countOpenFlags(), $page, $perPage),
            ],
            default => [],
        };
    }

    /** @return array{languages: array<int, array<string, mixed>>, defaults: array<string, string>, selectedLanguage: array<string, mixed>|null, translations: array<string, string>} */
    public function languageEditor(string $code = ''): array
    {
        $defaults = require BASE_PATH . '/config/translations.php';
        $defaults = is_array($defaults) ? array_map('strval', $defaults) : [];
        $languages = $this->languages->all();
        $selectedLanguage = $code !== '' ? $this->languages->findByCode($code) : ($languages[0] ?? null);
        $translations = is_array($selectedLanguage) ? $this->languages->translationsForLanguage((int) $selectedLanguage['id']) : [];
        foreach ($this->languages->knownTranslationKeys() as $key) {
            $defaults[$key] ??= '';
        }
        foreach ($translations as $key => $value) {
            $defaults[$key] ??= '';
        }
        ksort($defaults);

        return [
            'languages' => $languages,
            'defaults' => $defaults,
            'selectedLanguage' => $selectedLanguage,
            'translations' => $translations,
        ];
    }

    public function createLanguage(string $code, string $name, bool $active, bool $default, int $actorId): bool
    {
        $created = $this->languages->create($code, $name, $active, $default);
        if ($created) {
            $this->audit->record($actorId, 'language_create', 'language', 0, ['code' => strtolower(str_replace('_', '-', trim($code)))]);
        }

        return $created;
    }

    public function updateLanguage(int $id, string $name, bool $active, bool $default, int $actorId): bool
    {
        $updated = $this->languages->update($id, $name, $active, $default);
        if ($updated) {
            $this->audit->record($actorId, 'language_update', 'language', $id);
        }

        return $updated;
    }

    /** @param array<string, string> $translations */
    public function saveTranslations(int $languageId, array $translations, int $actorId): void
    {
        $this->languages->saveTranslations($languageId, $translations);
        $this->audit->record($actorId, 'translations_update', 'language', $languageId, ['keys' => array_keys($translations)]);
    }

    /** @param array<string, mixed> $file @return array{ok: bool, message: string, code: string} */
    public function importTranslations(int $languageId, array $file, int $actorId): array
    {
        $language = $this->languageById($languageId);
        if ($language === null) {
            return ['ok' => false, 'message' => 'Language was not found.', 'code' => ''];
        }

        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'Choose a JSON translation file to upload.', 'code' => (string) $language['code']];
        }

        if ((int) ($file['size'] ?? 0) > 1024 * 1024) {
            return ['ok' => false, 'message' => 'Translation file must be 1 MB or smaller.', 'code' => (string) $language['code']];
        }

        $name = (string) ($file['name'] ?? '');
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'json') {
            return ['ok' => false, 'message' => 'Upload a .json translation file.', 'code' => (string) $language['code']];
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $raw = is_uploaded_file($tmpName) || is_file($tmpName) ? file_get_contents($tmpName) : false;
        if (!is_string($raw) || $raw === '') {
            return ['ok' => false, 'message' => 'Translation file could not be read.', 'code' => (string) $language['code']];
        }

        try {
            $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['ok' => false, 'message' => 'Translation file contains invalid JSON.', 'code' => (string) $language['code']];
        }

        $translations = $this->translationsFromImportPayload(is_array($decoded) ? $decoded : []);
        if ($translations === []) {
            return ['ok' => false, 'message' => 'Translation file does not contain valid translation keys.', 'code' => (string) $language['code']];
        }

        $this->languages->saveTranslations($languageId, $translations);
        $this->audit->record($actorId, 'translations_import', 'language', $languageId, ['keys' => array_keys($translations)]);

        return ['ok' => true, 'message' => count($translations) . ' translations imported.', 'code' => (string) $language['code']];
    }

    public function exportTranslations(int $languageId): ?string
    {
        $language = $this->languageById($languageId);
        if ($language === null) {
            return null;
        }

        $payload = [
            'code' => (string) $language['code'],
            'name' => (string) $language['name'],
            'translations' => $this->languages->translationsForLanguage($languageId),
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed>|null */
    private function languageById(int $languageId): ?array
    {
        foreach ($this->languages->all() as $language) {
            if ((int) ($language['id'] ?? 0) === $languageId) {
                return $language;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $payload @return array<string, string> */
    private function translationsFromImportPayload(array $payload): array
    {
        $source = is_array($payload['translations'] ?? null) ? $payload['translations'] : $payload;
        $clean = [];
        $reserved = ['code' => true, 'name' => true, 'locale' => true, 'language' => true];
        foreach ($source as $key => $value) {
            if (!is_string($key) || isset($reserved[$key]) || !is_scalar($value) || !preg_match('/^[a-z0-9_.-]{2,120}$/', $key)) {
                continue;
            }
            $text = trim(str_replace("\0", '', (string) $value));
            $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
            $clean[$key] = mb_substr($text, 0, 4000);
        }

        return $clean;
    }

    public function setRole(int $userId, string $role, int $actorId): bool
    {
        if ($userId < 1) {
            return false;
        }

        $target = $this->users->findById($userId);
        if ($target === null || !empty($target['deleted_at'])) {
            return false;
        }

        $oldRole = (string) ($target['role'] ?? '');
        $updated = $this->users->setRole($userId, $role);
        if ($updated && $oldRole !== $role) {
            $this->audit->record($actorId, 'user_role_update', 'user', $userId, [
                'old_role' => $oldRole,
                'new_role' => $role,
            ]);
        }

        return $updated;
    }

    public function setUserBan(int $userId, bool $banned, int $actorId): bool
    {
        if ($userId < 1 || $userId === $actorId) {
            return false;
        }

        $target = $this->users->findById($userId);
        if ($target === null || !empty($target['deleted_at'])) {
            return false;
        }

        if ((string) ($target['role'] ?? '') === 'administrator' && !$this->users->hasOtherAdministrator($userId)) {
            return false;
        }

        $updated = $this->users->setBanned($userId, $banned);
        if ($updated) {
            $this->audit->record($actorId, $banned ? 'user_ban' : 'user_unban', 'user', $userId);
        }

        return $updated;
    }

    public function deleteUser(int $userId, bool $keepContent, int $actorId): bool
    {
        if ($userId < 1 || $userId === $actorId) {
            return false;
        }

        $target = $this->users->findById($userId);
        if ($target === null || !empty($target['deleted_at'])) {
            return false;
        }

        if ((string) ($target['role'] ?? '') === 'administrator' && !$this->users->hasOtherAdministrator($userId)) {
            return false;
        }

        $deleted = $this->users->adminDelete($userId, $keepContent);
        if ($deleted !== null) {
            $this->audit->record($actorId, 'user_delete', 'user', $userId, [
                'old_username' => (string) ($deleted['username'] ?? ''),
                'new_username' => (string) ($deleted['deleted_username'] ?? ''),
                'kept_content' => $keepContent,
            ]);
        }

        return $deleted !== null;
    }

    /** @param array<string, string> $input @param array<string, array<string, mixed>|null> $uploads */
    public function saveSettings(array $input, int $actorId, array $uploads = []): void
    {
        $before = $this->settings->all();
        $this->settings->set('site_title', excerpt($input['site_title'] ?? '', 80));
        $this->settings->set('site_url', normalize_site_url($input['site_url'] ?? ''));
        $this->settings->set('site_description', excerpt($input['site_description'] ?? '', 200));
        $this->settings->set('default_meta_title', excerpt($input['default_meta_title'] ?? '', 120));
        $this->settings->set('default_meta_description', excerpt($input['default_meta_description'] ?? '', 200));
        $this->settings->set('landing_questions_heading', excerpt($input['landing_questions_heading'] ?? '', 120));
        $this->settings->set('default_og_title', excerpt($input['default_og_title'] ?? '', 120));
        $this->settings->set('default_og_description', excerpt($input['default_og_description'] ?? '', 200));
        $this->settings->set('twitter_site', $this->socialHandle($input['twitter_site'] ?? ''));
        $this->settings->set('twitter_creator', $this->socialHandle($input['twitter_creator'] ?? ''));
        $logo = $uploads['site_logo'] ?? null;
        if (is_array($logo) && (int) ($logo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $this->settings->set('site_logo_path', $this->uploads->storeLogo($logo));
        }
        $favicon = $uploads['site_favicon'] ?? null;
        if (is_array($favicon) && (int) ($favicon['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $this->settings->set('site_favicon_path', $this->uploads->storeLogo($favicon, 'public-assets/images/favicons'));
        }
        $ogImage = $uploads['default_og_image'] ?? null;
        if (is_array($ogImage) && (int) ($ogImage['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $this->settings->set('default_og_image_path', $this->uploads->storeSocialImage($ogImage));
        }
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

        $this->settings->set('smtp_enabled', ($input['smtp_enabled'] ?? '0') === '1' ? '1' : '0');
        $this->settings->set('smtp_host', excerpt($input['smtp_host'] ?? '', 255));
        $this->settings->set('smtp_port', (string) max(1, min(65535, (int) ($input['smtp_port'] ?? 587))));
        $encryption = strtolower(trim($input['smtp_encryption'] ?? 'tls'));
        $this->settings->set('smtp_encryption', in_array($encryption, ['tls', 'ssl', 'none'], true) ? $encryption : 'tls');
        $this->settings->set('smtp_username', excerpt($input['smtp_username'] ?? '', 255));
        if (trim($input['smtp_password'] ?? '') !== '') {
            $this->settings->set('smtp_password', excerpt($input['smtp_password'] ?? '', 255));
        }
        $fromEmail = filter_var(trim($input['smtp_from_email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $this->settings->set('smtp_from_email', is_string($fromEmail) ? $fromEmail : '');
        $this->settings->set('smtp_from_name', excerpt($input['smtp_from_name'] ?? '', 120));

        $after = $this->settings->all();
        $changed = [];
        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                $changed[] = $key;
            }
        }
        if ($changed !== []) {
            $this->audit->record($actorId, 'settings_update', 'settings', 0, ['changed_keys' => $this->redactedSettingKeys($changed)]);
        }
    }

    /** @param array<string, string> $input */
    public function saveTrustPages(array $input, int $actorId): void
    {
        $before = $this->settings->all();
        foreach ([
            'about_description' => 240,
            'contact_description' => 240,
            'privacy_description' => 240,
            'terms_description' => 240,
            'guidelines_description' => 240,
        ] as $key => $length) {
            $this->settings->set($key, excerpt($input[$key] ?? '', $length));
        }
        foreach (['about_body', 'contact_body', 'privacy_body', 'terms_body', 'guidelines_body'] as $key) {
            $this->settings->set($key, $this->plainTextSetting($input[$key] ?? '', 4000));
        }

        $after = $this->settings->all();
        $changed = [];
        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                $changed[] = $key;
            }
        }
        if ($changed !== []) {
            $this->audit->record($actorId, 'trust_pages_update', 'settings', 0, ['changed_keys' => $this->redactedSettingKeys($changed)]);
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

    public function updateTag(int $id, string $name, string $description, string $seoTitle, string $seoDescription, string $intro): bool
    {
        $name = trim($name);
        if ($id < 1 || mb_strlen($name) < 1 || mb_strlen($name) > 80) {
            return false;
        }

        return $this->questions->updateTagSeo($id, $name, excerpt($description, 240), excerpt($seoTitle, 120), excerpt($seoDescription, 180), excerpt($intro, 4000));
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

    public function resolveReport(int $reportId, string $status, int $adminId): bool
    {
        if (!$this->questions->resolveFlag($reportId, $status)) {
            return false;
        }

        $this->audit->record($adminId, 'report_' . $status, 'flag', $reportId);
        return true;
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

    private function plainTextSetting(string $value, int $length): string
    {
        $value = trim(str_replace("\0", '', $value));
        $value = (string) preg_replace('/[^\P{C}\r\n\t]+/u', '', $value);

        return mb_strlen($value) <= $length ? $value : rtrim(mb_substr($value, 0, $length - 1)) . '...';
    }

    private function socialHandle(string $value): string
    {
        $value = trim(str_replace(["\0", "\r", "\n"], '', $value));
        if ($value === '') {
            return '';
        }
        $value = ltrim($value, '@');
        if (!preg_match('/^[A-Za-z0-9_]{1,30}$/', $value)) {
            return '';
        }

        return '@' . $value;
    }

    /** @param array<int, string> $keys @return array<int, string> */
    private function redactedSettingKeys(array $keys): array
    {
        sort($keys);
        return array_values(array_map(
            static fn (string $key): string => str_contains($key, 'secret') || str_contains($key, 'password') ? $key . ':redacted' : $key,
            array_unique($keys)
        ));
    }

    /** @param array<int, array<string, mixed>> $items @param array<string, mixed> $extra @return array<string, mixed> */
    private function paginatedArray(string $key, string $base, array $items, int $page, int $perPage, array $extra = []): array
    {
        $total = count($items);
        $page = min($page, max(1, (int) ceil(max(1, $total) / $perPage)));
        $offset = ($page - 1) * $perPage;

        return array_replace($extra, [
            $key => array_slice($items, $offset, $perPage),
            'adminPagination' => $this->pagination($base, $total, $page, $perPage),
        ]);
    }

    /** @return array<string, mixed> */
    private function categoryManagementPage(int $page, int $perPage): array
    {
        $categories = $this->categories->all();

        return $this->paginatedArray('categories', '/admin/categories', $categories, $page, $perPage, [
            'allCategories' => $categories,
        ]);
    }

    /** @return array{current: int, total_pages: int, total_items: int, base: string, query_mode: bool, window: array<int, int>, previous: string|null, next: string|null} */
    private function pagination(string $base, int $total, int $page, int $perPage, bool $queryMode = false): array
    {
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $totalPages);
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        if ($end - $start < 4) {
            $start = max(1, min($start, $end - 4));
            $end = min($totalPages, max($end, $start + 4));
        }

        $url = static function (int $target) use ($base, $queryMode): string {
            if ($queryMode) {
                return $base . ($target > 1 ? '&page=' . $target : '');
            }

            return $base . ($target > 1 ? '?page=' . $target : '');
        };

        return [
            'current' => $page,
            'total_pages' => $totalPages,
            'total_items' => $total,
            'base' => $queryMode ? $base . '&' : $base . '?',
            'query_mode' => true,
            'window' => range($start, $end),
            'previous' => $page > 1 ? $url($page - 1) : null,
            'next' => $page < $totalPages ? $url($page + 1) : null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Models\QuestionModel;
use App\Models\UserModel;
use App\Services\GamificationService;
use App\Services\SeoService;
use App\Services\TwoFactorService;
use App\Services\UploadService;
use Core\Controller;
use Core\Csrf;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Slugger;
use Core\View;
use RuntimeException;

final class UserController extends Controller
{
    private const PROFILE_STREAM_LIMIT = 10;

    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        private readonly UserModel $users,
        private readonly QuestionModel $questions,
        private readonly Slugger $slugger,
        private readonly UploadService $uploads,
        private readonly GamificationService $gamification,
        private readonly SeoService $seo,
        private readonly TwoFactorService $twoFactor,
        private readonly AuditLogModel $audit,
    )
    {
        parent::__construct($view, $session, $csrf);
    }

    /** @param array<string, string> $params */
    public function profile(Request $request, array $params): Response
    {
        $user = $this->users->findByUsername($params['username'] ?? '');
        if ($user === null) {
            return $this->view('errors/http', ['status' => 404, 'message' => 'User not found'], 404);
        }

        $stats = $this->users->activityStats((int) $user['id']);
        $rank = $this->gamification->rank((int) $user['reputation']);
        $badges = $this->gamification->badges((int) $user['id']);
        $questions = $this->users->recentQuestions((int) $user['id'], self::PROFILE_STREAM_LIMIT);
        $answers = $this->users->recentAnswers((int) $user['id'], self::PROFILE_STREAM_LIMIT);
        $activity = $this->users->recentActivity((int) $user['id'], self::PROFILE_STREAM_LIMIT);
        $participantQuestionIds = array_values(array_unique(array_filter(array_merge(
            array_map(static fn (array $question): int => (int) ($question['id'] ?? 0), $questions),
            array_map(static fn (array $answer): int => (int) ($answer['question_id'] ?? 0), $answers),
            array_map(static fn (array $item): int => (int) ($item['question_id'] ?? 0), $activity),
        ), static fn (int $id): bool => $id > 0)));
        $indexableProfile =
            (int) ($stats['questions'] ?? 0) > 0
            || (int) ($stats['answers'] ?? 0) > 0
            || (int) ($stats['comments'] ?? 0) > 0
            || trim((string) ($user['bio'] ?? '')) !== ''
            || trim((string) ($user['expertise'] ?? '')) !== ''
            || trim((string) ($user['credentials'] ?? '')) !== ''
            || trim((string) ($user['website_url'] ?? '')) !== ''
            || !empty($user['avatar_path']);

        return $this->view('users/profile', [
            'title' => (string) $user['username'],
            'metaDescription' => 'Profile and activity for ' . (string) $user['username'] . '.',
            'canonical' => url('/user/' . rawurlencode((string) $user['username'])),
            'ogType' => 'profile',
            'ogImage' => !empty($user['avatar_path']) ? url((string) $user['avatar_path']) : '',
            'robots' => $indexableProfile ? null : 'noindex,follow',
            'schema' => $this->seo->profileSchema($user, $stats, $rank, $badges),
            'profile' => $user,
            'questions' => $questions,
            'answers' => $answers,
            'activity' => $activity,
            'participantMap' => $this->users->participantsForQuestions($participantQuestionIds),
            'activityStats' => $stats,
            'rank' => $rank,
            'badges' => $badges,
        ]);
    }

    public function editProfile(): Response
    {
        $user = $this->users->findById((int) $this->session->get('user_id'));
        if ($user === null) {
            return $this->redirect('/login');
        }

        return $this->view('users/edit', [
            'title' => 'Profile Settings',
            'metaDescription' => 'Update your public profile.',
            'robots' => 'noindex,follow',
            'profile' => $user,
            'twoFactorProvisioningUri' => !empty($user['two_factor_secret'])
                ? $this->twoFactor->provisioningUri((string) ((require BASE_PATH . '/config/app.php')['name'] ?? 'Question And Answer Platform'), (string) $user['email'], (string) $user['two_factor_secret'])
                : '',
        ]);
    }

    public function updateProfile(Request $request): Response
    {
        $userId = (int) $this->session->get('user_id');
        $username = $this->slugger->slug($request->string('username', 40));
        if (mb_strlen($username) < 3) {
            $this->flash('error', 'Username must be at least 3 characters.');
            return $this->redirect('/settings/profile');
        }
        $bio = $request->string('bio', 2000);
        $websiteUrl = safe_external_url($request->string('website_url', 255));
        $expertise = $request->string('expertise', 240);
        $credentials = $request->string('credentials', 240);
        $existing = $this->users->findByUsername($username);
        if ($existing !== null && (int) $existing['id'] !== $userId) {
            $this->flash('error', 'That username is already taken.');
            return $this->redirect('/settings/profile');
        }

        try {
            $this->users->updateProfile($userId, $username, $bio, $websiteUrl, $expertise, $credentials);
        } catch (\Throwable $e) {
            if (is_duplicate_key_exception($e)) {
                $this->flash('error', 'That username is already taken.');
                return $this->redirect('/settings/profile');
            }

            throw $e;
        }
        $avatar = is_array($request->files['avatar'] ?? null) ? $request->files['avatar'] : null;
        if ($avatar !== null && (int) ($avatar['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $this->users->updateAvatar($userId, $this->uploads->storeImage($avatar, 'public-assets/images/avatars'));
            } catch (RuntimeException $e) {
                $this->flash('error', $e->getMessage());
                return $this->redirect('/settings/profile');
            }
        }
        $fresh = $this->users->findSessionStateById($userId);
        if ($fresh !== null) {
            if (array_key_exists('auth_fingerprint', $fresh)) {
                $this->session->set('_auth_fingerprint', (string) $fresh['auth_fingerprint']);
                unset($fresh['auth_fingerprint']);
            }
            $this->session->set('user', $fresh);
        }

        $this->flash('success', 'Profile updated.');
        return $this->redirect('/user/' . rawurlencode($username));
    }

    public function startTwoFactor(): Response
    {
        $user = $this->users->findById((int) $this->session->get('user_id'));
        if ($user === null || active_role($user) !== 'administrator') {
            return $this->redirect('/settings/profile');
        }

        $this->users->saveTwoFactorSecret((int) $user['id'], $this->twoFactor->generateSecret());
        $this->flash('success', 'Two-factor setup key generated. Add it to your authenticator app and confirm the code.');

        return $this->redirect('/settings/profile');
    }

    public function enableTwoFactor(Request $request): Response
    {
        $user = $this->users->findById((int) $this->session->get('user_id'));
        if ($user === null || active_role($user) !== 'administrator' || empty($user['two_factor_secret'])) {
            return $this->redirect('/settings/profile');
        }

        if (!$this->twoFactor->verify((string) $user['two_factor_secret'], $request->string('code', 16))) {
            $this->flash('error', 'Two-factor code is invalid.');
            return $this->redirect('/settings/profile');
        }

        $this->users->enableTwoFactor((int) $user['id']);
        $this->audit->record((int) $user['id'], 'two_factor_enable', 'user', (int) $user['id']);
        $this->flash('success', 'Two-factor authentication enabled.');

        return $this->redirect('/settings/profile');
    }

    public function disableTwoFactor(Request $request): Response
    {
        $user = $this->users->findById((int) $this->session->get('user_id'));
        if ($user === null || active_role($user) !== 'administrator') {
            return $this->redirect('/settings/profile');
        }

        if (!empty($user['two_factor_secret']) && !$this->twoFactor->verify((string) $user['two_factor_secret'], $request->string('code', 16))) {
            $this->flash('error', 'Two-factor code is invalid.');
            return $this->redirect('/settings/profile');
        }

        $this->users->disableTwoFactor((int) $user['id']);
        $this->audit->record((int) $user['id'], 'two_factor_disable', 'user', (int) $user['id']);
        $this->flash('success', 'Two-factor authentication disabled.');

        return $this->redirect('/settings/profile');
    }
}

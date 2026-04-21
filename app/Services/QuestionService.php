<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\QuestionModel;
use App\Models\SettingsModel;
use App\Models\UserModel;
use Core\Markdown;
use Core\Logger;
use Core\Queue;
use Core\Session;
use Core\Slugger;
use Core\Validator;
use RuntimeException;

final class QuestionService
{
    public function __construct(
        private QuestionModel $questions,
        private Session $session,
        private Slugger $slugger,
        private Markdown $markdown,
        private Queue $queue,
        private Logger $logger,
        private UploadService $uploads,
        private GamificationService $gamification,
        private UserModel $users,
        private SettingsModel $settings,
    ) {}

    /** @return array{ok: bool, slug: string, errors: array<string, string>, pending?: bool} */
    public function ask(string $title, string $body, string $tags, ?int $categoryId = null, ?array $media = null, string $videoUrls = ''): array
    {
        $body = $this->appendMedia($body, $media);
        $body = $this->appendExternalVideos($body, $videoUrls);
        $validator = (new Validator())
            ->required('title', $title)
            ->length('title', $title, 12, 180)
            ->required('body', $body)
            ->length('body', $body, 30, 20000);

        if (!$validator->passes()) {
            return ['ok' => false, 'slug' => '', 'errors' => $validator->errors()];
        }

        $meta = excerpt($body, 155);
        $userId = (int) $this->session->get('user_id');
        $requiresApproval = setting_enabled($this->settings->all(), 'question_approval_enabled');
        $approvalStatus = $requiresApproval ? 'pending' : 'approved';
        [$id, $slug] = $this->createQuestionWithUniqueSlug($userId, $categoryId, $title, $body, $meta, $approvalStatus);
        $this->questions->attachTags($id, preg_split('/[,#]+/', $tags) ?: []);
        $this->queueNotification('NotifyModerators', ['question_id' => $id]);
        if (!$requiresApproval) {
            $this->notifyMentions($body, $userId, 'Mention', 'You were mentioned in ' . $title, '/question/' . $slug);
            $this->gamification->award($userId, 3, 'question_created', 'question', $id);
        }

        return ['ok' => true, 'slug' => $slug, 'errors' => [], 'pending' => $requiresApproval];
    }

    public function answer(int $questionId, string $body, ?array $media = null, string $videoUrls = ''): bool
    {
        if ((int) $this->session->get('user_id') < 1) {
            return false;
        }

        if (!$this->questions->isQuestionOpen($questionId)) {
            return false;
        }

        $body = $this->appendMedia($body, $media);
        $body = $this->appendExternalVideos($body, $videoUrls);
        $validator = (new Validator())->required('body', $body)->length('body', $body, 10, 20000);
        if (!$validator->passes()) {
            return false;
        }

        $userId = (int) $this->session->get('user_id');
        $id = $this->questions->addAnswer($questionId, $userId, $body);
        $this->queueNotification('AnswerPostedNotification', ['question_id' => $questionId, 'answer_id' => $id]);
        $question = $this->questions->findSummaryById($questionId);
        if ($question !== null) {
            $this->notifyMentions($body, $userId, 'Mention', 'You were mentioned in ' . (string) $question['title'], '/question/' . (string) $question['slug'] . '#answer-' . $id);
        }
        $this->gamification->award($userId, 5, 'answer_created', 'answer', $id);

        return true;
    }

    public function comment(string $type, int $postId, string $body, ?int $parentId = null, ?array $media = null, string $videoUrls = ''): bool
    {
        if ((int) $this->session->get('user_id') < 1) {
            return false;
        }

        if (!in_array($type, ['question', 'answer'], true)) {
            return false;
        }

        if ($type === 'question' && !$this->questions->isQuestionOpen($postId)) {
            return false;
        }

        if ($type === 'answer') {
            $answer = $this->questions->findAnswer($postId);
            if ($answer === null || !$this->questions->isQuestionOpen((int) $answer['question_id'])) {
                return false;
            }
        }

        $body = $this->appendMedia($body, $media);
        $body = $this->appendExternalVideos($body, $videoUrls);
        $validator = (new Validator())->required('body', $body)->length('body', $body, 3, 6000);
        if (!$validator->passes()) {
            return false;
        }

        if ($parentId !== null && !$this->questions->commentParentBelongsTo($type, $postId, $parentId)) {
            return false;
        }

        $userId = (int) $this->session->get('user_id');
        $id = $this->questions->comment($type, $postId, $userId, $body, $parentId);
        $notifiedUserIds = [];
        if ($type === 'question') {
            $question = $this->questions->findSummaryById($postId);
            if ($question !== null) {
                $url = '/question/' . (string) $question['slug'] . '#comment-' . $id;
                $notifiedUserIds = $this->notifyUser(
                    (int) $question['user_id'],
                    $userId,
                    'Clarification',
                    'A new clarification was posted on ' . (string) $question['title'],
                    $url,
                    $notifiedUserIds
                );
                if ($parentId !== null) {
                    $parent = $this->questions->findComment($parentId);
                    if ($parent !== null) {
                        $notifiedUserIds = $this->notifyUser(
                            (int) $parent['user_id'],
                            $userId,
                            'Clarification reply',
                            'A new reply was posted in a clarification thread on ' . (string) $question['title'],
                            $url,
                            $notifiedUserIds
                        );
                    }
                }
                $this->notifyMentions($body, $userId, 'Mention', 'You were mentioned in a clarification on ' . (string) $question['title'], $url, $notifiedUserIds);
            }
        } elseif (isset($answer) && is_array($answer)) {
            $url = '/question/' . (string) $answer['question_slug'] . '#comment-' . $id;
            $notifiedUserIds = $this->notifyUser(
                (int) $answer['user_id'],
                $userId,
                'Clarification',
                'A new clarification was posted on your answer to ' . (string) ($answer['question_title'] ?? 'a question'),
                $url,
                $notifiedUserIds
            );
            if ($parentId !== null) {
                $parent = $this->questions->findComment($parentId);
                if ($parent !== null) {
                    $notifiedUserIds = $this->notifyUser(
                        (int) $parent['user_id'],
                        $userId,
                        'Clarification reply',
                        'A new reply was posted in a clarification thread on ' . (string) ($answer['question_title'] ?? 'a question'),
                        $url,
                        $notifiedUserIds
                    );
                }
            }
            $this->notifyMentions($body, $userId, 'Mention', 'You were mentioned in a clarification on ' . (string) ($answer['question_title'] ?? 'a question'), $url, $notifiedUserIds);
        }
        $this->gamification->award($userId, 1, 'clarification_created', 'comment', $id);
        return true;
    }

    /** @return array{votes: int, current_vote: int}|null */
    public function vote(string $type, int $postId, int $value): ?array
    {
        if (!in_array($type, ['question', 'answer', 'comment'], true)) {
            return null;
        }

        $userId = (int) $this->session->get('user_id');
        if ($userId < 1) {
            return null;
        }

        $result = $this->questions->applyVote($type, $postId, $userId, $value);
        if ($result === null) {
            return null;
        }

        if ((int) ($result['reputation_delta'] ?? 0) !== 0) {
            $this->gamification->award((int) $result['user_id'], (int) $result['reputation_delta'], $type . '_vote', $type, $postId);
        }
        $voteLabel = (int) ($result['current_vote'] ?? 0) > 0 ? 'upvote' : 'downvote';
        $url = $this->postUrl($type, $postId);
        if ($url !== '') {
            $this->notifyUser(
                (int) $result['user_id'],
                $userId,
                'Vote',
                'Your ' . $type . ' received an ' . $voteLabel . '.',
                $url
            );
        }

        return [
            'votes' => (int) ($result['votes'] ?? 0),
            'current_vote' => (int) ($result['current_vote'] ?? 0),
        ];
    }

    public function accept(int $answerId): bool
    {
        $result = $this->questions->acceptAnswer($answerId, (int) $this->session->get('user_id'));
        if ($result === null || !($result['accepted'] ?? false)) {
            return false;
        }

        if ($result['newly_accepted']) {
            $this->gamification->award((int) $result['answer_user_id'], 15, 'answer_accepted', 'answer', $answerId);
        }

        return true;
    }

    public function flag(string $type, int $postId, string $reason): void
    {
        if (!in_array($type, ['question', 'answer', 'comment', 'user'], true)) {
            return;
        }

        $reason = excerpt($reason, 240);
        if ($postId < 1 || trim($reason) === '' || !$this->questions->flagTargetExists($type, $postId)) {
            return;
        }

        $this->questions->flag($type, $postId, (int) $this->session->get('user_id'), $reason);
    }

    public function markdown(string $body): string
    {
        return $this->markdown->toHtml($body);
    }

    /** @return array{0: int, 1: string} */
    private function createQuestionWithUniqueSlug(int $userId, ?int $categoryId, string $title, string $body, string $meta, string $approvalStatus): array
    {
        $base = $this->slugger->slug($title);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $slug = $this->questions->uniqueSlug($base);

            try {
                $id = $this->questions->create($userId, $categoryId, $title, $slug, $body, $meta, $approvalStatus);
                return [$id, $slug];
            } catch (\Throwable $e) {
                if (!is_duplicate_key_exception($e)) {
                    throw $e;
                }
            }
        }

        throw new RuntimeException('Unable to create a unique question URL.');
    }

    private function appendMedia(string $body, ?array $media): string
    {
        if ($media === null) {
            return $body;
        }

        $attachments = [];
        $totalBytes = 0;
        $maxTotalBytes = 20_000_000; // 20 MB combined cap across all uploads in one post

        foreach ($this->uploadList($media) as $file) {
            if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $totalBytes += (int) ($file['size'] ?? 0);
            if ($totalBytes > $maxTotalBytes) {
                break;
            }

            try {
                $path = $this->uploads->storeMedia($file);
            } catch (RuntimeException) {
                continue;
            }

            $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            $attachments[] = in_array($extension, ['mp4', 'webm'], true)
                ? '[uploaded video](' . $path . ')'
                : '![uploaded image](' . $path . ')';
        }

        if ($attachments === []) {
            return $body;
        }

        return rtrim($body) . "\n\n" . implode("\n\n", $attachments);
    }

    private function appendExternalVideos(string $body, string $videoUrls): string
    {
        $videoUrls = trim($videoUrls);
        if ($videoUrls === '') {
            return $body;
        }

        $attachments = [];
        foreach (preg_split('/[\r\n,]+/', $videoUrls) ?: [] as $url) {
            $url = trim((string) $url);
            if ($url === '' || count($attachments) >= 4 || !$this->isEmbeddableVideoUrl($url)) {
                continue;
            }

            $attachments[] = '[embedded video](' . $url . ')';
        }

        if ($attachments === []) {
            return $body;
        }

        return rtrim($body) . "\n\n" . implode("\n\n", $attachments);
    }

    private function isEmbeddableVideoUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        parse_str((string) ($parts['query'] ?? ''), $query);

        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            $id = (string) ($query['v'] ?? '');
            if ($id === '' && str_starts_with($path, '/shorts/')) {
                $id = trim(substr($path, 8), '/');
            }

            return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1;
        }

        if ($host === 'youtu.be') {
            return preg_match('/^[A-Za-z0-9_-]{11}$/', trim($path, '/')) === 1;
        }

        if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
            $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $segment): bool => $segment !== ''));
            $id = end($segments);

            return is_string($id) && preg_match('/^\d{6,15}$/', $id) === 1;
        }

        return false;
    }

    /** @param array<int, bool> $skipUserIds */
    private function notifyMentions(string $body, int $authorId, string $type, string $message, string $url, array $skipUserIds = []): void
    {
        foreach ($this->mentionedUsers($body, $authorId) as $user) {
            if (isset($skipUserIds[(int) $user['id']])) {
                continue;
            }
            $this->queueNotification('MentionNotification', [
                'user_id' => (int) $user['id'],
                'type' => $type,
                'message' => $message,
                'url' => $url,
            ]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function mentionedUsers(string $body, int $authorId): array
    {
        $text = $this->mentionSearchText($body);
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

    /** @param array<int, bool> $alreadyNotified @return array<int, bool> */
    private function notifyUser(int $targetUserId, int $actorUserId, string $type, string $message, string $url, array $alreadyNotified = []): array
    {
        if ($targetUserId < 1 || $targetUserId === $actorUserId || isset($alreadyNotified[$targetUserId])) {
            return $alreadyNotified;
        }

        $this->queueNotification('MentionNotification', [
            'user_id' => $targetUserId,
            'type' => $type,
            'message' => $message,
            'url' => $url,
        ]);
        $alreadyNotified[$targetUserId] = true;

        return $alreadyNotified;
    }

    /** @param array<string, mixed> $payload */
    private function queueNotification(string $job, array $payload): void
    {
        try {
            $this->queue->push($job, $payload);
        } catch (\Throwable $e) {
            $this->logger->error($e);
        }
    }

    private function postUrl(string $type, int $postId): string
    {
        if ($type === 'question') {
            $question = $this->questions->findSummaryById($postId);
            return $question === null ? '' : '/question/' . (string) $question['slug'];
        }

        if ($type === 'answer') {
            $answer = $this->questions->findAnswer($postId);
            return $answer === null ? '' : '/question/' . (string) $answer['question_slug'] . '#answer-' . $postId;
        }

        if ($type === 'comment') {
            $comment = $this->questions->findComment($postId);
            if ($comment === null) {
                return '';
            }
            $slug = (string) ($comment['question_slug'] ?: $comment['answer_question_slug']);

            return $slug === '' ? '' : '/question/' . $slug . '#comment-' . $postId;
        }

        return '';
    }

    private function mentionSearchText(string $body): string
    {
        $text = preg_replace('/`[^`]+`/', ' ', $body) ?? $body;
        $text = preg_replace('/!\[[^\]]*\]\((?:\/public-assets\/images\/[a-zA-Z0-9\/._-]+|https?:\/\/[^\s)]+)\)/i', ' ', $text) ?? $text;
        $text = preg_replace('/\[[^\]]+\]\(https?:\/\/[^\s)]+\)/i', ' ', $text) ?? $text;

        return $text;
    }

    /** @param array<string, mixed> $files @return array<int, array<string, mixed>> */
    private function uploadList(array $files): array
    {
        if (!is_array($files['name'] ?? null)) {
            return [$files];
        }

        $normalized = [];
        foreach ($files['name'] as $index => $name) {
            $normalized[] = [
                'name' => $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            ];
        }

        return array_slice($normalized, 0, 6);
    }
}

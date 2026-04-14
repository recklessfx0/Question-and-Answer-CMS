<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\QuestionModel;
use Core\Markdown;
use Core\Queue;
use Core\Session;
use Core\Slugger;
use Core\Validator;
use RuntimeException;

final readonly class QuestionService
{
    public function __construct(
        private QuestionModel $questions,
        private Session $session,
        private Slugger $slugger,
        private Markdown $markdown,
        private Queue $queue,
        private UploadService $uploads,
        private GamificationService $gamification,
    ) {}

    /** @return array{ok: bool, slug: string, errors: array<string, string>} */
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
        [$id, $slug] = $this->createQuestionWithUniqueSlug((int) $this->session->get('user_id'), $categoryId, $title, $body, $meta);
        $this->questions->attachTags($id, preg_split('/[,#]+/', $tags) ?: []);
        $this->queue->push('NotifyModerators', ['question_id' => $id]);
        $this->gamification->award((int) $this->session->get('user_id'), 3, 'question_created', 'question', $id);

        return ['ok' => true, 'slug' => $slug, 'errors' => []];
    }

    public function answer(int $questionId, string $body, ?array $media = null, string $videoUrls = ''): bool
    {
        if (!$this->questions->isQuestionOpen($questionId)) {
            return false;
        }

        $body = $this->appendMedia($body, $media);
        $body = $this->appendExternalVideos($body, $videoUrls);
        $validator = (new Validator())->required('body', $body)->length('body', $body, 10, 20000);
        if (!$validator->passes()) {
            return false;
        }

        $id = $this->questions->addAnswer($questionId, (int) $this->session->get('user_id'), $body);
        $this->queue->push('AnswerPostedNotification', ['question_id' => $questionId, 'answer_id' => $id]);
        $this->gamification->award((int) $this->session->get('user_id'), 5, 'answer_created', 'answer', $id);

        return true;
    }

    public function comment(string $type, int $postId, string $body, ?int $parentId = null, ?array $media = null, string $videoUrls = ''): bool
    {
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

        $id = $this->questions->comment($type, $postId, (int) $this->session->get('user_id'), $body, $parentId);
        $this->gamification->award((int) $this->session->get('user_id'), 1, 'clarification_created', 'comment', $id);
        return true;
    }

    public function vote(string $type, int $postId, int $value): void
    {
        if (!in_array($type, ['question', 'answer', 'comment'], true)) {
            return;
        }

        $userId = (int) $this->session->get('user_id');
        $result = $this->questions->applyVote($type, $postId, $userId, $value);
        if ($result === null || (int) ($result['reputation_delta'] ?? 0) === 0) {
            return;
        }

        $this->gamification->award((int) $result['user_id'], (int) $result['reputation_delta'], $type . '_vote', $type, $postId);
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
    private function createQuestionWithUniqueSlug(int $userId, ?int $categoryId, string $title, string $body, string $meta): array
    {
        $base = $this->slugger->slug($title);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $slug = $this->questions->uniqueSlug($base);

            try {
                $id = $this->questions->create($userId, $categoryId, $title, $slug, $body, $meta);
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
        foreach ($this->uploadList($media) as $file) {
            if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
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

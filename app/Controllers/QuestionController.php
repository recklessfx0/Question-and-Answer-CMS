<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\QuestionModel;
use App\Models\CategoryModel;
use App\Services\CommunityChoiceService;
use App\Services\QuestionService;
use App\Services\ReactionService;
use App\Services\SeoService;
use App\Services\WidgetService;
use Core\Controller;
use Core\Csrf;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\View;

final class QuestionController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        private readonly QuestionModel $questions,
        private readonly CategoryModel $categories,
        private readonly QuestionService $service,
        private readonly ReactionService $reactionService,
        private readonly CommunityChoiceService $communityChoice,
        private readonly SeoService $seo,
        private readonly WidgetService $widgets,
    ) {
        parent::__construct($view, $session, $csrf);
    }

    public function askForm(): Response
    {
        $categories = $this->categories->all();

        return $this->view('questions/form', [
            'title' => 'Ask a Question',
            'metaDescription' => 'Ask a clear question and get community answers.',
            'robots' => 'noindex,follow',
            'categories' => $categories,
            'categoryCount' => count($categories),
            'maxTags' => QuestionModel::MAX_QUESTION_TAGS,
        ]);
    }

    public function suggest(Request $request): Response
    {
        $title = $request->string('q', 180);
        $items = mb_strlen($title) < 8 ? [] : $this->questions->similarTitles($title);

        return Response::text(json_encode(['items' => $items], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'application/json; charset=utf-8');
    }

    public function store(Request $request): Response
    {
        $categoryId = $request->int('category_id', 0, 0);
        $media = $this->mediaFiles($request);
        $result = $this->service->ask($request->string('title', 180), $request->string('body', 20000), $request->string('tags', 240), $categoryId > 0 ? $categoryId : null, $media, $request->string('video_urls', 1000));
        if (!$result['ok']) {
            $this->flash('error', implode(' ', $result['errors']));
            return $this->redirect('/ask');
        }

        if (!empty($result['pending'])) {
            $this->flash('success', 'Question submitted. It will appear publicly after moderator approval.');
            return $this->redirect('/');
        }

        return $this->redirect('/question/' . $result['slug']);
    }

    /** @param array<string, string> $params */
    public function show(Request $request, array $params): Response
    {
        $question = $this->questions->findBySlug($params['slug'] ?? '');
        if ($question === null) {
            return $this->view('errors/http', ['status' => 404, 'message' => 'Question not found'], 404);
        }

        $this->questions->incrementViews((int) $question['id']);
        $question['views'] = (int) ($question['views'] ?? 0) + 1;

        $answers = $this->communityChoice->annotateAnswers($this->questions->answers((int) $question['id']));
        $questionComments = $this->questions->commentsForQuestion((int) $question['id']);
        $answerComments = $this->questions->commentsForAnswers((int) $question['id']);
        $reactionTargets = [['type' => 'question', 'id' => (int) $question['id']]];
        foreach ($answers as $answer) {
            $reactionTargets[] = ['type' => 'answer', 'id' => (int) $answer['id']];
        }
        $duplicateSlug = (string) ($question['duplicate_slug'] ?? '');
        $canonicalUrl = $duplicateSlug !== '' ? url('/question/' . $duplicateSlug) : url('/question/' . (string) $question['slug']);
        $schema = $this->seo->qapageSchema($question, $answers, $this->seo->breadcrumbs($question), $canonicalUrl, $questionComments, $answerComments);
        $ogImage = $this->seo->firstImageFromBodies((string) $question['body'], ...array_map(static fn (array $answer): string => (string) $answer['body'], $answers));
        $thinUnanswered = count($answers) === 0 && mb_strlen(trim(strip_tags((string) $question['body']))) < 180;
        $tags = $this->questions->tags((int) $question['id']);

        return $this->view('questions/show', [
            'title' => (string) $question['title'],
            'metaDescription' => $this->seo->questionMetaDescription($question, $answers),
            'canonical' => $canonicalUrl,
            'ogType' => 'article',
            'ogImage' => $ogImage,
            'robots' => $duplicateSlug === '' && $thinUnanswered ? 'noindex,follow' : null,
            'authorName' => (string) ($question['username'] ?? ''),
            'publishedAt' => (string) ($question['created_at'] ?? ''),
            'modifiedAt' => (string) ($question['updated_at'] ?? $question['created_at'] ?? ''),
            'sectionName' => (string) ($question['category_name'] ?? ''),
            'keywords' => implode(', ', array_map(static fn (array $tag): string => (string) $tag['name'], $tags)),
            'question' => $question,
            'duplicateCanonical' => $duplicateSlug !== '' ? ['slug' => $duplicateSlug, 'title' => (string) ($question['duplicate_title'] ?? '')] : null,
            'answers' => $answers,
            'questionComments' => $questionComments,
            'answerComments' => $answerComments,
            'reactionMap' => $this->reactionService->forTargets($reactionTargets),
            'tags' => $tags,
            'related' => $this->questions->related((int) $question['id']),
            'schema' => $schema,
            'pageWidgets' => $this->widgets->page('question', (int) $this->session->get('user_id', 0) < 1),
            'markdown' => fn (string $body): string => $this->service->markdown($body),
        ]);
    }

    /** @param array<string, string> $params */
    public function answer(Request $request, array $params): Response
    {
        $question = $this->questions->findBySlug($params['slug'] ?? '');
        $media = $this->mediaFiles($request);
        $result = $question !== null
            ? $this->service->answer((int) $question['id'], $request->string('body', 20000), $media, $request->string('video_urls', 1000))
            : ['ok' => false, 'errors' => ['Question not found.']];
        if ($result['ok']) {
            $this->flash('success', 'Answer posted.');
        } else {
            $this->flash('error', implode(' ', $result['errors'] ?: ['Write a more complete answer.']));
        }

        return $this->redirect('/question/' . ($params['slug'] ?? ''));
    }

    /** @param array<string, string> $params */
    public function commentQuestion(Request $request, array $params): Response
    {
        $question = $this->questions->findBySlug($params['slug'] ?? '');
        if ($question !== null) {
            $parentId = $request->int('parent_id', 0, 0);
            $result = $this->service->comment('question', (int) $question['id'], $request->string('body', 2000), $parentId > 0 ? $parentId : null, $this->mediaFiles($request), $request->string('video_urls', 1000));
            $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Clarification posted.' : implode(' ', $result['errors'] ?: ['Clarification could not be posted.']));
        } else {
            $this->flash('error', 'Question not found.');
        }

        return $this->redirect('/question/' . ($params['slug'] ?? ''));
    }

    /** @param array<string, string> $params */
    public function commentAnswer(Request $request, array $params): Response
    {
        $answer = $this->questions->findAnswer((int) ($params['id'] ?? 0));
        if ($answer !== null) {
            $parentId = $request->int('parent_id', 0, 0);
            $result = $this->service->comment('answer', (int) $answer['id'], $request->string('body', 2000), $parentId > 0 ? $parentId : null, $this->mediaFiles($request), $request->string('video_urls', 1000));
            $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Clarification posted.' : implode(' ', $result['errors'] ?: ['Clarification could not be posted.']));
            return $this->redirect('/question/' . (string) $answer['question_slug'] . '#answer-' . (int) $answer['id']);
        }

        $this->flash('error', 'Answer not found.');
        return $this->redirect('/');
    }

    /** @param array<string, string> $params */
    public function vote(Request $request, array $params): Response
    {
        $state = $this->service->vote($params['type'] ?? '', (int) ($params['id'] ?? 0), $request->int('value', 1, -1, 1));
        if ($this->wantsJson($request)) {
            if ($state === null) {
                return $this->json(['ok' => false], 422);
            }

            return $this->json([
                'ok' => true,
                'votes' => (int) ($state['votes'] ?? 0),
                'current_vote' => (int) ($state['current_vote'] ?? 0),
            ]);
        }

        return Response::redirect($this->localReferer($request));
    }

    /** @param array<string, string> $params */
    public function react(Request $request, array $params): Response
    {
        $items = $this->reactionService->toggle($params['type'] ?? '', (int) ($params['id'] ?? 0), $request->int('reaction_type_id', 0, 1));
        if ($this->wantsJson($request)) {
            return $this->json([
                'ok' => $items !== [],
                'reactions' => $items,
            ], $items === [] ? 422 : 200);
        }

        return Response::redirect($this->localReferer($request));
    }

    /** @param array<string, string> $params */
    public function accept(Request $request, array $params): Response
    {
        $answerId = (int) ($params['id'] ?? 0);
        $answer = $this->questions->findAnswer($answerId);
        $accepted = $this->service->accept($answerId);
        $this->flash($accepted ? 'success' : 'error', $accepted ? 'Accepted answer updated.' : 'Answer could not be accepted.');
        if (is_array($answer) && !empty($answer['question_slug'])) {
            return Response::redirect('/question/' . (string) $answer['question_slug'] . '#answer-' . $answerId);
        }

        return Response::redirect($this->localReferer($request));
    }

    /** @param array<string, string> $params */
    public function flag(Request $request, array $params): Response
    {
        $flagged = $this->service->flag($params['type'] ?? '', (int) ($params['id'] ?? 0), $request->string('reason', 240));
        $this->flash($flagged ? 'success' : 'error', $flagged ? 'Flag submitted for review.' : 'Flag could not be submitted.');
        return Response::redirect($this->localReferer($request));
    }

    private function localReferer(Request $request): string
    {
        $referer = (string) ($request->server['HTTP_REFERER'] ?? '/');
        $internal = safe_internal_path($referer);

        return $internal !== '' ? $internal : '/';
    }

    /** @return array<string, mixed>|null */
    private function mediaFiles(Request $request): ?array
    {
        $media = is_array($request->files['media'] ?? null) ? $request->files['media'] : null;
        if ($media !== null) {
            return $media;
        }

        return is_array($request->files['image'] ?? null) ? $request->files['image'] : null;
    }

    private function wantsJson(Request $request): bool
    {
        $accept = strtolower((string) ($request->server['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string) ($request->server['HTTP_X_REQUESTED_WITH'] ?? ''));

        return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload, int $status = 200): Response
    {
        return new Response(
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }
}

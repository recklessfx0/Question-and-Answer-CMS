<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ModerationService;
use Core\Controller;
use Core\Csrf;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\View;

final class ModeratorController extends Controller
{
    public function __construct(View $view, Session $session, Csrf $csrf, private readonly ModerationService $moderation)
    {
        parent::__construct($view, $session, $csrf);
    }

    public function dashboard(Request $request): Response
    {
        return $this->view('moderator/dashboard', [
            'title' => 'Moderator Tools',
            'metaDescription' => 'Moderator review queue.',
            'moderatorReturnTo' => $this->moderatorReturnTo($request),
        ] + $this->moderation->dashboard(
            $request->int('pending_page', 1, 1, 100000),
            $request->int('reports_page', 1, 1, 100000),
            $request->int('audit_page', 1, 1, 100000)
        ));
    }

    /** @param array<string, string> $params */
    public function editForm(Request $request, array $params): Response
    {
        $type = $params['type'] ?? '';
        $post = $this->moderation->findPost($type, (int) ($params['id'] ?? 0));
        if ($post === null) {
            return $this->view('errors/http', ['status' => 404, 'message' => 'Post not found'], 404);
        }

        return $this->view('moderator/edit', [
            'title' => 'Edit Content',
            'metaDescription' => 'Moderator content editor.',
            'post' => $post,
            'type' => $type,
        ]);
    }

    /** @param array<string, string> $params */
    public function update(Request $request, array $params): Response
    {
        $updated = $this->moderation->update($params['type'] ?? '', (int) ($params['id'] ?? 0), $request->string('title', 180), $request->string('body', 20000));
        $this->flash($updated ? 'success' : 'error', $updated ? 'Content updated.' : 'Content could not be updated.');

        return Response::redirect($this->localReferer($request));
    }

    /** @param array<string, string> $params */
    public function delete(Request $request, array $params): Response
    {
        $type = $params['type'] ?? '';
        $this->moderation->delete($type, (int) ($params['id'] ?? 0));
        $this->flash('success', 'Content removed.');

        return Response::redirect($type === 'question' ? $this->moderatorReturnTo($request) : $this->localReferer($request));
    }

    /** @param array<string, string> $params */
    public function closeQuestion(Request $request, array $params): Response
    {
        $this->moderation->closeQuestion((int) ($params['id'] ?? 0));
        $this->flash('success', 'Question closed.');

        return Response::redirect($this->localReferer($request));
    }

    /** @param array<string, string> $params */
    public function markDuplicate(Request $request, array $params): Response
    {
        $duplicateId = $request->int('duplicate_of_id', 0, 0);
        $updated = $this->moderation->markDuplicate((int) ($params['id'] ?? 0), $duplicateId > 0 ? $duplicateId : null);
        $this->flash($updated ? 'success' : 'error', $updated ? 'Duplicate canonical updated.' : 'Duplicate canonical could not be updated.');

        return Response::redirect($this->localReferer($request));
    }

    /** @param array<string, string> $params */
    public function approveQuestion(Request $request, array $params): Response
    {
        $updated = $this->moderation->moderateQuestion((int) ($params['id'] ?? 0), 'approved');
        $this->flash($updated ? 'success' : 'error', $updated ? 'Question approved.' : 'Question could not be approved.');

        return Response::redirect($this->localReferer($request));
    }

    /** @param array<string, string> $params */
    public function declineQuestion(Request $request, array $params): Response
    {
        $updated = $this->moderation->moderateQuestion((int) ($params['id'] ?? 0), 'declined');
        $this->flash($updated ? 'success' : 'error', $updated ? 'Question declined.' : 'Question could not be declined.');

        return Response::redirect($this->localReferer($request));
    }

    /** @param array<string, string> $params */
    public function verifyAnswer(Request $request, array $params): Response
    {
        $updated = $this->moderation->verifyAnswer((int) ($params['id'] ?? 0), $request->string('review_note', 240));
        $this->flash($updated ? 'success' : 'error', $updated ? 'Answer marked as reviewed.' : 'Answer could not be marked as reviewed.');

        return Response::redirect($this->localReferer($request));
    }

    /** @param array<string, string> $params */
    public function unverifyAnswer(Request $request, array $params): Response
    {
        $updated = $this->moderation->unverifyAnswer((int) ($params['id'] ?? 0));
        $this->flash($updated ? 'success' : 'error', $updated ? 'Answer review removed.' : 'Answer review could not be removed.');

        return Response::redirect($this->localReferer($request));
    }

    /** @param array<string, string> $params */
    public function resolveFlag(Request $request, array $params): Response
    {
        $updated = $this->moderation->resolveFlag((int) ($params['id'] ?? 0), $request->string('status', 20));
        $this->flash($updated ? 'success' : 'error', $updated ? 'Flag updated.' : 'Flag could not be updated.');

        return Response::redirect($this->localReferer($request));
    }

    private function localReferer(Request $request): string
    {
        $redirect = $this->moderatorReturnTo($request->string('redirect', 160) !== '' ? $request : null);
        if ($redirect !== '/moderator' && $redirect !== '/moderate') {
            return $redirect;
        }

        $referer = (string) ($request->server['HTTP_REFERER'] ?? '/moderate');
        $path = parse_url($referer, PHP_URL_PATH);
        $query = parse_url($referer, PHP_URL_QUERY);
        if (!is_string($path) || !str_starts_with($path, '/')) {
            return '/moderate';
        }

        return $this->moderatorPathWithQuery($path, is_string($query) ? $query : '');
    }

    private function moderatorReturnTo(?Request $request): string
    {
        if (!$request instanceof Request) {
            return '/moderate';
        }

        $redirect = $request->string('redirect', 160);
        if ($redirect !== '') {
            $path = parse_url($redirect, PHP_URL_PATH);
            $query = parse_url($redirect, PHP_URL_QUERY);

            return $this->moderatorPathWithQuery(is_string($path) ? $path : '', is_string($query) ? $query : '');
        }

        return $this->moderatorPathWithQuery($request->path, (string) parse_url($request->uri, PHP_URL_QUERY));
    }

    private function moderatorPathWithQuery(string $path, string $queryString): string
    {
        if ($path !== '/moderator' && $path !== '/moderate') {
            return '/moderate';
        }

        parse_str($queryString, $query);
        $allowed = [];
        foreach (['pending_page', 'reports_page', 'audit_page'] as $key) {
            $value = filter_var($query[$key] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 2, 'max_range' => 100000],
            ]);
            if ($value !== false) {
                $allowed[$key] = (int) $value;
            }
        }

        return '/moderate' . ($allowed === [] ? '' : '?' . http_build_query($allowed, '', '&', PHP_QUERY_RFC3986));
    }
}

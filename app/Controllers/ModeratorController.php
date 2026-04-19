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

    public function dashboard(): Response
    {
        return $this->view('moderator/dashboard', [
            'title' => 'Moderator Tools',
            'metaDescription' => 'Moderator review queue.',
            'flags' => $this->moderation->flags(),
            'auditLog' => $this->moderation->auditLog(),
        ]);
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
        $this->moderation->update($params['type'] ?? '', (int) ($params['id'] ?? 0), $request->string('title', 180), $request->string('body', 20000));
        $this->flash('success', 'Content updated.');

        return Response::redirect($this->localReferer($request));
    }

    /** @param array<string, string> $params */
    public function delete(Request $request, array $params): Response
    {
        $type = $params['type'] ?? '';
        $this->moderation->delete($type, (int) ($params['id'] ?? 0));
        $this->flash('success', 'Content removed.');

        return Response::redirect($type === 'question' ? '/moderator' : $this->localReferer($request));
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
    public function verifyAnswer(Request $request, array $params): Response
    {
        $this->moderation->verifyAnswer((int) ($params['id'] ?? 0), $request->string('review_note', 240));
        $this->flash('success', 'Answer marked as reviewed.');

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

        return Response::redirect('/moderator');
    }

    private function localReferer(Request $request): string
    {
        $referer = (string) ($request->server['HTTP_REFERER'] ?? '/moderator');
        $path = parse_url($referer, PHP_URL_PATH);

        return is_string($path) && str_starts_with($path, '/') ? $path : '/moderator';
    }
}

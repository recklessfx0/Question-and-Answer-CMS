<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\NotificationModel;
use Core\Controller;
use Core\Csrf;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\View;

final class NotificationController extends Controller
{
    private const PAGE_SIZE = 20;

    public function __construct(View $view, Session $session, Csrf $csrf, private readonly NotificationModel $notifications)
    {
        parent::__construct($view, $session, $csrf);
    }

    public function index(Request $request): Response
    {
        $page = max(1, $request->int('page', 1, 1));
        $userId = (int) $this->session->get('user_id');
        $total = $this->notifications->countForUser($userId);
        $totalPages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($page, $totalPages);

        return $this->view('users/notifications', [
            'title' => t('notifications.title', 'Notifications'),
            'metaDescription' => t('notifications.description', 'Your notifications.'),
            'robots' => 'noindex,follow',
            'notifications' => $this->notifications->forUser($userId, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE),
            'notificationTotal' => $total,
            'notificationUnreadTotal' => $this->notifications->countUnreadForUser($userId),
            'pagination' => $this->pagination('/notifications', $page, $total),
        ]);
    }

    public function markRead(): Response
    {
        $this->notifications->markAllRead((int) $this->session->get('user_id'));
        return Response::redirect('/notifications');
    }

    /** @return array{current: int, total_pages: int, total_items: int, base: string, query_mode: bool, window: array<int, int>, previous: string|null, next: string|null} */
    private function pagination(string $base, int $page, int $total): array
    {
        $totalPages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min(max(1, $page), $totalPages);
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        if ($end - $start < 4) {
            $start = max(1, min($start, $end - 4));
            $end = min($totalPages, max($end, $start + 4));
        }

        $url = static fn (int $target): string => $target > 1 ? $base . '?page=' . $target : $base;

        return [
            'current' => $page,
            'total_pages' => $totalPages,
            'total_items' => $total,
            'base' => $base . '?',
            'query_mode' => true,
            'window' => range($start, $end),
            'previous' => $page > 1 ? $url($page - 1) : null,
            'next' => $page < $totalPages ? $url($page + 1) : null,
        ];
    }
}

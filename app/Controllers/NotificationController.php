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
    public function __construct(View $view, Session $session, Csrf $csrf, private readonly NotificationModel $notifications)
    {
        parent::__construct($view, $session, $csrf);
    }

    public function index(): Response
    {
        return $this->view('users/notifications', [
            'title' => 'Notifications',
            'metaDescription' => 'Your notifications.',
            'robots' => 'noindex,follow',
            'notifications' => $this->notifications->forUser((int) $this->session->get('user_id')),
        ]);
    }

    public function markRead(): Response
    {
        $this->notifications->markAllRead((int) $this->session->get('user_id'));
        return Response::redirect('/notifications');
    }
}

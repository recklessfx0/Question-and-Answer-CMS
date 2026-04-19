<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\GoogleAuthService;
use App\Services\RecaptchaService;
use Core\Controller;
use Core\Csrf;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\View;

final class AuthController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        private readonly AuthService $auth,
        private readonly RecaptchaService $recaptcha,
        private readonly GoogleAuthService $googleAuth,
    ) {
        parent::__construct($view, $session, $csrf);
    }

    public function registerForm(): Response
    {
        return $this->view('auth/register', [
            'title' => 'Create Account',
            'metaDescription' => 'Create your question and answer account.',
            'robots' => 'noindex,follow',
            'googleAuthUrl' => $this->googleAuth->authUrl(),
        ]);
    }

    public function register(Request $request): Response
    {
        if (!$this->recaptcha->verify($request)) {
            $this->flash('error', 'Please complete the verification challenge.');
            return $this->redirect('/register');
        }

        $result = $this->auth->register($request->string('username', 80), $request->string('email', 180), $request->string('password', 128));
        if (!$result['ok']) {
            $this->flash('error', implode(' ', $result['errors']));
            return $this->redirect('/register');
        }

        $this->flash('success', 'Welcome. Your account is ready.');
        return $this->redirect('/');
    }

    public function loginForm(): Response
    {
        return $this->view('auth/login', [
            'title' => 'Sign In',
            'metaDescription' => 'Sign in to ask and answer questions.',
            'robots' => 'noindex,follow',
            'googleAuthUrl' => $this->googleAuth->authUrl(),
        ]);
    }

    public function forgotPasswordForm(): Response
    {
        return $this->view('auth/forgot-password', ['title' => 'Reset Password', 'metaDescription' => 'Request a password reset link.', 'robots' => 'noindex,follow']);
    }

    public function forgotPassword(Request $request): Response
    {
        if (!$this->recaptcha->verify($request)) {
            $this->flash('error', 'Please complete the verification challenge.');
            return $this->redirect('/forgot-password');
        }

        $this->auth->requestPasswordReset($request->string('email', 180));
        $this->flash('success', 'If that email exists, a reset link has been queued.');
        return $this->redirect('/login');
    }

    /** @param array<string, string> $params */
    public function resetPasswordForm(Request $request, array $params): Response
    {
        return $this->view('auth/reset-password', ['title' => 'Choose New Password', 'metaDescription' => 'Choose a new password.', 'robots' => 'noindex,follow', 'token' => $params['token'] ?? '']);
    }

    /** @param array<string, string> $params */
    public function resetPassword(Request $request, array $params): Response
    {
        if (!$this->auth->resetPassword($params['token'] ?? '', $request->string('password', 128))) {
            $this->flash('error', 'Reset link is invalid or the password is too short.');
            return $this->redirect('/forgot-password');
        }

        $this->flash('success', 'Password updated. Sign in with the new password.');
        return $this->redirect('/login');
    }

    /** @param array<string, string> $params */
    public function verifyEmail(Request $request, array $params): Response
    {
        $verified = $this->auth->verifyEmail($params['token'] ?? '');
        $this->flash($verified ? 'success' : 'error', $verified ? 'Email verified.' : 'Verification link is invalid or expired.');
        return $this->redirect('/');
    }

    public function login(Request $request): Response
    {
        if (!$this->recaptcha->verify($request)) {
            $this->flash('error', 'Please complete the verification challenge.');
            return $this->redirect('/login');
        }

        if (!$this->auth->login($request->string('email', 180), $request->string('password', 128))) {
            $this->flash('error', 'Invalid email or password.');
            return $this->redirect('/login');
        }

        return $this->redirect('/');
    }

    public function logout(): Response
    {
        $this->auth->logout();
        return $this->redirect('/');
    }

    public function googleRedirect(): Response
    {
        $url = $this->googleAuth->authUrl();
        if ($url === null) {
            $this->flash('error', 'Google sign-in is not configured.');
            return $this->redirect('/login');
        }

        return Response::redirect($url);
    }

    public function googleCallback(Request $request): Response
    {
        if (!$this->googleAuth->handleCallback($request->string('code', 4096), $request->string('state', 256))) {
            $this->flash('error', 'Google sign-in could not be completed.');
            return $this->redirect('/login');
        }

        $this->flash('success', 'Signed in with Google.');
        return $this->redirect('/');
    }
}

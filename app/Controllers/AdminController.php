<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminService;
use Core\Controller;
use Core\Csrf;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\View;

final class AdminController extends Controller
{
    public function __construct(View $view, Session $session, Csrf $csrf, private readonly AdminService $admin)
    {
        parent::__construct($view, $session, $csrf);
    }

    public function dashboard(): Response
    {
        return $this->adminView('admin/dashboard', 'Admin Panel', 'Administration dashboard.');
    }

    public function settingsPage(): Response
    {
        return $this->adminView('admin/settings', 'Admin Settings', 'Manage site, SEO, and security settings.');
    }

    public function trustPagesPage(): Response
    {
        return $this->adminView('admin/trust-pages', 'Admin Trust Pages', 'Manage about, contact, privacy, terms, and community guidelines.');
    }

    public function languagesPage(Request $request): Response
    {
        return $this->view('admin/languages', array_merge([
            'title' => t('admin.languages.title', 'Languages'),
            'metaDescription' => t('admin.languages.description', 'Translate interface text and control which languages are available to visitors.'),
        ], $this->admin->dashboard(), $this->admin->languageEditor($request->string('code', 20))));
    }

    public function categoriesPage(): Response
    {
        return $this->adminView('admin/categories', 'Admin Categories', 'Manage categories and subcategories.');
    }

    public function levelsPage(): Response
    {
        return $this->adminView('admin/levels', 'Admin Gamification Levels', 'Manage gamification levels.');
    }

    public function reactionsPage(): Response
    {
        return $this->adminView('admin/reactions', 'Admin Reactions', 'Manage reaction types and gamification rewards.');
    }

    public function usersPage(): Response
    {
        return $this->adminView('admin/users', 'Admin Users', 'Manage users and roles.');
    }

    public function tagsPage(): Response
    {
        return $this->adminView('admin/tags', 'Admin Tags', 'Manage tag SEO and topical descriptions.');
    }

    public function reportsPage(): Response
    {
        return $this->adminView('admin/reports', 'Admin Reports', 'Review user reports.');
    }

    private function adminView(string $template, string $title, string $description): Response
    {
        return $this->view($template, array_merge([
            'title' => $title,
            'metaDescription' => $description,
        ], $this->admin->dashboard()));
    }

    /** @param array<string, string> $params */
    public function setRole(Request $request, array $params): Response
    {
        $updated = $this->admin->setRole((int) ($params['id'] ?? 0), $request->string('role', 30));
        $this->flash($updated ? 'success' : 'error', $updated ? 'User role updated.' : 'At least one administrator must remain.');
        return Response::redirect('/admin/users');
    }

    public function settings(Request $request): Response
    {
        $this->admin->saveSettings([
            'site_title' => $request->string('site_title', 100),
            'site_url' => $request->string('site_url', 255),
            'site_description' => $request->string('site_description', 200),
            'recaptcha_enabled' => (string) $request->int('recaptcha_enabled', 0, 0, 1),
            'recaptcha_site_key' => $request->string('recaptcha_site_key', 255),
            'recaptcha_secret_key' => $request->string('recaptcha_secret_key', 255),
            'google_auth_enabled' => (string) $request->int('google_auth_enabled', 0, 0, 1),
            'google_client_id' => $request->string('google_client_id', 255),
            'google_client_secret' => $request->string('google_client_secret', 255),
            'smtp_enabled' => (string) $request->int('smtp_enabled', 0, 0, 1),
            'smtp_host' => $request->string('smtp_host', 255),
            'smtp_port' => (string) $request->int('smtp_port', 587, 1, 65535),
            'smtp_encryption' => $request->string('smtp_encryption', 10),
            'smtp_username' => $request->string('smtp_username', 255),
            'smtp_password' => $request->string('smtp_password', 255),
            'smtp_from_email' => $request->string('smtp_from_email', 255),
            'smtp_from_name' => $request->string('smtp_from_name', 120),
        ], (int) $this->session->get('user_id', 0), is_array($request->files['site_logo'] ?? null) ? $request->files['site_logo'] : null);
        return Response::redirect('/admin/settings');
    }

    public function trustPages(Request $request): Response
    {
        $this->admin->saveTrustPages([
            'about_description' => $request->string('about_description', 240),
            'about_body' => $request->string('about_body', 4000),
            'contact_description' => $request->string('contact_description', 240),
            'contact_body' => $request->string('contact_body', 4000),
            'privacy_description' => $request->string('privacy_description', 240),
            'privacy_body' => $request->string('privacy_body', 4000),
            'terms_description' => $request->string('terms_description', 240),
            'terms_body' => $request->string('terms_body', 4000),
            'guidelines_description' => $request->string('guidelines_description', 240),
            'guidelines_body' => $request->string('guidelines_body', 4000),
        ], (int) $this->session->get('user_id', 0));

        return Response::redirect('/admin/trust-pages');
    }

    public function createLanguage(Request $request): Response
    {
        $created = $this->admin->createLanguage(
            $request->string('code', 20),
            $request->string('name', 80),
            $request->int('is_active', 0, 0, 1) === 1,
            $request->int('is_default', 0, 0, 1) === 1,
            (int) $this->session->get('user_id', 0)
        );
        $this->flash($created ? 'success' : 'error', $created ? 'Language saved.' : 'Language could not be saved.');

        return Response::redirect('/admin/languages');
    }

    /** @param array<string, string> $params */
    public function updateLanguage(Request $request, array $params): Response
    {
        $updated = $this->admin->updateLanguage(
            (int) ($params['id'] ?? 0),
            $request->string('name', 80),
            $request->int('is_active', 0, 0, 1) === 1,
            $request->int('is_default', 0, 0, 1) === 1,
            (int) $this->session->get('user_id', 0)
        );
        $this->flash($updated ? 'success' : 'error', $updated ? 'Language updated.' : 'Language could not be updated.');

        return Response::redirect('/admin/languages?code=' . rawurlencode($request->string('code', 20)));
    }

    /** @param array<string, string> $params */
    public function saveTranslations(Request $request, array $params): Response
    {
        $translations = is_array($request->post['translations'] ?? null) ? $request->post['translations'] : [];
        $clean = [];
        foreach ($translations as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $text = trim(str_replace("\0", '', (string) $value));
                $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
                $clean[$key] = mb_substr($text, 0, 4000);
            }
        }
        $this->admin->saveTranslations((int) ($params['id'] ?? 0), $clean, (int) $this->session->get('user_id', 0));
        $this->flash('success', 'Translations updated.');

        return Response::redirect('/admin/languages?code=' . rawurlencode($request->string('code', 20)));
    }

    public function createCategory(Request $request): Response
    {
        $created = $this->admin->createCategory($request->int('parent_id', 0, 0), $request->string('name', 80), $request->string('description', 240), $request->string('seo_title', 120), $request->string('seo_description', 180), $request->string('intro', 4000));
        $this->flash($created ? 'success' : 'error', $created ? 'Category created.' : 'Category could not be created.');
        return Response::redirect('/admin/categories');
    }

    /** @param array<string, string> $params */
    public function updateCategory(Request $request, array $params): Response
    {
        $updated = $this->admin->updateCategory((int) ($params['id'] ?? 0), $request->int('parent_id', 0, 0), $request->string('name', 80), $request->string('description', 240), $request->string('seo_title', 120), $request->string('seo_description', 180), $request->string('intro', 4000));
        $this->flash($updated ? 'success' : 'error', $updated ? 'Category updated.' : 'Category could not be updated.');
        return Response::redirect('/admin/categories');
    }

    /** @param array<string, string> $params */
    public function updateTag(Request $request, array $params): Response
    {
        $this->admin->updateTag((int) ($params['id'] ?? 0), $request->string('name', 80), $request->string('description', 240), $request->string('seo_title', 120), $request->string('seo_description', 180), $request->string('intro', 4000));
        return Response::redirect('/admin/tags');
    }

    /** @param array<string, string> $params */
    public function deleteCategory(Request $request, array $params): Response
    {
        $deleted = $this->admin->deleteCategory((int) ($params['id'] ?? 0));
        $this->flash($deleted ? 'success' : 'error', $deleted ? 'Category deleted.' : 'Category could not be deleted.');
        return Response::redirect('/admin/categories');
    }

    public function createLevel(Request $request): Response
    {
        $icon = is_array($request->files['icon'] ?? null) ? $request->files['icon'] : null;
        $created = $this->admin->createLevel(
            $request->int('level_number', 1, 1, 100),
            $request->string('title', 80),
            $request->int('min_reputation', 0, 0),
            $request->string('description', 240),
            $request->int('is_active', 0, 0, 1) === 1,
            $icon
        );
        $this->flash($created ? 'success' : 'error', $created ? 'Level created.' : 'Level could not be created.');

        return Response::redirect('/admin/levels');
    }

    /** @param array<string, string> $params */
    public function updateLevel(Request $request, array $params): Response
    {
        $icon = is_array($request->files['icon'] ?? null) ? $request->files['icon'] : null;
        $updated = $this->admin->updateLevel(
            (int) ($params['id'] ?? 0),
            $request->int('level_number', 1, 1, 100),
            $request->string('title', 80),
            $request->int('min_reputation', 0, 0),
            $request->string('description', 240),
            $request->int('is_active', 0, 0, 1) === 1,
            $icon
        );
        $this->flash($updated ? 'success' : 'error', $updated ? 'Level updated.' : 'Level could not be updated.');

        return Response::redirect('/admin/levels');
    }

    /** @param array<string, string> $params */
    public function deleteLevel(Request $request, array $params): Response
    {
        $deleted = $this->admin->deleteLevel((int) ($params['id'] ?? 0));
        $this->flash($deleted ? 'success' : 'error', $deleted ? 'Level deleted.' : 'Level could not be deleted.');
        return Response::redirect('/admin/levels');
    }

    public function createReaction(Request $request): Response
    {
        $icon = is_array($request->files['icon'] ?? null) ? $request->files['icon'] : null;
        $created = $this->admin->createReaction($request->string('name', 60), $request->int('reputation_delta', 1, -10, 25), $request->int('sort_order', 0, 0, 10000), $request->int('is_active', 0, 0, 1) === 1, $icon);
        $this->flash($created ? 'success' : 'error', $created ? 'Reaction created.' : 'Reaction could not be created.');

        return Response::redirect('/admin/reactions');
    }

    /** @param array<string, string> $params */
    public function updateReaction(Request $request, array $params): Response
    {
        $icon = is_array($request->files['icon'] ?? null) ? $request->files['icon'] : null;
        $updated = $this->admin->updateReaction((int) ($params['id'] ?? 0), $request->string('name', 60), $request->int('reputation_delta', 1, -10, 25), $request->int('sort_order', 0, 0, 10000), $request->int('is_active', 0, 0, 1) === 1, $icon);
        $this->flash($updated ? 'success' : 'error', $updated ? 'Reaction updated.' : 'Reaction could not be updated.');

        return Response::redirect('/admin/reactions');
    }

    /** @param array<string, string> $params */
    public function deleteReaction(Request $request, array $params): Response
    {
        $deleted = $this->admin->deleteReaction((int) ($params['id'] ?? 0));
        $this->flash($deleted ? 'success' : 'error', $deleted ? 'Reaction deleted.' : 'Reaction could not be deleted.');

        return Response::redirect('/admin/reactions');
    }

    /** @param array<string, string> $params */
    public function resolveReport(Request $request, array $params): Response
    {
        $updated = $this->admin->resolveReport((int) ($params['id'] ?? 0), $request->string('status', 20), (int) $this->session->get('user_id'));
        $this->flash($updated ? 'success' : 'error', $updated ? 'Report updated.' : 'Report could not be updated.');
        return Response::redirect('/admin/reports');
    }

    /** @param array<string, string> $params */
    public function deleteReportedContent(Request $request, array $params): Response
    {
        $this->admin->deleteReportedContent($params['type'] ?? '', (int) ($params['id'] ?? 0), (int) $this->session->get('user_id'));
        return Response::redirect('/admin/reports');
    }

    /** @param array<string, string> $params */
    public function closeQuestion(Request $request, array $params): Response
    {
        $this->admin->closeQuestion((int) ($params['id'] ?? 0), (int) $this->session->get('user_id'));
        return Response::redirect('/admin/reports');
    }
}

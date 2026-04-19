<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\SettingsModel;
use App\Services\SeoService;
use Core\Controller;
use Core\Csrf;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\View;

final class StaticPageController extends Controller
{
    /** @var array<string, array{title: string, description_key: string, body_key: string, path: string, schema: string}> */
    private const PAGES = [
        'about' => [
            'title' => 'About',
            'description_key' => 'about_description',
            'body_key' => 'about_body',
            'path' => '/about',
            'schema' => 'AboutPage',
        ],
        'contact' => [
            'title' => 'Contact',
            'description_key' => 'contact_description',
            'body_key' => 'contact_body',
            'path' => '/contact',
            'schema' => 'ContactPage',
        ],
        'privacy' => [
            'title' => 'Privacy',
            'description_key' => 'privacy_description',
            'body_key' => 'privacy_body',
            'path' => '/privacy',
            'schema' => 'WebPage',
        ],
        'terms' => [
            'title' => 'Terms',
            'description_key' => 'terms_description',
            'body_key' => 'terms_body',
            'path' => '/terms',
            'schema' => 'WebPage',
        ],
        'community-guidelines' => [
            'title' => 'Community Guidelines',
            'description_key' => 'guidelines_description',
            'body_key' => 'guidelines_body',
            'path' => '/community-guidelines',
            'schema' => 'WebPage',
        ],
    ];

    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        private readonly SettingsModel $settings,
        private readonly SeoService $seo,
    ) {
        parent::__construct($view, $session, $csrf);
    }

    public function about(Request $request): Response
    {
        return $this->page('about');
    }

    public function contact(Request $request): Response
    {
        return $this->page('contact');
    }

    public function privacy(Request $request): Response
    {
        return $this->page('privacy');
    }

    public function terms(Request $request): Response
    {
        return $this->page('terms');
    }

    public function guidelines(Request $request): Response
    {
        return $this->page('community-guidelines');
    }

    private function page(string $key): Response
    {
        $page = self::PAGES[$key];
        $settings = $this->settings->all();
        $siteTitle = (string) (($settings['site_title'] ?? '') ?: (require BASE_PATH . '/config/app.php')['name']);
        $description = $this->settingOrDefault($settings, $page['description_key'], $page['title'] . ' information for ' . $siteTitle . '.');
        $body = $this->settingOrDefault($settings, $page['body_key'], $this->defaultBody($page['title'], $siteTitle));

        return $this->view('static/page', [
            'title' => $page['title'],
            'metaDescription' => $description,
            'canonical' => url($page['path']),
            'schema' => $this->seo->staticPageSchema($page['schema'], $page['title'], $description, url($page['path'])),
            'pageTitle' => $page['title'],
            'pageDescription' => $description,
            'pageBody' => $body,
        ]);
    }

    /** @param array<string, string> $settings */
    private function settingOrDefault(array $settings, string $key, string $default): string
    {
        $value = trim((string) ($settings[$key] ?? ''));

        return $value !== '' ? $value : $default;
    }

    private function defaultBody(string $title, string $siteTitle): string
    {
        return $title . ' details for ' . $siteTitle . ' can be managed from the admin settings.';
    }
}

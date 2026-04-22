<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\QuestionModel;
use App\Models\CategoryModel;
use App\Models\SettingsModel;
use App\Models\UserModel;
use App\Services\SeoService;
use App\Services\WidgetService;
use Core\Controller;
use Core\Csrf;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\View;

final class HomeController extends Controller
{
    private const PAGE_SIZE = 20;
    private const LANDING_PAGE_SIZE = 15;

    public function __construct(View $view, Session $session, Csrf $csrf, private readonly QuestionModel $questions, private readonly CategoryModel $categories, private readonly UserModel $users, private readonly SeoService $seo, private readonly SettingsModel $settings, private readonly WidgetService $widgets)
    {
        parent::__construct($view, $session, $csrf);
    }

    /** @param array<string, string> $params */
    public function index(Request $request, array $params): Response
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = $this->intSetting('landing_questions_per_page', self::LANDING_PAGE_SIZE, 1, 50);
        $total = $this->questions->countLatest();
        if ($this->pageOutOfRange($page, $total, $perPage)) {
            return $this->view('errors/http', ['status' => 404, 'message' => 'Page not found'], 404);
        }
        $questions = $this->questions->withCardMeta($this->questions->latest($perPage, ($page - 1) * $perPage));
        $canonical = $page > 1 ? url('/questions/page/' . $page) : url('/');
        $pagination = $this->pagination('/questions', $page, $total, false, $perPage);
        $baseDescription = $this->siteDescription('Fresh community questions and expert answers.');
        $landingHeading = $this->setting('landing_questions_heading', 'Latest Questions');
        $pageHeading = $page > 1 ? $landingHeading . ' - Page ' . $page : $landingHeading;

        return $this->view('home/index', [
            'title' => $page > 1 ? $pageHeading : null,
            'heading' => $pageHeading,
            'metaDescription' => $page > 1 ? $baseDescription . ' Page ' . $page . '.' : $baseDescription,
            'canonical' => $canonical,
            'schema' => $this->seo->itemListSchema($landingHeading, $canonical, $questions, $baseDescription, [], 'Community questions'),
            'questions' => $questions,
            'page' => $page,
            'pagination' => $pagination,
            'pageWidgets' => $request->path === '/' && $page === 1 ? $this->widgets->page('landing', (int) $this->session->get('user_id', 0) < 1) : [],
        ]);
    }

    public function search(Request $request): Response
    {
        $query = $request->string('q', 120);
        $page = max(1, $request->int('page', 1, 1));
        $total = $query === '' ? 0 : $this->questions->countSearch($query);
        $questions = $query === '' ? [] : $this->questions->withCardMeta($this->questions->search($query, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE));
        $searchBase = '/search?q=' . rawurlencode($query);

        return $this->view('home/index', [
            'title' => $query === '' ? 'Search Questions' : 'Search: ' . $query,
            'metaDescription' => 'Search questions, answers, and tags.',
            'canonical' => url('/search'),
            'robots' => 'noindex,follow',
            'questions' => $questions,
            'query' => $query,
            'page' => $page,
            'pagination' => $this->pagination($searchBase, $page, $total, true),
        ]);
    }

    /** @param array<string, string> $params */
    public function unanswered(Request $request, array $params = []): Response
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $total = $this->questions->countUnanswered();
        if ($this->pageOutOfRange($page, $total)) {
            return $this->view('errors/http', ['status' => 404, 'message' => 'Page not found'], 404);
        }
        $questions = $this->questions->withCardMeta($this->questions->unanswered(self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE));
        $canonical = $page > 1 ? url('/unanswered/page/' . $page) : url('/unanswered');
        $pagination = $this->pagination('/unanswered', $page, $total);

        return $this->view('home/index', [
            'title' => $page > 1 ? 'Unanswered Questions - Page ' . $page : 'Unanswered Questions',
            'metaDescription' => $page > 1 ? 'Questions still waiting for an answer, page ' . $page . '.' : 'Questions still waiting for an answer.',
            'canonical' => $canonical,
            'schema' => $this->seo->itemListSchema('Unanswered Questions', $canonical, $questions, 'Questions still waiting for an answer.', [], 'Unanswered questions'),
            'questions' => $questions,
            'page' => $page,
            'pagination' => $pagination,
        ]);
    }

    public function tags(Request $request, array $params = []): Response
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = 48;
        $total = $this->questions->countTags();
        if ($this->pageOutOfRange($page, $total, $perPage)) {
            return $this->view('errors/http', ['status' => 404, 'message' => 'Page not found'], 404);
        }
        $tags = $this->questions->popularTags($perPage, ($page - 1) * $perPage);
        $canonical = $page > 1 ? url('/tags/page/' . $page) : url('/tags');
        $pagination = $this->pagination('/tags', $page, $total, false, $perPage);

        return $this->view('home/tags', [
            'title' => $page > 1 ? 'Tags - Page ' . $page : 'Tags',
            'metaDescription' => 'Browse all question tags.',
            'canonical' => $canonical,
            'schema' => $this->seo->tagDirectorySchema($tags, $canonical),
            'tags' => $tags,
            'page' => $page,
            'pagination' => $pagination,
        ]);
    }

    /** @param array<string, string> $params */
    public function users(Request $request, array $params = []): Response
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = 24;
        $total = $this->users->publicCount();
        if ($this->pageOutOfRange($page, $total, $perPage)) {
            return $this->view('errors/http', ['status' => 404, 'message' => 'Page not found'], 404);
        }
        $users = $this->users->publicUsers($perPage, ($page - 1) * $perPage);
        $summary = $this->users->directorySummary();
        $canonical = $page > 1 ? url('/users/page/' . $page) : url('/users');
        $pagination = $this->pagination('/users', $page, $total, false, $perPage);

        return $this->view('home/users', [
            'title' => $page > 1 ? 'Users - Page ' . $page : 'Users',
            'metaDescription' => 'Browse community members.',
            'canonical' => $canonical,
            'schema' => $this->seo->userDirectorySchema($users, $canonical),
            'summary' => $summary,
            'users' => $users,
            'page' => $page,
            'pagination' => $pagination,
        ]);
    }

    /** @param array<string, string> $params */
    public function tag(Request $request, array $params): Response
    {
        $slug = $params['slug'] ?? '';
        $tag = $this->questions->tagBySlug($slug);
        if ($tag === null) {
            return $this->view('errors/http', ['status' => 404, 'message' => 'Tag not found'], 404);
        }
        $page = max(1, (int) ($params['page'] ?? 1));
        $total = $this->questions->countByTag($slug);
        if ($this->pageOutOfRange($page, $total)) {
            return $this->view('errors/http', ['status' => 404, 'message' => 'Page not found'], 404);
        }
        $questions = $this->questions->withCardMeta($this->questions->byTag($slug, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE));
        $canonical = url('/tag/' . rawurlencode($slug));
        $pageCanonical = $page > 1 ? url('/tag/' . rawurlencode($slug) . '/page/' . $page) : $canonical;
        $pagination = $this->pagination('/tag/' . rawurlencode($slug), $page, $total);
        $baseTitle = (string) (($tag['seo_title'] ?? '') ?: ('Questions tagged ' . (string) $tag['name']));
        $baseDescription = (string) (($tag['seo_description'] ?? '') ?: ((int) ($tag['question_count'] ?? 0) . ' community questions tagged ' . (string) $tag['name'] . '.'));

        return $this->view('home/index', [
            'title' => $page > 1 ? $baseTitle . ' - Page ' . $page : $baseTitle,
            'metaDescription' => $page > 1 ? $baseDescription . ' Page ' . $page . '.' : $baseDescription,
            'canonical' => $pageCanonical,
            'schema' => $this->seo->itemListSchema('Questions tagged ' . (string) $tag['name'], $pageCanonical, $questions, $baseDescription, [(string) $tag['name']], (string) $tag['name']),
            'questions' => $questions,
            'tag' => (string) $tag['slug'],
            'tagInfo' => $tag,
            'relatedTags' => $this->questions->relatedTags((string) $tag['slug']),
            'page' => $page,
            'pagination' => $pagination,
        ]);
    }

    /** @param array<string, string> $params */
    public function category(Request $request, array $params): Response
    {
        $category = $this->categories->findBySlug($params['slug'] ?? '');
        if ($category === null) {
            return $this->view('errors/http', ['status' => 404, 'message' => 'Category not found'], 404);
        }

        $page = max(1, (int) ($params['page'] ?? 1));
        $total = $this->questions->countByCategory((int) $category['id']);
        if ($this->pageOutOfRange($page, $total)) {
            return $this->view('errors/http', ['status' => 404, 'message' => 'Page not found'], 404);
        }
        $questions = $this->questions->withCardMeta($this->questions->byCategory((int) $category['id'], self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE));
        $canonical = url('/category/' . rawurlencode((string) $category['slug']));
        $pageCanonical = $page > 1 ? url('/category/' . rawurlencode((string) $category['slug']) . '/page/' . $page) : $canonical;
        $pagination = $this->pagination('/category/' . rawurlencode((string) $category['slug']), $page, $total);
        $baseTitle = (string) (($category['seo_title'] ?? '') ?: $category['name']);
        $baseDescription = (string) (($category['seo_description'] ?? '') ?: ($category['description'] ?: 'Questions in ' . (string) $category['name'] . '.'));
        $relatedTags = $this->questions->popularTagsForCategory((int) $category['id']);

        return $this->view('home/index', [
            'title' => $page > 1 ? $baseTitle . ' - Page ' . $page : $baseTitle,
            'metaDescription' => $page > 1 ? $baseDescription . ' Page ' . $page . '.' : $baseDescription,
            'canonical' => $pageCanonical,
            'robots' => $total < 1 ? 'noindex,follow' : null,
            'schema' => $this->seo->itemListSchema((string) $category['name'], $pageCanonical, $questions, $baseDescription, array_map(static fn (array $tag): string => (string) ($tag['name'] ?? ''), $relatedTags), (string) $category['name']),
            'questions' => $questions,
            'category' => $category,
            'subcategories' => $this->categories->children((int) $category['id']),
            'relatedTags' => $relatedTags,
            'page' => $page,
            'pagination' => $pagination,
        ]);
    }

    /** @return array{current: int, total_pages: int, total_items: int, base: string, query_mode: bool, window: array<int, int>, previous: string|null, next: string|null} */
    private function pagination(string $base, int $page, int $total, bool $queryMode = false, int $perPage = self::PAGE_SIZE): array
    {
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $totalPages);
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        if ($end - $start < 4) {
            $start = max(1, min($start, $end - 4));
            $end = min($totalPages, max($end, $start + 4));
        }

        $url = function (int $target) use ($base, $queryMode): string {
            if ($queryMode) {
                return $base . ($target > 1 ? '&page=' . $target : '');
            }

            return $target > 1 ? rtrim($base, '/') . '/page/' . $target : rtrim($base, '/');
        };

        return [
            'current' => $page,
            'total_pages' => $totalPages,
            'total_items' => $total,
            'base' => $base,
            'query_mode' => $queryMode,
            'window' => range($start, $end),
            'previous' => $page > 1 ? $url($page - 1) : null,
            'next' => $page < $totalPages ? $url($page + 1) : null,
        ];
    }

    private function siteDescription(string $fallback): string
    {
        return $this->setting('site_description', $fallback);
    }

    private function setting(string $key, string $fallback): string
    {
        try {
            $value = trim($this->settings->get($key));
        } catch (\Throwable) {
            return $fallback;
        }

        return $value !== '' ? $value : $fallback;
    }

    private function intSetting(string $key, int $fallback, int $min, int $max): int
    {
        try {
            $value = trim($this->settings->get($key));
        } catch (\Throwable) {
            return $fallback;
        }

        if ($value === '' || !ctype_digit($value)) {
            return $fallback;
        }

        return max($min, min($max, (int) $value));
    }

    private function pageOutOfRange(int $page, int $total, int $perPage = self::PAGE_SIZE): bool
    {
        if ($page <= 1) {
            return false;
        }

        return $page > max(1, (int) ceil($total / $perPage));
    }
}

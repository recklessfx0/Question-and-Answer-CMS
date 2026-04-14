<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\QuestionModel;
use App\Models\CategoryModel;
use App\Models\UserModel;
use App\Services\SeoService;
use Core\Controller;
use Core\Csrf;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\View;

final class HomeController extends Controller
{
    private const PAGE_SIZE = 20;

    public function __construct(View $view, Session $session, Csrf $csrf, private readonly QuestionModel $questions, private readonly CategoryModel $categories, private readonly UserModel $users, private readonly SeoService $seo)
    {
        parent::__construct($view, $session, $csrf);
    }

    /** @param array<string, string> $params */
    public function index(Request $request, array $params): Response
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $total = $this->questions->countLatest();
        if ($this->pageOutOfRange($page, $total)) {
            return $this->view('errors/http', ['status' => 404, 'message' => 'Page not found'], 404);
        }
        $questions = $this->questions->withCardMeta($this->questions->latest(self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE));
        $canonical = $page > 1 ? url('/questions/page/' . $page) : url('/');
        $pagination = $this->pagination('/questions', $page, $total);

        return $this->view('home/index', [
            'title' => $page > 1 ? 'Latest Questions - Page ' . $page : 'Latest Questions',
            'metaDescription' => $page > 1 ? 'Fresh community questions and expert answers, page ' . $page . '.' : 'Fresh community questions and expert answers.',
            'canonical' => $canonical,
            'schema' => $this->seo->itemListSchema('Latest Questions', $canonical, $questions),
            'questions' => $questions,
            'page' => $page,
            'pagination' => $pagination,
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
            'schema' => $this->seo->itemListSchema('Unanswered Questions', $canonical, $questions),
            'questions' => $questions,
            'page' => $page,
            'pagination' => $pagination,
        ]);
    }

    public function tags(): Response
    {
        $tags = $this->questions->popularTags();

        return $this->view('home/tags', [
            'title' => 'Tags',
            'metaDescription' => 'Browse all question tags.',
            'canonical' => url('/tags'),
            'schema' => $this->seo->tagDirectorySchema($tags),
            'tags' => $tags,
        ]);
    }

    public function users(): Response
    {
        $users = $this->users->publicUsers();

        return $this->view('home/users', [
            'title' => 'Users',
            'metaDescription' => 'Browse community members.',
            'canonical' => url('/users'),
            'schema' => $this->seo->userDirectorySchema($users),
            'users' => $users,
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
            'schema' => $this->seo->itemListSchema('Questions tagged ' . (string) $tag['name'], $pageCanonical, $questions),
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

        return $this->view('home/index', [
            'title' => $page > 1 ? $baseTitle . ' - Page ' . $page : $baseTitle,
            'metaDescription' => $page > 1 ? $baseDescription . ' Page ' . $page . '.' : $baseDescription,
            'canonical' => $pageCanonical,
            'schema' => $this->seo->itemListSchema((string) $category['name'], $pageCanonical, $questions),
            'questions' => $questions,
            'category' => $category,
            'subcategories' => $this->categories->children((int) $category['id']),
            'relatedTags' => $this->questions->popularTagsForCategory((int) $category['id']),
            'page' => $page,
            'pagination' => $pagination,
        ]);
    }

    /** @return array{current: int, total_pages: int, total_items: int, base: string, query_mode: bool, window: array<int, int>, previous: string|null, next: string|null} */
    private function pagination(string $base, int $page, int $total, bool $queryMode = false): array
    {
        $totalPages = max(1, (int) ceil($total / self::PAGE_SIZE));
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

    private function pageOutOfRange(int $page, int $total): bool
    {
        if ($page <= 1) {
            return false;
        }

        return $page > max(1, (int) ceil($total / self::PAGE_SIZE));
    }
}

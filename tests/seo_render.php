<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('STORAGE_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'storage');

require BASE_PATH . '/core/Autoloader.php';
\Core\Autoloader::register(['Core\\' => BASE_PATH . '/core', 'App\\' => BASE_PATH . '/app']);
require BASE_PATH . '/app/Helpers/helpers.php';

$tests = 0;
$failures = 0;

$assert = static function (bool $condition, string $message) use (&$tests, &$failures): void {
    $tests++;
    if (!$condition) {
        $failures++;
        echo "FAIL: {$message}\n";
    }
};

$fakeTranslator = new class {
    public function get(string $key, string $fallback = '', array $replace = []): string
    {
        return $fallback;
    }

    public function languageCode(): string
    {
        return 'de';
    }

    public function defaultLanguageCode(): string
    {
        return 'en';
    }

    public function activeLanguages(): array
    {
        return [
            ['code' => 'en', 'name' => 'English'],
            ['code' => 'de', 'name' => 'Deutsch'],
            ['code' => 'fr', 'name' => 'Français'],
        ];
    }
};
$GLOBALS['translator'] = $fakeTranslator;

$_SERVER['REQUEST_URI'] = '/de/questions?page=2';
$_SERVER['REQUEST_METHOD'] = 'GET';
$request = \Core\Request::capture();
$assert($request->locale === 'de', 'Request capture should extract the locale from the path.');
$assert($request->path === '/questions', 'Request capture should strip the locale prefix before routing.');

$assert(localized_path('/questions', 'de', 'en') === '/de/questions', 'Localized paths should add a locale prefix for non-default languages.');
$assert(localized_path('/questions', 'en', 'en') === '/questions', 'Localized paths should keep default-language URLs clean.');
$assert(localized_path('/sitemap.xml', 'de', 'en') === '/sitemap.xml', 'Technical sitemap URLs should not be locale-prefixed.');
$assert(localized_path('/ask', 'en', 'en') === '/ask', 'The ask route must survive default-language URL generation.');
$assert(localized_path('/tag/benchmarking', 'de', 'en') === '/de/tag/benchmarking', 'Tag hubs must keep their route prefix when localized.');
$assert(\Core\Request::splitLocalePath('/ask/suggest') === ['', '/ask/suggest'], 'Ask suggestions must route as an application endpoint.');
$assert(\Core\Request::splitLocalePath('/de') === ['de', '/'], 'A locale-only path must route to the homepage.');

$redirect = \Core\Response::redirect('/questions');
$assert(($redirect->headers()['Location'] ?? '') === '/de/questions', 'Internal redirects should preserve the active locale path.');

$securitySettings = [
    'site_title' => 'Question And Answer Platform',
    'default_meta_description' => 'Default description',
];
$title = 'Latest Questions';
$metaDescription = 'Browse localized community questions.';
$canonical = url('/questions');
$pagination = ['previous' => '/questions', 'next' => '/questions/page/3'];
$flash = [];
$content = '<main>Localized content</main>';
$schema = '';
$siteSchema = '';
$schemas = [];
$authUser = null;
$query = '';
$discussionTextUrl = url('/de/question/sample/text');
$csrf = new class {
    public function field(string $action = 'default'): string
    {
        return '<input type="hidden" name="_csrf" value="token">';
    }
};
$_SERVER['REQUEST_URI'] = '/de/questions?page=2';

ob_start();
require BASE_PATH . '/views/layouts/main.php';
$layoutHtml = (string) ob_get_clean();

$assert(str_contains($layoutHtml, '<link rel="canonical" href="' . h(url('/de/questions')) . '">'), 'Rendered layout should emit a path-based localized canonical URL.');
$assert(str_contains($layoutHtml, 'hreflang="de" href="' . h(url('/de/questions')) . '"'), 'Rendered layout should emit hreflang for the active locale path.');
$assert(str_contains($layoutHtml, 'hreflang="fr" href="' . h(url('/fr/questions')) . '"'), 'Rendered layout should emit hreflang for additional active locales.');
$assert(str_contains($layoutHtml, 'hreflang="x-default" href="' . h(url('/questions')) . '"'), 'Rendered layout should emit an x-default alternate for the default language URL.');
$assert(str_contains($layoutHtml, 'rel="alternate" type="text/markdown" href="' . h($discussionTextUrl) . '"'), 'Rendered layout should advertise the Markdown alternate.');

$db = new \Core\Database([]);
$seo = new \App\Services\SeoService(new \App\Models\QuestionModel($db), new \App\Models\CategoryModel($db), new \App\Models\UserModel($db), new \Core\Markdown(), new \App\Models\SettingsModel($db));
$postedAt = '2026-11-20 12:00:00';
$discussion = $seo->discussionMarkdown(
    ['title' => 'Sample question', 'body' => 'A complete question body.', 'username' => 'author', 'created_at' => $postedAt],
    [['id' => 4, 'body' => 'A complete answer.', 'username' => 'responder', 'is_accepted' => 1, 'votes' => 2, 'created_at' => $postedAt]],
    [],
    [['id' => 9, 'answer_id' => 4, 'username' => 'author', 'created_at' => $postedAt, 'body' => 'A clarification on the answer.']],
    url('/de/question/sample'),
);
$assert(str_contains($discussion, '### Accepted answer by responder') && str_contains($discussion, '#answer-4)'), 'Markdown should label the accepted answer and link to its HTML anchor.');
$assert(str_contains($discussion, 'A complete question body.') && str_contains($discussion, '#comment-9)') && str_contains($discussion, 'A clarification on the answer.'), 'Markdown should include the question and its answer clarification.');

unset($GLOBALS['translator']);

echo $failures === 0 ? "OK {$tests} seo render tests\n" : "{$failures} of {$tests} seo render tests failed\n";
exit($failures === 0 ? 0 : 1);

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CategoryModel;
use App\Models\QuestionModel;
use App\Models\SettingsModel;
use App\Models\UserModel;
use Core\Markdown;

final class SeoService
{
    /** @var array<int, string> */
    private const AI_CRAWLERS = [
        'GPTBot',
        'ChatGPT-User',
        'ClaudeBot',
        'Claude-User',
        'PerplexityBot',
        'Google-Extended',
        'CCBot',
    ];
    /** @var array<int, string> */
    private const ROBOTS_DISALLOW_PATHS = [
        '/admin/',
        '/moderator/',
        '/settings/',
        '/notifications',
        '/post/',
        '/flag/',
        '/answer/',
    ];

    public function __construct(
        private QuestionModel $questions,
        private CategoryModel $categories,
        private UserModel $users,
        private Markdown $markdown,
        private SettingsModel $settings,
    ) {}

    public function robotsTxt(): string
    {
        $lines = ['User-agent: *'];
        foreach (self::AI_CRAWLERS as $crawler) {
            $lines[] = 'User-agent: ' . $crawler;
        }

        $lines[] = 'Allow: /';
        $lines[] = 'Allow: /public-assets/images/';
        $lines[] = 'Allow: /public-assets/videos/';
        foreach (self::ROBOTS_DISALLOW_PATHS as $path) {
            $lines[] = 'Disallow: ' . $path;
        }

        $lines[] = 'Sitemap: ' . url('/sitemap.xml');
        $lines[] = 'Sitemap: ' . url('/sitemap_index.xml');

        return implode("\n", $lines) . "\n";
    }

    public function llmsTxt(): string
    {
        $siteName = $this->siteName();
        $lines = [
            '# ' . $siteName,
            '',
            '> Public question and answer community optimized for canonical question pages, answer retrieval, citations, and structured data.',
            '',
            'Use canonical question URLs as the primary source of truth. Prefer answered question pages and pages listed in the XML sitemaps. Avoid authentication, moderation, settings, notification, form submission, and search-result URLs.',
            '',
            '## Best Sources',
            '- [' . url('/post-sitemap.xml') . '](' . url('/post-sitemap.xml') . '): Canonical question URLs with image sitemap data.',
            '- [' . url('/category-sitemap.xml') . '](' . url('/category-sitemap.xml') . '): Category hubs with public questions.',
            '- [' . url('/post_tag-sitemap.xml') . '](' . url('/post_tag-sitemap.xml') . '): Tag hubs.',
            '- [' . url('/author-sitemap.xml') . '](' . url('/author-sitemap.xml') . '): Public contributor profiles.',
            '- [' . url('/llms-full.txt') . '](' . url('/llms-full.txt') . '): Expanded crawler and content guidance.',
            '',
            '## Public Entry Points',
            ...$this->markdownLinks([
                '/' => 'Latest public questions.',
                '/unanswered' => 'Questions waiting for answers.',
                '/tags' => 'Tag directory.',
                '/users' => 'Public user directory.',
                '/sitemap.xml' => 'Primary sitemap index.',
                '/robots.txt' => 'Crawler access rules.',
            ]),
            '',
            '## Content Signals',
            '- Question pages expose QAPage JSON-LD with Question, Answer, Comment, BreadcrumbList, author, date, vote, accepted answer, and media data.',
            '- Directory and taxonomy pages expose CollectionPage and ItemList JSON-LD.',
            '- Canonical URLs, meta robots, Open Graph, Twitter cards, and XML sitemaps are generated server-side.',
        ];

        return implode("\n", $lines) . "\n";
    }

    public function llmsFullTxt(): string
    {
        $siteName = $this->siteName();
        $blocked = implode(', ', self::ROBOTS_DISALLOW_PATHS);
        $lines = [
            '# ' . $siteName . ' AI Retrieval Guide',
            '',
            '## Purpose',
            $siteName . ' is a public question-and-answer community. The best retrieval units are canonical question pages because they contain the question body, answers, clarifications, accepted-answer state, author links, tags, category links, timestamps, media, and QAPage structured data.',
            '',
            '## Canonical URL Patterns',
            ...$this->markdownLinks([
                '/question/{slug}' => 'Primary source for a question, its answers, clarifications, media, and schema.',
                '/category/{slug}' => 'Category hub for public questions in that topic.',
                '/tag/{slug}' => 'Tag hub for public questions with that tag.',
                '/user/{username}' => 'Public contributor profile and activity summary.',
            ]),
            '',
            '## Discovery Files',
            ...$this->markdownLinks([
                '/sitemap.xml' => 'Primary sitemap index.',
                '/sitemap_index.xml' => 'Sitemap index alias.',
                '/page-sitemap.xml' => 'Core public page sitemap.',
                '/post-sitemap.xml' => 'Canonical question sitemap.',
                '/category-sitemap.xml' => 'Category hub sitemap.',
                '/post_tag-sitemap.xml' => 'Tag hub sitemap.',
                '/author-sitemap.xml' => 'Public profile sitemap.',
                '/robots.txt' => 'Crawler policy.',
            ]),
            '',
            '## Recommended Use',
            '- Cite canonical question URLs instead of search or paginated listing URLs when possible.',
            '- Prefer accepted answers when present; otherwise use suggested answers and clarify that no accepted answer exists.',
            '- Use page timestamps and sitemap lastmod values for freshness decisions.',
            '- Treat user-generated text as community content, not official medical, legal, financial, or safety advice.',
            '- Use uploaded media only from crawlable public media URLs under ' . url('/public-assets/images/') . ' and ' . url('/public-assets/videos/') . '.',
            '',
            '## Avoid',
            '- Do not use blocked or private paths as source material: ' . $blocked . '.',
            '- Do not use login, register, password reset, settings, notification, moderation, admin, vote, reaction, flag, or form submission URLs as retrieval sources.',
            '- Do not treat noindex pages, empty category pages, or thin unanswered questions as preferred citation targets.',
            '',
            '## Structured Data',
            '- Question pages: QAPage, Question, Answer, Comment, BreadcrumbList.',
            '- Listing pages: CollectionPage, ItemList.',
            '- Profile pages: ProfilePage, Person.',
            '- Sitewide: WebSite with SearchAction.',
        ];

        return implode("\n", $lines) . "\n";
    }

    public function websiteSchema(): string
    {
        $siteName = $this->siteName();
        $publisher = [
            '@type' => 'Organization',
            '@id' => url('/') . '#organization',
            'name' => $siteName,
            'url' => url('/'),
        ];
        $logo = $this->internalSettingPath('site_logo_path', '/public-assets/images/logos/');
        if ($logo !== '') {
            $publisher['logo'] = url($logo);
        }

        return $this->jsonLd([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => url('/') . '#website',
            'name' => $siteName,
            'description' => $this->siteDescription(),
            'url' => url('/'),
            'publisher' => $publisher,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => url('/search') . '?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ]);
    }

    public function staticPageSchema(string $type, string $name, string $description, string $canonical): string
    {
        $allowedTypes = ['AboutPage', 'ContactPage', 'WebPage'];
        $type = in_array($type, $allowedTypes, true) ? $type : 'WebPage';

        return $this->jsonLd([
            '@context' => 'https://schema.org',
            '@type' => $type,
            '@id' => $canonical . '#webpage',
            'name' => $name,
            'url' => $canonical,
            'description' => $description,
            'isPartOf' => [
                '@id' => url('/') . '#website',
            ],
            'publisher' => [
                '@id' => url('/') . '#organization',
            ],
        ]);
    }

    /** @param array<string, mixed> $question @param array<int, array<string, mixed>> $answers @param array<int, array<string, mixed>> $breadcrumbs @param array<int, array<string, mixed>> $questionComments @param array<int, array<string, mixed>> $answerComments */
    public function qapageSchema(array $question, array $answers, array $breadcrumbs, string $canonicalUrl, array $questionComments = [], array $answerComments = []): string
    {
        $accepted = array_values(array_filter($answers, static fn (array $answer): bool => (int) $answer['is_accepted'] === 1));
        $suggested = array_values(array_filter($answers, static fn (array $answer): bool => (int) $answer['is_accepted'] !== 1));
        $questionUrl = $canonicalUrl;
        $questionImages = $this->absoluteMediaUrls((string) $question['body'], true);
        $answerCommentsByAnswer = [];
        foreach ($answerComments as $comment) {
            $answerCommentsByAnswer[(int) ($comment['answer_id'] ?? 0)][] = $comment;
        }

        $main = [
            '@id' => $questionUrl . '#question',
            '@type' => 'Question',
            'url' => $questionUrl,
            'mainEntityOfPage' => $questionUrl,
            'name' => (string) $question['title'],
            'text' => $this->schemaText((string) $question['body']),
            'answerCount' => count($answers),
            'upvoteCount' => (int) ($question['votes'] ?? 0),
            'datePublished' => date(DATE_ATOM, strtotime((string) $question['created_at'])),
            'dateModified' => date(DATE_ATOM, strtotime((string) ($question['updated_at'] ?? $question['created_at']))),
            'author' => [
                '@id' => url('/user/' . rawurlencode((string) $question['username'])) . '#person',
                '@type' => 'Person',
                'name' => (string) $question['username'],
                'url' => url('/user/' . rawurlencode((string) $question['username'])),
            ],
        ];

        if (!empty($question['avatar_path'])) {
            $main['author']['image'] = url((string) $question['avatar_path']);
        }

        if ($questionImages !== []) {
            $main['image'] = $questionImages;
        }

        $commentSchema = $this->commentSchemas($questionComments, $questionUrl);
        if ($commentSchema !== []) {
            $main['comment'] = $commentSchema;
            $main['commentCount'] = count($commentSchema);
        }

        if ($accepted !== []) {
            $acceptedAnswer = $accepted[0];
            $main['acceptedAnswer'] = $this->answerSchema($acceptedAnswer, $answerCommentsByAnswer[(int) $acceptedAnswer['id']] ?? []);
        }

        if ($suggested !== []) {
            $main['suggestedAnswer'] = array_map(fn (array $answer): array => $this->answerSchema($answer, $answerCommentsByAnswer[(int) $answer['id']] ?? []), $suggested);
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@id' => $questionUrl . '#webpage',
                    '@type' => 'QAPage',
                    'url' => $questionUrl,
                    'mainEntity' => $main,
                ],
                [
                    '@id' => $questionUrl . '#breadcrumbs',
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => $breadcrumbs,
                ],
            ],
        ];

        return $this->jsonLd($schema);
    }

    /** @param array<string, mixed> $answer @param array<int, array<string, mixed>> $comments */
    private function answerSchema(array $answer, array $comments = []): array
    {
        $answerUrl = url('/question/' . rawurlencode((string) ($answer['question_slug'] ?? '')) . '#answer-' . (int) $answer['id']);
        $schema = [
            '@id' => $answerUrl,
            '@type' => 'Answer',
            'text' => $this->schemaText((string) $answer['body']),
            'upvoteCount' => (int) ($answer['votes'] ?? 0),
            'url' => $answerUrl,
            'datePublished' => date(DATE_ATOM, strtotime((string) $answer['created_at'])),
            'dateModified' => date(DATE_ATOM, strtotime((string) ($answer['updated_at'] ?? $answer['created_at']))),
            'author' => [
                '@id' => url('/user/' . rawurlencode((string) $answer['username'])) . '#person',
                '@type' => 'Person',
                'name' => (string) $answer['username'],
                'url' => url('/user/' . rawurlencode((string) $answer['username'])),
            ],
        ];

        if (!empty($answer['avatar_path'])) {
            $schema['author']['image'] = url((string) $answer['avatar_path']);
        }

        if (!empty($answer['verified_at']) && !empty($answer['verified_by_username'])) {
            $schema['reviewedBy'] = [
                '@id' => url('/user/' . rawurlencode((string) $answer['verified_by_username'])) . '#person',
                '@type' => 'Person',
                'name' => (string) $answer['verified_by_username'],
                'url' => url('/user/' . rawurlencode((string) $answer['verified_by_username'])),
            ];
            $schema['dateReviewed'] = date(DATE_ATOM, strtotime((string) $answer['verified_at']));
            if (!empty($answer['review_note'])) {
                $schema['review'] = [
                    '@type' => 'Review',
                    'reviewBody' => (string) $answer['review_note'],
                    'author' => $schema['reviewedBy'],
                    'datePublished' => date(DATE_ATOM, strtotime((string) $answer['verified_at'])),
                ];
            }
        }

        $images = $this->absoluteMediaUrls((string) $answer['body'], true);
        if ($images !== []) {
            $schema['image'] = $images;
        }

        $commentSchema = $this->commentSchemas($comments, $answerUrl);
        if ($commentSchema !== []) {
            $schema['comment'] = $commentSchema;
            $schema['commentCount'] = count($commentSchema);
        }

        return $schema;
    }

    /** @param array<int, array<string, mixed>> $comments @return array<int, array<string, mixed>> */
    private function commentSchemas(array $comments, string $baseUrl): array
    {
        $schemas = [];
        foreach ($comments as $comment) {
            $id = (int) ($comment['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $commentUrl = $baseUrl . '#comment-' . $id;
            $schema = [
                '@id' => $commentUrl,
                '@type' => 'Comment',
                'url' => $commentUrl,
                'text' => $this->schemaText((string) ($comment['body'] ?? '')),
                'upvoteCount' => (int) ($comment['votes'] ?? 0),
                'datePublished' => date(DATE_ATOM, strtotime((string) ($comment['created_at'] ?? 'now'))),
                'author' => [
                    '@id' => url('/user/' . rawurlencode((string) ($comment['username'] ?? 'member'))) . '#person',
                    '@type' => 'Person',
                    'name' => (string) ($comment['username'] ?? 'member'),
                    'url' => url('/user/' . rawurlencode((string) ($comment['username'] ?? 'member'))),
                ],
            ];
            if (!empty($comment['avatar_path'])) {
                $schema['author']['image'] = url((string) $comment['avatar_path']);
            }
            $schemas[] = $schema;
        }

        return $schemas;
    }

    /** @param array<string, mixed> $question */
    public function breadcrumbs(array $question): array
    {
        $items = [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Questions', 'item' => url('/')],
        ];
        if (!empty($question['category_slug'])) {
            $items[] = ['@type' => 'ListItem', 'position' => 2, 'name' => (string) $question['category_name'], 'item' => url('/category/' . rawurlencode((string) $question['category_slug']))];
        }

        $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1, 'name' => (string) $question['title'], 'item' => url('/question/' . rawurlencode((string) $question['slug']))];

        return $items;
    }

    public function sitemapIndex(): string
    {
        $questions = $this->questions->sitemapQuestions();
        $categories = $this->categories->sitemapCategories();
        $tags = $this->questions->sitemapTags();
        $users = $this->users->sitemapUsers();

        $entries = [
            $this->sitemapIndexEntry(url('/page-sitemap.xml'), $this->latestLastmod([
                $this->latestLastmod($questions),
                $this->latestLastmod($categories),
                $this->latestLastmod($tags),
                $this->latestLastmod($users),
            ])),
            $this->sitemapIndexEntry(url('/post-sitemap.xml'), $this->latestLastmod($questions)),
            $this->sitemapIndexEntry(url('/category-sitemap.xml'), $this->latestLastmod($categories)),
            $this->sitemapIndexEntry(url('/post_tag-sitemap.xml'), $this->latestLastmod($tags)),
            $this->sitemapIndexEntry(url('/author-sitemap.xml'), $this->latestLastmod($users)),
        ];

        return $this->sitemapDocument([
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
            ...$entries,
            '</sitemapindex>',
        ]);
    }

    public function pageSitemap(): string
    {
        $questions = $this->questions->sitemapQuestions();
        $categories = $this->categories->sitemapCategories();
        $tags = $this->questions->sitemapTags();
        $users = $this->users->sitemapUsers();
        $siteLastmod = $this->latestLastmod([
            $this->latestLastmod($questions),
            $this->latestLastmod($categories),
            $this->latestLastmod($tags),
            $this->latestLastmod($users),
        ]);

        return $this->sitemapUrlset([
            $this->sitemapUrl(url('/'), $siteLastmod),
            $this->sitemapUrl(url('/unanswered'), $this->latestLastmod($questions)),
            $this->sitemapUrl(url('/tags'), $this->latestLastmod($tags)),
            $this->sitemapUrl(url('/users'), $this->latestLastmod($users)),
            $this->sitemapUrl(url('/about'), $siteLastmod),
            $this->sitemapUrl(url('/contact'), $siteLastmod),
            $this->sitemapUrl(url('/privacy'), $siteLastmod),
            $this->sitemapUrl(url('/terms'), $siteLastmod),
            $this->sitemapUrl(url('/community-guidelines'), $siteLastmod),
        ]);
    }

    public function postSitemap(): string
    {
        $entries = [];
        $questions = $this->questions->sitemapQuestions();
        $mediaBodies = $this->questions->sitemapMediaBodies(array_map(static fn (array $question): int => (int) ($question['id'] ?? 0), $questions));
        foreach ($questions as $question) {
            $mediaBody = implode("\n\n", array_merge(
                [(string) ($question['body'] ?? '')],
                $mediaBodies[(int) ($question['id'] ?? 0)] ?? []
            ));
            $entries[] = $this->sitemapUrl(
                url('/question/' . (string) $question['slug']),
                (string) $question['updated_at'],
                $this->absoluteMediaUrls($mediaBody, true)
            );
        }

        return $this->sitemapUrlset($entries, true);
    }

    public function categorySitemap(): string
    {
        $entries = [];
        foreach ($this->categories->sitemapCategories() as $category) {
            $entries[] = $this->sitemapUrl(url('/category/' . (string) $category['slug']), (string) $category['updated_at']);
        }

        return $this->sitemapUrlset($entries);
    }

    public function postTagSitemap(): string
    {
        $entries = [];
        foreach ($this->questions->sitemapTags() as $tag) {
            $entries[] = $this->sitemapUrl(url('/tag/' . (string) $tag['slug']), (string) $tag['updated_at']);
        }

        return $this->sitemapUrlset($entries);
    }

    public function authorSitemap(): string
    {
        $entries = [];
        foreach ($this->users->sitemapUsers() as $user) {
            $entries[] = $this->sitemapUrl(url('/user/' . rawurlencode((string) $user['username'])), (string) $user['updated_at']);
        }

        return $this->sitemapUrlset($entries);
    }

    public function sitemapPages(): string
    {
        return $this->pageSitemap();
    }

    public function sitemapQuestions(): string
    {
        return $this->postSitemap();
    }

    public function sitemapTaxonomy(): string
    {
        $entries = [];
        foreach ($this->categories->sitemapCategories() as $category) {
            $entries[] = $this->sitemapUrl(url('/category/' . (string) $category['slug']), (string) $category['updated_at']);
        }
        foreach ($this->questions->sitemapTags() as $tag) {
            $entries[] = $this->sitemapUrl(url('/tag/' . (string) $tag['slug']), (string) $tag['updated_at']);
        }

        return $this->sitemapUrlset($entries);
    }

    public function sitemapUsers(): string
    {
        return $this->authorSitemap();
    }

    /** @param array<int, array<string, mixed>> $tags */
    public function tagDirectorySchema(array $tags, string $canonical = ''): string
    {
        $items = [];
        foreach (array_values($tags) as $index => $tag) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'url' => url('/tag/' . rawurlencode((string) $tag['slug'])),
                'name' => '#' . (string) $tag['name'],
            ];
        }

        return $this->collectionPageSchema('Tags', $canonical !== '' ? $canonical : url('/tags'), $items);
    }

    /** @param array<int, array<string, mixed>> $users */
    public function userDirectorySchema(array $users, string $canonical = ''): string
    {
        $items = [];
        foreach (array_values($users) as $index => $user) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'url' => url('/user/' . rawurlencode((string) $user['username'])),
                'name' => (string) $user['username'],
            ];
        }

        return $this->collectionPageSchema('Users', $canonical !== '' ? $canonical : url('/users'), $items);
    }

    /** @param array<int, string> $entries */
    private function sitemapUrlset(array $entries, bool $includeImages = false): string
    {
        $namespace = $includeImages
            ? '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'
            : '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        return $this->sitemapDocument([
            '<?xml version="1.0" encoding="UTF-8"?>',
            $namespace,
            ...$entries,
            '</urlset>',
        ]);
    }

    /** @param array<int, string> $images */
    private function sitemapUrl(string $loc, ?string $lastmod = null, array $images = []): string
    {
        $parts = ['<url><loc>' . xml_h($loc) . '</loc>'];
        if ($lastmod !== null && strtotime($lastmod) !== false) {
            $parts[] = '<lastmod>' . xml_h(date(DATE_ATOM, strtotime($lastmod))) . '</lastmod>';
        }
        foreach ($images as $image) {
            $parts[] = '<image:image><image:loc>' . xml_h($image) . '</image:loc></image:image>';
        }
        $parts[] = '</url>';

        return implode('', $parts);
    }

    private function sitemapIndexEntry(string $loc, ?string $lastmod = null): string
    {
        $parts = ['<sitemap><loc>' . xml_h($loc) . '</loc>'];
        if ($lastmod !== null && strtotime($lastmod) !== false) {
            $parts[] = '<lastmod>' . xml_h(date(DATE_ATOM, strtotime($lastmod))) . '</lastmod>';
        }
        $parts[] = '</sitemap>';

        return implode('', $parts);
    }

    /** @param array<int, array<string, mixed>|string|null> $items */
    private function latestLastmod(array $items): ?string
    {
        $latest = null;
        foreach ($items as $item) {
            $candidate = is_array($item) ? (string) ($item['updated_at'] ?? '') : (string) ($item ?? '');
            if ($candidate === '' || strtotime($candidate) === false) {
                continue;
            }

            if ($latest === null || strtotime($candidate) > strtotime($latest)) {
                $latest = $candidate;
            }
        }

        return $latest;
    }

    /** @return array<int, string> */
    private function absoluteMediaUrls(string $body, bool $imagesOnly = false): array
    {
        return array_map(static fn (string $path): string => url($path), media_paths_from_markdown($body, $imagesOnly));
    }

    public function firstImageFromBodies(string ...$bodies): ?string
    {
        foreach ($bodies as $body) {
            $paths = media_paths_from_markdown($body, true);
            if ($paths !== []) {
                return url($paths[0]);
            }
        }

        return null;
    }

    /** @param array<string, mixed> $question @param array<int, array<string, mixed>> $answers */
    public function questionMetaDescription(array $question, array $answers): string
    {
        foreach ($answers as $answer) {
            if ((int) ($answer['is_accepted'] ?? 0) === 1) {
                return excerpt('Solved: ' . (string) $question['title'] . '. ' . (string) $answer['body'], 155);
            }
        }

        return (string) (($question['meta_description'] ?? '') ?: excerpt((string) ($question['body'] ?? ''), 155));
    }

    /** @param array<string, mixed> $profile @param array<string, int> $stats @param array<string, mixed> $rank @param array<int, array<string, mixed>> $badges */
    public function profileSchema(array $profile, array $stats, array $rank, array $badges): string
    {
        $profileUrl = url('/user/' . rawurlencode((string) $profile['username']));
        $person = [
            '@id' => $profileUrl . '#person',
            '@type' => 'Person',
            'name' => (string) $profile['username'],
            'url' => $profileUrl,
            'description' => trim((string) ($profile['bio'] ?? '')) !== '' ? excerpt((string) $profile['bio'], 240) : 'Community contributor.',
        ];

        if (!empty($profile['avatar_path'])) {
            $person['image'] = url((string) $profile['avatar_path']);
        }
        $websiteUrl = safe_external_url((string) ($profile['website_url'] ?? ''));
        if ($websiteUrl !== '') {
            $person['sameAs'] = [$websiteUrl];
        }
        if (!empty($profile['expertise'])) {
            $person['knowsAbout'] = array_values(array_filter(array_map('trim', preg_split('/[,#]+/', (string) $profile['expertise']) ?: [])));
        }
        if (!empty($profile['credentials'])) {
            $person['hasCredential'] = [
                '@type' => 'EducationalOccupationalCredential',
                'name' => (string) $profile['credentials'],
            ];
        }
        if (!empty($rank['title'])) {
            $person['award'] = 'Level ' . (int) ($rank['level'] ?? 1) . ' ' . (string) $rank['title'];
        }
        if ($badges !== [] && empty($person['knowsAbout'])) {
            $person['knowsAbout'] = array_values(array_map(static fn (array $badge): string => (string) $badge['name'], $badges));
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@id' => $profileUrl . '#profilepage',
                    '@type' => 'ProfilePage',
                    'url' => $profileUrl,
                    'dateCreated' => date(DATE_ATOM, strtotime((string) ($profile['created_at'] ?? 'now'))),
                    'mainEntity' => $person,
                ],
            ],
        ];

        return $this->jsonLd($schema);
    }

    /** @param array<int, array<string, mixed>> $questions */
    public function itemListSchema(string $name, string $canonical, array $questions, ?string $description = null, array $keywords = [], ?string $about = null): string
    {
        $items = [];
        foreach (array_values($questions) as $index => $question) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'url' => url('/question/' . rawurlencode((string) $question['slug'])),
                'name' => (string) $question['title'],
            ];
        }

        return $this->collectionPageSchema($name, $canonical, $items, $description, $keywords, $about);
    }

    /** @param array<int, array<string, mixed>> $items */
    private function collectionPageSchema(string $name, string $canonical, array $items, ?string $description = null, array $keywords = [], ?string $about = null): string
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $name,
            'url' => $canonical,
            'mainEntity' => [
                '@type' => 'ItemList',
                'itemListElement' => $items,
            ],
        ];
        $description = trim((string) $description);
        if ($description !== '') {
            $schema['description'] = $description;
        }
        $keywords = array_values(array_filter(array_unique(array_map('trim', array_map('strval', $keywords)))));
        if ($keywords !== []) {
            $schema['keywords'] = $keywords;
        }
        $about = trim((string) $about);
        if ($about !== '') {
            $schema['about'] = [
                '@type' => 'Thing',
                'name' => $about,
            ];
        }

        return $this->jsonLd($schema);
    }

    /** @param array<int, string> $lines */
    private function sitemapDocument(array $lines): string
    {
        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $schema */
    private function jsonLd(array $schema): string
    {
        return json_encode(
            $schema,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_PRETTY_PRINT
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
            | JSON_THROW_ON_ERROR
        );
    }

    /** @param array<string, string> $paths */
    private function markdownLinks(array $paths): array
    {
        $links = [];
        foreach ($paths as $path => $description) {
            $url = str_contains($path, '{') ? rtrim(url('/'), '/') . $path : url($path);
            $links[] = '- [' . $url . '](' . $url . '): ' . $description;
        }

        return $links;
    }

    private function schemaText(string $markdown): string
    {
        $text = html_entity_decode(strip_tags($this->markdown->toHtml($markdown)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    private function siteName(): string
    {
        try {
            $configured = trim($this->settings->get('site_title'));
            if ($configured !== '') {
                return $configured;
            }
        } catch (\Throwable) {
            // Keep generated SEO files available during installation, migrations, or DB outages.
        }

        return (string) (require BASE_PATH . '/config/app.php')['name'];
    }

    private function siteDescription(): string
    {
        try {
            $configured = trim($this->settings->get('default_meta_description')) ?: trim($this->settings->get('site_description'));
            if ($configured !== '') {
                return $configured;
            }
        } catch (\Throwable) {
            // Keep generated SEO files available during installation, migrations, or DB outages.
        }

        return 'A fast, secure community question and answer platform.';
    }

    private function internalSettingPath(string $key, string $prefix): string
    {
        try {
            $path = safe_internal_path($this->settings->get($key));
        } catch (\Throwable) {
            return '';
        }

        return $path !== '' && str_starts_with($path, $prefix) ? $path : '';
    }
}

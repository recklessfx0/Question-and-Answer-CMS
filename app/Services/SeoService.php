<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CategoryModel;
use App\Models\QuestionModel;
use App\Models\UserModel;
use Core\Markdown;

final readonly class SeoService
{
    private const string SITEMAP_STYLESHEET = '/sitemap.xsl';

    public function __construct(
        private QuestionModel $questions,
        private CategoryModel $categories,
        private UserModel $users,
        private Markdown $markdown,
    ) {}

    /** @param array<string, mixed> $question @param array<int, array<string, mixed>> $answers @param array<int, array<string, mixed>> $breadcrumbs @param array<int, array<string, mixed>> $questionComments @param array<int, array<string, mixed>> $answerComments */
    public function qapageSchema(array $question, array $answers, array $breadcrumbs, array $questionComments = [], array $answerComments = []): string
    {
        $accepted = array_values(array_filter($answers, static fn (array $answer): bool => (int) $answer['is_accepted'] === 1));
        $suggested = array_values(array_filter($answers, static fn (array $answer): bool => (int) $answer['is_accepted'] !== 1));
        $questionUrl = url('/question/' . rawurlencode((string) $question['slug']));
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
            'text' => strip_tags($this->markdown->toHtml((string) $question['body'])),
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
            $main['acceptedAnswer'] = array_map(fn (array $answer): array => $this->answerSchema($answer, $answerCommentsByAnswer[(int) $answer['id']] ?? []), $accepted);
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
            'text' => strip_tags($this->markdown->toHtml((string) $answer['body'])),
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
                'text' => strip_tags($this->markdown->toHtml((string) ($comment['body'] ?? ''))),
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
        ]);
    }

    public function postSitemap(): string
    {
        $entries = [];
        foreach ($this->questions->sitemapQuestions() as $question) {
            $entries[] = $this->sitemapUrl(
                url('/question/' . (string) $question['slug']),
                (string) $question['updated_at'],
                $this->absoluteMediaUrls((string) ($question['body'] ?? ''), true)
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
    public function tagDirectorySchema(array $tags): string
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

        return $this->collectionPageSchema('Tags', url('/tags'), $items);
    }

    /** @param array<int, array<string, mixed>> $users */
    public function userDirectorySchema(array $users): string
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

        return $this->collectionPageSchema('Users', url('/users'), $items);
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
            'description' => trim((string) ($profile['bio'] ?? '')) !== '' ? excerpt((string) $profile['bio'], 240) : 'Community contributor on 420Forum Q&A.',
            'interactionStatistic' => [
                [
                    '@type' => 'InteractionCounter',
                    'interactionType' => ['@type' => 'AskAction'],
                    'userInteractionCount' => (int) ($stats['questions'] ?? 0),
                ],
                [
                    '@type' => 'InteractionCounter',
                    'interactionType' => ['@type' => 'ReplyAction'],
                    'userInteractionCount' => (int) ($stats['answers'] ?? 0),
                ],
                [
                    '@type' => 'InteractionCounter',
                    'interactionType' => ['@type' => 'CommentAction'],
                    'userInteractionCount' => (int) ($stats['comments'] ?? 0),
                ],
            ],
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
    public function itemListSchema(string $name, string $canonical, array $questions): string
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

        return $this->collectionPageSchema($name, $canonical, $items);
    }

    /** @param array<int, array<string, mixed>> $items */
    private function collectionPageSchema(string $name, string $canonical, array $items): string
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

        return $this->jsonLd($schema);
    }

    /** @param array<int, string> $lines */
    private function sitemapDocument(array $lines): string
    {
        array_splice(
            $lines,
            1,
            0,
            ['<?xml-stylesheet type="text/xsl" href="' . xml_h(url(self::SITEMAP_STYLESHEET)) . '"?>']
        );

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
}

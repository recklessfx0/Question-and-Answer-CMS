<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\SeoService;
use Core\Response;

final readonly class SeoController
{
    public function __construct(private SeoService $seo) {}

    public function sitemap(): Response
    {
        return Response::text($this->seo->sitemapIndex(), 'application/xml; charset=utf-8');
    }

    public function sitemapIndex(): Response
    {
        return Response::text($this->seo->sitemapIndex(), 'application/xml; charset=utf-8');
    }

    public function pageSitemap(): Response
    {
        return Response::text($this->seo->pageSitemap(), 'application/xml; charset=utf-8');
    }

    public function postSitemap(): Response
    {
        return Response::text($this->seo->postSitemap(), 'application/xml; charset=utf-8');
    }

    public function categorySitemap(): Response
    {
        return Response::text($this->seo->categorySitemap(), 'application/xml; charset=utf-8');
    }

    public function postTagSitemap(): Response
    {
        return Response::text($this->seo->postTagSitemap(), 'application/xml; charset=utf-8');
    }

    public function authorSitemap(): Response
    {
        return Response::text($this->seo->authorSitemap(), 'application/xml; charset=utf-8');
    }

    public function sitemapPages(): Response
    {
        return Response::text($this->seo->sitemapPages(), 'application/xml; charset=utf-8');
    }

    public function sitemapQuestions(): Response
    {
        return Response::text($this->seo->sitemapQuestions(), 'application/xml; charset=utf-8');
    }

    public function sitemapTaxonomy(): Response
    {
        return Response::text($this->seo->sitemapTaxonomy(), 'application/xml; charset=utf-8');
    }

    public function sitemapUsers(): Response
    {
        return Response::text($this->seo->sitemapUsers(), 'application/xml; charset=utf-8');
    }

    public function robots(): Response
    {
        return Response::text("User-agent: *\nAllow: /\nAllow: /public-assets/images/\nDisallow: /admin/\nDisallow: /moderator/\nDisallow: /settings/\nDisallow: /notifications\nDisallow: /login\nDisallow: /register\nDisallow: /auth/\nDisallow: /search\nDisallow: /post/\nDisallow: /flag/\nDisallow: /answer/\nSitemap: " . url('/sitemap.xml') . "\nSitemap: " . url('/sitemap_index.xml') . "\n", 'text/plain; charset=utf-8');
    }

    public function llms(): Response
    {
        $body = "# 420Forum Q&A\n\n> A lightweight question and answer community with semantic question pages, accepted answers, tags, profiles, uploaded media, and structured data for search and AI retrieval.\n\nUse canonical question URLs as the primary source of truth. Public question pages expose QAPage JSON-LD, clean h1-h3 headings, accepted answers, suggested answers, author profile links, tags, related internal links, and crawlable media URLs. Do not use admin, moderator, authentication, notification, or search result URLs as source material.\n\n## Canonical Public Surfaces\n- [" . url('/') . "](" . url('/') . "): Latest public questions\n- [" . url('/unanswered') . "](" . url('/unanswered') . "): Questions waiting for answers\n- [" . url('/tags') . "](" . url('/tags') . "): Tag directory\n- [" . url('/users') . "](" . url('/users') . "): Public user directory\n- [" . url('/sitemap.xml') . "](" . url('/sitemap.xml') . "): Primary sitemap index\n- [" . url('/sitemap_index.xml') . "](" . url('/sitemap_index.xml') . "): Rank Math style sitemap index alias\n- [" . url('/post-sitemap.xml') . "](" . url('/post-sitemap.xml') . "): Canonical question URLs\n- [" . url('/category-sitemap.xml') . "](" . url('/category-sitemap.xml') . "): Category hub URLs\n- [" . url('/post_tag-sitemap.xml') . "](" . url('/post_tag-sitemap.xml') . "): Tag hub URLs\n- [" . url('/author-sitemap.xml') . "](" . url('/author-sitemap.xml') . "): Public author profiles with indexable content\n\n## Crawl Rules\n- [" . url('/robots.txt') . "](" . url('/robots.txt') . "): Crawler access rules\n- Uploaded public media lives under " . url('/public-assets/images/') . "\n";

        return Response::text($body, 'text/plain; charset=utf-8');
    }
}

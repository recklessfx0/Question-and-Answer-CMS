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
        return Response::text($this->seo->robotsTxt(), 'text/plain; charset=utf-8');
    }

    public function llms(): Response
    {
        return Response::text($this->seo->llmsTxt(), 'text/plain; charset=utf-8');
    }

    public function llmsFull(): Response
    {
        return Response::text($this->seo->llmsFullTxt(), 'text/plain; charset=utf-8');
    }
}

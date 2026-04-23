<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9"
    xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    <xsl:output method="html" encoding="UTF-8" indent="yes"/>

    <xsl:template match="/">
        <html lang="en">
            <head>
                <meta charset="utf-8"/>
                <meta name="viewport" content="width=device-width, initial-scale=1"/>
                <title>XML Sitemap</title>
                <style>
                    :root {
                        color-scheme: light;
                    }
                    * {
                        box-sizing: border-box;
                    }
                    body {
                        margin: 0;
                        font-family: Inter, "Segoe UI", Arial, sans-serif;
                        background: #f7faf8;
                        color: #15352e;
                    }
                    .wrap {
                        max-width: 1120px;
                        margin: 0 auto;
                        padding: 24px 16px 40px;
                    }
                    .hero {
                        border: 1px solid #d6e4dd;
                        background: #ffffff;
                        padding: 20px;
                    }
                    h1 {
                        margin: 0;
                        font-size: clamp(1.65rem, 2.4vw, 2.35rem);
                        line-height: 1.1;
                    }
                    p {
                        margin: 10px 0 0;
                        color: #48625a;
                        line-height: 1.6;
                    }
                    .hero-meta {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 12px;
                        align-items: center;
                        margin-top: 16px;
                        color: #2d4a42;
                        font-size: 0.96rem;
                    }
                    .hero-meta strong {
                        font-size: 1.02rem;
                    }
                    .table-wrap {
                        margin-top: 18px;
                        border: 1px solid #d6e4dd;
                        background: #ffffff;
                        overflow-x: auto;
                    }
                    table {
                        width: 100%;
                        min-width: 640px;
                        border-collapse: collapse;
                    }
                    th, td {
                        padding: 14px 16px;
                        text-align: left;
                        vertical-align: top;
                        border-bottom: 1px solid #e3ece7;
                    }
                    th {
                        font-size: 0.8rem;
                        text-transform: uppercase;
                        color: #587069;
                        background: #f3f7f5;
                    }
                    tr:last-child td {
                        border-bottom: 0;
                    }
                    a {
                        color: #0b7466;
                        text-decoration: none;
                        word-break: break-word;
                    }
                    a:hover {
                        text-decoration: underline;
                    }
                    .meta {
                        color: #5d756d;
                        white-space: nowrap;
                    }
                    .count-chip {
                        display: inline-flex;
                        align-items: center;
                        gap: 8px;
                        padding: 8px 12px;
                        border: 1px solid #d6e4dd;
                        background: #f8fbf9;
                    }
                    .empty {
                        margin-top: 18px;
                        border: 1px solid #d6e4dd;
                        background: #ffffff;
                        padding: 18px 20px;
                        color: #587069;
                    }
                    @media (max-width: 720px) {
                        .wrap {
                            padding: 18px 12px 28px;
                        }
                        .hero,
                        .empty {
                            padding: 16px;
                        }
                        th, td {
                            padding: 12px;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="wrap">
                    <div class="hero">
                        <h1>XML Sitemap</h1>
                        <p>This sitemap helps search engines discover the canonical public URLs on this site. It is organized into focused child sitemaps for pages, questions, categories, tags, and author profiles.</p>
                        <div class="hero-meta">
                            <div class="count-chip">
                                <strong>
                            <xsl:choose>
                                <xsl:when test="count(s:sitemapindex/s:sitemap) &gt; 0">
                                    <xsl:value-of select="count(s:sitemapindex/s:sitemap)"/> sitemap files
                                </xsl:when>
                                <xsl:otherwise>
                                    <xsl:value-of select="count(s:urlset/s:url)"/> URLs
                                </xsl:otherwise>
                            </xsl:choose>
                                </strong>
                            </div>
                            <span>Canonical public URLs only</span>
                        </div>
                    </div>

                    <xsl:choose>
                        <xsl:when test="count(s:sitemapindex/s:sitemap) &gt; 0">
                            <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Sitemap</th>
                                        <th>Last Modified</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <xsl:for-each select="s:sitemapindex/s:sitemap">
                                        <tr>
                                            <td><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></td>
                                            <td class="meta"><xsl:value-of select="s:lastmod"/></td>
                                        </tr>
                                    </xsl:for-each>
                                </tbody>
                            </table>
                            </div>
                        </xsl:when>
                        <xsl:when test="count(s:urlset/s:url) &gt; 0">
                            <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>URL</th>
                                        <th>Last Modified</th>
                                        <th>Images</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <xsl:for-each select="s:urlset/s:url">
                                        <tr>
                                            <td><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></td>
                                            <td class="meta"><xsl:value-of select="s:lastmod"/></td>
                                            <td class="meta"><xsl:value-of select="count(image:image)"/></td>
                                        </tr>
                                    </xsl:for-each>
                                </tbody>
                            </table>
                            </div>
                        </xsl:when>
                        <xsl:otherwise>
                            <div class="empty">No sitemap entries are available yet.</div>
                        </xsl:otherwise>
                    </xsl:choose>
                </div>
            </body>
        </html>
    </xsl:template>
</xsl:stylesheet>

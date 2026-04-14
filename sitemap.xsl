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
                    body { font-family: Georgia, "Times New Roman", serif; background: #f5f1e8; color: #1f2a1f; margin: 0; }
                    .wrap { max-width: 1080px; margin: 0 auto; padding: 32px 20px 48px; }
                    .hero { background: linear-gradient(135deg, #16311d, #2f5a34); color: #f6f3ea; border-radius: 20px; padding: 28px; box-shadow: 0 18px 40px rgba(16, 34, 20, 0.18); }
                    h1 { margin: 0 0 8px; font-size: 2.1rem; }
                    p { line-height: 1.6; }
                    table { width: 100%; border-collapse: collapse; margin-top: 24px; background: #fffdfa; border-radius: 16px; overflow: hidden; box-shadow: 0 12px 28px rgba(34, 45, 34, 0.08); }
                    th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #e5ddd0; vertical-align: top; }
                    th { background: #e8efe5; font-size: 0.92rem; text-transform: uppercase; letter-spacing: 0.04em; }
                    tr:last-child td { border-bottom: 0; }
                    a { color: #0d6b5f; text-decoration: none; }
                    a:hover { text-decoration: underline; }
                    .meta { color: #58655c; font-size: 0.95rem; }
                    .count { margin-top: 10px; font-weight: bold; }
                </style>
            </head>
            <body>
                <div class="wrap">
                    <div class="hero">
                        <h1>XML Sitemap</h1>
                        <p>This sitemap helps search engines discover the canonical public URLs on this site. It is organized into focused child sitemaps for pages, questions, categories, tags, and author profiles.</p>
                        <div class="count">
                            <xsl:choose>
                                <xsl:when test="count(s:sitemapindex/s:sitemap) &gt; 0">
                                    <xsl:value-of select="count(s:sitemapindex/s:sitemap)"/> sitemap files
                                </xsl:when>
                                <xsl:otherwise>
                                    <xsl:value-of select="count(s:urlset/s:url)"/> URLs
                                </xsl:otherwise>
                            </xsl:choose>
                        </div>
                    </div>

                    <xsl:choose>
                        <xsl:when test="count(s:sitemapindex/s:sitemap) &gt; 0">
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
                        </xsl:when>
                        <xsl:otherwise>
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
                        </xsl:otherwise>
                    </xsl:choose>
                </div>
            </body>
        </html>
    </xsl:template>
</xsl:stylesheet>

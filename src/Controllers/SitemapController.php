<?php

declare(strict_types=1);

namespace Pebblestack\Controllers;

use Pebblestack\Core\App;
use Pebblestack\Core\Request;
use Pebblestack\Core\Response;
use Pebblestack\Services\EntryRepository;

final class SitemapController
{
    public function __construct(private readonly App $app) {}

    public function sitemap(Request $request): Response
    {
        $base = $this->siteUrl();
        $repo = new EntryRepository($this->app->db);

        $urls = [];
        $urls[] = ['loc' => $base . '/', 'lastmod' => null];

        if ($this->app->collections->has('posts')) {
            $urls[] = ['loc' => $base . '/blog', 'lastmod' => null];
            foreach ($repo->listPublished('posts', 'updated_at DESC', 1000) as $post) {
                $urls[] = [
                    'loc'     => $base . '/blog/' . rawurlencode($post->slug),
                    'lastmod' => date('c', $post->updatedAt),
                ];
            }
        }
        if ($this->app->collections->has('pages')) {
            foreach ($repo->listPublished('pages', 'updated_at DESC', 1000) as $page) {
                if ($page->slug === 'home') {
                    continue;
                }
                $urls[] = [
                    'loc'     => $base . '/' . rawurlencode($page->slug),
                    'lastmod' => date('c', $page->updatedAt),
                ];
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= "  <url>\n    <loc>" . htmlspecialchars($u['loc'], ENT_XML1) . "</loc>\n";
            if ($u['lastmod'] !== null) {
                $xml .= "    <lastmod>" . htmlspecialchars($u['lastmod'], ENT_XML1) . "</lastmod>\n";
            }
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>' . "\n";

        return (new Response($xml, 200))
            ->setHeader('Content-Type', 'application/xml; charset=utf-8');
    }

    public function robots(Request $request): Response
    {
        $base = $this->siteUrl();
        $body = "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /install\n\nSitemap: {$base}/sitemap.xml\n";
        return (new Response($body, 200))
            ->setHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    private function siteUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host;
    }
}

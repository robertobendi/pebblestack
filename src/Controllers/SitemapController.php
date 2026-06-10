<?php

declare(strict_types=1);

namespace Pebblestack\Controllers;

use Pebblestack\Core\App;
use Pebblestack\Core\Request;
use Pebblestack\Core\Response;
use Pebblestack\Services\EntryRepository;

/**
 * Sitemap derives from each collection's `route` config — the same source
 * the router uses — so custom collections and changed routes are always
 * reflected without touching this file.
 */
final class SitemapController
{
    public function __construct(private readonly App $app) {}

    public function sitemap(Request $request): Response
    {
        $base = $request->baseUrl();
        $repo = new EntryRepository($this->app->db);

        $urls = [];
        $urls[] = ['loc' => $base . '/', 'lastmod' => null];

        foreach ($this->app->collections->all() as $name => $collection) {
            if ($collection->isForm()) {
                continue;
            }
            $route = $collection->publicRoute();
            if ($route === null) {
                continue;
            }
            $listPath = $collection->listPath();
            if ($listPath !== null && $collection->listTemplate() !== null) {
                $urls[] = ['loc' => $base . $listPath, 'lastmod' => null];
            }
            foreach ($repo->listPublished($name, 'updated_at DESC', 1000) as $entry) {
                // The "home" page renders at / — already listed above.
                if ($name === 'pages' && $entry->slug === 'home') {
                    continue;
                }
                $loc = (string) preg_replace(
                    '#\{[a-zA-Z_][a-zA-Z0-9_]*\}#',
                    rawurlencode($entry->slug),
                    $route
                );
                $urls[] = ['loc' => $base . $loc, 'lastmod' => date('c', $entry->updatedAt)];
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
        $base = $request->baseUrl();
        $body = "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /install\n\nSitemap: {$base}/sitemap.xml\n";
        return (new Response($body, 200))
            ->setHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}

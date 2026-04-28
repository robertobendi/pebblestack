<?php

declare(strict_types=1);

namespace Pebblestack\Controllers;

use Pebblestack\Core\App;
use Pebblestack\Core\Request;
use Pebblestack\Core\Response;
use Pebblestack\Services\EntryRepository;

final class PublicController
{
    private EntryRepository $repo;

    public function __construct(private readonly App $app)
    {
        $this->repo = new EntryRepository($app->db);
    }

    public function home(Request $request): Response
    {
        // If a Page has slug "home", render it as the homepage. Otherwise
        // fall back to the theme's home.twig with a list of recent posts.
        $home = $this->repo->findBySlug('pages', 'home');
        if ($home !== null && $home->isPublished()) {
            return $this->renderEntry($home, 'pages');
        }
        $recentPosts = $this->repo->listPublished('posts', 'publish_at DESC', 10);
        $body = $this->app->view->render('@theme/home.twig', $this->context([
            'recent_posts' => $recentPosts,
        ]));
        return Response::html($body);
    }

    public function blogIndex(Request $request): Response
    {
        $collection = $this->app->collections->get('posts');
        if ($collection === null) {
            return Response::notFound();
        }
        $posts = $this->repo->listPublished('posts', $collection->orderBy(), 100);
        $template = $collection->listTemplate() ?? 'post-list.twig';
        if (!$this->app->view->exists('@theme/' . $template)) {
            $template = 'post-list.twig';
        }
        $body = $this->app->view->render('@theme/' . $template, $this->context([
            'collection' => $collection,
            'posts'      => $posts,
        ]));
        return Response::html($body);
    }

    public function show(Request $request, string $collectionName): Response
    {
        $collection = $this->app->collections->get($collectionName);
        if ($collection === null) {
            return $this->renderNotFound();
        }
        $slug = (string) $request->param('slug', '');
        if ($slug === '') {
            return $this->renderNotFound();
        }
        $entry = $this->repo->findBySlug($collectionName, $slug);
        if ($entry === null || !$entry->isPublished()) {
            return $this->renderNotFound();
        }
        return $this->renderEntry($entry, $collectionName);
    }

    private function renderEntry(\Pebblestack\Models\Entry $entry, string $collectionName): Response
    {
        $collection = $this->app->collections->get($collectionName);
        $template = $collection?->template() ?? 'page.twig';
        if (!$this->app->view->exists('@theme/' . $template)) {
            $template = 'page.twig';
        }
        $body = $this->app->view->render('@theme/' . $template, $this->context([
            'entry'      => $entry,
            'collection' => $collection,
        ]));
        return Response::html($body);
    }

    private function renderNotFound(): Response
    {
        if ($this->app->view->exists('@theme/404.twig')) {
            $body = $this->app->view->render('@theme/404.twig', $this->context([]));
            return Response::html($body, 404);
        }
        return Response::notFound();
    }

    /**
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function context(array $extra): array
    {
        $row = $this->app->db->fetchOne("SELECT value FROM settings WHERE key = 'site_name'");
        $siteName = $row !== null ? (string) $row['value'] : 'Pebblestack';
        return array_merge([
            'site' => [
                'name' => $siteName,
                'url'  => $this->siteUrl(),
            ],
            'nav' => $this->buildNav(),
        ], $extra);
    }

    /**
     * @return list<array{label:string,url:string}>
     */
    private function buildNav(): array
    {
        $items = [];
        if ($this->app->collections->has('posts')) {
            $items[] = ['label' => 'Blog', 'url' => '/blog'];
        }
        // Promote up to 5 most recently updated published pages with route /{slug} into nav,
        // skipping "home" which is the front page.
        $pages = $this->repo->listPublished('pages', 'updated_at DESC', 20);
        foreach ($pages as $p) {
            if ($p->slug === 'home') {
                continue;
            }
            $titleField = $this->app->collections->get('pages')?->titleField() ?? 'title';
            $items[] = [
                'label' => (string) $p->field($titleField, ucfirst(str_replace('-', ' ', $p->slug))),
                'url'   => '/' . $p->slug,
            ];
            if (count($items) >= 6) {
                break;
            }
        }
        return $items;
    }

    private function siteUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host;
    }
}

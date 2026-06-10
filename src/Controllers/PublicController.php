<?php

declare(strict_types=1);

namespace Pebblestack\Controllers;

use Pebblestack\Core\App;
use Pebblestack\Core\Request;
use Pebblestack\Core\Response;
use Pebblestack\Services\EntryRepository;
use Pebblestack\Services\MetricsService;

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
            return $this->renderEntry($request, $home, 'pages');
        }
        $recentPosts = $this->repo->listPublished('posts', 'publish_at DESC', 10);
        $lastMod = $this->collectionListLastModified($recentPosts);
        return $this->cachedHtml($request, $lastMod, fn () => $this->app->view->render('@theme/home.twig', $this->context($request, [
            'recent_posts' => $recentPosts,
        ])));
    }

    public function listCollection(Request $request, string $collectionName): Response
    {
        $collection = $this->app->collections->get($collectionName);
        if ($collection === null || $collection->isForm() || $collection->listTemplate() === null) {
            return $this->renderNotFound($request);
        }
        $entries = $this->repo->listPublished($collectionName, $collection->orderBy(), $collection->listLimit() ?? 100);
        $template = $collection->listTemplate();
        if (!$this->app->view->exists('@theme/' . $template)) {
            return $this->renderNotFound($request);
        }
        return $this->cachedHtml($request, $this->collectionListLastModified($entries), function () use ($request, $template, $collection, $entries) {
            return $this->app->view->render('@theme/' . $template, $this->context($request, [
                'collection' => $collection,
                'entries'    => $entries,
                // Legacy alias: the shipped post-list.twig still iterates `posts`.
                'posts'      => $entries,
            ]));
        });
    }

    public function show(Request $request, string $collectionName): Response
    {
        $collection = $this->app->collections->get($collectionName);
        if ($collection === null || $collection->isForm()) {
            return $this->renderNotFound($request);
        }
        $slug = (string) $request->param('slug', '');
        if ($slug === '') {
            return $this->renderNotFound($request);
        }
        $entry = $this->repo->findBySlug($collectionName, $slug);
        if ($entry === null || !$entry->isPublished()) {
            return $this->renderNotFound($request);
        }
        return $this->renderEntry($request, $entry, $collectionName);
    }

    private function track(Request $request): void
    {
        // Best-effort — never let metrics break the page render.
        try {
            (new MetricsService($this->app->db))->recordView($request->path());
        } catch (\Throwable) {
            // intentionally swallowed
        }
    }

    private function renderEntry(Request $request, \Pebblestack\Models\Entry $entry, string $collectionName): Response
    {
        $collection = $this->app->collections->get($collectionName);
        $template = $collection?->template() ?? 'page.twig';
        if (!$this->app->view->exists('@theme/' . $template)) {
            $template = 'page.twig';
        }
        return $this->cachedHtml($request, $entry->updatedAt, fn () => $this->app->view->render('@theme/' . $template, $this->context($request, [
            'entry'      => $entry,
            'collection' => $collection,
        ])));
    }

    private function renderNotFound(Request $request): Response
    {
        if ($this->app->view->exists('@theme/404.twig')) {
            $body = $this->app->view->render('@theme/404.twig', $this->context($request, []));
            return Response::html($body, 404);
        }
        return Response::notFound();
    }

    /**
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function context(Request $request, array $extra): array
    {
        return [
            'site' => [
                'name' => $this->app->settings->siteName(),
                'url'  => $request->baseUrl(),
            ],
        ] + $extra;
    }

    /**
     * Render-or-304. If the client sent If-Modified-Since >= $lastMod, skip
     * the render and return 304. Otherwise build the body, attach a
     * Last-Modified header, and let the response stream as usual. The metric
     * tracking still fires on a 304 so admins see real visitor activity.
     *
     * @param \Closure(): string $build
     */
    private function cachedHtml(Request $request, ?int $lastMod, \Closure $build): Response
    {
        $this->track($request);
        if ($lastMod !== null) {
            $ifSince = $request->header('if-modified-since');
            if ($ifSince !== null) {
                $clientTs = strtotime($ifSince);
                if ($clientTs !== false && $clientTs >= $lastMod) {
                    $resp = (new Response('', 304))
                        ->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $lastMod) . ' GMT');
                    return $resp;
                }
            }
        }
        $resp = Response::html($build());
        if ($lastMod !== null) {
            $resp->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $lastMod) . ' GMT');
        }
        return $resp;
    }

    /**
     * @param list<\Pebblestack\Models\Entry> $entries
     */
    private function collectionListLastModified(array $entries): ?int
    {
        $max = null;
        foreach ($entries as $e) {
            if ($max === null || $e->updatedAt > $max) {
                $max = $e->updatedAt;
            }
        }
        return $max;
    }
}

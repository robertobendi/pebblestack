<?php

declare(strict_types=1);

namespace Pebblestack\Core;

use Pebblestack\Controllers\Admin\AuthController;
use Pebblestack\Controllers\Admin\DashboardController;
use Pebblestack\Controllers\Admin\EntryController;
use Pebblestack\Controllers\Admin\MediaController;
use Pebblestack\Controllers\Admin\MetricsController;
use Pebblestack\Controllers\Admin\RevisionController;
use Pebblestack\Controllers\Admin\SettingsController;
use Pebblestack\Controllers\Admin\SubmissionController;
use Pebblestack\Controllers\Admin\UserController;
use Pebblestack\Controllers\FormController;
use Pebblestack\Controllers\InstallController;
use Pebblestack\Controllers\PublicController;
use Pebblestack\Controllers\SitemapController;
use Pebblestack\Services\CollectionRegistry;
use Pebblestack\Services\EntryRepository;
use Pebblestack\Services\Installer;
use Pebblestack\Services\Migrator;
use Twig\TwigFunction;

/**
 * Builds the dependency graph and dispatches the request. Single instance
 * per request; nothing in here is process-global.
 */
final class App
{
    public readonly Config $config;
    public readonly Database $db;
    public readonly Session $session;
    public readonly Csrf $csrf;
    public readonly Auth $auth;
    public readonly View $view;
    public readonly CollectionRegistry $collections;
    public readonly Installer $installer;
    public readonly Router $router;

    public function __construct(public readonly string $rootDir)
    {
        $this->config = (new Config($rootDir . '/config'))->load('app');

        $sqlitePath = $rootDir . '/data/pebblestack.sqlite';
        $this->db = new Database($sqlitePath);
        $this->session = new Session();
        $this->csrf = new Csrf($this->session);
        $this->auth = new Auth($this->db, $this->session);

        $this->view = new View(
            adminPath: $rootDir . '/templates/admin',
            themePath: $rootDir . '/templates/theme/' . ($this->config->get('app.theme') ?? 'default'),
            csrf: $this->csrf,
            auth: $this->auth,
            session: $this->session,
            debug: (bool) $this->config->get('app.debug', false),
            cacheDir: $rootDir . '/data/cache/twig',
        );

        $this->installer = new Installer($rootDir, $this->db);

        // Collections only loaded once installed — config file may not exist yet.
        $collectionsFile = $rootDir . '/config/collections.php';
        $collectionsConfig = is_file($collectionsFile) ? require $collectionsFile : [];
        $this->collections = new CollectionRegistry(is_array($collectionsConfig) ? $collectionsConfig : []);

        $this->view->env()->addFunction(new TwigFunction('nav', fn () => $this->buildNav()));

        $this->router = $this->buildRouter();
    }

    /**
     * Built lazily by the nav() Twig function so themes that hardcode their
     * own navigation don't pay the cost of querying pages on every request.
     *
     * @return list<array{label:string,url:string}>
     */
    private function buildNav(): array
    {
        $items = [];
        if ($this->collections->has('posts')) {
            $items[] = ['label' => 'Blog', 'url' => '/blog'];
        }
        if (!$this->collections->has('pages')) {
            return $items;
        }
        $repo = new EntryRepository($this->db);
        $pages = $repo->listPublished('pages', 'updated_at DESC', 20);
        $titleField = $this->collections->get('pages')?->titleField() ?? 'title';
        foreach ($pages as $p) {
            if ($p->slug === 'home') {
                continue;
            }
            $items[] = [
                'label' => (string) $p->field($titleField, ucfirst(str_replace('-', ' ', $p->slug))),
                'url'   => '/' . $p->slug,
            ];
            if (count($items) >= 5) {
                break;
            }
        }
        return $items;
    }

    public function handle(Request $request): Response
    {
        try {
            // Canonicalize trailing slashes on GET so /blog and /blog/ don't
            // both render 200 — duplicate URLs hurt SEO and cache hit rate.
            if ($request->method() === 'GET') {
                $rawUri = (string) ($request->server['REQUEST_URI'] ?? '/');
                $rawPath = parse_url($rawUri, PHP_URL_PATH) ?: '/';
                if ($rawPath !== '/' && str_ends_with($rawPath, '/')) {
                    $canonical = rtrim($rawPath, '/');
                    $query = parse_url($rawUri, PHP_URL_QUERY);
                    if (is_string($query) && $query !== '') {
                        $canonical .= '?' . $query;
                    }
                    return Response::redirect($canonical, 301);
                }
            }
            // Until installed, every request goes to the installer.
            if (!$this->installer->isInstalled() && !str_starts_with($request->path(), '/install')) {
                return Response::redirect('/install');
            }
            // Apply any new migrations on boot. Idempotent; ~1ms when up-to-date.
            // This is how shared-hosted instances pick up upgrades — the user
            // overwrites files, the next request migrates the DB.
            if ($this->installer->isInstalled()) {
                (new Migrator($this->db, $this->rootDir . '/data/migrations'))->run();
            }
            return $this->router->dispatch($request);
        } catch (CsrfException $e) {
            return Response::html(
                '<!doctype html><meta charset="utf-8"><title>403 — Session expired</title>' .
                '<style>body{font:15px system-ui;margin:4rem auto;max-width:480px;padding:0 1rem;color:#1c1917;text-align:center}' .
                'a{color:#2563eb}</style>' .
                '<h1>403 — Session expired</h1>' .
                '<p>Your session expired or the form was opened in another tab. Refresh the page and try again.</p>',
                403
            );
        } catch (\Throwable $e) {
            return $this->renderError($e);
        }
    }

    private function buildRouter(): Router
    {
        $r = new Router();

        // Install — only reachable when not yet installed (controller checks).
        $install = new InstallController($this);
        $r->get('/install', fn ($req) => $install->show($req));
        $r->post('/install', fn ($req) => $install->submit($req));

        // Admin auth.
        $authCtrl = new AuthController($this);
        $r->get('/admin/login', fn ($req) => $authCtrl->showLogin($req));
        $r->post('/admin/login', fn ($req) => $authCtrl->login($req));
        $r->post('/admin/logout', fn ($req) => $authCtrl->logout($req));

        // Admin dashboard + entries.
        $dash = new DashboardController($this);
        $r->get('/admin', fn ($req) => $dash->index($req));

        $settings = new SettingsController($this);
        $r->get('/admin/settings', fn ($req) => $settings->show($req));
        $r->post('/admin/settings/site', fn ($req) => $settings->updateSite($req));
        $r->post('/admin/settings/password', fn ($req) => $settings->updatePassword($req));

        // User management (admin-only — guarded inside controller).
        $users = new UserController($this);
        $r->get('/admin/users', fn ($req) => $users->index($req));
        $r->get('/admin/users/new', fn ($req) => $users->create($req));
        $r->post('/admin/users/new', fn ($req) => $users->store($req));
        $r->get('/admin/users/{id}', fn ($req) => $users->edit($req));
        $r->post('/admin/users/{id}', fn ($req) => $users->update($req));
        $r->post('/admin/users/{id}/password', fn ($req) => $users->resetPassword($req));
        $r->post('/admin/users/{id}/delete', fn ($req) => $users->destroy($req));

        $metrics = new MetricsController($this);
        $r->get('/admin/metrics', fn ($req) => $metrics->index($req));

        $media = new MediaController($this);
        $r->get('/admin/media', fn ($req) => $media->index($req));
        $r->post('/admin/media', fn ($req) => $media->upload($req));
        $r->get('/admin/media/{id}', fn ($req) => $media->edit($req));
        $r->post('/admin/media/{id}', fn ($req) => $media->update($req));
        $r->post('/admin/media/{id}/delete', fn ($req) => $media->destroy($req));

        // Form-collection submissions (admin views).
        $subs = new SubmissionController($this);
        $r->get('/admin/forms/{collection}', fn ($req) => $subs->index($req));
        $r->get('/admin/forms/{collection}/{id}', fn ($req) => $subs->show($req));
        $r->post('/admin/forms/{collection}/{id}/delete', fn ($req) => $subs->destroy($req));

        // Public form submission endpoint.
        $form = new FormController($this);
        $r->post('/forms/{collection}', fn ($req) => $form->submit($req));

        $entries = new EntryController($this);
        $r->get('/admin/collections/{collection}', fn ($req) => $entries->index($req));
        $r->get('/admin/collections/{collection}/new', fn ($req) => $entries->create($req));
        $r->post('/admin/collections/{collection}/new', fn ($req) => $entries->store($req));

        // Revisions — registered before the {id} catch-all routes so the
        // literal "revisions" segment wins.
        $revs = new RevisionController($this);
        $r->get('/admin/collections/{collection}/{id}/revisions/{rid}', fn ($req) => $revs->show($req));
        $r->post('/admin/collections/{collection}/{id}/revisions/{rid}/restore', fn ($req) => $revs->restore($req));

        $r->get('/admin/collections/{collection}/{id}', fn ($req) => $entries->edit($req));
        $r->post('/admin/collections/{collection}/{id}', fn ($req) => $entries->update($req));
        $r->post('/admin/collections/{collection}/{id}/delete', fn ($req) => $entries->destroy($req));

        // SEO endpoints — registered before the catch-all so they always win.
        $sitemap = new SitemapController($this);
        $r->get('/sitemap.xml', fn ($req) => $sitemap->sitemap($req));
        $r->get('/robots.txt', fn ($req) => $sitemap->robots($req));

        // Public site. Routes derive from each collection's `route` config.
        // Sort by literal-segment depth descending so /{slug} (depth 0) is
        // registered last and doesn't swallow more specific routes.
        $public = new PublicController($this);
        $r->get('/', fn ($req) => $public->home($req));

        $publicCollections = [];
        foreach ($this->collections->all() as $name => $collection) {
            if ($collection->isForm()) {
                continue;
            }
            $route = $collection->publicRoute();
            if ($route === null) {
                continue;
            }
            $publicCollections[] = ['name' => $name, 'collection' => $collection, 'route' => $route];
        }
        usort(
            $publicCollections,
            fn ($a, $b) => self::routeDepth($b['route']) <=> self::routeDepth($a['route'])
        );

        foreach ($publicCollections as $pc) {
            $name = $pc['name'];
            $route = $pc['route'];
            $listPath = self::listPathFromRoute($route);
            if ($listPath !== null && $pc['collection']->listTemplate() !== null) {
                $r->get($listPath, fn ($req) => $public->listCollection($req, $name));
            }
            $r->get($route, fn ($req) => $public->show($req, $name));
        }

        return $r;
    }

    private static function routeDepth(string $route): int
    {
        $literal = 0;
        foreach (explode('/', trim($route, '/')) as $seg) {
            if ($seg === '' || str_starts_with($seg, '{')) {
                continue;
            }
            $literal++;
        }
        return $literal;
    }

    private static function listPathFromRoute(string $route): ?string
    {
        // Strip the trailing /{placeholder} segment to get the list path.
        // /blog/{slug}    -> /blog
        // /projects/{slug} -> /projects
        // /{slug}         -> null (the catch-all has no usable list path)
        if (!preg_match('#^(.*)/\{[a-zA-Z_][a-zA-Z0-9_]*\}$#', $route, $m)) {
            return null;
        }
        $prefix = $m[1];
        return $prefix === '' ? null : $prefix;
    }

    private function renderError(\Throwable $e): Response
    {
        $debug = (bool) $this->config->get('app.debug', false);
        if ($debug) {
            $body = '<!doctype html><title>Error</title><pre>' .
                htmlspecialchars($e::class . ': ' . $e->getMessage() . "\n\n" . $e->getTraceAsString()) .
                '</pre>';
            return Response::html($body, 500);
        }
        return Response::html('<!doctype html><title>Server error</title><h1>Something went wrong.</h1>', 500);
    }
}

<?php

declare(strict_types=1);

namespace Pebblestack\Core;

use Pebblestack\Controllers\Admin\AuthController;
use Pebblestack\Controllers\Admin\DashboardController;
use Pebblestack\Controllers\Admin\EntryController;
use Pebblestack\Controllers\InstallController;
use Pebblestack\Controllers\PublicController;
use Pebblestack\Services\CollectionRegistry;
use Pebblestack\Services\Installer;

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
        );

        $this->installer = new Installer($rootDir, $this->db);

        // Collections only loaded once installed — config file may not exist yet.
        $collectionsFile = $rootDir . '/config/collections.php';
        $collectionsConfig = is_file($collectionsFile) ? require $collectionsFile : [];
        $this->collections = new CollectionRegistry(is_array($collectionsConfig) ? $collectionsConfig : []);

        $this->router = $this->buildRouter();
    }

    public function handle(Request $request): Response
    {
        try {
            // Until installed, every request goes to the installer.
            if (!$this->installer->isInstalled() && !str_starts_with($request->path(), '/install')) {
                return Response::redirect('/install');
            }
            return $this->router->dispatch($request);
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

        $entries = new EntryController($this);
        $r->get('/admin/collections/{collection}', fn ($req) => $entries->index($req));
        $r->get('/admin/collections/{collection}/new', fn ($req) => $entries->create($req));
        $r->post('/admin/collections/{collection}/new', fn ($req) => $entries->store($req));
        $r->get('/admin/collections/{collection}/{id}', fn ($req) => $entries->edit($req));
        $r->post('/admin/collections/{collection}/{id}', fn ($req) => $entries->update($req));
        $r->post('/admin/collections/{collection}/{id}/delete', fn ($req) => $entries->destroy($req));

        // Public site (catch-all goes last).
        $public = new PublicController($this);
        $r->get('/', fn ($req) => $public->home($req));
        $r->get('/blog', fn ($req) => $public->blogIndex($req));
        $r->get('/blog/{slug}', fn ($req) => $public->show($req, 'posts'));
        $r->get('/{slug}', fn ($req) => $public->show($req, 'pages'));

        return $r;
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

<?php

declare(strict_types=1);

namespace Pebblestack\Controllers;

use Pebblestack\Core\App;
use Pebblestack\Core\Request;
use Pebblestack\Core\Response;

final class InstallController
{
    public function __construct(private readonly App $app) {}

    public function show(Request $request): Response
    {
        if ($this->app->installer->isInstalled()) {
            return Response::redirect('/admin');
        }
        $checks = $this->preflight();
        $body = $this->app->view->render('@admin/install.twig', [
            'checks' => $checks,
            'ready'  => array_reduce($checks, fn ($carry, $c) => $carry && $c['ok'], true),
            'errors' => [],
            'old'    => ['email' => '', 'name' => '', 'site_name' => ''],
        ]);
        return Response::html($body);
    }

    public function submit(Request $request): Response
    {
        if ($this->app->installer->isInstalled()) {
            return Response::redirect('/admin');
        }
        // CSRF doesn't apply pre-install (no session yet).
        $email     = trim((string) $request->input('email', ''));
        $password  = (string) $request->input('password', '');
        $password2 = (string) $request->input('password_confirm', '');
        $name      = trim((string) $request->input('name', ''));
        $siteName  = trim((string) $request->input('site_name', ''));

        $errors = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $password2) {
            $errors[] = 'Passwords do not match.';
        }
        if ($name === '') {
            $errors[] = 'Your name is required.';
        }
        if ($siteName === '') {
            $errors[] = 'Site name is required.';
        }

        $checks = $this->preflight();
        $ready = array_reduce($checks, fn ($carry, $c) => $carry && $c['ok'], true);
        if (!$ready) {
            $errors[] = 'Some preflight checks failed — fix them and try again.';
        }

        if ($errors !== []) {
            $body = $this->app->view->render('@admin/install.twig', [
                'checks' => $checks,
                'ready'  => $ready,
                'errors' => $errors,
                'old'    => ['email' => $email, 'name' => $name, 'site_name' => $siteName],
            ]);
            return Response::html($body, 422);
        }

        $this->app->installer->install($email, $password, $name, $siteName);
        return Response::redirect('/admin/login');
    }

    /**
     * @return list<array{label:string,ok:bool,detail:string}>
     */
    private function preflight(): array
    {
        $checks = [];
        $checks[] = [
            'label'  => 'PHP 8.2 or newer',
            'ok'     => version_compare(PHP_VERSION, '8.2.0', '>='),
            'detail' => 'Detected ' . PHP_VERSION,
        ];
        $checks[] = [
            'label'  => 'pdo_sqlite extension',
            'ok'     => extension_loaded('pdo_sqlite'),
            'detail' => extension_loaded('pdo_sqlite') ? 'Loaded' : 'Missing — enable pdo_sqlite in PHP.',
        ];
        $checks[] = [
            'label'  => 'mbstring extension',
            'ok'     => extension_loaded('mbstring'),
            'detail' => extension_loaded('mbstring') ? 'Loaded' : 'Missing — enable mbstring in PHP.',
        ];
        $dataDir = $this->app->rootDir . '/data';
        $checks[] = [
            'label'  => 'data/ writable',
            'ok'     => (is_dir($dataDir) && is_writable($dataDir)) || (!is_dir($dataDir) && is_writable(dirname($dataDir))),
            'detail' => 'Pebblestack stores its SQLite database here.',
        ];
        $uploadsDir = $this->app->rootDir . '/uploads';
        $checks[] = [
            'label'  => 'uploads/ writable',
            'ok'     => (is_dir($uploadsDir) && is_writable($uploadsDir)) || (!is_dir($uploadsDir) && is_writable(dirname($uploadsDir))),
            'detail' => 'Media uploads are stored here.',
        ];
        return $checks;
    }
}

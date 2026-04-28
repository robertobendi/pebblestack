<?php

declare(strict_types=1);

namespace Pebblestack\Controllers\Admin;

use Pebblestack\Core\App;
use Pebblestack\Core\Request;
use Pebblestack\Core\Response;
use Pebblestack\Services\EntryRepository;

final class DashboardController
{
    public function __construct(private readonly App $app) {}

    public function index(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/admin/login');
        }
        $repo = new EntryRepository($this->app->db);

        $stats = [];
        foreach ($this->app->collections->list() as $collection) {
            $stats[] = [
                'collection' => $collection,
                'count'      => $repo->countByCollection($collection->name),
                'recent'     => $repo->listByCollection($collection->name, $collection->orderBy(), 5),
            ];
        }

        $body = $this->app->view->render('@admin/dashboard.twig', [
            'stats'       => $stats,
            'collections' => $this->app->collections->list(),
            'site_name'   => $this->siteName(),
        ]);
        return Response::html($body);
    }

    private function siteName(): string
    {
        $row = $this->app->db->fetchOne("SELECT value FROM settings WHERE key = 'site_name'");
        return $row !== null ? (string) $row['value'] : 'Pebblestack';
    }
}

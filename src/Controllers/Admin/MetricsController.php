<?php

declare(strict_types=1);

namespace Pebblestack\Controllers\Admin;

use Pebblestack\Core\App;
use Pebblestack\Core\Request;
use Pebblestack\Core\Response;
use Pebblestack\Services\MetricsService;

final class MetricsController
{
    public function __construct(private readonly App $app) {}

    public function index(Request $request): Response
    {
        if ($block = $this->app->auth->guard('viewer')) return $block;

        $svc = new MetricsService($this->app->db);
        $body = $this->app->view->render('@admin/metrics.twig', [
            'total_30d'   => $svc->totalViews(30),
            'total_7d'    => $svc->totalViews(7),
            'top_paths'   => $svc->topPaths(30, 10),
            'daily'       => $svc->dailyTotals(30),
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

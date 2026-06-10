<?php

declare(strict_types=1);

namespace Pebblestack\Controllers\Admin;

use Pebblestack\Core\Request;
use Pebblestack\Core\Response;
use Pebblestack\Services\MetricsService;

final class MetricsController extends AdminController
{
    public function index(Request $request): Response
    {
        if ($block = $this->guard($request)) return $block;

        $svc = new MetricsService($this->app->db);
        return $this->render('@admin/metrics.twig', [
            'total_30d' => $svc->totalViews(30),
            'total_7d'  => $svc->totalViews(7),
            'top_paths' => $svc->topPaths(30, 10),
            'daily'     => $svc->dailyTotals(30),
        ]);
    }
}

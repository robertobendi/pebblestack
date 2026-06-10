<?php

declare(strict_types=1);

namespace Pebblestack\Controllers\Admin;

use Pebblestack\Core\Request;
use Pebblestack\Core\Response;
use Pebblestack\Services\EntryRepository;
use Pebblestack\Services\FormSubmissionRepository;

final class DashboardController extends AdminController
{
    public function index(Request $request): Response
    {
        if ($block = $this->guard($request)) return $block;
        $repo = new EntryRepository($this->app->db);
        $forms = new FormSubmissionRepository($this->app->db);

        $stats = [];
        foreach ($this->app->collections->list() as $collection) {
            if ($collection->isForm()) {
                $stats[] = [
                    'collection' => $collection,
                    'count'      => $forms->countByCollection($collection->name),
                    'recent'     => [],
                    'is_form'    => true,
                ];
                continue;
            }
            $stats[] = [
                'collection' => $collection,
                'count'      => $repo->countByCollection($collection->name),
                'recent'     => $repo->listByCollection($collection->name, $collection->orderBy(), 5),
                'is_form'    => false,
            ];
        }

        return $this->render('@admin/dashboard.twig', ['stats' => $stats]);
    }
}

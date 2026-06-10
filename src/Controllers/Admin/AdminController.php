<?php

declare(strict_types=1);

namespace Pebblestack\Controllers\Admin;

use Pebblestack\Core\App;
use Pebblestack\Core\Request;
use Pebblestack\Core\Response;

/**
 * Base for all admin controllers: role guarding and rendering with the
 * context every admin template needs (sidebar collections, site name).
 */
abstract class AdminController
{
    public function __construct(protected readonly App $app) {}

    /**
     * Role-gate the current request: GET (read) requires $readRole, anything
     * else (write) requires $writeRole. Returns a redirect/403 Response to
     * short-circuit with, or null when the user may proceed:
     *
     *   if ($block = $this->guard($request)) return $block;
     */
    protected function guard(Request $request, string $readRole = 'viewer', string $writeRole = 'editor'): ?Response
    {
        $reads = in_array($request->method(), ['GET', 'HEAD'], true);
        return $this->app->auth->guard($reads ? $readRole : $writeRole);
    }

    /** @param array<string,mixed> $context */
    protected function render(string $template, array $context = [], int $status = 200): Response
    {
        $body = $this->app->view->render($template, $context + [
            'collections' => $this->app->collections->list(),
            'site_name'   => $this->app->settings->siteName(),
        ]);
        return Response::html($body, $status);
    }
}

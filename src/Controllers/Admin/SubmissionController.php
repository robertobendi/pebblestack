<?php

declare(strict_types=1);

namespace Pebblestack\Controllers\Admin;

use Pebblestack\Core\App;
use Pebblestack\Core\Request;
use Pebblestack\Core\Response;
use Pebblestack\Services\FormSubmissionRepository;

final class SubmissionController
{
    private FormSubmissionRepository $repo;

    public function __construct(private readonly App $app)
    {
        $this->repo = new FormSubmissionRepository($app->db);
    }

    public function index(Request $request): Response
    {
        if ($block = $this->guardForCurrentMethod()) return $block;
        $collection = $this->app->collections->get((string) $request->param('collection', ''));
        if ($collection === null || !$collection->isForm()) {
            return Response::notFound('Form not found');
        }
        $body = $this->app->view->render('@admin/forms/index.twig', [
            'collection'  => $collection,
            'submissions' => $this->repo->listByCollection($collection->name),
            'collections' => $this->app->collections->list(),
            'site_name'   => $this->siteName(),
        ]);
        return Response::html($body);
    }

    public function show(Request $request): Response
    {
        if ($block = $this->guardForCurrentMethod()) return $block;
        $collection = $this->app->collections->get((string) $request->param('collection', ''));
        if ($collection === null || !$collection->isForm()) {
            return Response::notFound('Form not found');
        }
        $submission = $this->repo->find((int) $request->param('id'));
        if ($submission === null || $submission->collection !== $collection->name) {
            return Response::notFound('Submission not found');
        }
        $body = $this->app->view->render('@admin/forms/show.twig', [
            'collection'  => $collection,
            'submission'  => $submission,
            'collections' => $this->app->collections->list(),
            'site_name'   => $this->siteName(),
        ]);
        return Response::html($body);
    }

    public function destroy(Request $request): Response
    {
        if ($block = $this->guardForCurrentMethod()) return $block;
        $this->app->csrf->check($request);
        $collection = $this->app->collections->get((string) $request->param('collection', ''));
        if ($collection === null || !$collection->isForm()) {
            return Response::notFound('Form not found');
        }
        $submission = $this->repo->find((int) $request->param('id'));
        if ($submission !== null && $submission->collection === $collection->name) {
            $this->repo->delete($submission->id);
            $this->app->session->flash('success', 'Submission deleted.');
        }
        return Response::redirect('/admin/forms/' . $collection->name);
    }

    private function siteName(): string
    {
        $row = $this->app->db->fetchOne("SELECT value FROM settings WHERE key = 'site_name'");
        return $row !== null ? (string) $row['value'] : 'Pebblestack';
    }

    private function guardForCurrentMethod(): ?Response
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        return $this->app->auth->guard($method === 'GET' ? 'viewer' : 'editor');
    }
}

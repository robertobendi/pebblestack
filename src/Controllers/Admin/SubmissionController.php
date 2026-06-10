<?php

declare(strict_types=1);

namespace Pebblestack\Controllers\Admin;

use Pebblestack\Core\App;
use Pebblestack\Core\Request;
use Pebblestack\Core\Response;
use Pebblestack\Services\Collection;
use Pebblestack\Services\FormSubmissionRepository;

final class SubmissionController extends AdminController
{
    private FormSubmissionRepository $repo;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->repo = new FormSubmissionRepository($app->db);
    }

    public function index(Request $request): Response
    {
        if ($block = $this->guard($request)) return $block;
        $collection = $this->formCollection($request);
        if ($collection === null) {
            return Response::notFound('Form not found');
        }
        return $this->render('@admin/forms/index.twig', [
            'collection'  => $collection,
            'submissions' => $this->repo->listByCollection($collection->name),
        ]);
    }

    public function show(Request $request): Response
    {
        if ($block = $this->guard($request)) return $block;
        $collection = $this->formCollection($request);
        if ($collection === null) {
            return Response::notFound('Form not found');
        }
        $submission = $this->repo->find((int) $request->param('id'));
        if ($submission === null || $submission->collection !== $collection->name) {
            return Response::notFound('Submission not found');
        }
        return $this->render('@admin/forms/show.twig', [
            'collection' => $collection,
            'submission' => $submission,
        ]);
    }

    public function destroy(Request $request): Response
    {
        if ($block = $this->guard($request)) return $block;
        $this->app->csrf->check($request);
        $collection = $this->formCollection($request);
        if ($collection === null) {
            return Response::notFound('Form not found');
        }
        $submission = $this->repo->find((int) $request->param('id'));
        if ($submission !== null && $submission->collection === $collection->name) {
            $this->repo->delete($submission->id);
            $this->app->session->flash('success', 'Submission deleted.');
        }
        return Response::redirect('/admin/forms/' . $collection->name);
    }

    private function formCollection(Request $request): ?Collection
    {
        $collection = $this->app->collections->get((string) $request->param('collection', ''));
        return ($collection === null || !$collection->isForm()) ? null : $collection;
    }
}

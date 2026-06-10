<?php

declare(strict_types=1);

namespace Pebblestack\Controllers\Admin;

use Pebblestack\Core\App;
use Pebblestack\Core\Request;
use Pebblestack\Core\Response;
use Pebblestack\Services\MediaService;

final class MediaController extends AdminController
{
    private MediaService $media;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->media = new MediaService($app->db, $app->rootDir);
    }

    public function index(Request $request): Response
    {
        if ($block = $this->guard($request)) return $block;
        return $this->renderIndex([]);
    }

    public function upload(Request $request): Response
    {
        // Uploads are writes — viewers are read-only.
        if ($block = $this->guard($request)) return $block;
        $this->app->csrf->check($request);

        $file = $request->files['file'] ?? null;
        if (!is_array($file)) {
            return $this->renderIndex(['No file selected.']);
        }
        [$media, $errors] = $this->media->store($file, $this->app->auth->user()?->id);
        if ($media === null) {
            return $this->renderIndex($errors);
        }
        $this->app->session->flash('success', 'Uploaded ' . $media->originalName . '.');
        return Response::redirect('/admin/media');
    }

    public function edit(Request $request): Response
    {
        if ($block = $this->guard($request)) return $block;
        $media = $this->media->find((int) $request->param('id'));
        if ($media === null) {
            return Response::notFound('Media not found');
        }
        return $this->render('@admin/media/edit.twig', [
            'media'  => $media,
            'errors' => [],
        ]);
    }

    public function update(Request $request): Response
    {
        if ($block = $this->guard($request)) return $block;
        $this->app->csrf->check($request);
        $media = $this->media->find((int) $request->param('id'));
        if ($media === null) {
            return Response::notFound('Media not found');
        }
        $alt = trim((string) $request->input('alt', ''));
        $this->media->updateAlt($media->id, $alt === '' ? null : $alt);
        $this->app->session->flash('success', 'Saved.');
        return Response::redirect('/admin/media/' . $media->id);
    }

    public function destroy(Request $request): Response
    {
        if ($block = $this->guard($request)) return $block;
        $this->app->csrf->check($request);
        $this->media->delete((int) $request->param('id'));
        $this->app->session->flash('success', 'Media deleted.');
        return Response::redirect('/admin/media');
    }

    /** @param list<string> $errors */
    private function renderIndex(array $errors): Response
    {
        return $this->render('@admin/media/index.twig', [
            'items'       => $this->media->listAll(),
            'errors'      => $errors,
            'max_mb'      => MediaService::MAX_BYTES / 1024 / 1024,
            'allowed_ext' => MediaService::ALLOWED_EXT,
        ], $errors === [] ? 200 : 422);
    }
}

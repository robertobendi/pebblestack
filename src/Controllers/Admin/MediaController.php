<?php

declare(strict_types=1);

namespace Pebblestack\Controllers\Admin;

use Pebblestack\Core\App;
use Pebblestack\Core\Request;
use Pebblestack\Core\Response;
use Pebblestack\Services\MediaService;

final class MediaController
{
    private MediaService $media;

    public function __construct(private readonly App $app)
    {
        $this->media = new MediaService($app->db, $app->rootDir);
    }

    public function index(Request $request): Response
    {
        if ($block = $this->guardForCurrentMethod()) return $block;
        return $this->renderIndex([]);
    }

    public function upload(Request $request): Response
    {
        $user = $this->app->auth->user();
        if ($user === null) {
            return Response::redirect('/admin/login');
        }
        $this->app->csrf->check($request);

        $file = $request->files['file'] ?? null;
        if (!is_array($file)) {
            return $this->renderIndex(['No file selected.']);
        }
        [$media, $errors] = $this->media->store($file, $user->id);
        if ($media === null) {
            return $this->renderIndex($errors);
        }
        $this->app->session->flash('success', 'Uploaded ' . $media->originalName . '.');
        return Response::redirect('/admin/media');
    }

    public function edit(Request $request): Response
    {
        if ($block = $this->guardForCurrentMethod()) return $block;
        $media = $this->media->find((int) $request->param('id'));
        if ($media === null) {
            return Response::notFound('Media not found');
        }
        $body = $this->app->view->render('@admin/media/edit.twig', [
            'media'       => $media,
            'collections' => $this->app->collections->list(),
            'site_name'   => $this->siteName(),
            'errors'      => [],
        ]);
        return Response::html($body);
    }

    public function update(Request $request): Response
    {
        if ($block = $this->guardForCurrentMethod()) return $block;
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
        if ($block = $this->guardForCurrentMethod()) return $block;
        $this->app->csrf->check($request);
        $id = (int) $request->param('id');
        $this->media->delete($id);
        $this->app->session->flash('success', 'Media deleted.');
        return Response::redirect('/admin/media');
    }

    /** @param list<string> $errors */
    private function renderIndex(array $errors): Response
    {
        $body = $this->app->view->render('@admin/media/index.twig', [
            'items'       => $this->media->listAll(),
            'errors'      => $errors,
            'max_mb'      => MediaService::MAX_BYTES / 1024 / 1024,
            'allowed_ext' => MediaService::ALLOWED_EXT,
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

    private function guardForCurrentMethod(): ?Response
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        return $this->app->auth->guard($method === 'GET' ? 'viewer' : 'editor');
    }
}

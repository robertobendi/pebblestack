<?php

declare(strict_types=1);

namespace Pebblestack\Controllers\Admin;

use Pebblestack\Core\App;
use Pebblestack\Core\Request;
use Pebblestack\Core\Response;
use Pebblestack\Services\EntryRepository;
use Pebblestack\Services\EntryRevisionRepository;

final class RevisionController
{
    private EntryRepository $entries;
    private EntryRevisionRepository $revisions;

    public function __construct(private readonly App $app)
    {
        $this->entries = new EntryRepository($app->db);
        $this->revisions = new EntryRevisionRepository($app->db);
    }

    public function show(Request $request): Response
    {
        if ($block = $this->app->auth->guard('viewer')) return $block;
        $collection = $this->app->collections->get((string) $request->param('collection', ''));
        if ($collection === null || $collection->isForm()) {
            return Response::notFound('Collection not found');
        }
        $entry = $this->entries->find((int) $request->param('id'));
        $revision = $this->revisions->find((int) $request->param('rid'));
        if ($entry === null || $revision === null || $revision->entryId !== $entry->id) {
            return Response::notFound('Revision not found');
        }
        $body = $this->app->view->render('@admin/entries/revision.twig', [
            'collection'  => $collection,
            'entry'       => $entry,
            'revision'    => $revision,
            'collections' => $this->app->collections->list(),
            'site_name'   => $this->siteName(),
        ]);
        return Response::html($body);
    }

    public function restore(Request $request): Response
    {
        if ($block = $this->app->auth->guard('editor')) return $block;
        $this->app->csrf->check($request);
        $collection = $this->app->collections->get((string) $request->param('collection', ''));
        if ($collection === null || $collection->isForm()) {
            return Response::notFound('Collection not found');
        }
        $entry = $this->entries->find((int) $request->param('id'));
        $revision = $this->revisions->find((int) $request->param('rid'));
        if ($entry === null || $revision === null || $revision->entryId !== $entry->id) {
            return Response::notFound('Revision not found');
        }
        // Snapshot the current state, then overwrite with the revision's payload.
        $this->revisions->snapshot($entry, $this->app->auth->user()?->id);
        $this->entries->update(
            $entry->id,
            $revision->slug,
            $revision->status,
            $revision->data,
            $revision->publishAt,
        );
        $this->app->session->flash('success', 'Restored revision from ' . date('M j, Y H:i', $revision->createdAt) . '.');
        return Response::redirect('/admin/collections/' . $collection->name . '/' . $entry->id);
    }

    private function siteName(): string
    {
        $row = $this->app->db->fetchOne("SELECT value FROM settings WHERE key = 'site_name'");
        return $row !== null ? (string) $row['value'] : 'Pebblestack';
    }
}

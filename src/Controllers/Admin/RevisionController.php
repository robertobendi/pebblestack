<?php

declare(strict_types=1);

namespace Pebblestack\Controllers\Admin;

use Pebblestack\Core\App;
use Pebblestack\Core\Request;
use Pebblestack\Core\Response;
use Pebblestack\Services\EntryRepository;
use Pebblestack\Services\EntryRevisionRepository;

final class RevisionController extends AdminController
{
    private EntryRepository $entries;
    private EntryRevisionRepository $revisions;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->entries = new EntryRepository($app->db);
        $this->revisions = new EntryRevisionRepository($app->db);
    }

    public function show(Request $request): Response
    {
        if ($block = $this->guard($request)) return $block;
        $collection = $this->app->collections->get((string) $request->param('collection', ''));
        if ($collection === null || $collection->isForm()) {
            return Response::notFound('Collection not found');
        }
        $entry = $this->entries->find((int) $request->param('id'));
        $revision = $this->revisions->find((int) $request->param('rid'));
        if ($entry === null || $revision === null || $revision->entryId !== $entry->id) {
            return Response::notFound('Revision not found');
        }
        return $this->render('@admin/entries/revision.twig', [
            'collection' => $collection,
            'entry'      => $entry,
            'revision'   => $revision,
        ]);
    }

    public function restore(Request $request): Response
    {
        if ($block = $this->guard($request)) return $block;
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
        // Snapshot the current state, then overwrite with the revision's
        // payload — atomically, so a failure can't lose the live entry.
        $userId = $this->app->auth->user()?->id;
        $this->app->db->transaction(function () use ($entry, $revision, $userId): void {
            $this->revisions->snapshot($entry, $userId);
            $this->entries->update(
                $entry->id,
                $revision->slug,
                $revision->status,
                $revision->data,
                $revision->publishAt,
            );
        });
        $this->app->session->flash('success', 'Restored revision from ' . date('M j, Y H:i', $revision->createdAt) . '.');
        return Response::redirect('/admin/collections/' . $collection->name . '/' . $entry->id);
    }
}

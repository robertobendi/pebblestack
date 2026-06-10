<?php

declare(strict_types=1);

namespace Pebblestack\Controllers\Admin;

use Pebblestack\Core\App;
use Pebblestack\Core\Request;
use Pebblestack\Core\Response;
use Pebblestack\Services\Collection;
use Pebblestack\Services\EntryRepository;
use Pebblestack\Services\EntryRevisionRepository;
use Pebblestack\Services\Field;

final class EntryController extends AdminController
{
    private EntryRepository $repo;
    private EntryRevisionRepository $revisions;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->repo = new EntryRepository($app->db);
        $this->revisions = new EntryRevisionRepository($app->db);
    }

    public function index(Request $request): Response
    {
        if ($block = $this->guard($request)) return $block;
        $collection = $this->collection($request);
        if ($collection === null) {
            return Response::notFound('Unknown collection');
        }
        $query = trim((string) $request->input('q', ''));
        if ($query !== '') {
            $entries = $this->repo->search(
                $collection->name,
                $collection->titleField(),
                $query,
                $collection->orderBy()
            );
        } else {
            $entries = $this->repo->listByCollection($collection->name, $collection->orderBy(), 200);
        }
        return $this->render('@admin/entries/index.twig', [
            'collection' => $collection,
            'entries'    => $entries,
            'query'      => $query,
        ]);
    }

    public function create(Request $request): Response
    {
        if ($block = $this->guard($request)) return $block;
        $collection = $this->collection($request);
        if ($collection === null) {
            return Response::notFound('Unknown collection');
        }
        $values = [];
        foreach ($collection->fields() as $key => $field) {
            $values[$key] = $field->default();
        }
        return $this->renderForm($collection, null, $values, 'draft', null, []);
    }

    public function store(Request $request): Response
    {
        if ($block = $this->guard($request)) return $block;
        $this->app->csrf->check($request);
        $collection = $this->collection($request);
        if ($collection === null) {
            return Response::notFound('Unknown collection');
        }
        [$values, $errors, $status, $publishAt] = $this->collectInput($request, $collection);

        $slugField = $collection->slugField();
        $slug = (string) ($values[$slugField] ?? '');
        if ($slug === '' && isset($values[$collection->titleField()])) {
            $slug = Field::slugify((string) $values[$collection->titleField()]);
            $values[$slugField] = $slug;
        }
        if ($slug !== '' && $this->repo->slugTaken($collection->name, $slug)) {
            $errors[] = 'A ' . strtolower($collection->labelSingular()) . ' with that slug already exists.';
        }

        if ($errors !== []) {
            return $this->renderForm($collection, null, $values, $status, $publishAt, $errors);
        }

        $this->repo->create($collection->name, $slug, $status, $values, $publishAt);
        $this->app->session->flash('success', $collection->labelSingular() . ' created.');
        return Response::redirect('/admin/collections/' . $collection->name);
    }

    public function edit(Request $request): Response
    {
        if ($block = $this->guard($request)) return $block;
        $collection = $this->collection($request);
        if ($collection === null) {
            return Response::notFound('Unknown collection');
        }
        $entry = $this->repo->find((int) $request->param('id'));
        if ($entry === null || $entry->collection !== $collection->name) {
            return Response::notFound('Entry not found');
        }
        $values = $entry->data;
        $values[$collection->slugField()] = $entry->slug;
        return $this->renderForm($collection, $entry->id, $values, $entry->status, $entry->publishAt, []);
    }

    public function update(Request $request): Response
    {
        if ($block = $this->guard($request)) return $block;
        $this->app->csrf->check($request);
        $collection = $this->collection($request);
        if ($collection === null) {
            return Response::notFound('Unknown collection');
        }
        $entry = $this->repo->find((int) $request->param('id'));
        if ($entry === null || $entry->collection !== $collection->name) {
            return Response::notFound('Entry not found');
        }

        [$values, $errors, $status, $publishAt] = $this->collectInput($request, $collection);

        $slugField = $collection->slugField();
        $slug = (string) ($values[$slugField] ?? '');
        if ($slug === '') {
            $slug = $entry->slug;
            $values[$slugField] = $slug;
        }
        if ($this->repo->slugTaken($collection->name, $slug, $entry->id)) {
            $errors[] = 'A ' . strtolower($collection->labelSingular()) . ' with that slug already exists.';
        }

        if ($errors !== []) {
            return $this->renderForm($collection, $entry->id, $values, $status, $publishAt, $errors);
        }

        // Snapshot the prior state and overwrite atomically, so a failure
        // mid-save can't leave the entry changed without a revision (or
        // a revision without the change).
        $userId = $this->app->auth->user()?->id;
        $this->app->db->transaction(function () use ($entry, $slug, $status, $values, $publishAt, $userId): void {
            $this->revisions->snapshot($entry, $userId);
            $this->repo->update($entry->id, $slug, $status, $values, $publishAt);
        });
        $this->app->session->flash('success', $collection->labelSingular() . ' saved.');
        return Response::redirect('/admin/collections/' . $collection->name . '/' . $entry->id);
    }

    public function destroy(Request $request): Response
    {
        if ($block = $this->guard($request)) return $block;
        $this->app->csrf->check($request);
        $collection = $this->collection($request);
        if ($collection === null) {
            return Response::notFound('Unknown collection');
        }
        $entry = $this->repo->find((int) $request->param('id'));
        if ($entry === null || $entry->collection !== $collection->name) {
            return Response::notFound('Entry not found');
        }
        $this->repo->delete($entry->id);
        $this->app->session->flash('success', $collection->labelSingular() . ' deleted.');
        return Response::redirect('/admin/collections/' . $collection->name);
    }

    /**
     * @param array<string,mixed> $values
     * @param list<string> $errors
     */
    private function renderForm(
        Collection $collection,
        ?int $entryId,
        array $values,
        string $status,
        ?int $publishAt,
        array $errors,
    ): Response {
        $revisions = $entryId !== null ? $this->revisions->listForEntry($entryId) : [];
        return $this->render('@admin/entries/form.twig', [
            'collection' => $collection,
            'entry_id'   => $entryId,
            'values'     => $values,
            'status'     => $status,
            'publish_at' => $publishAt,
            'errors'     => $errors,
            'revisions'  => $revisions,
        ], $errors === [] ? 200 : 422);
    }

    /**
     * @return array{0:array<string,mixed>,1:list<string>,2:string,3:?int}
     */
    private function collectInput(Request $request, Collection $collection): array
    {
        $values = [];
        foreach ($collection->fields() as $key => $field) {
            $raw = $request->post[$key] ?? null;
            // Unchecked checkboxes don't post — treat as false for booleans.
            if ($field->type() === 'boolean' && $raw === null) {
                $raw = false;
            }
            $values[$key] = $field->coerce($raw);
        }

        // Auto-fill slug from title before validation so a missing slug
        // is recovered from the title rather than reported as an error.
        $slugField = $collection->slugField();
        $titleField = $collection->titleField();
        if (
            isset($values[$slugField], $values[$titleField])
            && $values[$slugField] === ''
            && (string) $values[$titleField] !== ''
        ) {
            $values[$slugField] = Field::slugify((string) $values[$titleField]);
        }

        $errors = [];
        foreach ($collection->fields() as $key => $field) {
            $errors = array_merge($errors, $field->validate($values[$key] ?? null));
        }
        $status = (string) ($request->post['_status'] ?? 'draft');
        if (!in_array($status, ['draft', 'published'], true)) {
            $status = 'draft';
        }
        $publishRaw = trim((string) ($request->post['_publish_at'] ?? ''));
        $publishAt = null;
        if ($publishRaw !== '') {
            $ts = strtotime($publishRaw);
            if ($ts === false) {
                $errors[] = 'Publish date is not a valid timestamp.';
            } else {
                $publishAt = $ts;
            }
        }
        return [$values, $errors, $status, $publishAt];
    }

    private function collection(Request $request): ?Collection
    {
        return $this->app->collections->get((string) $request->param('collection', ''));
    }
}

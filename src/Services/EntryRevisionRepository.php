<?php

declare(strict_types=1);

namespace Pebblestack\Services;

use Pebblestack\Core\Database;
use Pebblestack\Models\Entry;
use Pebblestack\Models\EntryRevision;

/**
 * Snapshots prior versions of entries on every update. Capped at 50 per entry
 * so a long-lived post doesn't accumulate megabytes of history.
 */
final class EntryRevisionRepository
{
    public const MAX_PER_ENTRY = 50;

    public function __construct(private readonly Database $db) {}

    /** Snapshot the state of an entry as it exists *now*, before it gets overwritten. */
    public function snapshot(Entry $entry, ?int $editedBy): void
    {
        $this->db->run(
            'INSERT INTO entry_revisions (entry_id, collection, slug, status, data, publish_at, edited_by, created_at)
             VALUES (:e, :c, :s, :st, :d, :p, :u, :ts)',
            [
                'e'  => $entry->id,
                'c'  => $entry->collection,
                's'  => $entry->slug,
                'st' => $entry->status,
                'd'  => json_encode($entry->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'p'  => $entry->publishAt,
                'u'  => $editedBy,
                'ts' => time(),
            ]
        );
        $this->prune($entry->id);
    }

    /** @return list<EntryRevision> */
    public function listForEntry(int $entryId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM entry_revisions WHERE entry_id = :id ORDER BY created_at DESC, id DESC',
            ['id' => $entryId]
        );
        return array_map(fn ($r) => EntryRevision::fromRow($r), $rows);
    }

    public function find(int $id): ?EntryRevision
    {
        $row = $this->db->fetchOne('SELECT * FROM entry_revisions WHERE id = :id', ['id' => $id]);
        return $row === null ? null : EntryRevision::fromRow($row);
    }

    private function prune(int $entryId): void
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS n FROM entry_revisions WHERE entry_id = :id',
            ['id' => $entryId]
        );
        $count = (int) ($row['n'] ?? 0);
        if ($count <= self::MAX_PER_ENTRY) {
            return;
        }
        $excess = $count - self::MAX_PER_ENTRY;
        $this->db->run(
            'DELETE FROM entry_revisions WHERE id IN (
                SELECT id FROM entry_revisions WHERE entry_id = :id ORDER BY created_at ASC, id ASC LIMIT :n
            )',
            ['id' => $entryId, 'n' => $excess]
        );
    }
}

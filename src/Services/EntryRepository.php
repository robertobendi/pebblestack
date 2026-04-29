<?php

declare(strict_types=1);

namespace Pebblestack\Services;

use Pebblestack\Core\Database;
use Pebblestack\Models\Entry;

final class EntryRepository
{
    public function __construct(private readonly Database $db) {}

    public function find(int $id): ?Entry
    {
        $row = $this->db->fetchOne('SELECT * FROM entries WHERE id = :id', ['id' => $id]);
        return $row === null ? null : Entry::fromRow($row);
    }

    public function findBySlug(string $collection, string $slug): ?Entry
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM entries WHERE collection = :c AND slug = :s',
            ['c' => $collection, 's' => $slug]
        );
        return $row === null ? null : Entry::fromRow($row);
    }

    /**
     * @return list<Entry>
     */
    public function listByCollection(string $collection, string $orderBy = 'updated_at DESC', int $limit = 100, int $offset = 0): array
    {
        $orderBy = $this->safeOrderBy($orderBy);
        $rows = $this->db->fetchAll(
            "SELECT * FROM entries WHERE collection = :c ORDER BY {$orderBy} LIMIT :l OFFSET :o",
            ['c' => $collection, 'l' => $limit, 'o' => $offset]
        );
        return array_map(fn ($r) => Entry::fromRow($r), $rows);
    }

    /**
     * Public list — only published, publish_at <= now (or null).
     * @return list<Entry>
     */
    public function listPublished(string $collection, string $orderBy = 'created_at DESC', int $limit = 100, int $offset = 0): array
    {
        $orderBy = $this->safeOrderBy($orderBy);
        $rows = $this->db->fetchAll(
            "SELECT * FROM entries
             WHERE collection = :c
               AND status = 'published'
               AND (publish_at IS NULL OR publish_at <= :now)
             ORDER BY {$orderBy} LIMIT :l OFFSET :o",
            ['c' => $collection, 'now' => time(), 'l' => $limit, 'o' => $offset]
        );
        return array_map(fn ($r) => Entry::fromRow($r), $rows);
    }

    public function countByCollection(string $collection): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS n FROM entries WHERE collection = :c', ['c' => $collection]);
        return (int) ($row['n'] ?? 0);
    }

    /**
     * Title-substring search inside the entry data JSON. SQLite's LIKE on
     * the whole JSON blob is fast enough for the hundreds-of-entries scale
     * Pebblestack targets; full-text indexing would be over-engineering.
     *
     * @return list<\Pebblestack\Models\Entry>
     */
    public function search(string $collection, string $titleField, string $query, string $orderBy = 'updated_at DESC', int $limit = 100): array
    {
        $orderBy = $this->safeOrderBy($orderBy);
        // Match the title field's value within the JSON. We look for "fieldname":"...query..."
        // which is precise enough to avoid false hits on other fields' values.
        // The query body is also JSON-quote-escaped so a search for a phrase
        // containing " matches the actual stored value (json_encode emits \").
        $needle = '%"' . self::likePatternForJson($titleField) . '":%' .
                  self::likePatternForJson($query) . '%';
        $rows = $this->db->fetchAll(
            "SELECT * FROM entries
             WHERE collection = :c AND data LIKE :q ESCAPE '\\'
             ORDER BY {$orderBy} LIMIT :l",
            ['c' => $collection, 'q' => $needle, 'l' => $limit]
        );
        return array_map(fn ($r) => \Pebblestack\Models\Entry::fromRow($r), $rows);
    }

    /** @param array<string,mixed> $data */
    public function create(string $collection, string $slug, string $status, array $data, ?int $publishAt): int
    {
        $now = time();
        $this->db->run(
            'INSERT INTO entries (collection, slug, status, data, publish_at, created_at, updated_at)
             VALUES (:c, :s, :st, :d, :p, :ca, :ua)',
            [
                'c' => $collection,
                's' => $slug,
                'st' => $status,
                'd' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'p' => $publishAt,
                'ca' => $now,
                'ua' => $now,
            ]
        );
        return $this->db->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, string $slug, string $status, array $data, ?int $publishAt): void
    {
        $this->db->run(
            'UPDATE entries
             SET slug = :s, status = :st, data = :d, publish_at = :p, updated_at = :ua
             WHERE id = :id',
            [
                'id' => $id,
                's' => $slug,
                'st' => $status,
                'd' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'p' => $publishAt,
                'ua' => time(),
            ]
        );
    }

    public function delete(int $id): void
    {
        $this->db->run('DELETE FROM entries WHERE id = :id', ['id' => $id]);
    }

    public function slugTaken(string $collection, string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId === null) {
            $row = $this->db->fetchOne(
                'SELECT id FROM entries WHERE collection = :c AND slug = :s',
                ['c' => $collection, 's' => $slug]
            );
        } else {
            $row = $this->db->fetchOne(
                'SELECT id FROM entries WHERE collection = :c AND slug = :s AND id <> :id',
                ['c' => $collection, 's' => $slug, 'id' => $excludeId]
            );
        }
        return $row !== null;
    }

    /**
     * Translate a user search string into a LIKE pattern that matches the
     * value as it lives inside the JSON-encoded `data` column.
     *
     * Two escape passes happen here:
     *   1. JSON form. json_encode writes " as \" and \ as \\, so the user's
     *      " has to become \" before matching, and \ has to become \\.
     *   2. LIKE form. With ESCAPE '\\', every \ in the pattern doubles to \\,
     *      % becomes \%, _ becomes \_.
     */
    private static function likePatternForJson(string $value): string
    {
        // Step 1 — JSON-encode just the parts that get escaped: \ and ".
        $value = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
        // Step 2 — LIKE-escape over the result. The doubled backslashes from
        // step 1 each get doubled again so SQLite treats them as literal \.
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function safeOrderBy(string $orderBy): string
    {
        // Whitelist columns + direction to keep this safe against arbitrary input
        // even though the source is config (defense in depth).
        $allowed = ['created_at', 'updated_at', 'publish_at', 'slug', 'id'];
        $parts = preg_split('/\s+/', trim($orderBy)) ?: [];
        $col = $parts[0] ?? 'updated_at';
        $dir = strtoupper($parts[1] ?? 'DESC');
        if (!in_array($col, $allowed, true)) {
            $col = 'updated_at';
        }
        if (!in_array($dir, ['ASC', 'DESC'], true)) {
            $dir = 'DESC';
        }
        return "{$col} {$dir}";
    }
}

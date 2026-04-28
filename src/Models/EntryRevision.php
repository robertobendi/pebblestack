<?php

declare(strict_types=1);

namespace Pebblestack\Models;

final class EntryRevision
{
    /** @param array<string,mixed> $data */
    public function __construct(
        public readonly int $id,
        public readonly int $entryId,
        public readonly string $collection,
        public readonly string $slug,
        public readonly string $status,
        public readonly array $data,
        public readonly ?int $publishAt,
        public readonly ?int $editedBy,
        public readonly int $createdAt,
    ) {}

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $data = json_decode((string) $row['data'], true);
        return new self(
            id:         (int) $row['id'],
            entryId:    (int) $row['entry_id'],
            collection: (string) $row['collection'],
            slug:       (string) $row['slug'],
            status:     (string) $row['status'],
            data:       is_array($data) ? $data : [],
            publishAt:  isset($row['publish_at']) ? (int) $row['publish_at'] : null,
            editedBy:   isset($row['edited_by']) ? (int) $row['edited_by'] : null,
            createdAt:  (int) $row['created_at'],
        );
    }

    public function field(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}

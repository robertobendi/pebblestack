<?php

declare(strict_types=1);

namespace Pebblestack\Models;

final class Entry
{
    /** @param array<string,mixed> $data */
    public function __construct(
        public readonly int $id,
        public readonly string $collection,
        public readonly string $slug,
        public readonly string $status,
        public readonly array $data,
        public readonly ?int $publishAt,
        public readonly int $createdAt,
        public readonly int $updatedAt,
    ) {}

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $data = json_decode((string) $row['data'], true);
        return new self(
            id: (int) $row['id'],
            collection: (string) $row['collection'],
            slug: (string) $row['slug'],
            status: (string) $row['status'],
            data: is_array($data) ? $data : [],
            publishAt: isset($row['publish_at']) ? (int) $row['publish_at'] : null,
            createdAt: (int) $row['created_at'],
            updatedAt: (int) $row['updated_at'],
        );
    }

    public function isPublished(): bool
    {
        if ($this->status !== 'published') {
            return false;
        }
        if ($this->publishAt !== null && $this->publishAt > time()) {
            return false;
        }
        return true;
    }

    public function field(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}

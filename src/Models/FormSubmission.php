<?php

declare(strict_types=1);

namespace Pebblestack\Models;

final class FormSubmission
{
    /** @param array<string,mixed> $data */
    public function __construct(
        public readonly int $id,
        public readonly string $collection,
        public readonly array $data,
        public readonly ?string $ipHash,
        public readonly ?string $userAgent,
        public readonly int $submittedAt,
    ) {}

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $data = json_decode((string) $row['data'], true);
        return new self(
            id:          (int) $row['id'],
            collection:  (string) $row['collection'],
            data:        is_array($data) ? $data : [],
            ipHash:      isset($row['ip_hash']) ? (string) $row['ip_hash'] : null,
            userAgent:   isset($row['user_agent']) ? (string) $row['user_agent'] : null,
            submittedAt: (int) $row['submitted_at'],
        );
    }

    public function field(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}

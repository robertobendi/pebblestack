<?php

declare(strict_types=1);

namespace Pebblestack\Models;

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $name,
        public readonly string $role,
        public readonly int $createdAt,
        public readonly int $updatedAt,
    ) {}

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            email: (string) $row['email'],
            name: (string) $row['name'],
            role: (string) $row['role'],
            createdAt: (int) $row['created_at'],
            updatedAt: (int) $row['updated_at'],
        );
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}

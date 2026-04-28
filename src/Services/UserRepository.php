<?php

declare(strict_types=1);

namespace Pebblestack\Services;

use Pebblestack\Core\Database;
use Pebblestack\Models\User;

final class UserRepository
{
    public function __construct(private readonly Database $db) {}

    /** @return list<User> */
    public function listAll(): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM users ORDER BY id ASC');
        return array_map(fn ($r) => User::fromRow($r), $rows);
    }

    public function find(int $id): ?User
    {
        $row = $this->db->fetchOne('SELECT * FROM users WHERE id = :id', ['id' => $id]);
        return $row === null ? null : User::fromRow($row);
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM users WHERE email = :e',
            ['e' => strtolower(trim($email))]
        );
        return $row === null ? null : User::fromRow($row);
    }

    public function create(string $email, string $name, string $passwordHash, string $role): int
    {
        $now = time();
        $this->db->run(
            'INSERT INTO users (email, password_hash, name, role, created_at, updated_at)
             VALUES (:e, :p, :n, :r, :ca, :ua)',
            [
                'e'  => strtolower(trim($email)),
                'p'  => $passwordHash,
                'n'  => trim($name),
                'r'  => $role,
                'ca' => $now,
                'ua' => $now,
            ]
        );
        return $this->db->lastInsertId();
    }

    public function update(int $id, string $email, string $name, string $role): void
    {
        $this->db->run(
            'UPDATE users SET email = :e, name = :n, role = :r, updated_at = :ua WHERE id = :id',
            [
                'id' => $id,
                'e'  => strtolower(trim($email)),
                'n'  => trim($name),
                'r'  => $role,
                'ua' => time(),
            ]
        );
    }

    public function setPassword(int $id, string $passwordHash): void
    {
        $this->db->run(
            'UPDATE users SET password_hash = :p, updated_at = :ua WHERE id = :id',
            ['id' => $id, 'p' => $passwordHash, 'ua' => time()]
        );
    }

    public function delete(int $id): void
    {
        $this->db->run('DELETE FROM users WHERE id = :id', ['id' => $id]);
    }

    public function countAdmins(): int
    {
        $row = $this->db->fetchOne("SELECT COUNT(*) AS n FROM users WHERE role = 'admin'");
        return (int) ($row['n'] ?? 0);
    }
}

<?php

declare(strict_types=1);

namespace Pebblestack\Core;

use Pebblestack\Models\User;

final class Auth
{
    private ?User $cachedUser = null;

    public function __construct(
        private readonly Database $db,
        private readonly Session $session,
    ) {}

    public function attempt(string $email, string $password): ?User
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM users WHERE email = :e',
            ['e' => strtolower(trim($email))]
        );
        if ($row === null) {
            return null;
        }
        if (!password_verify($password, (string) $row['password_hash'])) {
            return null;
        }
        $this->session->regenerate();
        $this->session->set('uid', (int) $row['id']);
        $user = User::fromRow($row);
        $this->cachedUser = $user;
        return $user;
    }

    public function logout(): void
    {
        $this->session->destroy();
        $this->cachedUser = null;
    }

    public function user(): ?User
    {
        if ($this->cachedUser !== null) {
            return $this->cachedUser;
        }
        $uid = $this->session->get('uid');
        if (!is_int($uid)) {
            return null;
        }
        $row = $this->db->fetchOne('SELECT * FROM users WHERE id = :id', ['id' => $uid]);
        if ($row === null) {
            return null;
        }
        return $this->cachedUser = User::fromRow($row);
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

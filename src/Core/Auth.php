<?php

declare(strict_types=1);

namespace Pebblestack\Core;

use Pebblestack\Models\User;

final class Auth
{
    /**
     * Role hierarchy. Higher rank includes everything below it.
     * - viewer: read-only access to admin
     * - editor: read + write content (entries, media, submissions)
     * - admin:  everything, including users and site settings
     */
    public const ROLE_RANK = ['viewer' => 1, 'editor' => 2, 'admin' => 3];

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

    public function hasMinRole(string $minRole): bool
    {
        if (!isset(self::ROLE_RANK[$minRole])) {
            // A typo'd $minRole used to silently deny ($needed defaulted to 99).
            // That hides the bug — every caller fails closed and the developer
            // never sees a stack trace. Fail loudly instead so the typo is
            // caught the first time the guarded route is hit.
            throw new \InvalidArgumentException("Unknown role: '{$minRole}'. Allowed: " . implode(', ', array_keys(self::ROLE_RANK)));
        }
        $u = $this->user();
        if ($u === null) {
            return false;
        }
        $userRank = self::ROLE_RANK[$u->role] ?? 0;
        return $userRank >= self::ROLE_RANK[$minRole];
    }

    /**
     * Returns a redirect/forbidden Response if the current request can't proceed,
     * or null if the user passes the role check. Use as:
     *
     *   if ($block = $this->app->auth->guard('editor')) return $block;
     */
    public function guard(string $minRole): ?Response
    {
        if (!$this->check()) {
            return Response::redirect('/admin/login');
        }
        if (!$this->hasMinRole($minRole)) {
            return Response::html(
                '<!doctype html><meta charset="utf-8"><title>403 Forbidden</title>' .
                '<style>body{font:15px system-ui;margin:4rem auto;max-width:480px;padding:0 1rem;color:#1c1917;text-align:center}' .
                'a{color:#2563eb}</style>' .
                '<h1>403 — Forbidden</h1>' .
                '<p>Your account doesn’t have permission for that action.</p>' .
                '<p><a href="/admin">← Back to admin</a></p>',
                403
            );
        }
        return null;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

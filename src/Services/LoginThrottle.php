<?php

declare(strict_types=1);

namespace Pebblestack\Services;

use Pebblestack\Core\Database;

/**
 * Per-IP brute-force throttle for the admin login. Failed attempts are
 * recorded against a salted IP hash (never the raw address); too many
 * failures inside the window locks the IP out until attempts age off.
 */
final class LoginThrottle
{
    public const MAX_ATTEMPTS = 10;
    public const WINDOW_SECONDS = 900; // 15 minutes

    public function __construct(private readonly Database $db) {}

    public function tooManyAttempts(string $ipHash): bool
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS n FROM login_attempts WHERE ip_hash = :ip AND attempted_at >= :since',
            ['ip' => $ipHash, 'since' => time() - self::WINDOW_SECONDS]
        );
        return (int) ($row['n'] ?? 0) >= self::MAX_ATTEMPTS;
    }

    public function recordFailure(string $ipHash): void
    {
        $this->db->run(
            'INSERT INTO login_attempts (ip_hash, attempted_at) VALUES (:ip, :t)',
            ['ip' => $ipHash, 't' => time()]
        );
        // Opportunistic prune so the table never grows unbounded.
        $this->db->run(
            'DELETE FROM login_attempts WHERE attempted_at < :cutoff',
            ['cutoff' => time() - self::WINDOW_SECONDS * 2]
        );
    }

    public function clear(string $ipHash): void
    {
        $this->db->run('DELETE FROM login_attempts WHERE ip_hash = :ip', ['ip' => $ipHash]);
    }
}

<?php

declare(strict_types=1);

namespace Pebblestack\Services;

use Pebblestack\Core\Database;

/**
 * Key/value site settings stored in the `settings` table, loaded once per
 * request. Reads are memoized; writes keep the cache in sync.
 */
final class Settings
{
    /** @var array<string,string>|null */
    private ?array $cache = null;

    public function __construct(private readonly Database $db) {}

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string $key, string $value): void
    {
        $this->db->run(
            'INSERT INTO settings (key, value) VALUES (:k, :v)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value',
            ['k' => $key, 'v' => $value]
        );
        if ($this->cache !== null) {
            $this->cache[$key] = $value;
        }
    }

    public function siteName(): string
    {
        return $this->get('site_name') ?? 'Pebblestack';
    }

    /**
     * Salted one-way hash of a visitor IP, used for rate limiting without
     * storing recoverable addresses. The salt is per-install (installed_at),
     * so hashes can't be compared across Pebblestack sites.
     */
    public function ipHash(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }
        $salt = $this->get('installed_at') ?? 'pebblestack';
        return hash('sha256', $ip . '|' . $salt);
    }

    /** @return array<string,string> */
    private function all(): array
    {
        if ($this->cache === null) {
            try {
                $rows = $this->db->fetchAll('SELECT key, value FROM settings');
            } catch (\Throwable) {
                // Pre-install the table doesn't exist yet; behave as empty.
                return [];
            }
            $this->cache = [];
            foreach ($rows as $row) {
                $this->cache[(string) $row['key']] = (string) $row['value'];
            }
        }
        return $this->cache;
    }
}

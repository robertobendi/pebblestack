<?php

declare(strict_types=1);

namespace Pebblestack\Services;

use Pebblestack\Core\Auth;
use Pebblestack\Core\Database;

final class Installer
{
    public function __construct(
        private readonly string $rootDir,
        private readonly Database $db,
    ) {}

    public function isInstalled(): bool
    {
        return is_file($this->lockPath());
    }

    /**
     * Run any pending migrations, create the first admin user, store
     * the site name, and mark the install complete.
     */
    public function install(string $email, string $password, string $name, string $siteName): void
    {
        $migrator = new Migrator($this->db, $this->rootDir . '/data/migrations');
        $migrator->run();

        $now = time();
        $this->db->transaction(function (Database $db) use ($email, $password, $name, $siteName, $now): void {
            $db->run(
                'INSERT INTO users (email, password_hash, name, role, created_at, updated_at)
                 VALUES (:e, :p, :n, :r, :ca, :ua)',
                [
                    'e' => strtolower(trim($email)),
                    'p' => Auth::hash($password),
                    'n' => trim($name),
                    'r' => 'admin',
                    'ca' => $now,
                    'ua' => $now,
                ]
            );
            $db->run(
                "INSERT INTO settings (key, value) VALUES ('site_name', :v)
                 ON CONFLICT(key) DO UPDATE SET value = excluded.value",
                ['v' => trim($siteName) !== '' ? trim($siteName) : 'Pebblestack']
            );
            $db->run(
                "INSERT INTO settings (key, value) VALUES ('installed_at', :v)
                 ON CONFLICT(key) DO UPDATE SET value = excluded.value",
                ['v' => (string) $now]
            );
        });

        file_put_contents($this->lockPath(), (string) $now);
    }

    private function lockPath(): string
    {
        return $this->rootDir . '/data/installed.lock';
    }
}

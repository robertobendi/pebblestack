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

    /**
     * "Installed" means the schema exists AND at least one user has been
     * created. Querying the users table directly is more reliable than a
     * sentinel file: if the install transaction commits but a subsequent
     * filesystem write fails, the next request is still able to log in
     * rather than looping back through the installer.
     */
    public function isInstalled(): bool
    {
        try {
            if (!$this->db->tableExists('users')) {
                return false;
            }
            $row = $this->db->fetchOne('SELECT 1 AS x FROM users LIMIT 1');
            return $row !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Run any pending migrations, create the first admin user, and store
     * the site name. Once the transaction commits, isInstalled() flips
     * true automatically — there is no separate sentinel to keep in sync.
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
    }
}

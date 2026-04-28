<?php

declare(strict_types=1);

namespace Pebblestack\Services;

use Pebblestack\Core\Database;

/**
 * Idempotent SQL migration runner. Picks up every .sql file in the
 * migrations directory, sorts by filename, runs anything not yet recorded
 * in the schema_migrations table.
 */
final class Migrator
{
    public function __construct(
        private readonly Database $db,
        private readonly string $migrationsDir,
    ) {}

    public function run(): int
    {
        $this->ensureLedger();
        $applied = $this->appliedSet();
        $count = 0;
        foreach ($this->files() as $file) {
            $name = basename($file);
            if (isset($applied[$name])) {
                continue;
            }
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \RuntimeException("Could not read migration: {$name}");
            }
            $this->db->transaction(function (Database $db) use ($sql, $name): void {
                $db->pdo()->exec($sql);
                $db->run(
                    'INSERT INTO schema_migrations (name, applied_at) VALUES (:n, :t)',
                    ['n' => $name, 't' => time()]
                );
            });
            $count++;
        }
        return $count;
    }

    private function ensureLedger(): void
    {
        $this->db->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                name       TEXT PRIMARY KEY,
                applied_at INTEGER NOT NULL
            )'
        );
    }

    /** @return array<string,bool> */
    private function appliedSet(): array
    {
        $rows = $this->db->fetchAll('SELECT name FROM schema_migrations');
        $set = [];
        foreach ($rows as $row) {
            $set[(string) $row['name']] = true;
        }
        return $set;
    }

    /** @return list<string> */
    private function files(): array
    {
        if (!is_dir($this->migrationsDir)) {
            return [];
        }
        $files = glob($this->migrationsDir . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        return $files;
    }
}

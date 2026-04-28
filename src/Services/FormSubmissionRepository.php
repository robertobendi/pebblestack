<?php

declare(strict_types=1);

namespace Pebblestack\Services;

use Pebblestack\Core\Database;
use Pebblestack\Models\FormSubmission;

final class FormSubmissionRepository
{
    public function __construct(private readonly Database $db) {}

    /** @param array<string,mixed> $data */
    public function create(string $collection, array $data, ?string $ipHash, ?string $userAgent): int
    {
        $this->db->run(
            'INSERT INTO form_submissions (collection, data, ip_hash, user_agent, submitted_at)
             VALUES (:c, :d, :ip, :ua, :ts)',
            [
                'c'  => $collection,
                'd'  => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'ip' => $ipHash,
                'ua' => $userAgent !== null ? mb_substr($userAgent, 0, 500) : null,
                'ts' => time(),
            ]
        );
        return $this->db->lastInsertId();
    }

    public function find(int $id): ?FormSubmission
    {
        $row = $this->db->fetchOne('SELECT * FROM form_submissions WHERE id = :id', ['id' => $id]);
        return $row === null ? null : FormSubmission::fromRow($row);
    }

    /** @return list<FormSubmission> */
    public function listByCollection(string $collection, int $limit = 200, int $offset = 0): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM form_submissions WHERE collection = :c ORDER BY submitted_at DESC LIMIT :l OFFSET :o',
            ['c' => $collection, 'l' => $limit, 'o' => $offset]
        );
        return array_map(fn ($r) => FormSubmission::fromRow($r), $rows);
    }

    public function countByCollection(string $collection): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS n FROM form_submissions WHERE collection = :c', ['c' => $collection]);
        return (int) ($row['n'] ?? 0);
    }

    public function delete(int $id): void
    {
        $this->db->run('DELETE FROM form_submissions WHERE id = :id', ['id' => $id]);
    }

    /**
     * Submissions in the last $seconds for an IP hash. Used for rate limiting.
     */
    public function recentCountForIp(string $ipHash, int $seconds): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS n FROM form_submissions WHERE ip_hash = :ip AND submitted_at >= :since',
            ['ip' => $ipHash, 'since' => time() - $seconds]
        );
        return (int) ($row['n'] ?? 0);
    }
}

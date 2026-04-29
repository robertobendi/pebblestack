<?php

declare(strict_types=1);

namespace Pebblestack\Services;

use Pebblestack\Core\Database;

/**
 * Privacy-friendly page view tracking. Stores one row per (path, day),
 * incremented atomically. No IPs, no cookies, no JS pixel — counts are
 * incremented server-side as part of rendering the public page.
 */
final class MetricsService
{
    public function __construct(private readonly Database $db) {}

    public function recordView(string $path): void
    {
        // Skip non-content paths (admin, install, etc.) and obvious assets.
        if (!$this->isTrackable($path)) {
            return;
        }
        $path = self::normalizePath($path);
        $day = $this->today();
        // ON CONFLICT DO UPDATE is supported by SQLite 3.24+. PHP's bundled
        // SQLite is well past that everywhere modern.
        $this->db->run(
            'INSERT INTO pageviews (path, day_utc, count) VALUES (:p, :d, 1)
             ON CONFLICT(path, day_utc) DO UPDATE SET count = count + 1',
            ['p' => $path, 'd' => $day]
        );
    }

    /**
     * Lowercase the path, collapse runs of slashes, and drop the trailing
     * slash so /Foo, /foo/ and /foo//bar all map to a single canonical row
     * in pageviews. Without this, topPaths is polluted by stray casing and
     * duplicate-slash variants.
     */
    private static function normalizePath(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }
        $path = strtolower($path);
        $path = (string) preg_replace('#/+#', '/', $path);
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        return $path;
    }

    public function totalViews(int $sinceDays = 30): int
    {
        $since = $this->today() - ($sinceDays - 1) * 86400;
        $row = $this->db->fetchOne(
            'SELECT SUM(count) AS n FROM pageviews WHERE day_utc >= :s',
            ['s' => $since]
        );
        return (int) ($row['n'] ?? 0);
    }

    /**
     * @return list<array{path:string,count:int}>
     */
    public function topPaths(int $sinceDays = 30, int $limit = 10): array
    {
        $since = $this->today() - ($sinceDays - 1) * 86400;
        $rows = $this->db->fetchAll(
            'SELECT path, SUM(count) AS n FROM pageviews
             WHERE day_utc >= :s
             GROUP BY path
             ORDER BY n DESC, path ASC
             LIMIT :l',
            ['s' => $since, 'l' => $limit]
        );
        return array_map(fn ($r) => ['path' => (string) $r['path'], 'count' => (int) $r['n']], $rows);
    }

    /**
     * Daily totals for the last $days, oldest-first. Missing days fill with 0
     * so the chart has a continuous x-axis.
     *
     * @return list<array{day:int,count:int}>
     */
    public function dailyTotals(int $days = 30): array
    {
        $today = $this->today();
        $since = $today - ($days - 1) * 86400;
        $rows = $this->db->fetchAll(
            'SELECT day_utc, SUM(count) AS n FROM pageviews
             WHERE day_utc >= :s GROUP BY day_utc',
            ['s' => $since]
        );
        $byDay = [];
        foreach ($rows as $r) {
            $byDay[(int) $r['day_utc']] = (int) $r['n'];
        }
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $since + $i * 86400;
            $out[] = ['day' => $day, 'count' => $byDay[$day] ?? 0];
        }
        return $out;
    }

    private function today(): int
    {
        // Midnight UTC of the current day.
        return (int) (floor(time() / 86400) * 86400);
    }

    private function isTrackable(string $path): bool
    {
        if ($path === '' || $path === '/') {
            return true;
        }
        if (str_starts_with($path, '/admin')
            || str_starts_with($path, '/install')
            || str_starts_with($path, '/forms/')
            || str_starts_with($path, '/uploads/')
            || str_starts_with($path, '/api/')
            || str_starts_with($path, '/sitemap')
            || str_starts_with($path, '/robots')) {
            return false;
        }
        // Skip obvious asset extensions even if they hit index.php.
        if (preg_match('/\.(css|js|png|jpe?g|gif|webp|svg|ico|woff2?|ttf|map)$/i', $path)) {
            return false;
        }
        return true;
    }
}

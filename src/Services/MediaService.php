<?php

declare(strict_types=1);

namespace Pebblestack\Services;

use Pebblestack\Core\Database;
use Pebblestack\Models\Media;

/**
 * Handles validation, storage, and bookkeeping for uploaded files.
 *
 * Storage layout: uploads/YYYY/MM/{8-hex-prefix}-{slugified-name}.{ext}
 * The hex prefix prevents URL guessing and avoids collisions.
 */
final class MediaService
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf'];
    public const ALLOWED_MIME = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'application/pdf',
    ];

    public function __construct(
        private readonly Database $db,
        private readonly string $rootDir,
    ) {}

    /**
     * Validate and persist an uploaded file.
     *
     * @param array{tmp_name?:string,name?:string,type?:string,size?:int,error?:int} $file an entry from $_FILES
     * @param int|null $uploadedBy user id
     * @return array{0:?Media,1:list<string>} [media, errors]
     */
    public function store(array $file, ?int $uploadedBy): array
    {
        $errors = [];

        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = $this->describeError($file['error'] ?? UPLOAD_ERR_NO_FILE);
            return [null, $errors];
        }
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'Upload was not transmitted correctly.';
            return [null, $errors];
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            $errors[] = 'File is empty.';
            return [null, $errors];
        }
        if ($size > self::MAX_BYTES) {
            $errors[] = 'File is larger than ' . (self::MAX_BYTES / 1024 / 1024) . ' MB.';
            return [null, $errors];
        }

        $originalName = (string) ($file['name'] ?? 'upload');
        $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            $errors[] = 'File type not allowed. Permitted: ' . implode(', ', self::ALLOWED_EXT) . '.';
            return [null, $errors];
        }

        // Sniff MIME from the actual file bytes — never trust the browser-supplied
        // Content-Type, which can be set by the client.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $sniffed = (string) $finfo->file($file['tmp_name']);
        if ($sniffed === '') {
            $sniffed = (string) ($file['type'] ?? '');
        }
        if (!in_array($sniffed, self::ALLOWED_MIME, true)) {
            $errors[] = 'Detected MIME type "' . $sniffed . '" is not allowed.';
            return [null, $errors];
        }

        // Build target path.
        $year = date('Y');
        $month = date('m');
        $dir = $this->rootDir . '/uploads/' . $year . '/' . $month;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            $errors[] = 'Could not create upload directory.';
            return [null, $errors];
        }

        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $slug = Field::slugify($base);
        if ($slug === '') {
            $slug = 'file';
        }
        $prefix = bin2hex(random_bytes(4));
        $filename = $prefix . '-' . $slug . '.' . $ext;
        $absolute = $dir . '/' . $filename;
        $relative = '/uploads/' . $year . '/' . $month . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $absolute)) {
            $errors[] = 'Could not write uploaded file to disk.';
            return [null, $errors];
        }
        @chmod($absolute, 0644);

        $width = null;
        $height = null;
        if (str_starts_with($sniffed, 'image/') && $sniffed !== 'image/svg+xml') {
            $info = @getimagesize($absolute);
            if (is_array($info)) {
                $width = (int) $info[0];
                $height = (int) $info[1];
            }
        }

        $now = time();
        $this->db->run(
            'INSERT INTO media (path, original_name, mime_type, size, width, height, alt, created_at, uploaded_by)
             VALUES (:p, :on, :m, :s, :w, :h, :a, :ca, :u)',
            [
                'p' => $relative,
                'on' => $originalName,
                'm' => $sniffed,
                's' => $size,
                'w' => $width,
                'h' => $height,
                'a' => null,
                'ca' => $now,
                'u' => $uploadedBy,
            ]
        );
        $media = $this->find($this->db->lastInsertId());
        return [$media, []];
    }

    public function find(int $id): ?Media
    {
        $row = $this->db->fetchOne('SELECT * FROM media WHERE id = :id', ['id' => $id]);
        return $row === null ? null : Media::fromRow($row);
    }

    /** @return list<Media> */
    public function listAll(int $limit = 200): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM media ORDER BY created_at DESC LIMIT :l',
            ['l' => $limit]
        );
        return array_map(fn ($r) => Media::fromRow($r), $rows);
    }

    public function updateAlt(int $id, ?string $alt): void
    {
        $this->db->run(
            'UPDATE media SET alt = :a WHERE id = :id',
            ['a' => $alt === null ? null : trim($alt), 'id' => $id]
        );
    }

    public function delete(int $id): bool
    {
        $media = $this->find($id);
        if ($media === null) {
            return false;
        }
        $absolute = $this->rootDir . $media->path;
        if (is_file($absolute)) {
            @unlink($absolute);
        }
        $this->db->run('DELETE FROM media WHERE id = :id', ['id' => $id]);
        return true;
    }

    public function count(): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS n FROM media');
        return (int) ($row['n'] ?? 0);
    }

    private function describeError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload_max_filesize.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form MAX_FILE_SIZE.',
            UPLOAD_ERR_PARTIAL    => 'Upload was interrupted.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server has no temporary upload directory.',
            UPLOAD_ERR_CANT_WRITE => 'Could not write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
            default               => 'Unknown upload error.',
        };
    }
}

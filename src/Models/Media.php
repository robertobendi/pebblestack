<?php

declare(strict_types=1);

namespace Pebblestack\Models;

final class Media
{
    public function __construct(
        public readonly int $id,
        public readonly string $path,
        public readonly string $originalName,
        public readonly string $mimeType,
        public readonly int $size,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly ?string $alt,
        public readonly int $createdAt,
        public readonly ?int $uploadedBy,
    ) {}

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:           (int) $row['id'],
            path:         (string) $row['path'],
            originalName: (string) $row['original_name'],
            mimeType:     (string) $row['mime_type'],
            size:         (int) $row['size'],
            width:        isset($row['width']) ? (int) $row['width'] : null,
            height:       isset($row['height']) ? (int) $row['height'] : null,
            alt:          isset($row['alt']) ? (string) $row['alt'] : null,
            createdAt:    (int) $row['created_at'],
            uploadedBy:   isset($row['uploaded_by']) ? (int) $row['uploaded_by'] : null,
        );
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }

    public function humanSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->size;
        $i = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return ($i === 0 ? (int) $size : number_format($size, 1)) . ' ' . $units[$i];
    }
}

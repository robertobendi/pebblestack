<?php

declare(strict_types=1);

namespace Pebblestack\Core;

/**
 * Loads PHP-array config files from /config and exposes dotted lookup.
 */
final class Config
{
    /** @var array<string,mixed> */
    private array $data = [];

    public function __construct(private readonly string $configDir) {}

    public function load(string $file): self
    {
        $path = $this->configDir . DIRECTORY_SEPARATOR . $file . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException("Config file not found: {$file}");
        }
        $loaded = require $path;
        if (!is_array($loaded)) {
            throw new \RuntimeException("Config file must return an array: {$file}");
        }
        $this->data[$file] = $loaded;
        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $cursor = $this->data;
        foreach ($parts as $part) {
            if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
                return $default;
            }
            $cursor = $cursor[$part];
        }
        return $cursor;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->data;
    }
}

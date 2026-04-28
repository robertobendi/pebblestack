<?php

declare(strict_types=1);

namespace Pebblestack\Services;

final class CollectionRegistry
{
    /** @var array<string,Collection> */
    private array $collections = [];

    /** @param array<string,mixed> $config raw map of name => collection config */
    public function __construct(array $config)
    {
        foreach ($config as $name => $cfg) {
            if (!is_string($name) || !is_array($cfg)) {
                continue;
            }
            $this->collections[$name] = new Collection($name, $cfg);
        }
    }

    public function get(string $name): ?Collection
    {
        return $this->collections[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->collections[$name]);
    }

    /** @return array<string,Collection> */
    public function all(): array
    {
        return $this->collections;
    }

    /** @return list<Collection> */
    public function list(): array
    {
        return array_values($this->collections);
    }
}

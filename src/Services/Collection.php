<?php

declare(strict_types=1);

namespace Pebblestack\Services;

/**
 * A collection definition. Wraps the config array with typed accessors
 * and produces Field objects on demand.
 */
final class Collection
{
    /** @param array<string,mixed> $config */
    public function __construct(
        public readonly string $name,
        public readonly array $config,
    ) {}

    public function label(): string
    {
        return (string) ($this->config['label'] ?? ucfirst($this->name));
    }

    public function labelSingular(): string
    {
        $explicit = $this->config['label_singular'] ?? null;
        if (is_string($explicit)) {
            return $explicit;
        }
        $label = $this->label();
        if (str_ends_with($label, 's')) {
            return rtrim($label, 's');
        }
        return $label;
    }

    public function icon(): string
    {
        return (string) ($this->config['icon'] ?? 'layers');
    }

    public function isForm(): bool
    {
        return (bool) ($this->config['is_form'] ?? false);
    }

    public function adminUrl(): string
    {
        return $this->isForm()
            ? '/admin/forms/' . $this->name
            : '/admin/collections/' . $this->name;
    }

    /** @return array<string,Field> */
    public function fields(): array
    {
        $out = [];
        $fields = $this->config['fields'] ?? [];
        if (!is_array($fields)) {
            return $out;
        }
        foreach ($fields as $key => $cfg) {
            if (!is_array($cfg)) {
                continue;
            }
            $out[(string) $key] = new Field((string) $key, $cfg);
        }
        return $out;
    }

    public function field(string $key): ?Field
    {
        $fields = $this->fields();
        return $fields[$key] ?? null;
    }

    public function titleField(): string
    {
        $explicit = $this->config['title_field'] ?? null;
        if (is_string($explicit) && $this->field($explicit) !== null) {
            return $explicit;
        }
        return 'title';
    }

    public function slugField(): string
    {
        $explicit = $this->config['slug_field'] ?? null;
        if (is_string($explicit) && $this->field($explicit) !== null) {
            return $explicit;
        }
        return 'slug';
    }

    public function template(): string
    {
        return (string) ($this->config['template'] ?? $this->name . '.twig');
    }

    public function listTemplate(): ?string
    {
        $t = $this->config['list_template'] ?? null;
        return is_string($t) ? $t : null;
    }

    public function publicRoute(): ?string
    {
        $r = $this->config['route'] ?? null;
        return is_string($r) ? $r : null;
    }

    public function orderBy(): string
    {
        return (string) ($this->config['order_by'] ?? 'updated_at DESC');
    }

    public function listLimit(): ?int
    {
        $v = $this->config['list_limit'] ?? null;
        if ($v === null) {
            return null;
        }
        $n = (int) $v;
        return $n > 0 ? $n : null;
    }
}

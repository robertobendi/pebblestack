<?php

declare(strict_types=1);

namespace Pebblestack\Services;

/**
 * A single field within a collection. Validates and normalizes raw input
 * coming from the admin form. Each field type maps to a Twig partial
 * (templates/admin/fields/<type>.twig) for rendering.
 */
final class Field
{
    public const TYPES = ['text', 'textarea', 'markdown', 'slug', 'boolean', 'number', 'select', 'datetime', 'url'];

    /** @param array<string,mixed> $config */
    public function __construct(
        public readonly string $key,
        public readonly array $config,
    ) {
        if (!in_array($this->type(), self::TYPES, true)) {
            throw new \InvalidArgumentException("Unknown field type for '{$key}': " . $this->type());
        }
    }

    public function type(): string
    {
        return (string) ($this->config['type'] ?? 'text');
    }

    public function label(): string
    {
        return (string) ($this->config['label'] ?? ucfirst(str_replace('_', ' ', $this->key)));
    }

    public function required(): bool
    {
        return (bool) ($this->config['required'] ?? false);
    }

    public function help(): ?string
    {
        $h = $this->config['help'] ?? null;
        return is_string($h) ? $h : null;
    }

    /** @return array<int|string,string> */
    public function options(): array
    {
        $opts = $this->config['options'] ?? [];
        return is_array($opts) ? $opts : [];
    }

    public function default(): mixed
    {
        return $this->config['default'] ?? match ($this->type()) {
            'boolean' => false,
            'number'  => null,
            default   => '',
        };
    }

    /**
     * Coerce a raw form value into the canonical type for storage.
     */
    public function coerce(mixed $raw): mixed
    {
        return match ($this->type()) {
            'boolean'  => (bool) $raw,
            'number'   => ($raw === null || $raw === '') ? null : (is_numeric($raw) ? $raw + 0 : null),
            'datetime' => ($raw === null || $raw === '') ? null : (string) $raw,
            'slug'     => self::slugify((string) ($raw ?? '')),
            default    => $raw === null ? '' : trim((string) $raw),
        };
    }

    /**
     * @return list<string> validation errors; empty list if valid.
     */
    public function validate(mixed $value): array
    {
        $errors = [];
        $isEmpty = $value === null || $value === '' || (is_array($value) && $value === []);
        if ($this->required() && $isEmpty) {
            $errors[] = $this->label() . ' is required.';
            return $errors;
        }
        if ($isEmpty) {
            return $errors;
        }
        switch ($this->type()) {
            case 'slug':
                if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $value)) {
                    $errors[] = $this->label() . ' must be lowercase letters, numbers, and dashes.';
                }
                break;
            case 'number':
                if (!is_numeric($value)) {
                    $errors[] = $this->label() . ' must be a number.';
                }
                break;
            case 'select':
                $opts = $this->options();
                $valid = array_keys($opts) === range(0, count($opts) - 1) ? array_values($opts) : array_keys($opts);
                if (!in_array((string) $value, array_map('strval', $valid), true)) {
                    $errors[] = $this->label() . ' is not a valid option.';
                }
                break;
            case 'url':
                if (!filter_var((string) $value, FILTER_VALIDATE_URL)) {
                    $errors[] = $this->label() . ' must be a valid URL.';
                }
                break;
        }
        return $errors;
    }

    public static function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        return trim($text, '-');
    }
}

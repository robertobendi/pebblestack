<?php

declare(strict_types=1);

namespace Pebblestack\Core;

final class Request
{
    /**
     * @param array<string,mixed> $get
     * @param array<string,mixed> $post
     * @param array<string,mixed> $server
     * @param array<string,mixed> $files
     * @param array<string,string> $cookies
     * @param array<string,string> $params route params, filled by router
     */
    public function __construct(
        public readonly array $get,
        public readonly array $post,
        public readonly array $server,
        public readonly array $files,
        public readonly array $cookies,
        public array $params = [],
    ) {}

    public static function fromGlobals(): self
    {
        return new self($_GET, $_POST, $_SERVER, $_FILES, $_COOKIE);
    }

    public function method(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function path(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        return $path;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->get[$key] ?? $default;
    }

    public function param(string $key, ?string $default = null): ?string
    {
        return $this->params[$key] ?? $default;
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $val = $this->server[$key] ?? null;
        return $val === null ? null : (string) $val;
    }

    public function isSecure(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || (($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    /**
     * Scheme + host of the current request, e.g. "https://example.com".
     * Honors X-Forwarded-Proto so absolute URLs (sitemap, canonical) stay
     * https behind Cloudflare or a host's TLS-terminating proxy.
     */
    public function baseUrl(): string
    {
        $host = (string) ($this->server['HTTP_HOST'] ?? 'localhost');
        return ($this->isSecure() ? 'https' : 'http') . '://' . $host;
    }

    public function clientIp(): ?string
    {
        $ip = (string) ($this->server['REMOTE_ADDR'] ?? '');
        return $ip === '' ? null : $ip;
    }
}

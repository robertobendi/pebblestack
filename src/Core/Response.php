<?php

declare(strict_types=1);

namespace Pebblestack\Core;

final class Response
{
    /** @var array<string,string> */
    private array $headers = [];

    public function __construct(
        private string $body = '',
        private int $status = 200,
    ) {}

    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public static function html(string $body, int $status = 200): self
    {
        return (new self($body, $status))->setHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return (new self('', $status))->setHeader('Location', $location);
    }

    public static function notFound(string $message = 'Not Found'): self
    {
        return self::html('<!doctype html><title>404</title><h1>404 — ' . htmlspecialchars($message) . '</h1>', 404);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }
        // 1xx/204/304 responses are not allowed to carry a body per RFC 9110.
        if ($this->status === 204 || $this->status === 304 || ($this->status >= 100 && $this->status < 200)) {
            return;
        }
        echo $this->body;
    }
}

<?php

declare(strict_types=1);

namespace Pebblestack\Core;

/**
 * Per-session CSRF token. One token per session is sufficient for an admin
 * UI; we don't need per-form tokens unless an admin shares a session across
 * very long timeframes.
 */
final class Csrf
{
    public function __construct(private readonly Session $session) {}

    public function token(): string
    {
        $token = $this->session->get('_csrf');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set('_csrf', $token);
        }
        return $token;
    }

    public function validate(?string $candidate): bool
    {
        $token = $this->session->get('_csrf');
        return is_string($token) && is_string($candidate) && hash_equals($token, $candidate);
    }

    public function check(Request $request): void
    {
        $token = (string) ($request->post['_csrf'] ?? $request->header('x-csrf-token') ?? '');
        if (!$this->validate($token)) {
            throw new CsrfException('CSRF token mismatch.');
        }
    }
}

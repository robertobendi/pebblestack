<?php

declare(strict_types=1);

namespace Pebblestack\Controllers;

use Pebblestack\Core\App;
use Pebblestack\Core\Request;
use Pebblestack\Core\Response;
use Pebblestack\Services\FormSubmissionRepository;

final class FormController
{
    public const RATE_LIMIT_PER_HOUR = 10;

    public function __construct(private readonly App $app) {}

    public function submit(Request $request): Response
    {
        $name = (string) $request->param('collection', '');
        $collection = $this->app->collections->get($name);
        if ($collection === null || !$collection->isForm()) {
            return Response::notFound('Form not found');
        }

        $repo = new FormSubmissionRepository($this->app->db);
        $ipHash = $this->ipHash($request);

        // Lightweight per-IP rate limit.
        if ($ipHash !== null && $repo->recentCountForIp($ipHash, 3600) >= self::RATE_LIMIT_PER_HOUR) {
            return $this->fail($request, $collection, ['Too many submissions. Try again later.'], [], 429);
        }

        // Honeypot — bots typically fill every input. A field named "_hp"
        // hidden via CSS catches them.
        if ((string) ($request->post['_hp'] ?? '') !== '') {
            // Pretend success — don't tip off the bot.
            return $this->success($request, $collection);
        }

        $values = [];
        $errors = [];
        foreach ($collection->fields() as $key => $field) {
            $raw = $request->post[$key] ?? null;
            if ($field->type() === 'boolean' && $raw === null) {
                $raw = false;
            }
            $values[$key] = $field->coerce($raw);
            $errors = array_merge($errors, $field->validate($values[$key]));
        }
        if ($errors !== []) {
            return $this->fail($request, $collection, $errors, $values, 422);
        }

        $repo->create(
            $collection->name,
            $values,
            $ipHash,
            $request->header('user-agent')
        );

        return $this->success($request, $collection);
    }

    /**
     * @param list<string> $errors
     * @param array<string,mixed> $values
     */
    private function fail(Request $request, \Pebblestack\Services\Collection $collection, array $errors, array $values, int $status): Response
    {
        // If submission came from an HTML form (Accept: text/html), re-render
        // a simple confirmation/error page. JSON requests get JSON back.
        if ($this->wantsJson($request)) {
            $body = json_encode(['ok' => false, 'errors' => $errors], JSON_UNESCAPED_SLASHES);
            return (new Response($body !== false ? $body : '{}', $status))
                ->setHeader('Content-Type', 'application/json; charset=utf-8');
        }
        $body = $this->app->view->render('@theme/form-result.twig', $this->context([
            'collection' => $collection,
            'ok'         => false,
            'errors'     => $errors,
            'values'     => $values,
        ]));
        return Response::html($body, $status);
    }

    private function success(Request $request, \Pebblestack\Services\Collection $collection): Response
    {
        if ($this->wantsJson($request)) {
            $body = json_encode(['ok' => true], JSON_UNESCAPED_SLASHES);
            return (new Response($body !== false ? $body : '{}', 200))
                ->setHeader('Content-Type', 'application/json; charset=utf-8');
        }
        $body = $this->app->view->render('@theme/form-result.twig', $this->context([
            'collection' => $collection,
            'ok'         => true,
            'errors'     => [],
            'values'     => [],
        ]));
        return Response::html($body);
    }

    private function wantsJson(Request $request): bool
    {
        $accept = (string) ($request->header('accept') ?? '');
        return str_contains($accept, 'application/json') && !str_contains($accept, 'text/html');
    }

    private function ipHash(Request $request): ?string
    {
        $ip = (string) ($request->server['REMOTE_ADDR'] ?? '');
        if ($ip === '') {
            return null;
        }
        // Hash with a per-install salt so the same IP isn't directly recoverable.
        $salt = (string) ($this->app->db->fetchOne("SELECT value FROM settings WHERE key = 'installed_at'")['value'] ?? 'pebblestack');
        return hash('sha256', $ip . '|' . $salt);
    }

    /**
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function context(array $extra): array
    {
        $row = $this->app->db->fetchOne("SELECT value FROM settings WHERE key = 'site_name'");
        $siteName = $row !== null ? (string) $row['value'] : 'Pebblestack';
        return array_merge([
            'site' => ['name' => $siteName, 'url' => ''],
            'nav'  => [],
        ], $extra);
    }
}

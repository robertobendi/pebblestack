<?php

declare(strict_types=1);

namespace Pebblestack\Controllers\Admin;

use Pebblestack\Core\Auth;
use Pebblestack\Core\Request;
use Pebblestack\Core\Response;

final class SettingsController extends AdminController
{
    public function show(Request $request): Response
    {
        if ($block = $this->guard($request)) return $block;
        return $this->renderSettings([], []);
    }

    public function updateSite(Request $request): Response
    {
        // Site rename is admin-only; password change is allowed for any user
        // and gated separately below.
        if ($block = $this->guard($request, 'admin', 'admin')) return $block;
        $this->app->csrf->check($request);

        $name = trim((string) $request->input('site_name', ''));
        if ($name === '') {
            return $this->renderSettings(['Site name cannot be empty.'], []);
        }

        $this->app->settings->set('site_name', $name);
        $this->app->session->flash('success', 'Site settings saved.');
        return Response::redirect('/admin/settings');
    }

    public function updatePassword(Request $request): Response
    {
        $user = $this->app->auth->user();
        if ($user === null) {
            return Response::redirect('/admin/login');
        }
        $this->app->csrf->check($request);

        $current = (string) $request->input('current_password', '');
        $next    = (string) $request->input('new_password', '');
        $confirm = (string) $request->input('new_password_confirm', '');

        $errors = [];
        $row = $this->app->db->fetchOne('SELECT password_hash FROM users WHERE id = :id', ['id' => $user->id]);
        if ($row === null || !password_verify($current, (string) $row['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        }
        if (strlen($next) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        }
        if ($next !== $confirm) {
            $errors[] = 'New passwords do not match.';
        }
        if ($errors !== []) {
            return $this->renderSettings([], $errors);
        }

        $this->app->db->run(
            'UPDATE users SET password_hash = :h, updated_at = :t WHERE id = :id',
            ['h' => Auth::hash($next), 't' => time(), 'id' => $user->id]
        );
        $this->app->session->regenerate();
        $this->app->session->flash('success', 'Password updated.');
        return Response::redirect('/admin/settings');
    }

    /**
     * @param list<string> $siteErrors
     * @param list<string> $passwordErrors
     */
    private function renderSettings(array $siteErrors, array $passwordErrors): Response
    {
        return $this->render('@admin/settings.twig', [
            'site_errors'     => $siteErrors,
            'password_errors' => $passwordErrors,
        ], $siteErrors === [] && $passwordErrors === [] ? 200 : 422);
    }
}

<?php

declare(strict_types=1);

namespace Pebblestack\Controllers\Admin;

use Pebblestack\Core\Request;
use Pebblestack\Core\Response;
use Pebblestack\Services\LoginThrottle;

final class AuthController extends AdminController
{
    public function showLogin(Request $request): Response
    {
        if ($this->app->auth->check()) {
            return Response::redirect('/admin');
        }
        return $this->render('@admin/login.twig', [
            'error' => null,
            'email' => '',
        ]);
    }

    public function login(Request $request): Response
    {
        $this->app->csrf->check($request);
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        $throttle = new LoginThrottle($this->app->db);
        $ipHash = $this->app->settings->ipHash($request->clientIp());
        if ($ipHash !== null && $throttle->tooManyAttempts($ipHash)) {
            return $this->render('@admin/login.twig', [
                'error' => 'Too many failed attempts. Wait a few minutes and try again.',
                'email' => $email,
            ], 429);
        }

        $user = $this->app->auth->attempt($email, $password);
        if ($user === null) {
            if ($ipHash !== null) {
                $throttle->recordFailure($ipHash);
            }
            return $this->render('@admin/login.twig', [
                'error' => 'Email or password is incorrect.',
                'email' => $email,
            ], 401);
        }
        if ($ipHash !== null) {
            $throttle->clear($ipHash);
        }
        $this->app->session->flash('success', 'Welcome back, ' . $user->name . '.');
        return Response::redirect('/admin');
    }

    public function logout(Request $request): Response
    {
        $this->app->csrf->check($request);
        $this->app->auth->logout();
        return Response::redirect('/admin/login');
    }
}

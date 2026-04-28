<?php

declare(strict_types=1);

namespace Pebblestack\Controllers\Admin;

use Pebblestack\Core\App;
use Pebblestack\Core\Request;
use Pebblestack\Core\Response;

final class AuthController
{
    public function __construct(private readonly App $app) {}

    public function showLogin(Request $request): Response
    {
        if ($this->app->auth->check()) {
            return Response::redirect('/admin');
        }
        $body = $this->app->view->render('@admin/login.twig', [
            'site_name' => $this->siteName(),
            'error'     => null,
            'email'     => '',
        ]);
        return Response::html($body);
    }

    public function login(Request $request): Response
    {
        $this->app->csrf->check($request);
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        $user = $this->app->auth->attempt($email, $password);
        if ($user === null) {
            $body = $this->app->view->render('@admin/login.twig', [
                'site_name' => $this->siteName(),
                'error'     => 'Email or password is incorrect.',
                'email'     => $email,
            ]);
            return Response::html($body, 401);
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

    private function siteName(): string
    {
        $row = $this->app->db->fetchOne("SELECT value FROM settings WHERE key = 'site_name'");
        return $row !== null ? (string) $row['value'] : 'Pebblestack';
    }
}

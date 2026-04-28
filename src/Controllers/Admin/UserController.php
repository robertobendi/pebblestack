<?php

declare(strict_types=1);

namespace Pebblestack\Controllers\Admin;

use Pebblestack\Core\App;
use Pebblestack\Core\Auth;
use Pebblestack\Core\Request;
use Pebblestack\Core\Response;
use Pebblestack\Services\UserRepository;

final class UserController
{
    private UserRepository $repo;

    public function __construct(private readonly App $app)
    {
        $this->repo = new UserRepository($app->db);
    }

    public function index(Request $request): Response
    {
        if ($block = $this->app->auth->guard('admin')) return $block;
        return $this->renderIndex();
    }

    public function create(Request $request): Response
    {
        if ($block = $this->app->auth->guard('admin')) return $block;
        return $this->renderForm(null, ['name' => '', 'email' => '', 'role' => 'editor'], []);
    }

    public function store(Request $request): Response
    {
        if ($block = $this->app->auth->guard('admin')) return $block;
        $this->app->csrf->check($request);

        $name = trim((string) $request->input('name', ''));
        $email = trim((string) $request->input('email', ''));
        $role = (string) $request->input('role', 'editor');
        $password = (string) $request->input('password', '');

        $errors = $this->validate($name, $email, $role);
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($errors === [] && $this->repo->findByEmail($email) !== null) {
            $errors[] = 'A user with that email already exists.';
        }
        if ($errors !== []) {
            return $this->renderForm(null, ['name' => $name, 'email' => $email, 'role' => $role], $errors);
        }

        $this->repo->create($email, $name, Auth::hash($password), $role);
        $this->app->session->flash('success', 'User created.');
        return Response::redirect('/admin/users');
    }

    public function edit(Request $request): Response
    {
        if ($block = $this->app->auth->guard('admin')) return $block;
        $user = $this->repo->find((int) $request->param('id'));
        if ($user === null) {
            return Response::notFound('User not found');
        }
        return $this->renderForm($user, ['name' => $user->name, 'email' => $user->email, 'role' => $user->role], []);
    }

    public function update(Request $request): Response
    {
        if ($block = $this->app->auth->guard('admin')) return $block;
        $this->app->csrf->check($request);
        $user = $this->repo->find((int) $request->param('id'));
        if ($user === null) {
            return Response::notFound('User not found');
        }

        $name = trim((string) $request->input('name', ''));
        $email = trim((string) $request->input('email', ''));
        $role = (string) $request->input('role', $user->role);

        $errors = $this->validate($name, $email, $role);
        $existing = $this->repo->findByEmail($email);
        if ($errors === [] && $existing !== null && $existing->id !== $user->id) {
            $errors[] = 'A user with that email already exists.';
        }
        // Guard the last-admin invariant: don't let an admin demote themselves
        // if they're the only admin remaining.
        $current = $this->app->auth->user();
        if ($user->isAdmin() && $role !== 'admin'
            && $current !== null && $current->id === $user->id
            && $this->repo->countAdmins() <= 1
        ) {
            $errors[] = 'Cannot demote yourself: you are the only admin.';
        }
        if ($errors !== []) {
            return $this->renderForm($user, ['name' => $name, 'email' => $email, 'role' => $role], $errors);
        }

        $this->repo->update($user->id, $email, $name, $role);
        $this->app->session->flash('success', 'User updated.');
        return Response::redirect('/admin/users/' . $user->id);
    }

    public function resetPassword(Request $request): Response
    {
        if ($block = $this->app->auth->guard('admin')) return $block;
        $this->app->csrf->check($request);
        $user = $this->repo->find((int) $request->param('id'));
        if ($user === null) {
            return Response::notFound('User not found');
        }
        $password = (string) $request->input('password', '');
        if (strlen($password) < 8) {
            return $this->renderForm(
                $user,
                ['name' => $user->name, 'email' => $user->email, 'role' => $user->role],
                ['New password must be at least 8 characters.']
            );
        }
        $this->repo->setPassword($user->id, Auth::hash($password));
        $this->app->session->flash('success', 'Password reset for ' . $user->name . '.');
        return Response::redirect('/admin/users/' . $user->id);
    }

    public function destroy(Request $request): Response
    {
        if ($block = $this->app->auth->guard('admin')) return $block;
        $this->app->csrf->check($request);
        $user = $this->repo->find((int) $request->param('id'));
        if ($user === null) {
            return Response::notFound('User not found');
        }
        $current = $this->app->auth->user();
        if ($current !== null && $current->id === $user->id) {
            $this->app->session->flash('success', 'You cannot delete your own account.');
            return Response::redirect('/admin/users/' . $user->id);
        }
        if ($user->isAdmin() && $this->repo->countAdmins() <= 1) {
            $this->app->session->flash('success', 'Cannot delete the only admin.');
            return Response::redirect('/admin/users/' . $user->id);
        }
        $this->repo->delete($user->id);
        $this->app->session->flash('success', 'User deleted.');
        return Response::redirect('/admin/users');
    }

    /** @return list<string> */
    private function validate(string $name, string $email, string $role): array
    {
        $errors = [];
        if ($name === '') {
            $errors[] = 'Name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email is not valid.';
        }
        if (!isset(Auth::ROLE_RANK[$role])) {
            $errors[] = 'Role must be admin, editor, or viewer.';
        }
        return $errors;
    }

    private function renderIndex(): Response
    {
        $body = $this->app->view->render('@admin/users/index.twig', [
            'users'       => $this->repo->listAll(),
            'collections' => $this->app->collections->list(),
            'site_name'   => $this->siteName(),
        ]);
        return Response::html($body);
    }

    /**
     * @param array{name:string,email:string,role:string} $values
     * @param list<string> $errors
     */
    private function renderForm(?\Pebblestack\Models\User $user, array $values, array $errors): Response
    {
        $body = $this->app->view->render('@admin/users/form.twig', [
            'user_obj'    => $user,
            'values'      => $values,
            'errors'      => $errors,
            'collections' => $this->app->collections->list(),
            'site_name'   => $this->siteName(),
        ]);
        return Response::html($body, $errors === [] ? 200 : 422);
    }

    private function siteName(): string
    {
        $row = $this->app->db->fetchOne("SELECT value FROM settings WHERE key = 'site_name'");
        return $row !== null ? (string) $row['value'] : 'Pebblestack';
    }
}

<?php

namespace App\Controllers;

use App\Models\AuthToken;
use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserController
{
    private const ALLOWED_ROLES = ['gestor', 'admin'];

    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];

        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            return $this->json($response, [
                'success' => false,
                'message' => 'Todos los campos son obligatorios'
            ], 400);
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json($response, [
                'success' => false,
                'message' => 'El email no es válido'
            ], 400);
        }

        if (!empty($data['role']) && !in_array($data['role'], self::ALLOWED_ROLES, true)) {
            return $this->json($response, [
                'success' => false,
                'message' => 'Rol no permitido'
            ], 400);
        }

        $existingUser = User::where('email', $data['email'])->first();
        if ($existingUser) {
            return $this->json($response, [
                'success' => false,
                'message' => 'El email ya está registrado'
            ], 400);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role' => $data['role'] ?? 'gestor',
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $this->json($response, [
            'success' => true,
            'message' => 'Usuario registrado exitosamente',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => (bool) $user->is_active,
            ],
        ], 201);
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];

        if (empty($data['email']) || empty($data['password'])) {
            return $this->json($response, [
                'success' => false,
                'message' => 'Email y contraseña son obligatorios'
            ], 400);
        }

        $user = User::where('email', $data['email'])->first();

        if (!$user || !password_verify($data['password'], $user->password) || !$user->is_active) {
            return $this->json($response, [
                'success' => false,
                'message' => 'Credenciales incorrectas o usuario inactivo'
            ], 401);
        }

        AuthToken::where('user_id', $user->id)->delete();

        $token = AuthToken::generateToken();

        AuthToken::create([
            'user_id' => $user->id,
            'token' => $token
        ]);

        return $this->json($response, [
            'success' => true,
            'message' => 'Inicio de sesión exitoso',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    public function logout(Request $request, Response $response): Response
    {
        $token = $request->getAttribute('token');

        AuthToken::where('token', $token)->delete();

        return $this->json($response, [
            'success' => true,
            'message' => 'Sesión cerrada exitosamente'
        ]);
    }

    public function listUsers(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');

        if ($user->role !== 'admin') {
            return $this->json($response, [
                'success' => false,
                'message' => 'Acceso denegado'
            ], 403);
        }

        $users = User::all();

        return $this->json($response, [
            'success' => true,
            'users' => $users
        ]);
    }

    public function updateUser(Request $request, Response $response, array $args): Response
    {
        $currentUser = $request->getAttribute('user');

        if ($currentUser->role !== 'admin') {
            return $this->json($response, [
                'success' => false,
                'message' => 'Acceso denegado'
            ], 403);
        }

        $userId = $args['id'];
        $data = $request->getParsedBody() ?? [];

        $user = User::find($userId);

        if (!$user) {
            return $this->json($response, [
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        if (isset($data['email']) && $data['email'] !== $user->email) {
            $exists = User::where('email', $data['email'])->where('id', '!=', $userId)->exists();
            if ($exists) {
                return $this->json($response, [
                    'success' => false,
                    'message' => 'El email ya está registrado en otro usuario'
                ], 400);
            }
            $user->email = $data['email'];
        }

        if (isset($data['name'])) {
            $user->name = $data['name'];
        }

        if (isset($data['role']) && in_array($data['role'], self::ALLOWED_ROLES, true)) {
            $user->role = $data['role'];
        }

        if (isset($data['password']) && $data['password'] !== '') {
            $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if (isset($data['is_active'])) {
            $user->is_active = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        $user->save();

        return $this->json($response, [
            'success' => true,
            'message' => 'Usuario actualizado exitosamente',
            'user' => $user
        ]);
    }

    public function deleteUser(Request $request, Response $response, array $args): Response
    {
        $currentUser = $request->getAttribute('user');

        if ($currentUser->role !== 'admin') {
            return $this->json($response, [
                'success' => false,
                'message' => 'Acceso denegado'
            ], 403);
        }

        $userId = $args['id'];

        $user = User::find($userId);

        if (!$user) {
            return $this->json($response, [
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $user->delete();

        return $this->json($response, [
            'success' => true,
            'message' => 'Usuario eliminado exitosamente'
        ]);
    }

    public function validateToken(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');

        return $this->json($response, [
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => (bool) $user->is_active,
            ],
        ]);
    }

    private function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}

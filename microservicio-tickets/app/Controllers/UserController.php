<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\User;
use App\Models\AuthToken;

class UserController
{
    public function register(Request $request, Response $response)
    {
        $data = $request->getParsedBody();
        
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Todos los campos son obligatorios'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $existingUser = User::where('email', $data['email'])->first();
        if ($existingUser) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'El email ya está registrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role' => $data['role'] ?? 'gestor'
        ]);
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Usuario registrado exitosamente',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ]
        ]));
        
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }
    
    public function login(Request $request, Response $response)
    {
        $data = $request->getParsedBody();
        
        if (empty($data['email']) || empty($data['password'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Email y contraseña son obligatorios'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $user = User::where('email', $data['email'])->first();
        
        if (!$user || !password_verify($data['password'], $user->password)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Credenciales incorrectas'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        
        AuthToken::where('user_id', $user->id)->delete();
        
        $token = AuthToken::generateToken();
        
        AuthToken::create([
            'user_id' => $user->id,
            'token' => $token
        ]);
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Inicio de sesión exitoso',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ]
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    public function logout(Request $request, Response $response)
    {
        $token = $request->getAttribute('token');
        
        AuthToken::where('token', $token)->delete();
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Sesión cerrada exitosamente'
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    public function listUsers(Request $request, Response $response)
    {
        $user = $request->getAttribute('user');
        
        if ($user->role !== 'admin') {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Acceso denegado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        
        $users = User::all();
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'users' => $users
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    public function updateUser(Request $request, Response $response, $args)
    {
        $currentUser = $request->getAttribute('user');
        
        if ($currentUser->role !== 'admin') {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Acceso denegado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        
        $userId = $args['id'];
        $data = $request->getParsedBody();
        
        $user = User::find($userId);
        
        if (!$user) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        if (isset($data['name'])) $user->name = $data['name'];
        if (isset($data['email'])) $user->email = $data['email'];
        if (isset($data['role'])) $user->role = $data['role'];
        if (isset($data['password'])) {
            $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        $user->save();
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente',
            'user' => $user
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    public function deleteUser(Request $request, Response $response, $args)
    {
        $currentUser = $request->getAttribute('user');
        
        if ($currentUser->role !== 'admin') {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Acceso denegado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        
        $userId = $args['id'];
        
        $user = User::find($userId);
        
        if (!$user) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        $user->delete();
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Usuario eliminado exitosamente'
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    public function validateToken(Request $request, Response $response)
    {
        $user = $request->getAttribute('user');
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ]
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
}
<?php

namespace App\Controllers;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TicketController
{
    private const STATUS = ['abierto', 'en_progreso', 'cerrado'];

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody() ?? [];

        if (!in_array($user->role, ['gestor', 'admin'], true)) {
            return $this->json($response, ['success' => false, 'message' => 'Acceso denegado'], 403);
        }

        if (empty($data['title']) || empty($data['description'])) {
            return $this->json($response, ['success' => false, 'message' => 'Título y descripción son obligatorios'], 400);
        }

        $ticket = Ticket::create([
            'title' => $data['title'],
            'description' => $data['description'],
            'creator_id' => $user->id,
            'assigned_admin_id' => $data['assigned_admin_id'] ?? null,
        ]);

        return $this->json($response, [
            'success' => true,
            'ticket' => $ticket,
            'message' => 'Ticket creado exitosamente'
        ], 201);
    }

    public function list(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $query = Ticket::with(['creator', 'assignedAdmin']);

        if ($user->role !== 'admin') {
            $query->where('creator_id', $user->id);
        } else {
            $filters = $request->getQueryParams();
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            if (!empty($filters['creator_id'])) {
                $query->where('creator_id', $filters['creator_id']);
            }
            if (!empty($filters['assigned_admin_id'])) {
                $query->where('assigned_admin_id', $filters['assigned_admin_id']);
            }
        }

        $tickets = $query->orderBy('created_at', 'desc')->get();

        return $this->json($response, ['success' => true, 'tickets' => $tickets]);
    }

    public function detail(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $ticket = Ticket::with(['creator', 'assignedAdmin', 'comments.user'])->find($args['id']);

        if (!$ticket) {
            return $this->json($response, ['success' => false, 'message' => 'Ticket no encontrado'], 404);
        }

        if ($user->role !== 'admin' && $ticket->creator_id !== $user->id) {
            return $this->json($response, ['success' => false, 'message' => 'No puedes ver este ticket'], 403);
        }

        return $this->json($response, ['success' => true, 'ticket' => $ticket]);
    }

    public function updateStatus(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody() ?? [];
        $ticket = Ticket::find($args['id']);

        if ($user->role !== 'admin') {
            return $this->json($response, ['success' => false, 'message' => 'Solo administradores pueden cambiar estados'], 403);
        }

        if (!$ticket) {
            return $this->json($response, ['success' => false, 'message' => 'Ticket no encontrado'], 404);
        }

        if (empty($data['status']) || !in_array($data['status'], self::STATUS, true)) {
            return $this->json($response, ['success' => false, 'message' => 'Estado inválido'], 400);
        }

        $ticket->status = $data['status'];
        $ticket->save();

        return $this->json($response, ['success' => true, 'message' => 'Estado actualizado', 'ticket' => $ticket]);
    }

    public function assign(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody() ?? [];
        $ticket = Ticket::find($args['id']);

        if ($user->role !== 'admin') {
            return $this->json($response, ['success' => false, 'message' => 'Solo administradores pueden asignar tickets'], 403);
        }

        if (!$ticket) {
            return $this->json($response, ['success' => false, 'message' => 'Ticket no encontrado'], 404);
        }

        if (empty($data['assigned_admin_id'])) {
            return $this->json($response, ['success' => false, 'message' => 'Debe indicar el administrador'], 400);
        }

        $admin = User::find($data['assigned_admin_id']);
        if (!$admin || $admin->role !== 'admin') {
            return $this->json($response, ['success' => false, 'message' => 'El usuario asignado debe ser administrador'], 400);
        }

        $ticket->assigned_admin_id = $admin->id;
        $ticket->save();

        return $this->json($response, ['success' => true, 'message' => 'Ticket asignado', 'ticket' => $ticket]);
    }

    public function comment(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody() ?? [];
        $ticket = Ticket::find($args['id']);

        if (!$ticket) {
            return $this->json($response, ['success' => false, 'message' => 'Ticket no encontrado'], 404);
        }

        $isOwner = $ticket->creator_id === $user->id;
        $isAdmin = $user->role === 'admin';

        if (!$isAdmin && !$isOwner) {
            return $this->json($response, ['success' => false, 'message' => 'No puedes comentar este ticket'], 403);
        }

        if (empty($data['comment'])) {
            return $this->json($response, ['success' => false, 'message' => 'El comentario es obligatorio'], 400);
        }

        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'comment' => $data['comment'],
        ]);

        return $this->json($response, [
            'success' => true,
            'message' => 'Comentario agregado',
            'comment' => $comment,
        ], 201);
    }

    private function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}

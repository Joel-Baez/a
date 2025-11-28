<?php

namespace App\Controllers;

use App\Models\Ticket;
use App\Models\TicketActividad;
use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TicketController
{
    private const ESTADOS = ['abierto', 'en_progreso', 'resuelto', 'cerrado'];

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody() ?? [];

        if (!in_array($user->role, ['gestor', 'admin'], true)) {
            return $this->json($response, ['success' => false, 'message' => 'Acceso denegado'], 403);
        }

        if (empty($data['titulo']) || empty($data['descripcion'])) {
            return $this->json($response, ['success' => false, 'message' => 'Título y descripción son obligatorios'], 400);
        }

        $ticket = Ticket::create([
            'titulo' => $data['titulo'],
            'descripcion' => $data['descripcion'],
            'gestor_id' => $user->id,
            'admin_id' => $data['admin_id'] ?? null,
        ]);

        $ticket->load(['gestor', 'admin']);

        return $this->json($response, [
            'success' => true,
            'ticket' => $ticket,
            'message' => 'Ticket creado exitosamente'
        ], 201);
    }

    public function list(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $query = Ticket::with(['gestor', 'admin']);

        if ($user->role !== 'admin') {
            $query->where('gestor_id', $user->id);
        } else {
            $filters = $request->getQueryParams();
            if (!empty($filters['estado'])) {
                $query->where('estado', $filters['estado']);
            }
            if (!empty($filters['gestor_id'])) {
                $query->where('gestor_id', $filters['gestor_id']);
            }
            if (!empty($filters['admin_id'])) {
                $query->where('admin_id', $filters['admin_id']);
            }
        }

        $tickets = $query->orderBy('created_at', 'desc')->get();

        return $this->json($response, ['success' => true, 'tickets' => $tickets]);
    }

    public function detail(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $ticket = Ticket::with(['gestor', 'admin', 'actividades.user'])->find($args['id']);

        if (!$ticket) {
            return $this->json($response, ['success' => false, 'message' => 'Ticket no encontrado'], 404);
        }

        if ($user->role !== 'admin' && $ticket->gestor_id !== $user->id) {
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

        if (empty($data['estado']) || !in_array($data['estado'], self::ESTADOS, true)) {
            return $this->json($response, ['success' => false, 'message' => 'Estado inválido'], 400);
        }

        $ticket->estado = $data['estado'];
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

        if (empty($data['admin_id'])) {
            return $this->json($response, ['success' => false, 'message' => 'Debe indicar el administrador'], 400);
        }

        $admin = User::find($data['admin_id']);
        if (!$admin || $admin->role !== 'admin') {
            return $this->json($response, ['success' => false, 'message' => 'El usuario asignado debe ser administrador'], 400);
        }

        $ticket->admin_id = $admin->id;
        $ticket->save();

        $ticket->load(['gestor', 'admin']);

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

        $isOwner = $ticket->gestor_id === $user->id;
        $isAdmin = $user->role === 'admin';

        if (!$isAdmin && !$isOwner) {
            return $this->json($response, ['success' => false, 'message' => 'No puedes comentar este ticket'], 403);
        }

        if (empty($data['mensaje'])) {
            return $this->json($response, ['success' => false, 'message' => 'El comentario es obligatorio'], 400);
        }

        $comentario = TicketActividad::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'mensaje' => $data['mensaje'],
        ]);

        $comentario->load('user');

        return $this->json($response, [
            'success' => true,
            'message' => 'Comentario agregado',
            'comentario' => $comentario,
        ], 201);
    }

    private function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}

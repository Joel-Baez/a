<?php

namespace App\Middleware;

use App\Models\AuthToken;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

class AuthMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader)) {
            return $this->unauthorized('Token no proporcionado');
        }

        $token = str_replace('Bearer ', '', $authHeader);

        $authToken = AuthToken::where('token', $token)->with('user')->first();

        if (!$authToken || !$authToken->user || !$authToken->user->is_active) {
            return $this->unauthorized('Token inválido, expirado o usuario inactivo');
        }

        $request = $request->withAttribute('user', $authToken->user);
        $request = $request->withAttribute('token', $token);

        return $handler->handle($request);
    }

    private function unauthorized(string $message): Response
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message,
        ]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }
}

<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Illuminate\Database\Capsule\Manager as Capsule;
use App\Controllers\UserController;
use App\Middleware\AuthMiddleware;

require __DIR__ . '/../vendor/autoload.php';

// Configuración de Eloquent
$capsule = new Capsule;

$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => '127.0.0.1',
    'database'  => 'practicaparcial',
    'username'  => 'root',
    'password'  => '',
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

// Crear aplicación Slim
$app = AppFactory::create();

$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write('Microservicio de usuarios activo');
    return $response;
});

// Configuración de CORS
$app->options('/{routes:.+}', fn($req, $res) => $res);

$app->add(function (Request $request, $handler) {
    $origin = $request->getHeaderLine('Origin') ?: '*';
    $response = $handler->handle($request);
    $response = $response
        ->withHeader('Access-Control-Allow-Origin', $origin)
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Credentials', 'true');

    if ($request->getMethod() === 'OPTIONS') {
        return $response->withStatus(200);
    }

    return $response;
});

// Middleware de errores
$app->addErrorMiddleware(true, true, true);

// Rutas públicas (sin autenticación)
$app->post('/register', [UserController::class, 'register']);
$app->post('/login', [UserController::class, 'login']);

// Rutas protegidas (con middleware de autenticación)
$app->group('', function ($group) {
    $group->post('/logout', [UserController::class, 'logout']);
    $group->get('/validate', [UserController::class, 'validateToken']);
    $group->get('/users', [UserController::class, 'listUsers']);
    $group->put('/users/{id}', [UserController::class, 'updateUser']);
    $group->delete('/users/{id}', [UserController::class, 'deleteUser']);
})->add(new AuthMiddleware());

$app->run();
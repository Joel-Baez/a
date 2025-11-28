<?php

use Dotenv\Dotenv;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Illuminate\Database\Capsule\Manager as Capsule;
use App\Controllers\UserController;
use App\Middleware\AuthMiddleware;

autoload();

// Inicializar entorno
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Configuración de Eloquent
$database = require dirname(__DIR__) . '/config/database.php';
$capsule = new Capsule();
$capsule->addConnection($database);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Crear aplicación Slim
$app = AppFactory::create();
$app->addBodyParsingMiddleware();

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

function autoload(): void
{
    require_once __DIR__ . '/../vendor/autoload.php';
}

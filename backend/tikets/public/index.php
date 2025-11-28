<?php

use Dotenv\Dotenv;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Illuminate\Database\Capsule\Manager as Capsule;
use App\Controllers\TicketController;
use App\Middleware\AuthMiddleware;

autoload();

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$database = require dirname(__DIR__) . '/config/database.php';
$capsule = new Capsule();
$capsule->addConnection($database);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write('Microservicio de tickets activo');
    return $response;
});

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

$app->addErrorMiddleware(true, true, true);

$app->group('', function ($group) {
    $group->post('/tickets', [TicketController::class, 'create']);
    $group->get('/tickets', [TicketController::class, 'list']);
    $group->get('/tickets/{id}', [TicketController::class, 'detail']);
    $group->put('/tickets/{id}/status', [TicketController::class, 'updateStatus']);
    $group->put('/tickets/{id}/assign', [TicketController::class, 'assign']);
    $group->post('/tickets/{id}/comments', [TicketController::class, 'comment']);
})->add(new AuthMiddleware());

$app->run();

function autoload(): void
{
    require_once __DIR__ . '/../vendor/autoload.php';
}

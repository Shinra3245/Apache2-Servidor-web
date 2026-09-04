<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header(
    'Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With'
);
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Router.php';
require_once __DIR__ . '/../core/JsonResponse.php';
require_once __DIR__ . '/../core/AuthMiddleware.php';
require_once __DIR__ . '/../resources/v1/UserResource.php';
require_once __DIR__ . '/../resources/v1/ProductResource.php';
require_once __DIR__ . '/../resources/v2/AuthResource.php';

try {
    $basePath = dirname($_SERVER['SCRIPT_NAME']);
    $router = new Router('v2', $basePath);
    $db = (new Database())->getConnection();

    $authMiddleware = new AuthMiddleware($db);
    $authResource = new AuthResource($db, $authMiddleware);
    $userResource = new UserResource($db);
    $productResource = new ProductResource($db);
    $protected = [$authMiddleware, 'handle'];

    $router->addRoute('POST', '/login', [$authResource, 'login']);
    $router->addRoute(
        'POST',
        '/logout',
        [$authResource, 'logout'],
        $protected
    );
    $router->addRoute(
        'GET',
        '/me',
        [$authResource, 'me'],
        $protected
    );

    $router->addRoute('GET', '/users', [$userResource, 'index'], $protected);
    $router->addRoute('GET', '/users/{id}', [$userResource, 'show'], $protected);
    $router->addRoute('POST', '/users', [$userResource, 'store'], $protected);
    $router->addRoute('PUT', '/users/{id}', [$userResource, 'update'], $protected);
    $router->addRoute('DELETE', '/users/{id}', [$userResource, 'destroy'], $protected);

    $router->addRoute('GET', '/products', [$productResource, 'index'], $protected);
    $router->addRoute('GET', '/products/{id}', [$productResource, 'show'], $protected);
    $router->addRoute('POST', '/products', [$productResource, 'store'], $protected);
    $router->addRoute('PUT', '/products/{id}', [$productResource, 'update'], $protected);
    $router->addRoute('DELETE', '/products/{id}', [$productResource, 'destroy'], $protected);

    $router->dispatch();
} catch (Throwable $e) {
    error_log($e->__toString());

    JsonResponse::send(500, [
        'error' => 'internal_server_error',
        'message' => 'Ocurrió un error interno en el servidor'
    ]);
}

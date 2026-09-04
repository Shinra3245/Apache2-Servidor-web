<?php

require_once __DIR__ . '/JsonResponse.php';

class Router
{
    private $routes = [];
    private $version;
    private $basePath;

    public function __construct($version = 'v1', $basePath = '')
    {
        $this->version = $version;
        $this->basePath = rtrim($basePath, '/');
    }

    public function addRoute($method, $path, $handler, $middleware = null)
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => "/api/{$this->version}" . $path,
            'handler' => $handler,
            'middleware' => $middleware
        ];
    }

    public function dispatch()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if (!empty($this->basePath) && strpos($uri, $this->basePath) === 0) {
            $uri = substr($uri, strlen($this->basePath));
        }

        // Asegurar que la URI comience con 
        $uri = '/' . ltrim($uri, '/');

        foreach ($this->routes as $route) {
            $pattern = preg_replace('/\{[a-zA-Z0-9_]+\}/', '([a-zA-Z0-9_-]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if ($route['method'] === $method && preg_match($pattern, $uri, $matches)) {
                array_shift($matches);

                if (is_callable($route['middleware'])) {
                    call_user_func($route['middleware']);
                }

                return call_user_func_array($route['handler'], $matches);
            }
        }

        JsonResponse::send(404, [
            'message' => 'Ruta no encontrada',
            'uri' => $uri
        ]);
    }
}

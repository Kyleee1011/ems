<?php
namespace App\Api;

use PDO;

class Router
{
    private $routes = [];
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function get($path, $handler)
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post($path, $handler)
    {
        $this->addRoute('POST', $path, $handler);
    }

    private function addRoute($method, $path, $handler)
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
        ];
    }

    public function dispatch($uri, $method)
    {
        // Remove query string and /api prefix
        $path = parse_url($uri, PHP_URL_PATH);
        $path = str_replace('/ems/api', '', $path); // Adjust based on base URL
        if ($path === '' || $path === '/') $path = '/';

        foreach ($this->routes as $route) {
            if ($route['path'] === $path && $route['method'] === $method) {
                $controllerClass = $route['handler'][0];
                $action = $route['handler'][1];
                
                // Instantiate Controller
                $controller = new $controllerClass($this->pdo);
                return $controller->$action();
            }
        }

        header("HTTP/1.0 404 Not Found");
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Endpoint Not Found', 'path' => $path]);
        exit;
    }
}

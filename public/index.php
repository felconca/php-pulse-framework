<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../routes/web.php';

use Dotenv\Dotenv;
use App\Routes\Route;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$routes = Route::all();

$requestPath = isset($_GET['path']) ? trim($_GET['path'], '/') : '';
$segments = $requestPath ? explode('/', $requestPath) : [];
$method = $_SERVER['REQUEST_METHOD'];

// Route matching logic
$matched = false;
$params = [];
$routeMiddleware = [];
$controller = null;
$handler = null;

foreach ($routes as $route) {
    list($routeMethod, $pattern, $handlerDef, $middleware) = $route;
    if ($method !== $routeMethod) {
        continue;
    }

    $patternSegments = explode('/', trim($pattern, '/'));
    if (count($patternSegments) === count($segments)) {
        $match = true;
        $params = [];
        for ($i = 0; $i < count($patternSegments); $i++) {
            if (preg_match('/^{.*}$/', $patternSegments[$i])) {
                $paramName = trim($patternSegments[$i], '{}');
                $params[$paramName] = $segments[$i];
            } elseif ($patternSegments[$i] !== $segments[$i]) {
                $match = false;
                break;
            }
        }
        if ($match) {
            $matched = true;
            list($controllerName, $methodName) = explode('@', $handlerDef);
            $controllerClass = "App\\Controllers\\$controllerName";
            if (!class_exists($controllerClass)) {
                header("HTTP/1.1 500 Internal Server Error");
                echo json_encode(['error' => "Controller $controllerClass not found"]);
                exit;
            }
            $controller = new $controllerClass();
            if (!method_exists($controller, $methodName)) {
                header("HTTP/1.1 500 Internal Server Error");
                echo json_encode(['error' => "Method $methodName not found in $controllerClass"]);
                exit;
            }
            $handler = function () use ($controller, $methodName, $params) {
                call_user_func_array([$controller, $methodName], [$params]);
            };
            $routeMiddleware = $middleware;
            break;
        }
    }
}

if (!$matched) {
    header("HTTP/1.1 404 Not Found");
    echo json_encode(['error' => 'Route not found']);
    exit;
}

// Apply middleware with the controller instance
foreach ($routeMiddleware as $mw) {
    $handler = function () use ($mw, $controller, $handler) {
        $mw->handle($controller, $handler); // Pass controller, not closure
    };
}

$handler();
// upto here

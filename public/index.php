<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Http/Http.php';

use Core\Requests\Request;
use Dotenv\Dotenv;
use Core\Routes\Route;
use Includes\Rest;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Load all defined routes
$routes = Route::all();

// Read incoming request info
$requestPath = isset($_GET['path']) ? trim($_GET['path'], '/') : '';
$segments = $requestPath ? explode('/', $requestPath) : [];
$method = $_SERVER['REQUEST_METHOD'];

$matched       = false;
$params        = [];
$routeMiddleware = [];
$controller    = null;
$handler       = null;

// ROUTE MATCHING LOOP
foreach ($routes as $route) {
    list($routeMethod, $pattern, $handlerDef, $middleware) = $route;

    if ($method !== $routeMethod) {
        continue;
    }

    // Pattern matching
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

            // Extract controller + method
            list($controllerName, $methodName) = explode('@', $handlerDef);
            $controllerClass = "App\\Controllers\\$controllerName";

            // Validate controller
            if (!class_exists($controllerClass)) {
                header("HTTP/1.1 500 Internal Server Error");
                echo json_encode(['error' => "Controller $controllerClass not found"]);
                exit;
            }

            $controller = new $controllerClass();

            // Validate method
            if (!method_exists($controller, $methodName)) {
                header("HTTP/1.1 500 Internal Server Error");
                echo json_encode(['error' => "Method $methodName not found in $controllerClass"]);
                exit;
            }

            // Build REST helper (request + response)
            $rest = new Rest();
            $requestData = $rest->inputs();
            $request = new Request($requestData);
            $paramRequest = new Request($params);  // <-- wrap params as Request

            $response = function ($data, $status = 200) use ($rest) {
                return $rest->response($data, $status);
            };

            // NEW HANDLER SIGNATURE → ($request, $response, $params)
            // $handler = function () use ($controller, $methodName, $requestData, $response, $params) {
            //     return $controller->$methodName($requestData, $response, $params);
            // };
            $handler = function () use ($controller, $methodName, $request, $response, $paramRequest) {
                return $controller->$methodName($request, $response, $paramRequest);
            };



            $routeMiddleware = $middleware;
            break;
        }
    }
}

// Route not found
if (!$matched) {
    header("HTTP/1.1 404 Not Found");
    echo json_encode(['error' => 'Route not found']);
    exit;
}

// APPLY MIDDLEWARE (chain)
foreach ($routeMiddleware as $mw) {
    $handler = function () use ($mw, $controller, $handler) {
        return $mw->handle($controller, $handler);
    };
}

// Execute final handler
$handler();

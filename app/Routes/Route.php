<?php

namespace App\Routes;

class Route
{
    private static array $routes = [];
    private static array $groupStack = []; // Stack to track nested groups

    // Add GET route
    public static function get(string $path, string $handler, array $middleware = [])
    {
        self::addRoute('GET', $path, $handler, $middleware);
    }

    // Add POST route
    public static function post(string $path, string $handler, array $middleware = [])
    {
        self::addRoute('POST', $path, $handler, $middleware);
    }

    public static function put(string $path, string $handler, array $middleware = [])
    {
        self::addRoute('PUT', $path, $handler, $middleware);
    }

    public static function delete(string $path, string $handler, array $middleware = [])
    {
        self::addRoute('DELETE', $path, $handler, $middleware);
    }

    // Return all routes
    public static function all(): array
    {
        return self::$routes;
    }

    // Grouping function
    public static function group(array $attributes, callable $callback)
    {
        // Push current group attributes to stack
        self::$groupStack[] = $attributes;

        // Execute the callback (user defines routes inside)
        $callback();

        // Pop after callback ends
        array_pop(self::$groupStack);
    }

    // Internal route addition considering current group(s)
    private static function addRoute(string $method, string $path, string $handler, array $middleware)
    {
        $prefix = '';
        $groupMiddleware = [];

        // Apply all nested group attributes
        foreach (self::$groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            if (isset($group['middleware']) && is_array($group['middleware'])) {
                $groupMiddleware = array_merge($groupMiddleware, $group['middleware']);
            }
        }

        // Combine group prefix and route path
        $fullPath = trim($prefix . '/' . trim($path, '/'), '/');

        // Merge middleware
        $allMiddleware = array_merge($groupMiddleware, $middleware);

        self::$routes[] = [$method, $fullPath, $handler, $allMiddleware];
    }
}

<?php

namespace App\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthToken
{
    private $jwtSecret;

    public function __construct()
    {
        $this->jwtSecret = $_ENV['JWT_SECRET'] ?? 'default-secret';
    }

    public function handle($controller, $next)
    {
        $headers = getallheaders();
        $token = null;

        if (isset($headers['X-Auth-Token'])) {
            $token = $headers['X-Auth-Token'];
        } elseif (isset($headers['Authorization'])) {
            if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
                $token = $matches[1];
            }
        }

        if (!$token) {
            return $controller->response(['error' => 'Token required'], 401);
        }

        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            $controller->setUserData((array)$decoded);
            return $next();
        } catch (\Exception $e) {
            return $controller->response(['error' => 'Invalid token: ' . $e->getMessage()], 401);
        }
    }
}

<?php

namespace Core\Middleware;

class AuthSession
{
    private $sessionKey;

    public function __construct($sessionKey = "user")
    {
        $this->sessionKey = $sessionKey;
    }

    public function handle($controller, $next)
    {
        session_name('GMED_INVENTORY_SESS'); // unique, branded
        // ✅ Secure session settings before starting
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'secure' => true,       // only send cookie via HTTPS
                'httponly' => true,     // JS can’t read it
                'samesite' => 'Strict'  // prevent CSRF
            ]);
            session_start();
        }

        // 🔒 Check if the session key exists
        if (!isset($_SESSION[$this->sessionKey])) {
            return $controller->response([
                "status" => 401,
                "error"  => "Unauthorized - please login"
            ], 401);
        }

        // ✅ Optionally attach user data to controller (like JWT middleware)
        $controller->setUserData($_SESSION[$this->sessionKey]);

        // ✅ Continue to next handler (controller action)
        return $next();
    }
}

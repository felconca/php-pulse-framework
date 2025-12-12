<?php
namespace App\Controllers;

use Includes\Rest;
use App\Database\Database;
use App\Requests\RequestValidator;
use Firebase\J\WT\JWT;
use Firebase\JWT\Key;

class AuthController extends Rest
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Manila');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token, Origin, Authorization');
        header("Access-Control-Allow-Credentials: true");

        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            http_response_code(200);
            exit();
        }

        parent::__construct();
        
        $this->jwtSecret = $_ENV['JWT_SECRET'] ?? 'default-secret';
        $this->db = new Database();
    }

    public function index()
    {
        $this->response(['message' => 'AuthController index'], 200);
    }
}
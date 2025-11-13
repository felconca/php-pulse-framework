<?php

namespace App\Controllers;

use Includes\Rest;
use App\Database\Database;
use App\Requests\RequestValidator;
use Firebase\J\WT\JWT;
use Firebase\JWT\Key;

class AppController extends Rest
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
        $this->jwtSecret = $_ENV['JWT_SECRET'] ? $_ENV['JWT_SECRET'] : 'default-secret'; // Fallback for testing

        $this->db = new Database();
    }

    public function index()
    {

        $users = $this->db->marsdb()
            ->SELECT(['id', 'name', 'username'], 'usersimple')
            ->WHERE(['deleted' => 0])
            ->ORDERBY('id', 'DESC')
            ->LIMIT(10)
            ->get();

        foreach ($users as $user) {
            $rows[] = $user;
        }
        $this->response(['data' => $rows], 200);

        $singleUser = $this->db->getConnection("marsdb")
            ->SELECT('*', 'usersimple')
            ->WHERE(['id' => 1])
            ->first();

        // // echo $singleUser->email;
        $this->response(['message' => $singleUser->username], 200);

        // $conn = $this->db->marsdb;

        // // LEFT JOIN
        // $users = $conn->SELECT('u.id, u.name, p.photo', 'users u')
        //     ->LEFTJOIN('profiles p', 'p.user_id = u.id')
        //     ->WHERE(['u.active' => 1])
        //     ->get();

        // // RIGHT JOIN
        // $users = $conn->SELECT('*', 'users u')
        //     ->RIGHTJOIN('orders o', 'o.user_id = u.id')
        //     ->get();

        // // CROSS JOIN
        // $users = $conn->SELECT('*', 'users u')
        //     ->CROSSJOIN('roles r', 'r.id = u.role_id')
        //     ->get();

        // // Plain JOIN
        // $users = $conn->SELECT('*', 'users u')
        //     ->JOIN('profiles p', 'p.user_id = u.id')
        //     ->get();
    }
    public function store()
    {
        // // Option 1: classic
        // $conn = $this->db->getConnection();
        // $conn = $this->db->getConnection("marsdb");

        // // Option 2: magic property
        // $conn = $this->db->marsdb;

        // // Option 3: magic method
        // $conn = $this->db->marsdb();

        $conn = $this->db->marsdb;

        // Simple insert
        $insertedId = $conn->insert('usersimple', [
            'name' => 'John Doe',
            'username' => 'john@example.com',
            'password' => password_hash('123456', PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $this->response(["data" => $insertedId], 200);
    }
    public function update()
    {
        $conn = $this->db->marsdb;

        // UPDATE executes immediately when WHERE() is called
        $rowsUpdated = $conn->update('usersimple', [
            'username' => 'updated@example.com',
            'password' => password_hash('123456', PASSWORD_DEFAULT),
            'updated_at' => date('Y-m-d H:i:s')
        ])->WHERE(['id' => 1]);
        $this->response(["data" => $rowsUpdated], 200);
    }
    public function delete()
    {
        $conn = $this->db->marsdb;

        // DELETE executes immediately with WHERE
        $rowsDeleted = $conn->delete('usersimple')
            ->WHERE(['id' => 1]);

        echo "Rows deleted: $rowsDeleted";

        // UPDATE still works
        $rowsUpdated = $conn->update('usersimple', ['username' => 'new@example.com'])
            ->WHERE(['id' => 2]);

        // SELECT still works
        $users = $conn->SELECT('*', 'usersimple')
            ->WHERE(['deleted' => 0])
            ->get();
    }
}

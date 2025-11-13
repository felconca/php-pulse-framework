<?php

namespace App\Database;

class Database
{
    private $connections = [];

    public function __construct()
    {
        $host = $_ENV['DB_HOST'] ? $_ENV['DB_HOST'] : 'localhost';
        $user = $_ENV['DB_USER'] ? $_ENV['DB_USER'] : 'root';
        $password = $_ENV['DB_PASSWORD'] ? $_ENV['DB_PASSWORD'] : '';
        $dbNames = explode(',', $_ENV['DB_CONNECTIONS'] ? $_ENV['DB_CONNECTIONS'] : '');

        foreach ($dbNames as $dbName) {
            $this->connections[$dbName] = new \mysqli($host, $user, $password, $dbName);
            if ($this->connections[$dbName]->connect_error) {
                $this->logError("Connection failed to $dbName: " . $this->connections[$dbName]->connect_error);
                $this->connections[$dbName] = null; // Set to null to indicate failure
            }
        }
    }

    public function getConnection($dbName = null)
    {
        // If no DB name is given, pick from env (first in DB_CONNECTIONS)
        if ($dbName === null) {
            if (!empty($_ENV['DB_CONNECTIONS'])) {
                $dbList = explode(',', $_ENV['DB_CONNECTIONS']);
                $dbName = trim($dbList[0]); // take the first database in the list
            } else {
                $this->logError("No DB name provided and DB_CONNECTIONS is not set");
                return null;
            }
        }

        // Now check if we have that connection
        if (!isset($this->connections[$dbName]) || $this->connections[$dbName] === null) {
            $this->logError("No connection available for $dbName");
            return null;
        }

        return $this->connections[$dbName];
    }


    private function logError($message)
    {
        $logFile = __DIR__ . '/../../logs/database.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] ERROR: $message\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}

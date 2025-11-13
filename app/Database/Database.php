<?php

namespace App\Database;

class Database
{
    private $connections = [];

    public function __construct()
    {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $user = $_ENV['DB_USER'] ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';
        $dbNames = array_filter(array_map('trim', explode(',', $_ENV['DB_CONNECTIONS'] ?? '')));

        // If no database names are listed, still allow a single fallback
        if (empty($dbNames)) {
            $dbNames = [$_ENV['DB_NAME'] ?? ''];
        }

        foreach ($dbNames as $dbName) {
            if ($dbName === '') continue;
            $conn = @new \mysqli($host, $user, $password, $dbName);
            if ($conn->connect_error) {
                $this->logError("Connection failed to $dbName: " . $conn->connect_error);
                $this->connections[$dbName] = null;
            } else {
                $this->connections[$dbName] = $conn;
            }
        }
    }

    /**
     * Traditional getter (still works)
     */
    public function getConnection($dbName = null)
    {
        // Use the first DB as default if none specified
        if ($dbName === null) {
            $first = array_key_first($this->connections);
            return $first ? $this->connections[$first] : null;
        }

        if (!isset($this->connections[$dbName])) {
            $this->logError("No connection found for database: $dbName");
            return null;
        }

        // return $this->connections[$dbName];
        return new QueryBuilder($this->connections[$dbName]);
    }

    /**
     * Magic property access: $db->marsdb
     */
    public function __get($name)
    {
        if (isset($this->connections[$name])) {
            return new QueryBuilder($this->connections[$name]);
        }

        $this->logError("No connection found for '$name'");
        return null;
    }

    /**
     * Magic call: $db->getConnection()->query("...") OR $db->marsdb->query("...")
     * Also supports $db->marsdb() style.
     */
    public function __call($name, $arguments)
    {
        // Allow calling like $db->marsdb()
        if (isset($this->connections[$name])) {
            // return $this->connections[$name];
            return new QueryBuilder($this->connections[$name]);
        }

        // Or redirect to getConnection() if "getMarsdb" etc.
        if (strpos($name, 'get') === 0) {
            $dbName = lcfirst(substr($name, 3));
            if (isset($this->connections[$dbName])) {
                // return $this->connections[$dbName];
                return new QueryBuilder($this->connections[$dbName]);
            }
        }

        $this->logError("Attempted to call undefined method or connection: $name");
        return null;
    }

    private function logError($message)
    {
        $logFile = __DIR__ . '/../../logs/database.log';
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0777, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] ERROR: $message\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}

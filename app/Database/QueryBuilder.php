<?php

namespace App\Database;

class QueryBuilder
{
    private $conn;
    private $select = '*';
    private $table = '';
    private $updateTable = '';
    private $updateData = [];
    private $where = [];
    private $joins = '';
    private $orderBy = '';
    private $limit = '';
    private $groupBy = '';
    private $deleteTable = '';

    public function __construct($connection)
    {
        $this->conn = $connection;
    }

    public function SELECT($columns = '*', $table = '')
    {
        $this->select = is_array($columns) ? implode(', ', $columns) : $columns;
        if ($table) $this->table = $table;
        return $this;
    }

    // public function WHERE($conditions)
    // {
    //     foreach ($conditions as $column => $value) {
    //         $escaped = $this->conn->real_escape_string($value);
    //         $this->where[] = "`$column` = '$escaped'";
    //     }
    //     return $this;
    // }

    public function ORDERBY($column, $direction = 'ASC')
    {
        $this->orderBy = "ORDER BY `$column` $direction";
        return $this;
    }

    public function LIMIT($limit, $offset = null)
    {
        $this->limit = $offset !== null ? "LIMIT $offset, $limit" : "LIMIT $limit";
        return $this;
    }

    // -----------------------
    // MAGIC METHOD for JOIN
    // -----------------------
    public function __call($name, $arguments)
    {
        // If method ends with "JOIN", treat it as a JOIN
        if (str_ends_with(strtoupper($name), 'JOIN') && count($arguments) === 2) {
            $type = strtoupper(str_replace('JOIN', '', $name)); // e.g., LEFT, RIGHT, INNER, CROSS
            if ($type === '') $type = ''; // plain JOIN if no prefix
            $table = $arguments[0];
            $on = $arguments[1];
            $this->joins .= " {$type} JOIN `$table` ON $on";
            return $this;
        }

        throw new \BadMethodCallException("Method {$name} does not exist.");
    }

    private function buildQuery()
    {
        $sql = "SELECT {$this->select} FROM `{$this->table}`";

        if (!empty($this->joins)) {
            $sql .= ' ' . $this->joins;
        }

        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        if (!empty($this->groupBy)) {
            $sql .= ' ' . $this->groupBy;
        }

        if (!empty($this->orderBy)) {
            $sql .= ' ' . $this->orderBy;
        }

        if (!empty($this->limit)) {
            $sql .= ' ' . $this->limit;
        }

        return $sql;
    }

    public function get()
    {
        $query = $this->buildQuery();
        $result = $this->conn->query($query);

        if (!$result) {
            throw new \Exception("MySQL Error: " . $this->conn->error . "\nQuery: $query");
        }

        $rows = [];
        while ($row = $result->fetch_object()) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function first()
    {
        $this->LIMIT(1);
        $rows = $this->get();
        return $rows[0] ?? null;
    }
    public function reset()
    {
        $this->select = '*';
        $this->table = '';
        $this->where = [];
        $this->joins = '';
        $this->orderBy = '';
        $this->limit = '';
        $this->groupBy = '';
        return $this;
    }

    /**
     * Insert a new record
     *
     * @param string $table Table name
     * @param array $data  Associative array of column => value
     * @return int|bool     Inserted ID on success, false on failure
     */
    public function insert(string $table, array $data)
    {
        $columns = implode('`, `', array_keys($data));

        $valuesArr = array_map(function ($val) {
            return "'" . $this->conn->real_escape_string($val) . "'";
        }, array_values($data));

        $values = implode(', ', $valuesArr);

        $sql = "INSERT INTO `$table` (`$columns`) VALUES ($values)";

        $result = $this->conn->query($sql);

        if (!$result) {
            throw new \Exception("MySQL Insert Error: " . $this->conn->error . "\nQuery: $sql");
        }

        return $this->conn->insert_id; // Return the inserted ID
    }
    /**
     * Start an update on a table with data
     *
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @return $this
     */
    public function update(string $table, array $data)
    {
        if (empty($table) || empty($data)) {
            throw new \Exception("Table and data are required for update");
        }

        $this->updateTable = $table;
        $this->updateData = $data;

        return $this; // allow chaining with WHERE()
    }

    /**
     * Start a delete on a table
     *
     * @param string $table Table name
     * @return $this
     */
    public function delete(string $table)
    {
        if (empty($table)) {
            throw new \Exception("Table name is required for delete");
        }

        $this->deleteTable = $table;

        return $this; // allow chaining with WHERE()
    }


    /**
     * Build WHERE clause (shared)
     *
     * Executes delete if deleteTable is set.
     *
     * @param array $conditions
     * @return mixed $this for SELECT, affected_rows for UPDATE/DELETE
     */
    public function WHERE(array $conditions)
    {
        // Build WHERE array
        $this->where = [];
        foreach ($conditions as $column => $value) {
            $escaped = $this->conn->real_escape_string($value);
            $this->where[] = "`$column` = '$escaped'";
        }

        // Execute update if updateTable is set
        if ($this->updateTable && !empty($this->updateData)) {
            $set = [];
            foreach ($this->updateData as $column => $value) {
                $escaped = $this->conn->real_escape_string($value);
                $set[] = "`$column` = '$escaped'";
            }
            $setStr = implode(', ', $set);
            $whereStr = implode(' AND ', $this->where);

            $sql = "UPDATE `{$this->updateTable}` SET $setStr WHERE $whereStr";
            $result = $this->conn->query($sql);

            $affectedRows = $this->conn->affected_rows;
            $this->updateTable = '';
            $this->updateData = [];
            $this->where = [];

            if (!$result) {
                throw new \Exception("MySQL Update Error: " . $this->conn->error . "\nQuery: $sql");
            }

            return $affectedRows;
        }

        // Execute delete if deleteTable is set
        if ($this->deleteTable) {
            $whereStr = implode(' AND ', $this->where);
            $sql = "DELETE FROM `{$this->deleteTable}` WHERE $whereStr";
            $result = $this->conn->query($sql);

            $affectedRows = $this->conn->affected_rows;
            $this->deleteTable = '';
            $this->where = [];

            if (!$result) {
                throw new \Exception("MySQL Delete Error: " . $this->conn->error . "\nQuery: $sql");
            }

            return $affectedRows;
        }

        // Otherwise, allow chaining for SELECT
        return $this;
    }
}

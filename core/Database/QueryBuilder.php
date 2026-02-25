<?php

namespace Core\Database;

class QueryBuilder
{
    private $conn;
    private $select = '*';
    private $table = '';
    private $updateTable = '';
    private $updateData = array();
    private $where = array();
    private $joins = '';
    private $orderBy = '';
    private $limit = '';
    private $groupBy = '';
    private $deleteTable = '';
    private $having  = array();
    private $bindings = array();

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

    public function __call($name, $arguments)
    {
        $upper = strtoupper($name);
        $aliases = array(
            'SELECT'           => 'SELECT',
            'WHERE'            => 'WHERE',
            'OR_WHERE'         => 'OR_WHERE',
            'AND_WHERE'        => 'AND_WHERE',
            'WHERE_IN'         => 'WHERE_IN',
            'WHERE_NOT_IN'     => 'WHERE_NOT_IN',
            'WHERE_BETWEEN'    => 'WHERE_BETWEEN',
            'WHERE_LIKE'       => 'WHERE_LIKE',
            'WHERE_NOT_LIKE'   => 'WHERE_NOT_LIKE',
            'WHERE_NULL'       => 'WHERE_NULL',
            'WHERE_NOT_NULL'   => 'WHERE_NOT_NULL',
            'WHERE_EXISTS'     => 'WHERE_EXISTS',
            'WHERE_NOT_EXISTS' => 'WHERE_NOT_EXISTS',
            'ORDERBY'          => 'ORDERBY',
            'GROUPBY'          => 'GROUPBY',
            'HAVING'           => 'HAVING',
            'LIMIT'            => 'LIMIT',

            // Execution
            'GET'              => 'get',
            'FIRST'            => 'first',
            'COUNT'            => 'COUNT',
            'SUM'              => 'SUM',
            'AVG'              => 'AVG',
            'MIN'              => 'MIN',
            'MAX'              => 'MAX',
            'PAGINATE'         => 'paginate',
            'GETSQL'           => 'getSql',
            'RESET'            => 'reset',

            // Write operations
            'INSERT'           => 'INSERT',
            'INSERT_BATCH'     => 'INSERT_BATCH',
            'UPDATE'           => 'UPDATE',
            'DELETE'           => 'DELETE',
            'UPSERT'           => 'UPSERT',
            'TRUNCATE'         => 'TRUNCATE',

            // Misc
            'RAW'              => 'RAW',
            'TRANSACTION'      => 'transaction',
            'JOIN_SUB'         => 'JOIN_SUB',
            'BEGIN'            => 'begin',
            'COMMIT'           => 'commit',
            'ROLLBACK'         => 'rollback',
        );

        if (isset($aliases[$upper])) {
            $realMethod = $aliases[$upper];
            return call_user_func_array(array($this, $realMethod), $arguments);
        }

        if (substr($upper, -4) === 'JOIN' && count($arguments) === 2) {
            $type      = trim(substr($upper, 0, -4));
            $table     = $arguments[0];
            $on        = $arguments[1];
            $keyword   = $type ? "{$type} JOIN" : "JOIN";
            $this->joins .= " $keyword " . $this->formatTable($table) . " ON $on";
            return $this;
        }

        throw new \BadMethodCallException("Method {$name} does not exist.");
    }

    private function buildQuery()
    {
        // $sql = "SELECT {$this->select} FROM `{$this->table}`";
        $sql = "SELECT {$this->select} FROM " . $this->formatTable($this->table);


        if (!empty($this->joins)) {
            $sql .= ' ' . $this->joins;
        }

        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        if (!empty($this->groupBy)) {
            $sql .= ' ' . $this->groupBy;
        }

        if (!empty($this->having)) {
            $sql .= ' HAVING ' . implode(' AND ', $this->having);
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

        $rows = array();
        while ($row = $result->fetch_object()) {
            $rows[] = $row;
        }
        return $rows;
    }
    /**
     * Returns the raw SQL query string constructed for the current query builder state.
     *
     * @return string
     */
    public function ToSQL()
    {
        return $this->buildQuery();
    }
    public function first()
    {
        $results = $this->LIMIT(1)->get();
        return isset($results[0]) ? $results[0] : null;
    }
    /**
     * Begin a transaction.
     */
    public function begin()
    {
        $this->conn->begin_transaction();
        return $this;
    }

    /**
     * Commit the current transaction.
     */
    public function commit()
    {
        $this->conn->commit();
        return $this;
    }

    /**
     * Roll back the current transaction.
     */
    public function rollback()
    {
        $this->conn->rollback();
        return $this;
    }
    // reset sql functions
    public function reset()
    {
        $this->select = '*';
        $this->table = '';
        $this->where = array();
        $this->joins = '';
        $this->orderBy = '';
        $this->limit = '';
        $this->groupBy = '';
        $this->having   = array();
        $this->bindings = array();
        return $this;
    }

    /**
     * Insert a new record
     *
     * @param string $table Table name
     * @param array $data  Associative array of column => value
     * @return int|bool     Inserted ID on success, false on failure
     */
    public function INSERT($table, array $data)
    {
        $columns = implode('`, `', array_keys($data));

        $valuesArr = array();
        foreach (array_values($data) as $val) {
            $valuesArr[] = "'" . $this->conn->real_escape_string($val) . "'";
        }

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
    public function UPDATE($table, array $data)
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
    public function DELETE($table)
    {
        if (empty($table)) {
            throw new \Exception("Table name is required for delete");
        }

        $this->deleteTable = $table;

        return $this; // allow chaining with WHERE()
    }
    /**
     * Insert a record, or update it if a duplicate key is found.
     *
     * @param string $table      Table name
     * @param array  $data       Associative array of column => value to insert
     * @param array  $updateData Columns to update on duplicate (if empty, updates all $data columns)
     * @return int|bool          Inserted/updated ID on success
     */
    public function UPSERT($table, array $data, array $updateData = array())
    {
        if (empty($table) || empty($data)) {
            throw new \Exception("Table and data are required for upsert");
        }

        $columns = implode('`, `', array_keys($data));

        $valuesArr = array();
        foreach (array_values($data) as $val) {
            $valuesArr[] = is_null($val)
                ? 'NULL'
                : "'" . $this->conn->real_escape_string($val) . "'";
        }
        $values = implode(', ', $valuesArr);

        // If no specific update columns are given, update all inserted columns
        $onDuplicate = empty($updateData) ? $data : $updateData;

        $setArr = array();
        foreach ($onDuplicate as $column => $value) {
            $escaped   = is_null($value)
                ? 'NULL'
                : "'" . $this->conn->real_escape_string($value) . "'";
            $setArr[] = "`$column` = $escaped";
        }

        $sql = "INSERT INTO `$table` (`$columns`) VALUES ($values)"
            . " ON DUPLICATE KEY UPDATE " . implode(', ', $setArr);

        $result = $this->conn->query($sql);

        if (!$result) {
            throw new \Exception("MySQL Upsert Error: " . $this->conn->error . "\nQuery: $sql");
        }

        return $this->conn->insert_id;
    }
    /**
     * Execute a raw SQL query.
     *
     * For SELECT statements, returns an array of result objects.
     * For INSERT, returns the inserted ID.
     * For UPDATE/DELETE, returns the number of affected rows.
     * For other statements (CREATE, DROP, etc.), returns true on success.
     *
     * @param string $sql      Raw SQL string
     * @param array  $bindings Optional values to safely bind via real_escape_string
     *                         Use ? as placeholder in your SQL string
     * @return mixed
     */
    public function RAW($sql, array $bindings = array())
    {
        // Replace ? placeholders with escaped values in order
        if (!empty($bindings)) {
            foreach ($bindings as $value) {
                $escaped = is_null($value)
                    ? 'NULL'
                    : "'" . $this->conn->real_escape_string($value) . "'";
                $pos = strpos($sql, '?');
                if ($pos !== false) {
                    $sql = substr_replace($sql, $escaped, $pos, 1);
                }
            }
        }

        $result = $this->conn->query($sql);

        if (!$result) {
            throw new \Exception("MySQL Raw Query Error: " . $this->conn->error . "\nQuery: $sql");
        }

        $type = strtoupper(strtok(ltrim($sql), " \t\n\r"));

        switch ($type) {
            case 'SELECT':
            case 'SHOW':
            case 'DESCRIBE':
            case 'EXPLAIN':
                $rows = array();
                while ($row = $result->fetch_object()) {
                    $rows[] = $row;
                }
                return $rows;

            case 'INSERT':
                return $this->conn->insert_id;

            case 'UPDATE':
            case 'DELETE':
                return $this->conn->affected_rows;

            default:
                return true; // CREATE, DROP, ALTER, TRUNCATE, etc.
        }
    }
    /**
     * Build WHERE clause (shared)
     *
     * Executes delete if deleteTable is set.
     *
     * @param array $conditions
     * @return mixed $this for SELECT, affected_rows for UPDATE/DELETE
     */
    public function WHERE($conditions, array $bindings = array())
    {
        // Initialize where only if empty (allows multiple WHERE calls)
        if (!is_array($this->where)) {
            $this->where = array();
        }
        if (is_string($conditions)) {
            $this->where[] = $conditions;

            // Store bindings (optional but recommended)
            if (!empty($bindings)) {
                foreach ($bindings as $value) {
                    $this->bindings[] = $value;
                }
            }
        } elseif (is_array($conditions)) {
            foreach ($conditions as $column => $value) {
                $col = $this->wrapColumn($column);
                if (is_null($value)) {
                    $this->where[] = "$col IS NULL";
                } else {
                    $escaped = $this->conn->real_escape_string($value);
                    $this->where[] = "$col = '$escaped'";
                }
            }
        } else {
            throw new \InvalidArgumentException("WHERE expects array or raw SQL string");
        }

        if ($this->updateTable && !empty($this->updateData)) {
            $set = array();
            foreach ($this->updateData as $column => $value) {
                $escaped = $this->conn->real_escape_string($value);
                $set[] = "`$column` = '$escaped'";
            }

            $sql = "UPDATE `{$this->updateTable}` 
            SET " . implode(', ', $set) . "
            WHERE " . implode(' AND ', $this->where);

            $result = $this->conn->query($sql);
            $affectedRows = $this->conn->affected_rows;

            // reset state
            $this->updateTable = '';
            $this->updateData = array();
            $this->where = array();

            if (!$result) {
                throw new \Exception("MySQL Update Error: {$this->conn->error}\nQuery: $sql");
            }

            return $affectedRows;
        }

        if ($this->deleteTable) {
            $sql = "DELETE FROM `{$this->deleteTable}` 
            WHERE " . implode(' AND ', $this->where);

            $result = $this->conn->query($sql);
            $affectedRows = $this->conn->affected_rows;

            // reset state
            $this->deleteTable = '';
            $this->where = array();

            if (!$result) {
                throw new \Exception("MySQL Delete Error: {$this->conn->error}\nQuery: $sql");
            }

            return $affectedRows;
        }

        // SELECT chaining
        return $this;
    }
    public function OR_WHERE($conditions)
    {
        if (!is_array($this->where)) {
            $this->where = array();
        }

        if (is_string($conditions)) {
            $this->where[] = "OR ($conditions)";
        } elseif (is_array($conditions)) {
            $orParts = array();
            foreach ($conditions as $column => $value) {
                if (is_null($value)) {
                    $orParts[] = "`$column` IS NULL";
                } else {
                    $escaped = $this->conn->real_escape_string($value);
                    $orParts[] = "`$column` = '$escaped'";
                }
            }
            $this->where[] = "OR (" . implode(' AND ', $orParts) . ")";
        } else {
            throw new \InvalidArgumentException("OR_WHERE expects array or raw SQL string");
        }

        return $this;
    }
    public function AND_WHERE($conditions)
    {
        if (!is_array($this->where)) {
            $this->where = array();
        }

        if (is_string($conditions)) {
            $this->where[] = "AND ($conditions)";
        } elseif (is_array($conditions)) {
            $andParts = array();
            foreach ($conditions as $column => $value) {
                if (is_null($value)) {
                    $andParts[] = "`$column` IS NULL";
                } else {
                    $escaped = $this->conn->real_escape_string($value);
                    $andParts[] = "`$column` = '$escaped'";
                }
            }
            $this->where[] = "AND (" . implode(' AND ', $andParts) . ")";
        } else {
            throw new \InvalidArgumentException("AND_WHERE expects array or raw SQL string");
        }

        return $this;
    }
    public function WHERE_IN($column, array $values)
    {
        if (empty($values)) {
            // Prevent invalid SQL: IN ()
            $this->where[] = "0 = 1";
            return $this;
        }

        $escaped = array();
        foreach ($values as $v) {
            if (is_numeric($v)) {
                $escaped[] = $v;
            } else {
                $escaped[] = "'" . $this->conn->real_escape_string($v) . "'";
            }
        }

        $columnSql = $this->wrapColumn($column);

        $this->where[] = "$columnSql IN (" . implode(',', $escaped) . ")";
        return $this;
    }
    public function WHERE_NOT_IN($column, array $values)
    {
        if (empty($values)) {
            // If no values, the condition is always true
            $this->where[] = "1 = 1";
            return $this;
        }

        $escaped = array();
        foreach ($values as $v) {
            if (is_numeric($v)) {
                $escaped[] = $v;
            } else {
                $escaped[] = "'" . $this->conn->real_escape_string($v) . "'";
            }
        }

        $columnSql = $this->wrapColumn($column);

        $this->where[] = "$columnSql NOT IN (" . implode(',', $escaped) . ")";
        return $this;
    }
    public function WHERE_BETWEEN($column, $start, $end)
    {
        $startEscaped = $this->conn->real_escape_string($start);
        $endEscaped   = $this->conn->real_escape_string($end);

        // Handle table alias (p.TranDate → `p`.`TranDate`)
        if (strpos($column, '.') !== false) {
            $parts = explode('.', $column, 2);
            $table = $parts[0];
            $col = $parts[1];
            $columnSql = "`$table`.`$col`";
        } else {
            $columnSql = $this->wrapColumn($column);
        }

        $this->where[] = "$columnSql BETWEEN '$startEscaped' AND '$endEscaped'";
        return $this;
    }
    /**
     * Add a WHERE column IS NULL condition.
     *
     * @param string $column
     * @return $this
     */
    public function WHERE_NULL($column)
    {
        $col = $this->wrapColumn($column);
        $this->where[] = "$col IS NULL";
        return $this;
    }

    /**
     * Add a WHERE column IS NOT NULL condition.
     *
     * @param string $column
     * @return $this
     */
    public function WHERE_NOT_NULL($column)
    {
        $col = $this->wrapColumn($column);
        $this->where[] = "$col IS NOT NULL";
        return $this;
    }

    /**
     * Add a WHERE column LIKE condition.
     * Wildcards (%, _) in $pattern are preserved; the rest is escaped.
     *
     * @param string $column
     * @param string $pattern  e.g. '%john%', 'smith%', '%@gmail.com'
     * @return $this
     */
    public function WHERE_LIKE($column, $pattern)
    {
        // Split on wildcard chars, escape the non-wildcard parts, then reassemble
        $parts    = preg_split('/([%_])/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);
        $escaped  = '';
        foreach ($parts as $part) {
            if ($part === '%' || $part === '_') {
                $escaped .= $part;
            } else {
                $escaped .= $this->conn->real_escape_string($part);
            }
        }

        $col = $this->wrapColumn($column);
        $this->where[] = "$col LIKE '$escaped'";
        return $this;
    }

    /**
     * Add a WHERE column NOT LIKE condition.
     *
     * @param string $column
     * @param string $pattern
     * @return $this
     */
    public function WHERE_NOT_LIKE($column, $pattern)
    {
        $parts   = preg_split('/([%_])/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);
        $escaped = '';
        foreach ($parts as $part) {
            if ($part === '%' || $part === '_') {
                $escaped .= $part;
            } else {
                $escaped .= $this->conn->real_escape_string($part);
            }
        }

        $col = $this->wrapColumn($column);
        $this->where[] = "$col NOT LIKE '$escaped'";
        return $this;
    }

    /**
     * Add a HAVING condition (for use with GROUP BY).
     * Accepts a raw SQL string or an associative array.
     *
     * @param string|array $conditions
     * @return $this
     */
    public function HAVING($conditions)
    {
        if (!isset($this->having)) {
            $this->having = array();
        }

        if (is_string($conditions)) {
            $this->having[] = $conditions;
        } elseif (is_array($conditions)) {
            foreach ($conditions as $column => $value) {
                $escaped        = $this->conn->real_escape_string($value);
                $this->having[] = "$column = '$escaped'";
            }
        } else {
            throw new \InvalidArgumentException("HAVING expects array or raw SQL string");
        }

        return $this;
    }

    /**
     * Add a JOIN where the table can be a name, a raw SQL subquery string,
     * or a closure that receives a fresh QueryBuilder and returns it.
     *
     * @param string          $type   JOIN type: LEFT, RIGHT, INNER, CROSS, or ''
     * @param string|callable $table  Table name, raw "(SELECT ...) alias", or closure
     * @param string          $on     ON condition
     * @return $this
     */
    public function JOIN_SUB($type, $table, $on)
    {
        $type = strtoupper(trim($type));

        if ($table instanceof \Closure) {
            // Pass a fresh builder; caller returns it after building the subquery
            $sub   = new self($this->conn);
            $built = $table($sub);

            if (!($built instanceof self)) {
                throw new \InvalidArgumentException("JOIN_SUB closure must return the QueryBuilder instance");
            }

            $subSql = $built->buildQuery();
            // Caller must embed an alias in the ON clause or append it to the closure result;
            // require the alias to be the last word before the ON clause.
            // We wrap it safely:
            $tableExpr = "($subSql)";
        } elseif (is_string($table) && strpos(ltrim($table), '(') === 0) {
            // Raw subquery string already provided e.g. "(SELECT ...) alias"
            $tableExpr = $table;
        } else {
            // Plain table name — delegate to existing formatTable
            $tableExpr = $this->formatTable($table);
        }

        $keyword       = $type ? "{$type} JOIN" : "JOIN";
        $this->joins  .= " $keyword $tableExpr ON $on";

        return $this;
    }

    /**
     * Insert multiple rows into a table in one query.
     *
     * @param string $table
     * @param array  $rows  Array of associative arrays — all must share the same keys
     * @return int          Number of affected rows
     */
    public function INSERT_BATCH($table, array $rows)
    {
        if (empty($rows)) {
            throw new \InvalidArgumentException("INSERT_BATCH requires at least one row");
        }

        // Derive columns from the first row; all subsequent rows must match
        $columns    = array_keys($rows[0]);
        $colsSql    = '`' . implode('`, `', $columns) . '`';
        $valueSets  = array();

        foreach ($rows as $index => $row) {
            if (array_keys($row) !== $columns) {
                throw new \InvalidArgumentException(
                    "INSERT_BATCH: row $index has different keys than row 0"
                );
            }

            $vals = array();
            foreach ($row as $value) {
                $vals[] = is_null($value)
                    ? 'NULL'
                    : "'" . $this->conn->real_escape_string($value) . "'";
            }
            $valueSets[] = '(' . implode(', ', $vals) . ')';
        }

        $sql    = "INSERT INTO `$table` ($colsSql) VALUES " . implode(', ', $valueSets);
        $result = $this->conn->query($sql);

        if (!$result) {
            throw new \Exception("MySQL Insert Batch Error: " . $this->conn->error . "\nQuery: $sql");
        }

        return $this->conn->affected_rows;
    }

    /**
     * Return COUNT of rows matching the current WHERE conditions.
     *
     * @param string $column  Column to count (default *)
     * @return int
     */
    public function COUNT($column = '*')
    {
        $col         = ($column === '*') ? '*' : $this->wrapColumn($column);
        $this->select = "COUNT($col) AS __agg";
        $row          = $this->first();
        return $row ? (int) $row->__agg : 0;
    }

    /**
     * Return SUM of a column matching the current WHERE conditions.
     *
     * @param string $column
     * @return float|null
     */
    public function SUM($column)
    {
        $col          = $this->wrapColumn($column);
        $this->select = "SUM($col) AS __agg";
        $row          = $this->first();
        return $row ? (float) $row->__agg : null;
    }

    /**
     * Return AVG of a column matching the current WHERE conditions.
     *
     * @param string $column
     * @return float|null
     */
    public function AVG($column)
    {
        $col          = $this->wrapColumn($column);
        $this->select = "AVG($col) AS __agg";
        $row          = $this->first();
        return $row ? (float) $row->__agg : null;
    }

    /**
     * Return MIN value of a column matching the current WHERE conditions.
     *
     * @param string $column
     * @return mixed
     */
    public function MIN($column)
    {
        $col          = $this->wrapColumn($column);
        $this->select = "MIN($col) AS __agg";
        $row          = $this->first();
        return $row ? $row->__agg : null;
    }

    /**
     * Return MAX value of a column matching the current WHERE conditions.
     *
     * @param string $column
     * @return mixed
     */
    public function MAX($column)
    {
        $col          = $this->wrapColumn($column);
        $this->select = "MAX($col) AS __agg";
        $row          = $this->first();
        return $row ? $row->__agg : null;
    }


    /**
     * Fetch a paginated result set.
     *
     * Runs two queries: one for the current page rows, one for the total count.
     * All previously chained SELECT / WHERE / JOIN / ORDER BY conditions apply.
     *
     * @param int $perPage  Rows per page
     * @param int $page     1-based page number
     * @return array {
     *     data:         array of row objects,
     *     total:        int total matching rows,
     *     per_page:     int,
     *     current_page: int,
     *     last_page:    int,
     *     from:         int first row index (1-based),
     *     to:           int last row index (1-based)
     * }
     */
    public function paginate($perPage = 15, $page = 1)
    {
        $page    = max(1, (int) $page);
        $perPage = max(1, (int) $perPage);
        $offset  = ($page - 1) * $perPage;

        // ── Count query: preserve joins + where, strip select/order/limit
        $savedSelect  = $this->select;
        $savedOrderBy = $this->orderBy;
        $savedLimit   = $this->limit;

        $this->select  = 'COUNT(*) AS __total';
        $this->orderBy = '';
        $this->limit   = '';

        $countRow = $this->first();
        $total    = $countRow ? (int) $countRow->__total : 0;

        // ── Restore and fetch the actual page
        $this->select  = $savedSelect;
        $this->orderBy = $savedOrderBy;
        $this->LIMIT($perPage, $offset);

        $data = $this->get();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $from     = $total > 0 ? $offset + 1 : 0;
        $to       = min($offset + $perPage, $total);

        return array(
            'data'         => $data,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => $lastPage,
            'from'         => $from,
            'to'           => $to,
        );
    }


    /**
     * Execute a callable inside a database transaction.
     *
     * The callable receives this QueryBuilder instance.
     * Return value of the callable is returned from transaction().
     * Any exception causes a ROLLBACK and is re-thrown.
     *
     * @param callable $callback  function(QueryBuilder $db) { ... }
     * @return mixed              Whatever the callback returns
     * @throws \Exception         Re-throws any exception after rolling back
     */
    public function transaction(callable $callback)
    {
        $this->conn->begin_transaction();

        try {
            $result = $callback($this);
            $this->conn->commit();
            return $result;
        } catch (\Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    /**
     * Add a WHERE EXISTS (subquery) condition.
     *
     * @param string|callable $subquery  Raw SQL string or closure receiving a fresh QueryBuilder
     * @return $this
     */
    public function WHERE_EXISTS($subquery)
    {
        $sql = $this->resolveSubquery($subquery);
        $this->where[] = "EXISTS ($sql)";
        return $this;
    }

    /**
     * Add a WHERE NOT EXISTS (subquery) condition.
     *
     * @param string|callable $subquery  Raw SQL string or closure receiving a fresh QueryBuilder
     * @return $this
     */
    public function WHERE_NOT_EXISTS($subquery)
    {
        $sql = $this->resolveSubquery($subquery);
        $this->where[] = "NOT EXISTS ($sql)";
        return $this;
    }

    /**
     * Internal helper — resolve a subquery from a closure or raw string.
     *
     * @param string|callable $subquery
     * @return string
     */
    private function resolveSubquery($subquery)
    {
        if ($subquery instanceof \Closure) {
            $sub   = new self($this->conn);
            $built = $subquery($sub);

            if (!($built instanceof self)) {
                throw new \InvalidArgumentException("Subquery closure must return the QueryBuilder instance");
            }

            return $built->buildQuery();
        }

        if (is_string($subquery)) {
            return $subquery;
        }

        throw new \InvalidArgumentException("Subquery must be a string or closure");
    }


    /**
     * Truncate a table — removes all rows and resets AUTO_INCREMENT.
     * Cannot be rolled back in most MySQL storage engines.
     *
     * @param string $table
     * @param bool   $safe   When true, uses DELETE FROM instead of TRUNCATE
     *                       (safe for foreign key constraints and transaction support)
     * @return bool
     */
    public function TRUNCATE($table, $safe = false)
    {
        if (empty($table)) {
            throw new \Exception("Table name is required for TRUNCATE");
        }

        if ($safe) {
            $sql = "DELETE FROM " . $this->formatTable($table);
        } else {
            $sql = "TRUNCATE TABLE " . $this->formatTable($table);
        }

        $result = $this->conn->query($sql);

        if (!$result) {
            throw new \Exception("MySQL Truncate Error: " . $this->conn->error . "\nQuery: $sql");
        }

        return true;
    }

    private function formatTable($table)
    {
        // db.table alias OR table alias
        if (preg_match('/^(.+?)\s+(\w+)$/', $table, $m)) {
            $tableName = $m[1];
            $alias     = $m[2];

            if (strpos($tableName, '.') !== false) {
                $parts = explode('.', $tableName, 2);
                $db = $parts[0];
                $tbl = $parts[1];
                return "`$db`.`$tbl` $alias";
            }

            return "`$tableName` $alias";
        }

        // db.table (no alias)
        if (strpos($table, '.') !== false) {
            $parts = explode('.', $table, 2);
            $db = $parts[0];
            $tbl = $parts[1];
            return "`$db`.`$tbl`";
        }

        // plain table
        return "`$table`";
    }

    private function wrapColumn($column)
    {
        if (strpos($column, '.') !== false) {
            $parts = explode('.', $column, 2);
            $table = $parts[0];
            $col = $parts[1];
            return "`$table`.`$col`";
        }
        return "`$column`";
    }
}

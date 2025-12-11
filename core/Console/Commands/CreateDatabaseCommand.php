<?php

namespace Core\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ReflectionClass;
use ReflectionProperty;

class CreateDatabaseCommand extends Command
{
    protected static $defaultName = 'create:database';
    protected static $defaultDescription = 'Create database and optionally tables based on table classes.';

    protected function configure()
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the database to create')
            ->addOption('with-tables', null, InputOption::VALUE_NONE, 'Create tables based on classes in console/tables');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dbName = $input->getArgument('name');
        $withTables = $input->getOption('with-tables');

        $this->loadEnv();

        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASSWORD'] ?? '';
        $collation = $_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci';
        $charset = explode('_', $collation)[0];

        $mysqli = new \mysqli($host, $user, $pass);
        if ($mysqli->connect_error) {
            $output->writeln("<error>Connection failed: {$mysqli->connect_error}</error>");
            return Command::FAILURE;
        }

        // Create DB
        $createDbQuery = "CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET $charset COLLATE $collation";
        if ($mysqli->query($createDbQuery)) {
            $output->writeln("<info>Database '$dbName' created or already exists.</info>");
        } else {
            $output->writeln("<error>Failed to create database: {$mysqli->error}</error>");
            return Command::FAILURE;
        }

        $mysqli->select_db($dbName);

        if ($withTables) {
            $this->createTables($mysqli, $output);
        }

        $mysqli->close();
        return Command::SUCCESS;
    }

    private function createTables($mysqli, OutputInterface $output)
    {
        $tablesDir = __DIR__ . '/../../Tables';
        $files = glob("$tablesDir/*.php");

        foreach ($files as $file) {
            require_once $file;
            $className = pathinfo($file, PATHINFO_FILENAME);
            $fqcn = "App\\Tables\\$className";

            if (!class_exists($fqcn)) continue;

            $definition = $this->getClassDefinition($fqcn);
            $sql = $this->buildCreateTableSQL(strtolower($className), $definition);

            if ($mysqli->multi_query($sql)) {
                do {
                    $mysqli->store_result();
                } while ($mysqli->more_results() && $mysqli->next_result());
                $output->writeln("<comment>Table '$className' created.</comment>");
            } else {
                $output->writeln("<error>Failed to create table '$className': {$mysqli->error}</error>");
            }
        }
    }

    /**
     * Dynamic detection — if class has static definition(), use it.
     * Otherwise, infer from typed public properties.
     */
    private function getClassDefinition($fqcn)
    {
        if (method_exists($fqcn, 'definition')) {
            return $fqcn::definition();
        }

        $ref = new ReflectionClass($fqcn);
        $props = $ref->getProperties(ReflectionProperty::IS_PUBLIC);

        $columns = [];
        foreach ($props as $prop) {
            $type = $prop->getType() ? $prop->getType()->getName() : 'string';
            $columns[$prop->getName()] = $this->inferColumn($type, $prop->getName());
        }

        // Add primary key automatically if "id" exists
        if (isset($columns['id'])) {
            $columns['id']['primary'] = true;
            $columns['id']['auto_increment'] = true;
        }

        return ['columns' => $columns];
    }

    /**
     * Map PHP types → SQL types (can be expanded easily)
     */
    // private function inferColumn($phpType, $name)
    // {
    //     $map = [
    //         'int' => ['type' => 'INT', 'nullable' => false],
    //         'float' => ['type' => 'FLOAT', 'nullable' => true],
    //         'bool' => ['type' => 'TINYINT', 'length' => 1, 'nullable' => false],
    //         'string' => ['type' => 'VARCHAR', 'length' => 255, 'nullable' => true],
    //         'DateTime' => ['type' => 'DATETIME', 'nullable' => true],
    //     ];

    //     return $map[$phpType] ?? ['type' => 'TEXT', 'nullable' => true];
    // }
    private function inferColumn($phpType, $name)
    {
        // Force datetime for created_at and updated_at
        if ($name === 'created_at') {
            return ['type' => 'DATETIME', 'nullable' => false, 'default' => 'CURRENT_TIMESTAMP'];
        }
        if ($name === 'updated_at') {
            return ['type' => 'DATETIME', 'nullable' => false, 'default' => 'CURRENT_TIMESTAMP', 'on_update' => 'CURRENT_TIMESTAMP'];
        }

        $map = [
            'int' => ['type' => 'INT', 'nullable' => false],
            'float' => ['type' => 'FLOAT', 'nullable' => true],
            'bool' => ['type' => 'TINYINT', 'length' => 1, 'nullable' => false],
            'string' => ['type' => 'VARCHAR', 'length' => 255, 'nullable' => true],
            'DateTime' => ['type' => 'DATETIME', 'nullable' => true],
        ];

        return $map[$phpType] ?? ['type' => 'TEXT', 'nullable' => true];
    }

    private function buildCreateTableSQL($tableName, $definition)
    {
        $columnsSql = [];
        $primaryKeys = [];
        $indexes = [];
        $uniques = [];
        $foreignKeys = [];

        foreach ($definition['columns'] as $name => $props) {
            $type = strtoupper($props['type']);
            $length = isset($props['length']) ? "({$props['length']})" : '';
            $nullable = isset($props['nullable']) && $props['nullable'] === false ? 'NOT NULL' : 'NULL';
            $default = isset($props['default']) ? "DEFAULT {$props['default']}" : '';
            $onUpdate = isset($props['on_update']) ? "ON UPDATE {$props['on_update']}" : '';
            $autoIncrement = !empty($props['auto_increment']) ? 'AUTO_INCREMENT' : '';
            $comment = isset($props['comment']) ? "COMMENT '{$props['comment']}'" : '';

            $columnsSql[] = "`$name` $type$length $nullable $default $onUpdate $autoIncrement $comment";

            if (!empty($props['primary'])) $primaryKeys[] = "`$name`";
            if (!empty($props['index'])) $indexes[] = $name;
            if (!empty($props['unique'])) $uniques[] = $name;
        }

        if (!empty($primaryKeys)) {
            $columnsSql[] = "PRIMARY KEY (" . implode(', ', $primaryKeys) . ")";
        }

        if (!empty($definition['foreign_keys'])) {
            foreach ($definition['foreign_keys'] as $fk) {
                $foreignKeys[] =
                    "FOREIGN KEY (`{$fk['column']}`) REFERENCES {$fk['references']}" .
                    (!empty($fk['on_delete']) ? " ON DELETE {$fk['on_delete']}" : '') .
                    (!empty($fk['on_update']) ? " ON UPDATE {$fk['on_update']}" : '');
            }
        }

        $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (\n  " .
            implode(",\n  ", array_merge($columnsSql, $foreignKeys)) .
            "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        foreach ($indexes as $col) {
            $sql .= "\nCREATE INDEX idx_{$tableName}_{$col} ON `$tableName` (`$col`);";
        }
        foreach ($uniques as $col) {
            $sql .= "\nCREATE UNIQUE INDEX uq_{$tableName}_{$col} ON `$tableName` (`$col`);";
        }

        return $sql;
    }

    private function loadEnv()
    {
        $envFile = __DIR__ . '/../../../.env';
        if (!file_exists($envFile)) return;

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            [$name, $value] = array_map('trim', explode('=', $line, 2));
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
}

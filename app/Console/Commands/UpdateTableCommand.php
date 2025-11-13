<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use ReflectionClass;
use ReflectionProperty;
use mysqli;

class UpdateTableCommand extends Command
{
    protected static $defaultName = 'update:table';
    protected static $defaultDescription = 'Update table schema based on table class definition';

    protected function configure()
    {
        $this
            ->addArgument('table', InputArgument::REQUIRED, 'The table class name (case-sensitive)')
            ->addArgument('database', InputArgument::REQUIRED, 'The database name');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $tableClass = $input->getArgument('table');
        $database = $input->getArgument('database');

        $this->loadEnv();

        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASSWORD'] ?? '';

        $mysqli = new mysqli($host, $user, $pass, $database);
        if ($mysqli->connect_error) {
            $output->writeln("<error>Connection failed: {$mysqli->connect_error}</error>");
            return Command::FAILURE;
        }

        $tablesDir = __DIR__ . '/../../Tables';
        $filePath = $tablesDir . '/' . $tableClass . '.php';

        if (!file_exists($filePath)) {
            $output->writeln("<error>Table class file '$tableClass.php' does not exist.</error>");
            return Command::FAILURE;
        }

        require_once $filePath;
        $fqcn = "App\\Tables\\$tableClass";

        if (!class_exists($fqcn)) {
            $output->writeln("<error>Class '$fqcn' not found.</error>");
            return Command::FAILURE;
        }

        // Get columns in class order
        $columnsInOrder = $this->getClassDefinition($fqcn);

        // Fetch existing DB columns
        $existingColumns = [];
        $res = $mysqli->query("SHOW COLUMNS FROM `" . strtolower($tableClass) . "`");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $existingColumns[] = $row['Field'];
            }
        }

        // Add new columns in class order
        foreach ($columnsInOrder as $colName => $props) {
            if (in_array($colName, $existingColumns)) continue; // already exists
            if (in_array($colName, ['id'])) continue; // id handled automatically

            $sql = "ALTER TABLE `" . strtolower($tableClass) . "` ADD COLUMN " . $this->getColumnSQL($colName, $props);

            // Place after the previous existing column in class order
            $previous = $this->findPreviousColumn($colName, $columnsInOrder, $existingColumns);
            if ($previous) {
                $sql .= " AFTER `$previous`";
            }

            if ($mysqli->query($sql)) {
                $output->writeln("<info>Added column '$colName' to '$tableClass'.</info>");
                $existingColumns[] = $colName;
            } else {
                $output->writeln("<error>Failed to add column '$colName': {$mysqli->error}</error>");
            }
        }

        $mysqli->close();
        $output->writeln("<info>Table '$tableClass' updated successfully.</info>");
        return Command::SUCCESS;
    }

    private function getClassDefinition($fqcn)
    {
        if (method_exists($fqcn, 'definition')) {
            return $fqcn::definition()['columns'];
        }

        $ref = new ReflectionClass($fqcn);
        $props = $ref->getProperties(ReflectionProperty::IS_PUBLIC);
        $columns = [];

        foreach ($props as $prop) {
            $name = $prop->getName();
            $columns[$name] = $this->inferColumn($prop->getType() ? $prop->getType()->getName() : 'string', $name);
        }

        if (isset($columns['id'])) {
            $columns['id']['primary'] = true;
            $columns['id']['auto_increment'] = true;
        }

        return $columns;
    }

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

    private function getColumnSQL($name, $props)
    {
        $type = strtoupper($props['type']);
        $length = isset($props['length']) ? "({$props['length']})" : '';
        $nullable = (!empty($props['nullable']) ? 'NULL' : 'NOT NULL');
        $default = isset($props['default']) ? "DEFAULT {$props['default']}" : '';
        $autoIncrement = !empty($props['auto_increment']) ? 'AUTO_INCREMENT' : '';

        return "`$name` $type$length $nullable $default $autoIncrement";
    }

    private function findPreviousColumn($colName, $columnsInOrder, $existingColumns)
    {
        $keys = array_keys($columnsInOrder);
        $pos = array_search($colName, $keys);

        for ($i = $pos - 1; $i >= 0; $i--) {
            if (in_array($keys[$i], $existingColumns)) {
                return $keys[$i];
            }
        }
        return null;
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

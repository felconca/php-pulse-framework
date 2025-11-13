<?php

namespace App\Tables;

class UserDefinition
{
    public static function definition()
    {
        return [
            'columns' => [
                'id' => ['type' => 'INT', 'auto_increment' => true, 'primary' => true],
                'username' => ['type' => 'VARCHAR', 'length' => 255, 'nullable' => false],
                'name' => ['type' => 'VARCHAR', 'length' => 255, 'nullable' => false],
                'created_at' => ['type' => 'DATETIME', 'default' => 'CURRENT_TIMESTAMP'],
                'updated_at' => ['type' => 'DATETIME', 'default' => 'CURRENT_TIMESTAMP', 'on_update' => 'CURRENT_TIMESTAMP'],
            ],
            'foreign_keys' => [
                // ['column' => 'role_id', 'references' => 'roles(id)', 'on_delete' => 'CASCADE']
            ]
        ];
    }
}

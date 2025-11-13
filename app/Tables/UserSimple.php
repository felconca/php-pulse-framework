<?php

namespace App\Tables;

class UserSimple
{
    public int $id = 0;
    public string $name = '';
    public string $username = '';
    public string $password = '';
    public string $created_at = '';
    public string $updated_at = '';
    public bool $deleted = false;
}

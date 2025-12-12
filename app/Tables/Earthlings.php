<?php

namespace App\Tables;

class Earthlings
{
    public int $id = 0;
    public string $name = '';

    /**
     * @type DATETIME
     */
    public $created_at;

    /**
     * @type DATETIME
     * @on_update CURRENT_TIMESTAMP
     */
    public $updated_at;
}
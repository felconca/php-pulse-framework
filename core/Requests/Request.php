<?php

namespace Core\Requests;

use Core\Requests\RequestValidator;
use Includes\Rest;

class Request
{
    protected array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data ?? [];
    }

    public function all(): array
    {
        return $this->data;
    }

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function validate(array $rules): array
    {
        $errors = RequestValidator::validate($this->data, $rules);

        if (!empty($errors)) {
            $rest = new Rest();
            echo $rest->response(['errors' => $errors], 400);
            exit;
        }

        return $this->data;
    }
}

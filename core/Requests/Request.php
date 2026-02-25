<?php

namespace Core\Requests;

use ArrayAccess;
use ArrayIterator;
use Includes\Rest;
use IteratorAggregate;

class Request implements ArrayAccess, IteratorAggregate
{
    protected array $data;

    public function __construct(array $data = [])
    {
        $this->data = !empty($data) ? $data : [];
    }

    // =====================
    // Existing methods
    // =====================

    public function all(): array
    {
        return $this->data;
    }

    public function get(string $key, $default = null)
    {
        return isset($this->data[$key]) ? $this->data[$key] : $default;
    }

    // ===== Iteration =====
    public function getIterator()
    {
        return new ArrayIterator($this->data);
    }


    public function validate(array $rules): array
    {
        $errors = RequestValidator::validate($this->data, $rules);

        if (!empty($errors)) {
            $rest = new Rest();
            return $rest->response(['errors' => $errors], 400);
            exit;
        }

        return $this->data;
    }

    // =====================
    // ArrayAccess methods
    // =====================

    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }
}

<?php

namespace Includes;

class Rest
{
    protected $_allow = [];
    protected $_content_type = "application/json";
    protected $_request = [];
    private $_method = "";
    private $_code = 200;
    private $userData = null;

    public function __construct()
    {
        $this->inputs();
    }

    // public function response($data, $status = 200)
    // {
    //     $this->_code = $status;
    //     $this->set_headers();

    //     echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    //     exit;
    // }
    public function response($data, $status = 200)
    {
        $this->_code = $status;
        $this->set_headers();

        // Numbers (including float) and boolean are encoded as such in json_encode,
        // but if you want 500.00 (float) instead of "500", ensure your data uses correct types.
        // However, json_encode cannot force trailing .00 (will output 500 not 500.00).
        // To avoid quoted numbers/booleans, cast them before encoding.
        // Here, force correct types recursively.

        function ensureProperTypes($item)
        {
            if (is_array($item)) {
                foreach ($item as $k => $v) {
                    $item[$k] = ensureProperTypes($v);
                }
                return $item;
            }
            // Try to detect booleans (but not 0/1 strings)
            if ($item === "true") return true;
            if ($item === "false") return false;

            // Try to detect integer
            if (is_string($item) && ctype_digit($item)) {
                return (int)$item;
            }
            // Try to detect float
            if (
                is_string($item) && is_numeric($item) &&
                strpos($item, '.') !== false
            ) {
                return (float)$item;
            }
            return $item;
        }

        $data = ensureProperTypes($data);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
        exit;
    }
    public function setUserData($data)
    {
        $this->userData = $data;
    }

    public function getUserData()
    {
        return $this->userData;
    }

    private function get_status_message()
    {
        $status = [
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            500 => 'Internal Server Error' // Added 500
        ];
        return $status[$this->_code] ?? 'Unknown Status';
    }

    public function get_request_method()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public function inputs()
    {
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? strtolower($_SERVER['CONTENT_TYPE']) : '';
        $method = $this->get_request_method();

        if ($method === "GET" || $method === "DELETE") {
            $this->_request = $this->cleanInputs($_GET);
            return $this->_request;
        }

        if ($method === "POST" || $method === "PUT") {
            if (!empty($_POST)) {
                $this->_request = $this->cleanInputs($_POST);
            } else {
                $rawInput = file_get_contents("php://input");
                if (!empty($rawInput)) {
                    if (stripos($contentType, 'application/json') !== false) {
                        $this->_request = json_decode($rawInput, true) ?? [];
                    } elseif (stripos($contentType, 'application/x-www-form-urlencoded') !== false) {
                        parse_str($rawInput, $this->_request);
                        $this->_request = $this->cleanInputs($this->_request);
                    } else {
                        $this->_request = ['raw' => $this->cleanInputs($rawInput)];
                    }
                }
            }
        }

        if (empty($this->_request)) {
            $this->_request = [];
        }

        return $this->_request;
    }

    private function cleanInputs($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'cleanInputs'], $data);
        }
        return trim(strip_tags(stripslashes($data)));
    }

    private function set_headers()
    {
        header("HTTP/1.1 " . $this->_code . " " . $this->get_status_message());
        header("Content-Type: " . $this->_content_type);
    }
}

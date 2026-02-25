<?php

namespace Core\Requests;

use DateTime;

class RequestValidator
{
    public static function validate($data, $rules)
    {
        $errors = [];

        foreach ($rules as $field => $rule) {
            $rulesArray = explode('|', $rule);
            $fieldExists = array_key_exists($field, $data);
            $value = $fieldExists ? $data[$field] : null;

            foreach ($rulesArray as $singleRule) {
                $ruleParts = explode(':', $singleRule);
                $ruleName = $ruleParts[0];
                $ruleValue = $ruleParts[1] ?? null;

                //-----------------------------
                //  REQUIRED CHECK
                //-----------------------------
                if ($ruleName === 'required') {
                    if (!$fieldExists || $value === null || $value === '' || $value === []) {
                        $errors[$field] = "$field is required";
                        continue;
                    }
                }

                // Stop checking additional rules if required already failed
                if (isset($errors[$field])) continue;

                //-----------------------------
                //  OTHER TYPE VALIDATIONS
                //-----------------------------
                switch ($ruleName) {

                    case 'string':
                        if (!is_string($value)) {
                            $errors[$field] = "$field must be a string";
                        }
                        break;

                    case 'array':
                        if (!is_array($value)) {
                            $errors[$field] = "$field must be an array";
                        }
                        break;

                    case 'object':
                        if (!is_object($value)) {
                            $errors[$field] = "$field must be an object";
                        }
                        break;

                    case 'boolean':
                    case 'bool':
                        if (!is_bool($value) && !in_array($value, ['0', '1', 0, 1], true)) {
                            $errors[$field] = "$field must be true or false";
                        }
                        break;

                    case 'integer':
                    case 'int':
                        if (!filter_var($value, FILTER_VALIDATE_INT)) {
                            $errors[$field] = "$field must be an integer";
                        }
                        break;

                    case 'float':
                    case 'double':
                        if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                            $errors[$field] = "$field must be a floating number";
                        }
                        break;

                    case 'numeric':
                        if (!is_numeric($value)) {
                            $errors[$field] = "$field must be numeric";
                        }
                        break;

                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = "$field must be a valid email address";
                        }
                        break;

                    case 'phone':
                        if (!preg_match('/^\+?[1-9]\d{1,14}$|^[\d-]{7,15}$/', $value)) {
                            $errors[$field] = "$field must be a valid phone number";
                        }
                        break;

                    case 'date':
                        $d = DateTime::createFromFormat('Y-m-d', $value);
                        if (!$d || $d->format('Y-m-d') !== $value) {
                            $errors[$field] = "$field must be a valid date (YYYY-MM-DD)";
                        }
                        break;

                    case 'min':
                        if (is_string($value) && strlen($value) < (int)$ruleValue) {
                            $errors[$field] = "$field must be at least $ruleValue characters";
                        }
                        if (is_array($value) && count($value) < (int)$ruleValue) {
                            $errors[$field] = "$field must have at least $ruleValue items";
                        }
                        break;

                    case 'max':
                        if (is_string($value) && strlen($value) > (int)$ruleValue) {
                            $errors[$field] = "$field must not exceed $ruleValue characters";
                        }
                        if (is_array($value) && count($value) > (int)$ruleValue) {
                            $errors[$field] = "$field must not exceed $ruleValue items";
                        }
                        break;
                }
            }
        }

        return $errors;
    }
}

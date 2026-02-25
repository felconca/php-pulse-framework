<?php

namespace Core\Requests;

use DateTime;

class RequestValidator
{
    public static function validate($data, $rules)
    {
        $errors = array();

        foreach ($rules as $field => $rule) {

            // =========================================
            // WILDCARD VALIDATION (data.*.field)
            // =========================================
            if (strpos($field, '.*.') !== false) {

                list($arrayKey, $subKey) = explode('.*.', $field, 2);

                if (!isset($data[$arrayKey]) || !is_array($data[$arrayKey])) {
                    $errors[$arrayKey] = "$arrayKey must be an array";
                    continue;
                }

                foreach ($data[$arrayKey] as $index => $row) {
                    $value = isset($row[$subKey]) ? $row[$subKey] : null;

                    self::applyRules(
                        $value,
                        $rule,
                        $arrayKey . '.' . $index . '.' . $subKey,
                        $errors
                    );
                }

                continue;
            }

            // =========================================
            // NORMAL FIELD VALIDATION
            // =========================================
            $value = array_key_exists($field, $data) ? $data[$field] : null;

            self::applyRules($value, $rule, $field, $errors);
        }

        return $errors;
    }

    // ==================================================
    // APPLY RULES TO A SINGLE VALUE
    // ==================================================
    private static function applyRules($value, $ruleString, $field, &$errors)
    {
        $rules = explode('|', $ruleString);
        $fieldExists = ($value !== null);

        foreach ($rules as $singleRule) {

            $ruleParts = explode(':', $singleRule);
            $ruleName  = $ruleParts[0];
            $ruleValue = isset($ruleParts[1]) ? $ruleParts[1] : null;

            // -------------------------
            // REQUIRED
            // -------------------------
            if ($ruleName === 'required') {
                if (!$fieldExists || $value === '' || $value === array()) {
                    $errors[$field] = "$field is required";
                    return;
                }
            }

            // Stop further rules if error exists
            if (isset($errors[$field])) {
                return;
            }

            // -------------------------
            // TYPE VALIDATIONS
            // -------------------------
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
                    if (!is_array($value) && !is_object($value)) {
                        $errors[$field] = "$field must be an object";
                    }
                    break;

                case 'boolean':
                case 'bool':
                    if (!is_bool($value) && !in_array($value, array(0, 1, '0', '1'), true)) {
                        $errors[$field] = "$field must be boolean";
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
                        $errors[$field] = "$field must be a float";
                    }
                    break;

                case 'numeric':
                    if (!is_numeric($value)) {
                        $errors[$field] = "$field must be numeric";
                    }
                    break;

                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$field] = "$field must be a valid email";
                    }
                    break;

                case 'date':
                    $d = DateTime::createFromFormat('Y-m-d', $value);
                    if (!$d || $d->format('Y-m-d') !== $value) {
                        $errors[$field] = "$field must be YYYY-MM-DD";
                    }
                    break;

                // -------------------------
                // LIMITS
                // -------------------------
                case 'min':
                    if (is_string($value) && strlen($value) < (int)$ruleValue) {
                        $errors[$field] = "$field minimum $ruleValue characters";
                    }
                    if (is_array($value) && count($value) < (int)$ruleValue) {
                        $errors[$field] = "$field minimum $ruleValue items";
                    }
                    if (is_numeric($value) && $value < $ruleValue) {
                        $errors[$field] = "$field minimum $ruleValue";
                    }
                    break;

                case 'max':
                    if (is_string($value) && strlen($value) > (int)$ruleValue) {
                        $errors[$field] = "$field maximum $ruleValue characters";
                    }
                    if (is_array($value) && count($value) > (int)$ruleValue) {
                        $errors[$field] = "$field maximum $ruleValue items";
                    }
                    if (is_numeric($value) && $value > $ruleValue) {
                        $errors[$field] = "$field maximum $ruleValue";
                    }
                    break;
            }
        }
    }
}

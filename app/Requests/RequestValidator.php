<?php

namespace App\Requests;

class RequestValidator
{
    public static function validate($data, $rules)
    {
        $errors = [];
        foreach ($rules as $field => $rule) {
            $rulesArray = explode('|', $rule); // Split rules like "required|email"
            $fieldExists = array_key_exists($field, $data);
            $value = $fieldExists ? $data[$field] : null;

            foreach ($rulesArray as $singleRule) {
                // Handle parameterized rules (e.g., min:5)
                $ruleParts = explode(':', $singleRule);
                $ruleName = $ruleParts[0];
                $ruleValue = isset($ruleParts[1]) ? $ruleParts[1] : null;

                // Required rule
                if ($ruleName === 'required') {
                    if (!$fieldExists || empty($value)) {
                        $errors[$field] = "$field is required";
                    }
                }

                // Only validate further if field exists and has a value
                if ($fieldExists && !empty($value)) {
                    switch ($ruleName) {
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
                            // Simple phone validation (e.g., +1234567890 or 123-456-7890)
                            if (!preg_match('/^\+?[1-9]\d{1,14}$|^[\d-]{7,15}$/', $value)) {
                                $errors[$field] = "$field must be a valid phone number";
                            }
                            break;
                        case 'date':
                            // Check if it's a valid date (e.g., YYYY-MM-DD)
                            $d = DateTime::createFromFormat('Y-m-d', $value);
                            if (!$d || $d->format('Y-m-d') !== $value) {
                                $errors[$field] = "$field must be a valid date (YYYY-MM-DD)";
                            }
                            break;
                        case 'min':
                            if ($ruleValue && strlen($value) < (int)$ruleValue) {
                                $errors[$field] = "$field must be at least $ruleValue characters";
                            }
                            break;
                        case 'max':
                            if ($ruleValue && strlen($value) > (int)$ruleValue) {
                                $errors[$field] = "$field must not exceed $ruleValue characters";
                            }
                            break;
                    }
                }
            }
        }
        return $errors;
    }
}

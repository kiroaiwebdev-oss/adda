<?php
/**
 * Validator - Input validation engine
 * Validates user input against rules
 */

class Validator {
    private $errors = [];
    private $data = [];
    
    public function __construct($data) {
        $this->data = $data;
    }
    
    /**
     * Validate data against rules
     */
    public function validate($rules) {
        foreach ($rules as $field => $ruleSet) {
            $rulesArray = explode('|', $ruleSet);
            
            foreach ($rulesArray as $rule) {
                $this->applyRule($field, $rule);
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Apply individual validation rule
     */
    private function applyRule($field, $rule) {
        $value = $this->data[$field] ?? null;
        
        // Required
        if ($rule === 'required') {
            if (empty($value) && $value !== '0') {
                $this->errors[$field][] = ucfirst($field) . ' is required';
            }
        }
        
        // Email
        if ($rule === 'email' && !empty($value)) {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $this->errors[$field][] = ucfirst($field) . ' must be a valid email';
            }
        }
        
        // Min length
        if (strpos($rule, 'min:') === 0) {
            $min = (int)substr($rule, 4);
            if (!empty($value) && strlen($value) < $min) {
                $this->errors[$field][] = ucfirst($field) . ' must be at least ' . $min . ' characters';
            }
        }
        
        // Max length
        if (strpos($rule, 'max:') === 0) {
            $max = (int)substr($rule, 4);
            if (!empty($value) && strlen($value) > $max) {
                $this->errors[$field][] = ucfirst($field) . ' must not exceed ' . $max . ' characters';
            }
        }
        
        // Numeric
        if ($rule === 'numeric' && !empty($value)) {
            if (!is_numeric($value)) {
                $this->errors[$field][] = ucfirst($field) . ' must be a number';
            }
        }
        
        // URL
        if ($rule === 'url' && !empty($value)) {
            if (!filter_var($value, FILTER_VALIDATE_URL)) {
                $this->errors[$field][] = ucfirst($field) . ' must be a valid URL';
            }
        }
        
        // Match (for password confirmation)
        if (strpos($rule, 'match:') === 0) {
            $matchField = substr($rule, 6);
            if (isset($this->data[$matchField]) && $value !== $this->data[$matchField]) {
                $this->errors[$field][] = ucfirst($field) . ' must match ' . $matchField;
            }
        }
    }
    
    /**
     * Get validation errors
     */
    public function errors() {
        return $this->errors;
    }
    
    /**
     * Get first error for a field
     */
    public function firstError($field = null) {
        if ($field) {
            return $this->errors[$field][0] ?? null;
        }
        
        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0];
        }
        
        return null;
    }
    
    /**
     * Check if validation passed
     */
    public function passes() {
        return empty($this->errors);
    }
    
    /**
     * Check if validation failed
     */
    public function fails() {
        return !$this->passes();
    }
}

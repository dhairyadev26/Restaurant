<?php
/**
 * Validator Library for Food Chef Cafe Management System
 * Handles form validation and data sanitization
 */

class Validator {
    
    private $errors = [];
    private $data = [];
    
    /**
     * Validate form data
     * @param array $data
     * @param array $rules
     * @return bool
     */
    public function validate($data, $rules) {
        $this->data = $data;
        $this->errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $fieldRules = explode('|', $fieldRules);
            
            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $rule);
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Apply validation rule
     * @param string $field
     * @param string $rule
     */
    private function applyRule($field, $rule) {
        $value = $this->data[$field] ?? '';
        
        if (strpos($rule, ':') !== false) {
            list($rule, $parameter) = explode(':', $rule, 2);
        }
        
        switch ($rule) {
            case 'required':
                if (empty($value)) {
                    $this->addError($field, 'This field is required');
                }
                break;
                
            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, 'Please enter a valid email address');
                }
                break;
                
            case 'min':
                if (!empty($value) && strlen($value) < $parameter) {
                    $this->addError($field, "Minimum length is {$parameter} characters");
                }
                break;
                
            case 'max':
                if (!empty($value) && strlen($value) > $parameter) {
                    $this->addError($field, "Maximum length is {$parameter} characters");
                }
                break;
                
            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, 'This field must be numeric');
                }
                break;
                
            case 'date':
                if (!empty($value) && !$this->isValidDate($value)) {
                    $this->addError($field, 'Please enter a valid date');
                }
                break;
                
            case 'phone':
                if (!empty($value) && !$this->isValidPhone($value)) {
                    $this->addError($field, 'Please enter a valid phone number');
                }
                break;
                
            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, 'Please enter a valid URL');
                }
                break;
                
            case 'alpha':
                if (!empty($value) && !ctype_alpha($value)) {
                    $this->addError($field, 'This field must contain only letters');
                }
                break;
                
            case 'alphanumeric':
                if (!empty($value) && !ctype_alnum($value)) {
                    $this->addError($field, 'This field must contain only letters and numbers');
                }
                break;
                
            case 'integer':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, 'This field must be an integer');
                }
                break;
                
            case 'decimal':
                if (!empty($value) && !is_numeric($value) && !filter_var($value, FILTER_VALIDATE_FLOAT)) {
                    $this->addError($field, 'This field must be a decimal number');
                }
                break;
                
            case 'in':
                if (!empty($value) && !in_array($value, explode(',', $parameter))) {
                    $this->addError($field, 'Invalid value selected');
                }
                break;
                
            case 'regex':
                if (!empty($value) && !preg_match($parameter, $value)) {
                    $this->addError($field, 'Invalid format');
                }
                break;
        }
    }
    
    /**
     * Add validation error
     * @param string $field
     * @param string $message
     */
    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    /**
     * Get validation errors
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Get first error for a field
     * @param string $field
     * @return string|null
     */
    public function getFirstError($field) {
        return $this->errors[$field][0] ?? null;
    }
    
    /**
     * Check if field has errors
     * @param string $field
     * @return bool
     */
    public function hasError($field) {
        return isset($this->errors[$field]);
    }
    
    /**
     * Validate date format
     * @param string $date
     * @return bool
     */
    private function isValidDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Validate phone number
     * @param string $phone
     * @return bool
     */
    private function isValidPhone($phone) {
        return preg_match('/^[\+]?[1-9][\d]{0,15}$/', $phone);
    }
    
    /**
     * Sanitize input data
     * @param mixed $data
     * @return mixed
     */
    public static function sanitize($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate file upload
     * @param array $file
     * @param array $allowedTypes
     * @param int $maxSize
     * @return array
     */
    public static function validateFile($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'], $maxSize = 5242880) {
        $errors = [];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed';
            return $errors;
        }
        
        if ($file['size'] > $maxSize) {
            $errors[] = 'File size exceeds limit';
        }
        
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedTypes)) {
            $errors[] = 'File type not allowed';
        }
        
        return $errors;
    }
    
    /**
     * Validate credit card number using Luhn algorithm
     * @param string $number
     * @return bool
     */
    public static function validateCreditCard($number) {
        $number = preg_replace('/\D/', '', $number);
        
        if (strlen($number) < 13 || strlen($number) > 19) {
            return false;
        }
        
        $sum = 0;
        $length = strlen($number);
        $parity = $length % 2;
        
        for ($i = 0; $i < $length; $i++) {
            $digit = $number[$i];
            if ($i % 2 == $parity) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }
        
        return $sum % 10 == 0;
    }
    
    /**
     * Validate postal code format
     * @param string $postalCode
     * @param string $country
     * @return bool
     */
    public static function validatePostalCode($postalCode, $country = 'US') {
        $patterns = [
            'US' => '/^\d{5}(-\d{4})?$/',
            'CA' => '/^[A-Za-z]\d[A-Za-z] \d[A-Za-z]\d$/',
            'UK' => '/^[A-Z]{1,2}\d[A-Z\d]? ?\d[A-Z]{2}$/i'
        ];
        
        $pattern = $patterns[$country] ?? '/^[\w\s-]{3,10}$/';
        return preg_match($pattern, $postalCode);
    }
    
    /**
     * Validate time format
     * @param string $time
     * @param string $format
     * @return bool
     */
    public static function validateTime($time, $format = 'H:i') {
        $d = DateTime::createFromFormat($format, $time);
        return $d && $d->format($format) === $time;
    }
    
    /**
     * Get validation rules for common fields
     * @return array
     */
    public static function getCommonRules() {
        return [
            'name' => 'required|min:2|max:50|alpha',
            'email' => 'required|email',
            'phone' => 'required|phone',
            'password' => 'required|min:8',
            'confirm_password' => 'required|same:password',
            'amount' => 'required|decimal',
            'quantity' => 'required|integer|min:1'
        ];
    }
}
?>

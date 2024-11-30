<?php
/**
 * Configuration Validator Class
 * Validates application configuration and provides helpful error messages
 */
class ConfigValidator {
    private $errors = [];
    private $warnings = [];
    
    public function validateConfig() {
        $this->validateDatabaseConfig();
        $this->validateEmailConfig();
        $this->validateSecurityConfig();
        $this->validateFileUploadConfig();
        
        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings
        ];
    }
    
    private function validateDatabaseConfig() {
        if (!defined('HOSTNAME') || empty(HOSTNAME)) {
            $this->errors[] = 'Database hostname is not configured';
        }
        
        if (!defined('USERNAME') || empty(USERNAME)) {
            $this->errors[] = 'Database username is not configured';
        }
        
        if (!defined('DB') || empty(DB)) {
            $this->errors[] = 'Database name is not configured';
        }
        
        if (!defined('BASEURL') || empty(BASEURL)) {
            $this->errors[] = 'Base URL is not configured';
        }
    }
    
    private function validateEmailConfig() {
        if (!defined('SMTP_HOST') || empty(SMTP_HOST)) {
            $this->warnings[] = 'SMTP host not configured - emails may not work';
        }
        
        if (!defined('FROM_EMAIL') || empty(FROM_EMAIL)) {
            $this->warnings[] = 'From email not configured';
        }
    }
    
    private function validateSecurityConfig() {
        if (!defined('SESSION_TIMEOUT') || SESSION_TIMEOUT < 300) {
            $this->warnings[] = 'Session timeout is very short or not configured';
        }
        
        if (!defined('MAX_LOGIN_ATTEMPTS') || MAX_LOGIN_ATTEMPTS < 3) {
            $this->warnings[] = 'Max login attempts is very low or not configured';
        }
    }
    
    private function validateFileUploadConfig() {
        if (!defined('MAX_FILE_SIZE') || MAX_FILE_SIZE < 1048576) {
            $this->warnings[] = 'Max file size is very small or not configured';
        }
        
        if (!defined('UPLOAD_PATH') || !is_dir(UPLOAD_PATH)) {
            $this->warnings[] = 'Upload path does not exist or not configured';
        }
    }
    
    public function getValidationReport() {
        $report = "Configuration Validation Report\n";
        $report .= "=============================\n\n";
        
        if (empty($this->errors) && empty($this->warnings)) {
            $report .= "✓ All configurations are valid!\n";
        } else {
            if (!empty($this->errors)) {
                $report .= "Errors:\n";
                foreach ($this->errors as $error) {
                    $report .= "✗ $error\n";
                }
                $report .= "\n";
            }
            
            if (!empty($this->warnings)) {
                $report .= "Warnings:\n";
                foreach ($this->warnings as $warning) {
                    $report .= "⚠ $warning\n";
                }
            }
        }
        
        return $report;
    }
    
    public function hasErrors() {
        return !empty($this->errors);
    }
    
    public function hasWarnings() {
        return !empty($this->warnings);
    }
}
?>

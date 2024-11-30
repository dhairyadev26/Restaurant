<?php
/**
 * Error Handler Class
 * Provides centralized error handling and logging
 */
class ErrorHandler {
    private static $instance = null;
    private $logFile;
    
    private function __construct() {
        $this->logFile = 'logs/errors.log';
        $this->ensureLogDirectory();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function ensureLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    public function handleError($errno, $errstr, $errfile, $errline) {
        $errorType = $this->getErrorType($errno);
        $message = "[$errorType] $errstr in $errfile on line $errline";
        
        $this->logError($message);
        
        if (ENVIRONMENT === 'development') {
            echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin: 10px; border-radius: 4px;'>";
            echo "<strong>Error:</strong> $errstr<br>";
            echo "<strong>File:</strong> $errfile<br>";
            echo "<strong>Line:</strong> $errline";
            echo "</div>";
        }
        
        return true;
    }
    
    public function handleException($exception) {
        $message = "Exception: " . $exception->getMessage() . 
                   " in " . $exception->getFile() . 
                   " on line " . $exception->getLine();
        
        $this->logError($message);
        
        if (ENVIRONMENT === 'development') {
            echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin: 10px; border-radius: 4px;'>";
            echo "<strong>Exception:</strong> " . $exception->getMessage() . "<br>";
            echo "<strong>File:</strong> " . $exception->getFile() . "<br>";
            echo "<strong>Line:</strong> " . $exception->getLine();
            echo "</div>";
        }
    }
    
    private function getErrorType($errno) {
        switch ($errno) {
            case E_ERROR: return 'E_ERROR';
            case E_WARNING: return 'E_WARNING';
            case E_PARSE: return 'E_PARSE';
            case E_NOTICE: return 'E_NOTICE';
            case E_CORE_ERROR: return 'E_CORE_ERROR';
            case E_CORE_WARNING: return 'E_CORE_WARNING';
            case E_COMPILE_ERROR: return 'E_COMPILE_ERROR';
            case E_COMPILE_WARNING: return 'E_COMPILE_WARNING';
            case E_USER_ERROR: return 'E_USER_ERROR';
            case E_USER_WARNING: return 'E_USER_WARNING';
            case E_USER_NOTICE: return 'E_USER_NOTICE';
            case E_STRICT: return 'E_STRICT';
            case E_RECOVERABLE_ERROR: return 'E_RECOVERABLE_ERROR';
            case E_DEPRECATED: return 'E_DEPRECATED';
            case E_USER_DEPRECATED: return 'E_USER_DEPRECATED';
            default: return 'UNKNOWN';
        }
    }
    
    private function logError($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] $message" . PHP_EOL;
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public function setErrorHandlers() {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
    }
}
?>

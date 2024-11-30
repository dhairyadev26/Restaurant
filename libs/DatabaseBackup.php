<?php
/**
 * Database Backup Utility
 * Provides automated database backup functionality
 */
class DatabaseBackup {
    private $pdo;
    private $backupDir;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->backupDir = 'backups/';
        $this->ensureBackupDirectory();
    }
    
    private function ensureBackupDirectory() {
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    public function createBackup($tables = null) {
        try {
            $backupFile = $this->backupDir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $output = '';
            
            // Get all tables if none specified
            if ($tables === null) {
                $stmt = $this->pdo->query("SHOW TABLES");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            
            // Add database creation
            $output .= "-- Food Chef Database Backup\n";
            $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $output .= "-- Database: " . DB . "\n\n";
            
            foreach ($tables as $table) {
                $output .= $this->backupTable($table);
            }
            
            // Write backup file
            if (file_put_contents($backupFile, $output)) {
                return [
                    'success' => true,
                    'file' => $backupFile,
                    'size' => filesize($backupFile),
                    'tables' => count($tables)
                ];
            } else {
                throw new Exception("Failed to write backup file");
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function backupTable($table) {
        $output = "\n-- Table structure for table `$table`\n";
        $output .= "DROP TABLE IF EXISTS `$table`;\n";
        
        // Get table structure
        $stmt = $this->pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $output .= $row['Create Table'] . ";\n\n";
        
        // Get table data
        $stmt = $this->pdo->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            $output .= "-- Data for table `$table`\n";
            $output .= "INSERT INTO `$table` VALUES\n";
            
            $values = [];
            foreach ($rows as $row) {
                $rowValues = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $rowValues[] = 'NULL';
                    } else {
                        $rowValues[] = $this->pdo->quote($value);
                    }
                }
                $values[] = '(' . implode(',', $rowValues) . ')';
            }
            
            $output .= implode(",\n", $values) . ";\n";
        }
        
        return $output;
    }
    
    public function listBackups() {
        $backups = [];
        $files = glob($this->backupDir . 'backup_*.sql');
        
        foreach ($files as $file) {
            $backups[] = [
                'file' => basename($file),
                'size' => filesize($file),
                'date' => filemtime($file),
                'path' => $file
            ];
        }
        
        // Sort by date (newest first)
        usort($backups, function($a, $b) {
            return $b['date'] - $a['date'];
        });
        
        return $backups;
    }
    
    public function deleteBackup($filename) {
        $filepath = $this->backupDir . $filename;
        
        if (file_exists($filepath) && unlink($filepath)) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Failed to delete backup file'];
        }
    }
    
    public function getBackupInfo($filename) {
        $filepath = $this->backupDir . $filename;
        
        if (!file_exists($filepath)) {
            return ['success' => false, 'error' => 'Backup file not found'];
        }
        
        return [
            'success' => true,
            'file' => $filename,
            'size' => filesize($filepath),
            'date' => filemtime($filepath),
            'path' => $filepath
        ];
    }
}
?>

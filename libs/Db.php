
<?php

class Db {
    private $pdo;
    
    function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . HOSTNAME . ";dbname=" . DB . ";charset=utf8mb4",
                USERNAME,
                PASSWORD,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    function addEdit($table, $data, $id = '') {
        try {
            if ($id) {
                // Update
                $fields = [];
                foreach ($data as $key => $value) {
                    $fields[] = "`$key` = ?";
                }
                $sql = "UPDATE `$table` SET " . implode(', ', $fields) . " WHERE id = ?";
                $values = array_values($data);
                $values[] = $id;
            } else {
                // Insert
                $fields = array_keys($data);
                $placeholders = str_repeat('?,', count($fields) - 1) . '?';
                $sql = "INSERT INTO `$table` (`" . implode('`, `', $fields) . "`) VALUES ($placeholders)";
                $values = array_values($data);
            }
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("Database error in addEdit: " . $e->getMessage());
            return false;
        }
    }
    
    function fetchOne($table, $id, $cols = '*') {
        try {
            $sql = "SELECT $cols FROM `$table` WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Database error in fetchOne: " . $e->getMessage());
            return false;
        }
    }
    
    function fetchQry($qry, $params = []) {
        try {
            if (!empty($params)) {
                $stmt = $this->pdo->prepare($qry);
                $stmt->execute($params);
            } else {
                $stmt = $this->pdo->query($qry);
            }
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Database error in fetchQry: " . $e->getMessage());
            return false;
        }
    }
    
    function fetchAll($sql, $params = []) {
        try {
            if (!empty($params)) {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                $stmt = $this->pdo->query($sql);
            }
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Database error in fetchAll: " . $e->getMessage());
            return [];
        }
    }
    
    function fetchRow($sql) {
        try {
            $stmt = $this->pdo->query($sql);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Database error in fetchRow: " . $e->getMessage());
            return 0;
        }
    }
    
    function delete($table, $id) {
        try {
            $sql = "DELETE FROM `$table` WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Database error in delete: " . $e->getMessage());
            return false;
        }
    }
    
    function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database error in query: " . $e->getMessage());
            return false;
        }
    }
    
    function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    function commit() {
        return $this->pdo->commit();
    }
    
    function rollBack() {
        return $this->pdo->rollBack();
    }
    
    function tableExists($tableName) {
        try {
            $sql = "SHOW TABLES LIKE ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tableName]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Database error in tableExists: " . $e->getMessage());
            return false;
        }
    }
    
    function getTableColumns($tableName) {
        try {
            $sql = "DESCRIBE `$tableName`";
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Database error in getTableColumns: " . $e->getMessage());
            return [];
        }
    }
    
    function count($table, $where = '', $params = []) {
        try {
            $sql = "SELECT COUNT(*) as count FROM `$table`";
            if ($where) {
                $sql .= " WHERE $where";
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result['count'] ?? 0;
        } catch (PDOException $e) {
            error_log("Database error in count: " . $e->getMessage());
            return 0;
        }
    }
}
?>
<?php
/**
 * Table Manager Class
 * Manages restaurant table assignments and availability
 */
class TableManager {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function addTable($data) {
        $sql = "INSERT INTO tables (table_number, capacity, location, status, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        
        $result = $this->db->query($sql, [
            $data['table_number'],
            $data['capacity'],
            $data['location'] ?? 'main',
            $data['status'] ?? 'available'
        ]);
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    public function updateTable($tableId, $data) {
        $sql = "UPDATE tables SET 
                table_number = ?, capacity = ?, location = ?, status = ?, 
                updated_at = NOW() 
                WHERE id = ?";
        
        return $this->db->query($sql, [
            $data['table_number'],
            $data['capacity'],
            $data['location'],
            $data['status'],
            $tableId
        ]);
    }
    
    public function getTable($tableId) {
        $sql = "SELECT * FROM tables WHERE id = ?";
        return $this->db->fetchOne($sql, [$tableId]);
    }
    
    public function getAllTables() {
        $sql = "SELECT * FROM tables ORDER BY table_number";
        return $this->db->fetchAll($sql);
    }
    
    public function getAvailableTables($capacity = null, $date = null, $time = null) {
        $sql = "SELECT t.* FROM tables t 
                WHERE t.status = 'available'";
        
        $params = [];
        
        if ($capacity) {
            $sql .= " AND t.capacity >= ?";
            $params[] = $capacity;
        }
        
        if ($date && $time) {
            // Check if table has conflicting reservations
            $sql .= " AND t.id NOT IN (
                SELECT DISTINCT table_id FROM reservations 
                WHERE reservation_date = ? 
                AND reservation_time BETWEEN ? AND DATE_ADD(?, INTERVAL 2 HOUR)
                AND status IN ('confirmed', 'pending')
            )";
            $params[] = $date;
            $params[] = $time;
            $params[] = $time;
        }
        
        $sql .= " ORDER BY t.capacity, t.table_number";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getTableReservations($tableId, $date = null) {
        $sql = "SELECT r.*, c.name as customer_name, c.phone as customer_phone 
                FROM reservations r 
                LEFT JOIN customers c ON r.customer_id = c.id 
                WHERE r.table_id = ?";
        
        $params = [$tableId];
        
        if ($date) {
            $sql .= " AND r.reservation_date = ?";
            $params[] = $date;
        }
        
        $sql .= " ORDER BY r.reservation_time";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function assignTableToReservation($reservationId, $tableId) {
        try {
            $this->db->beginTransaction();
            
            // Check if table is available for the reservation time
            $reservation = $this->db->fetchOne(
                "SELECT * FROM reservations WHERE id = ?", 
                [$reservationId]
            );
            
            if (!$reservation) {
                throw new Exception("Reservation not found");
            }
            
            // Check table availability
            $availableTables = $this->getAvailableTables(
                null, 
                $reservation['reservation_date'], 
                $reservation['reservation_time']
            );
            
            $tableAvailable = false;
            foreach ($availableTables as $table) {
                if ($table['id'] == $tableId) {
                    $tableAvailable = true;
                    break;
                }
            }
            
            if (!$tableAvailable) {
                throw new Exception("Table is not available for the selected time");
            }
            
            // Update reservation with table assignment
            $updateSql = "UPDATE reservations SET 
                         table_id = ?, status = 'confirmed', updated_at = NOW() 
                         WHERE id = ?";
            
            $result = $this->db->query($updateSql, [$tableId, $reservationId]);
            
            if (!$result) {
                throw new Exception("Failed to assign table to reservation");
            }
            
            // Update table status
            $tableSql = "UPDATE tables SET status = 'reserved', updated_at = NOW() WHERE id = ?";
            $this->db->query($tableSql, [$tableId]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Table assigned successfully'];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function releaseTable($tableId) {
        $sql = "UPDATE tables SET status = 'available', updated_at = NOW() WHERE id = ?";
        return $this->db->query($sql, [$tableId]);
    }
    
    public function getTableStatus($tableId) {
        $sql = "SELECT t.*, 
                (SELECT COUNT(*) FROM reservations r 
                 WHERE r.table_id = t.id 
                 AND r.reservation_date = CURDATE() 
                 AND r.status IN ('confirmed', 'pending')) as today_reservations
                FROM tables t WHERE t.id = ?";
        
        return $this->db->fetchOne($sql, [$tableId]);
    }
    
    public function getTableUtilization($startDate = null, $endDate = null) {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if ($startDate) {
            $whereClause .= " AND r.reservation_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $whereClause .= " AND r.reservation_date <= ?";
            $params[] = $endDate;
        }
        
        $sql = "SELECT t.table_number, t.capacity, t.location,
                COUNT(r.id) as total_reservations,
                AVG(r.guests) as avg_guests,
                SUM(CASE WHEN r.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_reservations,
                SUM(CASE WHEN r.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_reservations
                FROM tables t 
                LEFT JOIN reservations r ON t.id = r.table_id 
                $whereClause
                GROUP BY t.id, t.table_number, t.capacity, t.location
                ORDER BY t.table_number";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getTableCapacityDistribution() {
        $sql = "SELECT capacity, COUNT(*) as table_count 
                FROM tables 
                GROUP BY capacity 
                ORDER BY capacity";
        
        return $this->db->fetchAll($sql);
    }
    
    public function deleteTable($tableId) {
        // Check if table has any reservations
        $reservationCheck = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM reservations WHERE table_id = ?", 
            [$tableId]
        );
        
        if (($reservationCheck['count'] ?? 0) > 0) {
            return ['success' => false, 'error' => 'Cannot delete table with existing reservations'];
        }
        
        $sql = "DELETE FROM tables WHERE id = ?";
        $result = $this->db->query($sql, [$tableId]);
        
        if ($result) {
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Failed to delete table'];
    }
    
    public function getTableSuggestions($guests, $date, $time) {
        // Find tables that can accommodate the group and are available
        $availableTables = $this->getAvailableTables($guests, $date, $time);
        
        // Sort by best fit (closest capacity match)
        usort($availableTables, function($a, $b) use ($guests) {
            $aDiff = abs($a['capacity'] - $guests);
            $bDiff = abs($b['capacity'] - $guests);
            return $aDiff - $bDiff;
        });
        
        return array_slice($availableTables, 0, 5); // Return top 5 suggestions
    }
}
?>

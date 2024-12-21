<?php
/**
 * Staff Manager Class
 * Manages employee data, schedules, and performance
 */
class StaffManager {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function addStaff($data) {
        $sql = "INSERT INTO staff (name, email, phone, position, department, 
                hire_date, salary, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $result = $this->db->query($sql, [
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['position'],
            $data['department'],
            $data['hire_date'],
            $data['salary'],
            $data['status'] ?? 'active'
        ]);
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    public function updateStaff($staffId, $data) {
        $sql = "UPDATE staff SET 
                name = ?, email = ?, phone = ?, position = ?, department = ?, 
                salary = ?, status = ?, updated_at = NOW() 
                WHERE id = ?";
        
        return $this->db->query($sql, [
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['position'],
            $data['department'],
            $data['salary'],
            $data['status'],
            $staffId
        ]);
    }
    
    public function getStaff($staffId) {
        $sql = "SELECT * FROM staff WHERE id = ?";
        return $this->db->fetchOne($sql, [$staffId]);
    }
    
    public function getAllStaff($activeOnly = true) {
        $sql = "SELECT * FROM staff";
        
        if ($activeOnly) {
            $sql .= " WHERE status = 'active'";
        }
        
        $sql .= " ORDER BY name";
        
        return $this->db->fetchAll($sql);
    }
    
    public function getStaffByDepartment($department) {
        $sql = "SELECT * FROM staff WHERE department = ? AND status = 'active' ORDER BY name";
        return $this->db->fetchAll($sql, [$department]);
    }
    
    public function searchStaff($query, $limit = 20) {
        $sql = "SELECT * FROM staff 
                WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? 
                ORDER BY name LIMIT ?";
        
        $searchTerm = "%$query%";
        return $this->db->fetchAll($sql, [$searchTerm, $searchTerm, $searchTerm, $limit]);
    }
    
    public function addSchedule($staffId, $data) {
        $sql = "INSERT INTO staff_schedules (staff_id, work_date, start_time, end_time, 
                break_start, break_end, notes, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $result = $this->db->query($sql, [
            $staffId,
            $data['work_date'],
            $data['start_time'],
            $data['end_time'],
            $data['break_start'] ?? null,
            $data['break_end'] ?? null,
            $data['notes'] ?? ''
        ]);
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    public function getStaffSchedule($staffId, $startDate = null, $endDate = null) {
        $sql = "SELECT * FROM staff_schedules WHERE staff_id = ?";
        $params = [$staffId];
        
        if ($startDate) {
            $sql .= " AND work_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND work_date <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " ORDER BY work_date, start_time";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getWeeklySchedule($weekStart) {
        $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
        
        $sql = "SELECT s.*, st.name as staff_name, st.position, st.department 
                FROM staff_schedules s 
                JOIN staff st ON s.staff_id = st.id 
                WHERE s.work_date BETWEEN ? AND ? 
                ORDER BY s.work_date, s.start_time, st.name";
        
        return $this->db->fetchAll($sql, [$weekStart, $weekEnd]);
    }
    
    public function updateSchedule($scheduleId, $data) {
        $sql = "UPDATE staff_schedules SET 
                work_date = ?, start_time = ?, end_time = ?, 
                break_start = ?, break_end = ?, notes = ?, updated_at = NOW() 
                WHERE id = ?";
        
        return $this->db->query($sql, [
            $data['work_date'],
            $data['start_time'],
            $data['end_time'],
            $data['break_start'] ?? null,
            $data['break_end'] ?? null,
            $data['notes'] ?? '',
            $scheduleId
        ]);
    }
    
    public function deleteSchedule($scheduleId) {
        $sql = "DELETE FROM staff_schedules WHERE id = ?";
        return $this->db->query($sql, [$scheduleId]);
    }
    
    public function addPerformance($staffId, $data) {
        $sql = "INSERT INTO staff_performance (staff_id, evaluation_date, 
                rating, comments, evaluator_id, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $result = $this->db->query($sql, [
            $staffId,
            $data['evaluation_date'],
            $data['rating'],
            $data['comments'],
            $data['evaluator_id']
        ]);
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    public function getStaffPerformance($staffId, $limit = 10) {
        $sql = "SELECT p.*, e.name as evaluator_name 
                FROM staff_performance p 
                LEFT JOIN staff e ON p.evaluator_id = e.id 
                WHERE p.staff_id = ? 
                ORDER BY p.evaluation_date DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$staffId, $limit]);
    }
    
    public function getPerformanceStats($startDate = null, $endDate = null) {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if ($startDate) {
            $whereClause .= " AND p.evaluation_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $whereClause .= " AND p.evaluation_date <= ?";
            $params[] = $endDate;
        }
        
        $sql = "SELECT s.department, s.position,
                AVG(p.rating) as avg_rating,
                COUNT(p.id) as evaluations_count,
                MIN(p.rating) as min_rating,
                MAX(p.rating) as max_rating
                FROM staff_performance p 
                JOIN staff s ON p.staff_id = s.id 
                $whereClause
                GROUP BY s.department, s.position
                ORDER BY avg_rating DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getStaffAttendance($staffId, $month, $year) {
        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $sql = "SELECT s.work_date, s.start_time, s.end_time, s.break_start, s.break_end,
                CASE 
                    WHEN s.start_time <= '09:00:00' THEN 'on_time'
                    WHEN s.start_time <= '09:15:00' THEN 'late'
                    ELSE 'very_late'
                END as punctuality_status
                FROM staff_schedules s 
                WHERE s.staff_id = ? AND s.work_date BETWEEN ? AND ?
                ORDER BY s.work_date";
        
        return $this->db->fetchAll($sql, [$staffId, $startDate, $endDate]);
    }
    
    public function calculateWorkHours($staffId, $startDate, $endDate) {
        $sql = "SELECT 
                SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as total_minutes,
                SUM(TIMESTAMPDIFF(MINUTE, break_start, break_end)) as break_minutes,
                COUNT(DISTINCT work_date) as working_days
                FROM staff_schedules 
                WHERE staff_id = ? AND work_date BETWEEN ? AND ?";
        
        $result = $this->db->fetchOne($sql, [$staffId, $startDate, $endDate]);
        
        if ($result) {
            $totalHours = ($result['total_minutes'] - ($result['break_minutes'] ?? 0)) / 60;
            return [
                'total_hours' => round($totalHours, 2),
                'working_days' => $result['working_days'],
                'avg_hours_per_day' => $result['working_days'] > 0 ? round($totalHours / $result['working_days'], 2) : 0
            ];
        }
        
        return ['total_hours' => 0, 'working_days' => 0, 'avg_hours_per_day' => 0];
    }
    
    public function getDepartments() {
        $sql = "SELECT DISTINCT department FROM staff WHERE status = 'active' ORDER BY department";
        return $this->db->fetchAll($sql);
    }
    
    public function getPositions() {
        $sql = "SELECT DISTINCT position FROM staff WHERE status = 'active' ORDER BY position";
        return $this->db->fetchAll($sql);
    }
    
    public function deleteStaff($staffId) {
        // Check if staff has any schedules or performance records
        $scheduleCheck = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM staff_schedules WHERE staff_id = ?", 
            [$staffId]
        );
        
        $performanceCheck = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM staff_performance WHERE staff_id = ?", 
            [$staffId]
        );
        
        if (($scheduleCheck['count'] ?? 0) > 0 || ($performanceCheck['count'] ?? 0) > 0) {
            return ['success' => false, 'error' => 'Cannot delete staff with existing schedules or performance records'];
        }
        
        $sql = "DELETE FROM staff WHERE id = ?";
        $result = $this->db->query($sql, [$staffId]);
        
        if ($result) {
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Failed to delete staff'];
    }
}
?>

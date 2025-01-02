<?php
/**
 * Delivery Tracker Class
 * Manages food delivery tracking and status updates
 */
class DeliveryTracker {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function createDelivery($orderId, $deliveryData) {
        $sql = "INSERT INTO deliveries (order_id, customer_id, delivery_address, 
                delivery_instructions, estimated_time, driver_id, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $result = $this->db->query($sql, [
            $orderId,
            $deliveryData['customer_id'],
            $deliveryData['delivery_address'],
            $deliveryData['delivery_instructions'] ?? '',
            $deliveryData['estimated_time'],
            $deliveryData['driver_id'] ?? null,
            'pending'
        ]);
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    public function assignDriver($deliveryId, $driverId) {
        $sql = "UPDATE deliveries SET 
                driver_id = ?, status = 'assigned', updated_at = NOW() 
                WHERE id = ?";
        
        return $this->db->query($sql, [$driverId, $deliveryId]);
    }
    
    public function updateDeliveryStatus($deliveryId, $status, $notes = '') {
        $sql = "UPDATE deliveries SET 
                status = ?, notes = ?, updated_at = NOW() 
                WHERE id = ?";
        
        return $this->db->query($sql, [$status, $notes, $deliveryId]);
    }
    
    public function updateDeliveryLocation($deliveryId, $latitude, $longitude) {
        $sql = "INSERT INTO delivery_locations (delivery_id, latitude, longitude, 
                timestamp) VALUES (?, ?, ?, NOW())";
        
        return $this->db->query($sql, [$deliveryId, $latitude, $longitude]);
    }
    
    public function getDelivery($deliveryId) {
        $sql = "SELECT d.*, o.order_number, c.name as customer_name, c.phone as customer_phone,
                dr.name as driver_name, dr.phone as driver_phone
                FROM deliveries d
                JOIN orders o ON d.order_id = o.id
                JOIN customers c ON d.customer_id = c.id
                LEFT JOIN drivers dr ON d.driver_id = dr.id
                WHERE d.id = ?";
        
        return $this->db->fetchOne($sql, [$deliveryId]);
    }
    
    public function getActiveDeliveries() {
        $sql = "SELECT d.*, o.order_number, c.name as customer_name, c.phone as customer_phone,
                dr.name as driver_name, dr.phone as driver_phone
                FROM deliveries d
                JOIN orders o ON d.order_id = o.id
                JOIN customers c ON d.customer_id = c.id
                LEFT JOIN drivers dr ON d.driver_id = dr.id
                WHERE d.status IN ('assigned', 'picked_up', 'in_transit')
                ORDER BY d.created_at ASC";
        
        return $this->db->fetchAll($sql);
    }
    
    public function getDeliveriesByDriver($driverId, $status = null) {
        $sql = "SELECT d.*, o.order_number, c.name as customer_name, c.phone as customer_phone
                FROM deliveries d
                JOIN orders o ON d.order_id = o.id
                JOIN customers c ON d.customer_id = c.id
                WHERE d.driver_id = ?";
        
        $params = [$driverId];
        
        if ($status) {
            $sql .= " AND d.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY d.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getDeliveryHistory($customerId = null, $limit = 50) {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if ($customerId) {
            $whereClause .= " AND d.customer_id = ?";
            $params[] = $customerId;
        }
        
        $sql = "SELECT d.*, o.order_number, c.name as customer_name,
                dr.name as driver_name, dr.phone as driver_phone
                FROM deliveries d
                JOIN orders o ON d.order_id = o.id
                JOIN customers c ON d.customer_id = c.id
                LEFT JOIN drivers dr ON d.driver_id = dr.id
                $whereClause
                ORDER BY d.created_at DESC
                LIMIT ?";
        
        $params[] = $limit;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function calculateDeliveryTime($deliveryId) {
        $sql = "SELECT d.created_at, d.estimated_time, d.completed_at,
                dl.timestamp as last_location_update
                FROM deliveries d
                LEFT JOIN delivery_locations dl ON d.id = dl.delivery_id
                WHERE d.id = ?
                ORDER BY dl.timestamp DESC
                LIMIT 1";
        
        $result = $this->db->fetchOne($sql, [$deliveryId]);
        
        if (!$result) {
            return null;
        }
        
        $created = strtotime($result['created_at']);
        $completed = $result['completed_at'] ? strtotime($result['completed_at']) : time();
        $estimated = strtotime($result['estimated_time']);
        
        return [
            'total_time' => $completed - $created,
            'estimated_time' => $estimated - $created,
            'actual_time' => $completed - $created,
            'is_late' => ($completed - $created) > ($estimated - $created)
        ];
    }
    
    public function getDeliveryRoute($deliveryId) {
        $sql = "SELECT latitude, longitude, timestamp
                FROM delivery_locations
                WHERE delivery_id = ?
                ORDER BY timestamp ASC";
        
        return $this->db->fetchAll($sql, [$deliveryId]);
    }
    
    public function getDeliveryStats($startDate = null, $endDate = null) {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if ($startDate) {
            $whereClause .= " AND d.created_at >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $whereClause .= " AND d.created_at <= ?";
            $params[] = $endDate;
        }
        
        $sql = "SELECT 
                COUNT(*) as total_deliveries,
                SUM(CASE WHEN d.status = 'completed' THEN 1 ELSE 0 END) as completed_deliveries,
                SUM(CASE WHEN d.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_deliveries,
                AVG(CASE WHEN d.completed_at IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, d.created_at, d.completed_at) 
                    ELSE NULL END) as avg_delivery_time
                FROM deliveries d
                $whereClause";
        
        return $this->db->fetchOne($sql, $params);
    }
    
    public function getDriverPerformance($driverId, $period = '30') {
        $sql = "SELECT 
                COUNT(*) as total_deliveries,
                SUM(CASE WHEN d.status = 'completed' THEN 1 ELSE 0 END) as completed_deliveries,
                AVG(CASE WHEN d.completed_at IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, d.created_at, d.completed_at) 
                    ELSE NULL END) as avg_delivery_time,
                SUM(CASE WHEN d.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_deliveries
                FROM deliveries d
                WHERE d.driver_id = ? 
                AND d.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        return $this->db->fetchOne($sql, [$driverId, $period]);
    }
    
    public function sendDeliveryNotification($deliveryId, $type = 'status_update') {
        $delivery = $this->getDelivery($deliveryId);
        
        if (!$delivery) {
            return false;
        }
        
        $notificationData = [
            'delivery_id' => $deliveryId,
            'customer_id' => $delivery['customer_id'],
            'type' => $type,
            'message' => $this->getNotificationMessage($type, $delivery),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $sql = "INSERT INTO delivery_notifications (delivery_id, customer_id, type, 
                message, created_at) VALUES (?, ?, ?, ?, ?)";
        
        return $this->db->query($sql, [
            $notificationData['delivery_id'],
            $notificationData['customer_id'],
            $notificationData['type'],
            $notificationData['message'],
            $notificationData['created_at']
        ]);
    }
    
    private function getNotificationMessage($type, $delivery) {
        switch ($type) {
            case 'assigned':
                return "Your delivery has been assigned to driver {$delivery['driver_name']}";
            case 'picked_up':
                return "Your order has been picked up and is on its way";
            case 'in_transit':
                return "Your order is currently being delivered";
            case 'completed':
                return "Your delivery has been completed. Enjoy your meal!";
            case 'delayed':
                return "Your delivery is running behind schedule. We apologize for the delay.";
            default:
                return "Your delivery status has been updated";
        }
    }
    
    public function cancelDelivery($deliveryId, $reason) {
        $sql = "UPDATE deliveries SET 
                status = 'cancelled', notes = ?, updated_at = NOW() 
                WHERE id = ?";
        
        return $this->db->query($sql, [$reason, $deliveryId]);
    }
}
?>

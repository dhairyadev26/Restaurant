<?php
/**
 * Customer Manager Class
 * Manages customer data, profiles, and relationships
 */
class CustomerManager {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function createCustomer($data) {
        $sql = "INSERT INTO customers (name, email, phone, address, city, state, postal_code, 
                date_of_birth, preferences, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $preferences = json_encode($data['preferences'] ?? []);
        
        $result = $this->db->query($sql, [
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['address'] ?? '',
            $data['city'] ?? '',
            $data['state'] ?? '',
            $data['postal_code'] ?? '',
            $data['date_of_birth'] ?? null,
            $preferences
        ]);
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    public function updateCustomer($customerId, $data) {
        $sql = "UPDATE customers SET 
                name = ?, email = ?, phone = ?, address = ?, city = ?, 
                state = ?, postal_code = ?, date_of_birth = ?, preferences = ?, 
                updated_at = NOW() 
                WHERE id = ?";
        
        $preferences = json_encode($data['preferences'] ?? []);
        
        return $this->db->query($sql, [
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['address'] ?? '',
            $data['city'] ?? '',
            $data['state'] ?? '',
            $data['postal_code'] ?? '',
            $data['date_of_birth'] ?? null,
            $preferences,
            $customerId
        ]);
    }
    
    public function getCustomer($customerId) {
        $sql = "SELECT * FROM customers WHERE id = ?";
        return $this->db->fetchOne($sql, [$customerId]);
    }
    
    public function getCustomerByEmail($email) {
        $sql = "SELECT * FROM customers WHERE email = ?";
        return $this->db->fetchOne($sql, [$email]);
    }
    
    public function getAllCustomers($limit = 50, $offset = 0) {
        $sql = "SELECT * FROM customers ORDER BY created_at DESC LIMIT ? OFFSET ?";
        return $this->db->fetchAll($sql, [$limit, $offset]);
    }
    
    public function searchCustomers($query, $limit = 20) {
        $sql = "SELECT * FROM customers 
                WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? 
                ORDER BY name LIMIT ?";
        
        $searchTerm = "%$query%";
        return $this->db->fetchAll($sql, [$searchTerm, $searchTerm, $searchTerm, $limit]);
    }
    
    public function getCustomerOrders($customerId, $limit = 20) {
        $sql = "SELECT o.*, COUNT(oi.id) as items_count 
                FROM orders o 
                LEFT JOIN order_items oi ON o.id = oi.order_id 
                WHERE o.customer_id = ? 
                GROUP BY o.id 
                ORDER BY o.created_at DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$customerId, $limit]);
    }
    
    public function getCustomerReservations($customerId, $limit = 20) {
        $sql = "SELECT * FROM reservations 
                WHERE customer_id = ? 
                ORDER BY reservation_date DESC, reservation_time DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$customerId, $limit]);
    }
    
    public function getCustomerFeedback($customerId, $limit = 20) {
        $sql = "SELECT * FROM customer_feedback 
                WHERE customer_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$customerId, $limit]);
    }
    
    public function getCustomerStats($customerId) {
        // Total orders
        $orderSql = "SELECT COUNT(*) as total_orders, SUM(total_amount) as total_spent 
                     FROM orders WHERE customer_id = ? AND status != 'cancelled'";
        $orderStats = $this->db->fetchOne($orderSql, [$customerId]);
        
        // Total reservations
        $reservationSql = "SELECT COUNT(*) as total_reservations 
                           FROM reservations WHERE customer_id = ?";
        $reservationStats = $this->db->fetchOne($reservationSql, [$customerId]);
        
        // Average rating
        $ratingSql = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
                      FROM customer_feedback WHERE customer_id = ?";
        $ratingStats = $this->db->fetchOne($ratingSql, [$customerId]);
        
        // Last visit
        $lastVisitSql = "SELECT MAX(created_at) as last_visit 
                         FROM orders WHERE customer_id = ? AND status != 'cancelled'";
        $lastVisit = $this->db->fetchOne($lastVisitSql, [$customerId]);
        
        return [
            'total_orders' => $orderStats['total_orders'] ?? 0,
            'total_spent' => $orderStats['total_spent'] ?? 0,
            'total_reservations' => $reservationStats['total_reservations'] ?? 0,
            'avg_rating' => round($ratingStats['avg_rating'] ?? 0, 1),
            'total_reviews' => $ratingStats['total_reviews'] ?? 0,
            'last_visit' => $lastVisit['last_visit'] ?? null
        ];
    }
    
    public function updateCustomerPreferences($customerId, $preferences) {
        $sql = "UPDATE customers SET preferences = ?, updated_at = NOW() WHERE id = ?";
        $preferencesJson = json_encode($preferences);
        
        return $this->db->query($sql, [$preferencesJson, $customerId]);
    }
    
    public function getCustomerPreferences($customerId) {
        $sql = "SELECT preferences FROM customers WHERE id = ?";
        $result = $this->db->fetchOne($sql, [$customerId]);
        
        if ($result && $result['preferences']) {
            return json_decode($result['preferences'], true);
        }
        
        return [];
    }
    
    public function deleteCustomer($customerId) {
        // Check if customer has any orders or reservations
        $orderCheck = $this->db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE customer_id = ?", [$customerId]);
        $reservationCheck = $this->db->fetchOne("SELECT COUNT(*) as count FROM reservations WHERE customer_id = ?", [$customerId]);
        
        if (($orderCheck['count'] ?? 0) > 0 || ($reservationCheck['count'] ?? 0) > 0) {
            return ['success' => false, 'error' => 'Cannot delete customer with existing orders or reservations'];
        }
        
        $sql = "DELETE FROM customers WHERE id = ?";
        $result = $this->db->query($sql, [$customerId]);
        
        if ($result) {
            // Also delete related feedback
            $this->db->query("DELETE FROM customer_feedback WHERE customer_id = ?", [$customerId]);
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Failed to delete customer'];
    }
    
    public function getTopCustomers($limit = 10) {
        $sql = "SELECT c.*, COUNT(o.id) as order_count, SUM(o.total_amount) as total_spent 
                FROM customers c 
                LEFT JOIN orders o ON c.id = o.customer_id AND o.status != 'cancelled' 
                GROUP BY c.id 
                ORDER BY total_spent DESC, order_count DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
}
?>

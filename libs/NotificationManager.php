<?php
/**
 * Notification Manager Class
 * Handles various types of notifications in the application
 */
class NotificationManager {
    private $db;
    private $mailer;
    
    public function __construct($db, $mailer = null) {
        $this->db = $db;
        $this->mailer = $mailer;
    }
    
    public function createNotification($userId, $type, $message, $data = []) {
        $sql = "INSERT INTO notifications (user_id, type, message, data, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        
        $dataJson = json_encode($data);
        
        $result = $this->db->query($sql, [$userId, $type, $message, $dataJson]);
        
        if ($result) {
            $notificationId = $this->db->lastInsertId();
            
            // Send email notification if mailer is available
            if ($this->mailer && !empty($data['email'])) {
                $this->sendEmailNotification($data['email'], $type, $message, $data);
            }
            
            return $notificationId;
        }
        
        return false;
    }
    
    public function getNotifications($userId, $limit = 20, $offset = 0) {
        $sql = "SELECT * FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        
        return $this->db->fetchAll($sql, [$userId, $limit, $offset]);
    }
    
    public function getUnreadCount($userId) {
        $sql = "SELECT COUNT(*) as count FROM notifications 
                WHERE user_id = ? AND is_read = 0";
        
        $result = $this->db->fetchOne($sql, [$userId]);
        return $result['count'] ?? 0;
    }
    
    public function markAsRead($notificationId, $userId) {
        $sql = "UPDATE notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE id = ? AND user_id = ?";
        
        return $this->db->query($sql, [$notificationId, $userId]);
    }
    
    public function markAllAsRead($userId) {
        $sql = "UPDATE notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE user_id = ? AND is_read = 0";
        
        return $this->db->query($sql, [$userId]);
    }
    
    public function deleteNotification($notificationId, $userId) {
        $sql = "DELETE FROM notifications 
                WHERE id = ? AND user_id = ?";
        
        return $this->db->query($sql, [$notificationId, $userId]);
    }
    
    public function sendReservationNotification($reservationData) {
        $message = "New reservation request from {$reservationData['name']}";
        $data = [
            'reservation_id' => $reservationData['id'],
            'name' => $reservationData['name'],
            'email' => $reservationData['email'],
            'date' => $reservationData['reservation_date'],
            'time' => $reservationData['reservation_time'],
            'guests' => $reservationData['guests']
        ];
        
        // Notify admin
        $this->createNotification(1, 'reservation', $message, $data);
        
        // Send confirmation to customer
        if ($this->mailer) {
            $this->mailer->sendReservationConfirmation($reservationData['email'], $reservationData);
        }
    }
    
    public function sendOrderNotification($orderData) {
        $message = "New order #{$orderData['order_number']} received";
        $data = [
            'order_id' => $orderData['id'],
            'order_number' => $orderData['order_number'],
            'customer_name' => $orderData['customer_name'],
            'total_amount' => $orderData['total_amount'],
            'items_count' => $orderData['items_count']
        ];
        
        // Notify admin
        $this->createNotification(1, 'order', $message, $data);
        
        // Send confirmation to customer
        if ($this->mailer && !empty($orderData['customer_email'])) {
            $this->mailer->sendOrderConfirmation($orderData['customer_email'], $orderData);
        }
    }
    
    public function sendLowStockNotification($itemData) {
        $message = "Low stock alert: {$itemData['name']} (Quantity: {$itemData['stock_quantity']})";
        $data = [
            'item_id' => $itemData['id'],
            'item_name' => $itemData['name'],
            'current_stock' => $itemData['stock_quantity'],
            'min_stock' => $itemData['min_stock_level']
        ];
        
        // Notify admin
        $this->createNotification(1, 'low_stock', $message, $data);
    }
    
    public function sendFeedbackNotification($feedbackData) {
        $message = "New feedback received from {$feedbackData['customer_name']}";
        $data = [
            'feedback_id' => $feedbackData['id'],
            'customer_name' => $feedbackData['customer_name'],
            'rating' => $feedbackData['rating'],
            'message' => $feedbackData['message']
        ];
        
        // Notify admin
        $this->createNotification(1, 'feedback', $message, $data);
    }
    
    private function sendEmailNotification($email, $type, $message, $data) {
        if (!$this->mailer) return;
        
        $subject = "Food Chef - " . ucfirst($type) . " Notification";
        $htmlMessage = $this->generateEmailTemplate($type, $message, $data);
        
        $this->mailer->sendMail($email, $subject, $htmlMessage);
    }
    
    private function generateEmailTemplate($type, $message, $data) {
        $template = "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>";
        $template .= "<h2 style='color: #333;'>Food Chef Notification</h2>";
        $template .= "<p><strong>Type:</strong> " . ucfirst($type) . "</p>";
        $template .= "<p><strong>Message:</strong> $message</p>";
        
        if (!empty($data)) {
            $template .= "<h3>Details:</h3><ul>";
            foreach ($data as $key => $value) {
                if (is_string($value)) {
                    $template .= "<li><strong>" . ucfirst($key) . ":</strong> $value</li>";
                }
            }
            $template .= "</ul>";
        }
        
        $template .= "<p style='color: #666; font-size: 12px;'>This is an automated notification from Food Chef system.</p>";
        $template .= "</div>";
        
        return $template;
    }
}
?>

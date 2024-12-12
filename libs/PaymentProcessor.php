<?php
/**
 * Payment Processor Class
 * Handles various payment methods and transactions
 */
class PaymentProcessor {
    private $db;
    private $supportedMethods = ['cash', 'card', 'online', 'upi'];
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function processPayment($orderId, $amount, $method, $paymentData = []) {
        try {
            $this->db->beginTransaction();
            
            // Validate payment method
            if (!in_array($method, $this->supportedMethods)) {
                throw new Exception("Unsupported payment method: $method");
            }
            
            // Create payment transaction record
            $transactionId = $this->createTransaction($orderId, $amount, $method, $paymentData);
            
            if (!$transactionId) {
                throw new Exception("Failed to create payment transaction");
            }
            
            // Update order status
            $this->updateOrderStatus($orderId, 'paid');
            
            // Log payment success
            $this->logPayment($transactionId, 'success', 'Payment processed successfully');
            
            $this->db->commit();
            
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'message' => 'Payment processed successfully'
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            
            // Log payment failure
            if (isset($transactionId)) {
                $this->logPayment($transactionId, 'failed', $e->getMessage());
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function createTransaction($orderId, $amount, $method, $paymentData) {
        $sql = "INSERT INTO payment_transactions (order_id, amount, payment_method, 
                transaction_data, status, created_at) 
                VALUES (?, ?, ?, ?, 'pending', NOW())";
        
        $transactionData = json_encode($paymentData);
        
        $result = $this->db->query($sql, [$orderId, $amount, $method, $transactionData]);
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    private function updateOrderStatus($orderId, $status) {
        $sql = "UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?";
        return $this->db->query($sql, [$status, $orderId]);
    }
    
    private function logPayment($transactionId, $status, $message) {
        $sql = "UPDATE payment_transactions SET 
                status = ?, message = ?, updated_at = NOW() 
                WHERE id = ?";
        
        return $this->db->query($sql, [$status, $message, $transactionId]);
    }
    
    public function getPaymentMethods() {
        return $this->supportedMethods;
    }
    
    public function getTransaction($transactionId) {
        $sql = "SELECT * FROM payment_transactions WHERE id = ?";
        return $this->db->fetchOne($sql, [$transactionId]);
    }
    
    public function getOrderTransactions($orderId) {
        $sql = "SELECT * FROM payment_transactions WHERE order_id = ? ORDER BY created_at DESC";
        return $this->db->fetchAll($sql, [$orderId]);
    }
    
    public function refundPayment($transactionId, $amount, $reason) {
        try {
            $this->db->beginTransaction();
            
            // Get original transaction
            $transaction = $this->getTransaction($transactionId);
            if (!$transaction) {
                throw new Exception("Transaction not found");
            }
            
            if ($transaction['status'] !== 'success') {
                throw new Exception("Cannot refund non-successful transaction");
            }
            
            // Create refund record
            $refundId = $this->createRefund($transactionId, $amount, $reason);
            
            if (!$refundId) {
                throw new Exception("Failed to create refund record");
            }
            
            // Update transaction status
            $this->logPayment($transactionId, 'refunded', "Refunded: $reason");
            
            // Update order status if full refund
            if ($amount >= $transaction['amount']) {
                $this->updateOrderStatus($transaction['order_id'], 'refunded');
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'refund_id' => $refundId,
                'message' => 'Refund processed successfully'
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function createRefund($transactionId, $amount, $reason) {
        $sql = "INSERT INTO refunds (transaction_id, amount, reason, created_at) 
                VALUES (?, ?, ?, NOW())";
        
        $result = $this->db->query($sql, [$transactionId, $amount, $reason]);
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    public function getPaymentStats($startDate = null, $endDate = null) {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if ($startDate) {
            $whereClause .= " AND DATE(created_at) >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $whereClause .= " AND DATE(created_at) <= ?";
            $params[] = $endDate;
        }
        
        // Total payments
        $totalSql = "SELECT COUNT(*) as count, SUM(amount) as total 
                     FROM payment_transactions 
                     $whereClause AND status = 'success'";
        $totalStats = $this->db->fetchOne($totalSql, $params);
        
        // Payment methods breakdown
        $methodSql = "SELECT payment_method, COUNT(*) as count, SUM(amount) as total 
                      FROM payment_transactions 
                      $whereClause AND status = 'success' 
                      GROUP BY payment_method";
        $methodStats = $this->db->fetchAll($methodSql, $params);
        
        // Daily breakdown
        $dailySql = "SELECT DATE(created_at) as date, COUNT(*) as count, SUM(amount) as total 
                     FROM payment_transactions 
                     $whereClause AND status = 'success' 
                     GROUP BY DATE(created_at) 
                     ORDER BY date DESC 
                     LIMIT 30";
        $dailyStats = $this->db->fetchAll($dailySql, $params);
        
        return [
            'total_transactions' => $totalStats['count'] ?? 0,
            'total_amount' => $totalStats['total'] ?? 0,
            'method_breakdown' => $methodStats,
            'daily_breakdown' => $dailyStats
        ];
    }
    
    public function validatePaymentData($method, $paymentData) {
        $errors = [];
        
        switch ($method) {
            case 'card':
                if (empty($paymentData['card_number']) || strlen($paymentData['card_number']) < 13) {
                    $errors[] = 'Invalid card number';
                }
                if (empty($paymentData['expiry']) || !preg_match('/^\d{2}\/\d{2}$/', $paymentData['expiry'])) {
                    $errors[] = 'Invalid expiry date (MM/YY)';
                }
                if (empty($paymentData['cvv']) || strlen($paymentData['cvv']) < 3) {
                    $errors[] = 'Invalid CVV';
                }
                break;
                
            case 'upi':
                if (empty($paymentData['upi_id']) || !filter_var($paymentData['upi_id'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Invalid UPI ID';
                }
                break;
                
            case 'online':
                if (empty($paymentData['gateway']) || empty($paymentData['transaction_id'])) {
                    $errors[] = 'Missing gateway or transaction ID';
                }
                break;
        }
        
        return $errors;
    }
}
?>

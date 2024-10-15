<?php
/**
 * Inventory Manager for Food Chef Cafe Management System
 * Handles stock tracking, inventory transactions, and low stock alerts
 */

class InventoryManager {
    
    private $db;
    private $logger;
    
    public function __construct($db, $logger = null) {
        $this->db = $db;
        $this->logger = $logger;
    }
    
    /**
     * Add stock to inventory
     */
    public function addStock($foodId, $quantity, $reason = 'purchase', $referenceId = null, $performedBy = null) {
        try {
            $this->db->beginTransaction();
            
            // Update food stock quantity
            $stmt = $this->db->query(
                "UPDATE food SET stock_quantity = stock_quantity + ? WHERE id = ?",
                [$quantity, $foodId]
            );
            
            if (!$stmt) {
                throw new Exception("Failed to update stock quantity");
            }
            
            // Record inventory transaction
            $stmt = $this->db->query(
                "INSERT INTO inventory_transactions (food_id, transaction_type, quantity, reason, reference_id, performed_by) 
                 VALUES (?, 'in', ?, ?, ?, ?)",
                [$foodId, $quantity, $reason, $referenceId, $performedBy]
            );
            
            if (!$stmt) {
                throw new Exception("Failed to record inventory transaction");
            }
            
            $this->db->commit();
            
            if ($this->logger) {
                $this->logger->info("Stock added to inventory", [
                    'food_id' => $foodId,
                    'quantity' => $quantity,
                    'reason' => $reason
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            
            if ($this->logger) {
                $this->logger->error("Failed to add stock", [
                    'food_id' => $foodId,
                    'quantity' => $quantity,
                    'error' => $e->getMessage()
                ]);
            }
            
            return false;
        }
    }
    
    /**
     * Remove stock from inventory
     */
    public function removeStock($foodId, $quantity, $reason = 'order', $referenceId = null, $performedBy = null) {
        try {
            $this->db->beginTransaction();
            
            // Check current stock
            $currentStock = $this->db->query(
                "SELECT stock_quantity FROM food WHERE id = ?",
                [$foodId]
            )->fetch();
            
            if (!$currentStock || $currentStock['stock_quantity'] < $quantity) {
                throw new Exception("Insufficient stock available");
            }
            
            // Update food stock quantity
            $stmt = $this->db->query(
                "UPDATE food SET stock_quantity = stock_quantity - ? WHERE id = ?",
                [$quantity, $foodId]
            );
            
            if (!$stmt) {
                throw new Exception("Failed to update stock quantity");
            }
            
            // Record inventory transaction
            $stmt = $this->db->query(
                "INSERT INTO inventory_transactions (food_id, transaction_type, quantity, reason, reference_id, performed_by) 
                 VALUES (?, 'out', ?, ?, ?, ?)",
                [$foodId, $quantity, $reason, $referenceId, $performedBy]
            );
            
            if (!$stmt) {
                throw new Exception("Failed to record inventory transaction");
            }
            
            $this->db->commit();
            
            if ($this->logger) {
                $this->logger->info("Stock removed from inventory", [
                    'food_id' => $foodId,
                    'quantity' => $quantity,
                    'reason' => $reason
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            
            if ($this->logger) {
                $this->logger->error("Failed to remove stock", [
                    'food_id' => $foodId,
                    'quantity' => $quantity,
                    'error' => $e->getMessage()
                ]);
            }
            
            return false;
        }
    }
    
    /**
     * Get low stock items
     */
    public function getLowStockItems() {
        try {
            return $this->db->query(
                "SELECT * FROM low_stock_items ORDER BY stock_quantity ASC"
            )->fetchAll();
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to get low stock items", ['error' => $e->getMessage()]);
            }
            return [];
        }
    }
    
    /**
     * Get inventory transactions
     */
    public function getInventoryTransactions($foodId = null, $limit = 50) {
        try {
            if ($foodId) {
                return $this->db->query(
                    "SELECT it.*, f.name as food_name 
                     FROM inventory_transactions it 
                     JOIN food f ON it.food_id = f.id 
                     WHERE it.food_id = ? 
                     ORDER BY it.created_at DESC 
                     LIMIT ?",
                    [$foodId, $limit]
                )->fetchAll();
            } else {
                return $this->db->query(
                    "SELECT it.*, f.name as food_name 
                     FROM inventory_transactions it 
                     JOIN food f ON it.food_id = f.id 
                     ORDER BY it.created_at DESC 
                     LIMIT ?",
                    [$limit]
                )->fetchAll();
            }
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to get inventory transactions", ['error' => $e->getMessage()]);
            }
            return [];
        }
    }
    
    /**
     * Get stock statistics
     */
    public function getStockStatistics() {
        try {
            $stats = $this->db->query(
                "SELECT 
                    COUNT(*) as total_items,
                    SUM(stock_quantity) as total_stock,
                    AVG(stock_quantity) as avg_stock,
                    COUNT(CASE WHEN stock_quantity <= min_stock_level THEN 1 END) as low_stock_count,
                    COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock_count
                 FROM food 
                 WHERE is_active = 1"
            )->fetch();
            
            return $stats;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to get stock statistics", ['error' => $e->getMessage()]);
            }
            return [];
        }
    }
    
    /**
     * Create purchase order
     */
    public function createPurchaseOrder($supplierId, $items, $expectedDelivery = null, $notes = '', $createdBy = null) {
        try {
            $this->db->beginTransaction();
            
            // Create purchase order
            $stmt = $this->db->query(
                "INSERT INTO purchase_orders (supplier_id, order_date, expected_delivery, notes, created_by) 
                 VALUES (?, CURDATE(), ?, ?, ?)",
                [$supplierId, $expectedDelivery, $notes, $createdBy]
            );
            
            if (!$stmt) {
                throw new Exception("Failed to create purchase order");
            }
            
            $purchaseOrderId = $this->db->lastInsertId();
            $totalAmount = 0;
            
            // Add purchase order items
            foreach ($items as $item) {
                $itemTotal = $item['quantity'] * $item['unit_price'];
                $totalAmount += $itemTotal;
                
                $stmt = $this->db->query(
                    "INSERT INTO purchase_order_items (purchase_order_id, food_id, quantity, unit_price, total_price) 
                     VALUES (?, ?, ?, ?, ?)",
                    [$purchaseOrderId, $item['food_id'], $item['quantity'], $item['unit_price'], $itemTotal]
                );
                
                if (!$stmt) {
                    throw new Exception("Failed to add purchase order item");
                }
            }
            
            // Update total amount
            $stmt = $this->db->query(
                "UPDATE purchase_orders SET total_amount = ? WHERE id = ?",
                [$totalAmount, $purchaseOrderId]
            );
            
            $this->db->commit();
            
            if ($this->logger) {
                $this->logger->info("Purchase order created", [
                    'purchase_order_id' => $purchaseOrderId,
                    'supplier_id' => $supplierId,
                    'total_amount' => $totalAmount
                ]);
            }
            
            return $purchaseOrderId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            
            if ($this->logger) {
                $this->logger->error("Failed to create purchase order", [
                    'supplier_id' => $supplierId,
                    'error' => $e->getMessage()
                ]);
            }
            
            return false;
        }
    }
    
    /**
     * Receive purchase order
     */
    public function receivePurchaseOrder($purchaseOrderId) {
        try {
            $this->db->beginTransaction();
            
            // Get purchase order items
            $items = $this->db->query(
                "SELECT * FROM purchase_order_items WHERE purchase_order_id = ?",
                [$purchaseOrderId]
            )->fetchAll();
            
            foreach ($items as $item) {
                // Add stock to inventory
                $this->addStock(
                    $item['food_id'],
                    $item['quantity'],
                    'purchase_order',
                    $purchaseOrderId
                );
                
                // Update received quantity
                $this->db->query(
                    "UPDATE purchase_order_items SET received_quantity = ? WHERE id = ?",
                    [$item['quantity'], $item['id']]
                );
            }
            
            // Update purchase order status
            $this->db->query(
                "UPDATE purchase_orders SET status = 'delivered' WHERE id = ?",
                [$purchaseOrderId]
            );
            
            $this->db->commit();
            
            if ($this->logger) {
                $this->logger->info("Purchase order received", [
                    'purchase_order_id' => $purchaseOrderId
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            
            if ($this->logger) {
                $this->logger->error("Failed to receive purchase order", [
                    'purchase_order_id' => $purchaseOrderId,
                    'error' => $e->getMessage()
                ]);
            }
            
            return false;
        }
    }
}
?>

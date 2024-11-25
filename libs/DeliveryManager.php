<?php
/**
 * Delivery Manager for Food Chef Cafe Management System
 * Handles delivery orders, driver assignment, and delivery tracking
 */

class DeliveryManager {
    
    private $db;
    private $logger;
    
    public function __construct($db, $logger = null) {
        $this->db = $db;
        $this->logger = $logger;
    }
    
    /**
     * Create delivery order
     */
    public function createDeliveryOrder($orderId, $deliveryAddress, $deliveryInstructions = '', $preferredTime = null) {
        try {
            $this->db->beginTransaction();
            
            // Get order details
            $order = $this->db->query(
                "SELECT * FROM orders WHERE id = ?",
                [$orderId]
            )->fetch();
            
            if (!$order) {
                throw new Exception("Order not found");
            }
            
            // Calculate delivery fee based on distance
            $deliveryFee = $this->calculateDeliveryFee($deliveryAddress);
            $estimatedTime = $this->calculateEstimatedDeliveryTime($deliveryAddress);
            
            // Create delivery record
            $stmt = $this->db->query(
                "INSERT INTO deliveries (order_id, delivery_address, delivery_instructions, 
                 delivery_fee, estimated_delivery_time, preferred_time, status, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())",
                [$orderId, $deliveryAddress, $deliveryInstructions, $deliveryFee, $estimatedTime, $preferredTime]
            );
            
            if (!$stmt) {
                throw new Exception("Failed to create delivery record");
            }
            
            $deliveryId = $this->db->lastInsertId();
            
            // Update order with delivery info
            $this->db->query(
                "UPDATE orders SET delivery_id = ?, delivery_fee = ?, total_amount = total_amount + ? WHERE id = ?",
                [$deliveryId, $deliveryFee, $deliveryFee, $orderId]
            );
            
            $this->db->commit();
            
            if ($this->logger) {
                $this->logger->info("Delivery order created", [
                    'delivery_id' => $deliveryId,
                    'order_id' => $orderId,
                    'delivery_fee' => $deliveryFee
                ]);
            }
            
            return $deliveryId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            
            if ($this->logger) {
                $this->logger->error("Failed to create delivery order", ['error' => $e->getMessage()]);
            }
            
            return false;
        }
    }
    
    /**
     * Assign driver to delivery
     */
    public function assignDriver($deliveryId, $driverId) {
        try {
            $stmt = $this->db->query(
                "UPDATE deliveries SET driver_id = ?, status = 'assigned', assigned_at = NOW() WHERE id = ?",
                [$driverId, $deliveryId]
            );
            
            if ($stmt) {
                // Update driver status
                $this->db->query(
                    "UPDATE delivery_drivers SET status = 'busy', current_delivery_id = ? WHERE id = ?",
                    [$deliveryId, $driverId]
                );
                
                if ($this->logger) {
                    $this->logger->info("Driver assigned to delivery", [
                        'delivery_id' => $deliveryId,
                        'driver_id' => $driverId
                    ]);
                }
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to assign driver", ['error' => $e->getMessage()]);
            }
            return false;
        }
    }
    
    /**
     * Update delivery status
     */
    public function updateDeliveryStatus($deliveryId, $status, $notes = '') {
        try {
            $currentStatus = $this->db->query(
                "SELECT status, driver_id FROM deliveries WHERE id = ?",
                [$deliveryId]
            )->fetch();
            
            if (!$currentStatus) {
                return false;
            }
            
            $stmt = $this->db->query(
                "UPDATE deliveries SET status = ?, status_notes = ?, updated_at = NOW() WHERE id = ?",
                [$status, $notes, $deliveryId]
            );
            
            if ($stmt) {
                // Update driver status if delivery completed
                if ($status === 'delivered' && $currentStatus['driver_id']) {
                    $this->db->query(
                        "UPDATE delivery_drivers SET status = 'available', current_delivery_id = NULL WHERE id = ?",
                        [$currentStatus['driver_id']]
                    );
                }
                
                if ($this->logger) {
                    $this->logger->info("Delivery status updated", [
                        'delivery_id' => $deliveryId,
                        'status' => $status
                    ]);
                }
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to update delivery status", ['error' => $e->getMessage()]);
            }
            return false;
        }
    }
    
    /**
     * Get available drivers
     */
    public function getAvailableDrivers() {
        try {
            return $this->db->query(
                "SELECT * FROM delivery_drivers WHERE status = 'available' AND is_active = 1"
            )->fetchAll();
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to get available drivers", ['error' => $e->getMessage()]);
            }
            return [];
        }
    }
    
    /**
     * Get delivery details
     */
    public function getDeliveryDetails($deliveryId) {
        try {
            return $this->db->query(
                "SELECT d.*, o.customer_name, o.customer_email, o.customer_phone, 
                 o.total_amount, oi.food_name, oi.quantity,
                 dd.name as driver_name, dd.phone as driver_phone
                 FROM deliveries d
                 JOIN orders o ON d.order_id = o.id
                 JOIN order_items oi ON o.id = oi.order_id
                 LEFT JOIN delivery_drivers dd ON d.driver_id = dd.id
                 WHERE d.id = ?",
                [$deliveryId]
            )->fetchAll();
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to get delivery details", ['error' => $e->getMessage()]);
            }
            return [];
        }
    }
    
    /**
     * Get active deliveries
     */
    public function getActiveDeliveries() {
        try {
            return $this->db->query(
                "SELECT d.*, o.customer_name, o.customer_phone, dd.name as driver_name
                 FROM deliveries d
                 JOIN orders o ON d.order_id = o.id
                 LEFT JOIN delivery_drivers dd ON d.driver_id = dd.id
                 WHERE d.status IN ('pending', 'assigned', 'picked_up', 'in_transit')
                 ORDER BY d.created_at ASC"
            )->fetchAll();
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to get active deliveries", ['error' => $e->getMessage()]);
            }
            return [];
        }
    }
    
    /**
     * Calculate delivery fee
     */
    private function calculateDeliveryFee($address) {
        // Simple distance calculation (in real app, use Google Maps API)
        $baseFee = 5.00;
        $distanceMultiplier = 0.50; // $0.50 per km
        
        // For demo purposes, generate random distance
        $distance = rand(1, 15); // 1-15 km
        
        return $baseFee + ($distance * $distanceMultiplier);
    }
    
    /**
     * Calculate estimated delivery time
     */
    private function calculateEstimatedDeliveryTime($address) {
        // Base preparation time + delivery time
        $preparationTime = 20; // minutes
        $deliveryTime = 15; // minutes
        
        return $preparationTime + $deliveryTime;
    }
    
    /**
     * Get delivery statistics
     */
    public function getDeliveryStatistics($startDate = null, $endDate = null) {
        try {
            $dateFilter = "";
            $params = [];
            
            if ($startDate && $endDate) {
                $dateFilter = "WHERE DATE(d.created_at) BETWEEN ? AND ?";
                $params = [$startDate, $endDate];
            }
            
            $sql = "SELECT 
                        COUNT(*) as total_deliveries,
                        COUNT(CASE WHEN d.status = 'delivered' THEN 1 END) as completed,
                        COUNT(CASE WHEN d.status = 'cancelled' THEN 1 END) as cancelled,
                        AVG(d.delivery_fee) as avg_delivery_fee,
                        AVG(TIMESTAMPDIFF(MINUTE, d.created_at, d.updated_at)) as avg_delivery_time
                     FROM deliveries d 
                     $dateFilter";
            
            $stats = $this->db->query($sql, $params)->fetch();
            
            return $stats;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to get delivery statistics", ['error' => $e->getMessage()]);
            }
            return [];
        }
    }
    
    /**
     * Track delivery location
     */
    public function updateDeliveryLocation($deliveryId, $latitude, $longitude) {
        try {
            $stmt = $this->db->query(
                "UPDATE deliveries SET 
                 current_latitude = ?, current_longitude = ?, location_updated_at = NOW() 
                 WHERE id = ?",
                [$latitude, $longitude, $deliveryId]
            );
            
            if ($stmt) {
                if ($this->logger) {
                    $this->logger->info("Delivery location updated", [
                        'delivery_id' => $deliveryId,
                        'latitude' => $latitude,
                        'longitude' => $longitude
                    ]);
                }
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to update delivery location", ['error' => $e->getMessage()]);
            }
            return false;
        }
    }
}
?>

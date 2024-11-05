<?php
/**
 * Loyalty Manager for Food Chef Cafe Management System
 * Handles customer loyalty points, rewards, and membership tiers
 */

class LoyaltyManager {
    
    private $db;
    private $logger;
    
    public function __construct($db, $logger = null) {
        $this->db = $db;
        $this->logger = $logger;
    }
    
    /**
     * Award points for order
     */
    public function awardPointsForOrder($orderId, $customerEmail, $orderAmount) {
        try {
            $points = floor($orderAmount * 10); // 10 points per dollar
            
            $stmt = $this->db->query(
                "INSERT INTO loyalty_points (customer_email, points_earned, order_id, reason, created_at) 
                 VALUES (?, ?, ?, 'order_purchase', NOW())",
                [$customerEmail, $points, $orderId]
            );
            
            if ($stmt) {
                $this->updateCustomerPoints($customerEmail);
                
                if ($this->logger) {
                    $this->logger->info("Loyalty points awarded", [
                        'customer_email' => $customerEmail,
                        'points' => $points,
                        'order_id' => $orderId
                    ]);
                }
                
                return $points;
            }
            
            return false;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to award loyalty points", ['error' => $e->getMessage()]);
            }
            return false;
        }
    }
    
    /**
     * Update customer total points
     */
    private function updateCustomerPoints($customerEmail) {
        try {
            $totalPoints = $this->db->query(
                "SELECT SUM(points_earned - COALESCE(points_used, 0)) as total_points 
                 FROM loyalty_points 
                 WHERE customer_email = ?",
                [$customerEmail]
            )->fetch();
            
            $points = $totalPoints['total_points'] ?? 0;
            
            // Update or insert customer record
            $this->db->query(
                "INSERT INTO loyalty_customers (customer_email, total_points, tier, updated_at) 
                 VALUES (?, ?, ?, NOW()) 
                 ON DUPLICATE KEY UPDATE 
                 total_points = VALUES(total_points), 
                 tier = VALUES(tier), 
                 updated_at = NOW()",
                [$customerEmail, $points, $this->calculateTier($points)]
            );
            
            return true;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to update customer points", ['error' => $e->getMessage()]);
            }
            return false;
        }
    }
    
    /**
     * Calculate customer tier based on points
     */
    private function calculateTier($points) {
        if ($points >= 1000) return 'platinum';
        if ($points >= 500) return 'gold';
        if ($points >= 100) return 'silver';
        return 'bronze';
    }
    
    /**
     * Get customer loyalty status
     */
    public function getCustomerStatus($customerEmail) {
        try {
            $customer = $this->db->query(
                "SELECT * FROM loyalty_customers WHERE customer_email = ?",
                [$customerEmail]
            )->fetch();
            
            if (!$customer) {
                return [
                    'tier' => 'bronze',
                    'total_points' => 0,
                    'next_tier' => 'silver',
                    'points_needed' => 100
                ];
            }
            
            $nextTier = $this->getNextTier($customer['tier']);
            $pointsNeeded = $this->getPointsForTier($nextTier) - $customer['total_points'];
            
            return [
                'tier' => $customer['tier'],
                'total_points' => $customer['total_points'],
                'next_tier' => $nextTier,
                'points_needed' => max(0, $pointsNeeded)
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to get customer status", ['error' => $e->getMessage()]);
            }
            return false;
        }
    }
    
    /**
     * Get next tier
     */
    private function getNextTier($currentTier) {
        $tiers = ['bronze' => 'silver', 'silver' => 'gold', 'gold' => 'platinum'];
        return $tiers[$currentTier] ?? 'platinum';
    }
    
    /**
     * Get points required for tier
     */
    private function getPointsForTier($tier) {
        $tierPoints = ['bronze' => 0, 'silver' => 100, 'gold' => 500, 'platinum' => 1000];
        return $tierPoints[$tier] ?? 0;
    }
    
    /**
     * Get available rewards
     */
    public function getAvailableRewards($customerEmail) {
        try {
            $customerStatus = $this->getCustomerStatus($customerEmail);
            $tier = $customerStatus['tier'];
            
            $rewards = $this->db->query(
                "SELECT * FROM loyalty_rewards 
                 WHERE required_tier = ? AND is_active = 1 
                 ORDER BY points_cost ASC",
                [$tier]
            )->fetchAll();
            
            return $rewards;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to get available rewards", ['error' => $e->getMessage()]);
            }
            return [];
        }
    }
    
    /**
     * Redeem reward
     */
    public function redeemReward($customerEmail, $rewardId) {
        try {
            $this->db->beginTransaction();
            
            // Get reward details
            $reward = $this->db->query(
                "SELECT * FROM loyalty_rewards WHERE id = ? AND is_active = 1",
                [$rewardId]
            )->fetch();
            
            if (!$reward) {
                throw new Exception("Reward not found or inactive");
            }
            
            // Check customer has enough points
            $customerStatus = $this->getCustomerStatus($customerEmail);
            if ($customerStatus['total_points'] < $reward['points_cost']) {
                throw new Exception("Insufficient points");
            }
            
            // Deduct points
            $stmt = $this->db->query(
                "INSERT INTO loyalty_points (customer_email, points_earned, points_used, reason, created_at) 
                 VALUES (?, 0, ?, 'reward_redemption', NOW())",
                [$customerEmail, $reward['points_cost']]
            );
            
            if (!$stmt) {
                throw new Exception("Failed to deduct points");
            }
            
            // Create redemption record
            $stmt = $this->db->query(
                "INSERT INTO loyalty_redemptions (customer_email, reward_id, points_used, created_at) 
                 VALUES (?, ?, ?, NOW())",
                [$customerEmail, $rewardId, $reward['points_cost']]
            );
            
            if (!$stmt) {
                throw new Exception("Failed to create redemption record");
            }
            
            // Update customer points
            $this->updateCustomerPoints($customerEmail);
            
            $this->db->commit();
            
            if ($this->logger) {
                $this->logger->info("Reward redeemed", [
                    'customer_email' => $customerEmail,
                    'reward_id' => $rewardId,
                    'points_used' => $reward['points_cost']
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            
            if ($this->logger) {
                $this->logger->error("Failed to redeem reward", ['error' => $e->getMessage()]);
            }
            
            return false;
        }
    }
    
    /**
     * Get loyalty statistics
     */
    public function getLoyaltyStatistics() {
        try {
            $stats = $this->db->query(
                "SELECT 
                    COUNT(*) as total_customers,
                    COUNT(CASE WHEN tier = 'platinum' THEN 1 END) as platinum_customers,
                    COUNT(CASE WHEN tier = 'gold' THEN 1 END) as gold_customers,
                    COUNT(CASE WHEN tier = 'silver' THEN 1 END) as silver_customers,
                    COUNT(CASE WHEN tier = 'bronze' THEN 1 END) as bronze_customers,
                    AVG(total_points) as avg_points
                 FROM loyalty_customers"
            )->fetch();
            
            return $stats;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to get loyalty statistics", ['error' => $e->getMessage()]);
            }
            return [];
        }
    }
    
    /**
     * Get top customers by points
     */
    public function getTopCustomers($limit = 10) {
        try {
            return $this->db->query(
                "SELECT customer_email, total_points, tier, updated_at 
                 FROM loyalty_customers 
                 ORDER BY total_points DESC 
                 LIMIT ?",
                [$limit]
            )->fetchAll();
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to get top customers", ['error' => $e->getMessage()]);
            }
            return [];
        }
    }
}
?>

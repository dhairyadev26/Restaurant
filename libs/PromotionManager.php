<?php
/**
 * Promotion Manager Class
 * Manages discounts, promotions, and special offers
 */
class PromotionManager {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function createPromotion($data) {
        $sql = "INSERT INTO promotions (name, description, discount_type, discount_value, 
                min_order_amount, max_discount, start_date, end_date, 
                applicable_categories, applicable_items, usage_limit, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $applicableCategories = json_encode($data['applicable_categories'] ?? []);
        $applicableItems = json_encode($data['applicable_items'] ?? []);
        
        $result = $this->db->query($sql, [
            $data['name'],
            $data['description'],
            $data['discount_type'], // percentage, fixed, buy_one_get_one
            $data['discount_value'],
            $data['min_order_amount'] ?? 0,
            $data['max_discount'] ?? 0,
            $data['start_date'],
            $data['end_date'],
            $applicableCategories,
            $applicableItems,
            $data['usage_limit'] ?? 0
        ]);
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    public function updatePromotion($promotionId, $data) {
        $sql = "UPDATE promotions SET 
                name = ?, description = ?, discount_type = ?, discount_value = ?, 
                min_order_amount = ?, max_discount = ?, start_date = ?, end_date = ?, 
                applicable_categories = ?, applicable_items = ?, usage_limit = ?, 
                updated_at = NOW() 
                WHERE id = ?";
        
        $applicableCategories = json_encode($data['applicable_categories'] ?? []);
        $applicableItems = json_encode($data['applicable_items'] ?? []);
        
        return $this->db->query($sql, [
            $data['name'],
            $data['description'],
            $data['discount_type'],
            $data['discount_value'],
            $data['min_order_amount'] ?? 0,
            $data['max_discount'] ?? 0,
            $data['start_date'],
            $data['end_date'],
            $applicableCategories,
            $applicableItems,
            $data['usage_limit'] ?? 0,
            $promotionId
        ]);
    }
    
    public function getPromotion($promotionId) {
        $sql = "SELECT * FROM promotions WHERE id = ?";
        $promotion = $this->db->fetchOne($sql, [$promotionId]);
        
        if ($promotion) {
            $promotion['applicable_categories'] = json_decode($promotion['applicable_categories'], true);
            $promotion['applicable_items'] = json_decode($promotion['applicable_items'], true);
        }
        
        return $promotion;
    }
    
    public function getAllPromotions($activeOnly = true) {
        $sql = "SELECT * FROM promotions";
        
        if ($activeOnly) {
            $sql .= " WHERE start_date <= CURDATE() AND end_date >= CURDATE()";
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $promotions = $this->db->fetchAll($sql);
        
        // Decode JSON fields
        foreach ($promotions as &$promotion) {
            $promotion['applicable_categories'] = json_decode($promotion['applicable_categories'], true);
            $promotion['applicable_items'] = json_decode($promotion['applicable_items'], true);
        }
        
        return $promotions;
    }
    
    public function getApplicablePromotions($orderItems, $orderAmount, $customerId = null) {
        $activePromotions = $this->getAllPromotions(true);
        $applicablePromotions = [];
        
        foreach ($activePromotions as $promotion) {
            if ($this->isPromotionApplicable($promotion, $orderItems, $orderAmount, $customerId)) {
                $applicablePromotions[] = $promotion;
            }
        }
        
        // Sort by discount value (highest first)
        usort($applicablePromotions, function($a, $b) {
            return $b['discount_value'] - $a['discount_value'];
        });
        
        return $applicablePromotions;
    }
    
    private function isPromotionApplicable($promotion, $orderItems, $orderAmount, $customerId) {
        // Check date validity
        if (strtotime($promotion['start_date']) > time() || strtotime($promotion['end_date']) < time()) {
            return false;
        }
        
        // Check minimum order amount
        if ($orderAmount < $promotion['min_order_amount']) {
            return false;
        }
        
        // Check usage limit
        if ($promotion['usage_limit'] > 0) {
            $usageCount = $this->getPromotionUsageCount($promotion['id'], $customerId);
            if ($usageCount >= $promotion['usage_limit']) {
                return false;
            }
        }
        
        // Check if items are applicable
        if (!empty($promotion['applicable_items'])) {
            $hasApplicableItem = false;
            foreach ($orderItems as $item) {
                if (in_array($item['food_id'], $promotion['applicable_items'])) {
                    $hasApplicableItem = true;
                    break;
                }
            }
            if (!$hasApplicableItem) {
                return false;
            }
        }
        
        // Check if categories are applicable
        if (!empty($promotion['applicable_categories'])) {
            $hasApplicableCategory = false;
            foreach ($orderItems as $item) {
                if (in_array($item['category_id'], $promotion['applicable_categories'])) {
                    $hasApplicableCategory = true;
                    break;
                }
            }
            if (!$hasApplicableCategory) {
                return false;
            }
        }
        
        return true;
    }
    
    public function calculateDiscount($promotion, $orderItems, $orderAmount) {
        $discountAmount = 0;
        
        switch ($promotion['discount_type']) {
            case 'percentage':
                $discountAmount = ($orderAmount * $promotion['discount_value']) / 100;
                if ($promotion['max_discount'] > 0) {
                    $discountAmount = min($discountAmount, $promotion['max_discount']);
                }
                break;
                
            case 'fixed':
                $discountAmount = $promotion['discount_value'];
                break;
                
            case 'buy_one_get_one':
                $discountAmount = $this->calculateBOGODiscount($orderItems, $promotion);
                break;
        }
        
        return round($discountAmount, 2);
    }
    
    private function calculateBOGODiscount($orderItems, $promotion) {
        $discountAmount = 0;
        
        if (!empty($promotion['applicable_items'])) {
            foreach ($promotion['applicable_items'] as $itemId) {
                foreach ($orderItems as $item) {
                    if ($item['food_id'] == $itemId && $item['quantity'] >= 2) {
                        // Buy one get one free - discount is half the price
                        $discountAmount += ($item['price'] * floor($item['quantity'] / 2));
                    }
                }
            }
        }
        
        return $discountAmount;
    }
    
    public function applyPromotion($promotionId, $orderId, $customerId, $discountAmount) {
        $sql = "INSERT INTO promotion_usage (promotion_id, order_id, customer_id, 
                discount_amount, used_at) VALUES (?, ?, ?, ?, NOW())";
        
        return $this->db->query($sql, [$promotionId, $orderId, $customerId, $discountAmount]);
    }
    
    public function getPromotionUsageCount($promotionId, $customerId = null) {
        $sql = "SELECT COUNT(*) as count FROM promotion_usage WHERE promotion_id = ?";
        $params = [$promotionId];
        
        if ($customerId) {
            $sql .= " AND customer_id = ?";
            $params[] = $customerId;
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return $result['count'] ?? 0;
    }
    
    public function getPromotionStats($startDate = null, $endDate = null) {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if ($startDate) {
            $whereClause .= " AND pu.used_at >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $whereClause .= " AND pu.used_at <= ?";
            $params[] = $endDate;
        }
        
        $sql = "SELECT p.name, p.discount_type, p.discount_value,
                COUNT(pu.id) as usage_count,
                SUM(pu.discount_amount) as total_discount_given
                FROM promotions p 
                LEFT JOIN promotion_usage pu ON p.id = pu.promotion_id 
                $whereClause
                GROUP BY p.id, p.name, p.discount_type, p.discount_value
                ORDER BY total_discount_given DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function deletePromotion($promotionId) {
        // Check if promotion has been used
        $usageCheck = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM promotion_usage WHERE promotion_id = ?", 
            [$promotionId]
        );
        
        if (($usageCheck['count'] ?? 0) > 0) {
            return ['success' => false, 'error' => 'Cannot delete promotion that has been used'];
        }
        
        $sql = "DELETE FROM promotions WHERE id = ?";
        $result = $this->db->query($sql, [$promotionId]);
        
        if ($result) {
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Failed to delete promotion'];
    }
}
?>

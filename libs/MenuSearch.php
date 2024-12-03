<?php
/**
 * Menu Search Class
 * Provides advanced search functionality for the food menu
 */
class MenuSearch {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function searchMenu($query, $filters = []) {
        $sql = "SELECT f.*, c.name as category_name 
                FROM food f 
                LEFT JOIN menu_categories c ON f.category_id = c.id 
                WHERE 1=1";
        
        $params = [];
        
        // Basic search
        if (!empty($query)) {
            $sql .= " AND (f.name LIKE ? OR f.description LIKE ?)";
            $params[] = "%$query%";
            $params[] = "%$query%";
        }
        
        // Category filter
        if (!empty($filters['category'])) {
            $sql .= " AND f.category_id = ?";
            $params[] = $filters['category'];
        }
        
        // Price range filter
        if (!empty($filters['min_price'])) {
            $sql .= " AND f.price >= ?";
            $params[] = $filters['min_price'];
        }
        
        if (!empty($filters['max_price'])) {
            $sql .= " AND f.price <= ?";
            $params[] = $filters['max_price'];
        }
        
        // Dietary filters
        if (!empty($filters['vegetarian'])) {
            $sql .= " AND f.is_vegetarian = 1";
        }
        
        if (!empty($filters['spicy'])) {
            $sql .= " AND f.is_spicy = 1";
        }
        
        // Availability filter
        if (!empty($filters['available'])) {
            $sql .= " AND f.is_available = 1";
        }
        
        // Featured items
        if (!empty($filters['featured'])) {
            $sql .= " AND f.is_featured = 1";
        }
        
        // Sort options
        $sort = $filters['sort'] ?? 'name';
        $order = $filters['order'] ?? 'ASC';
        
        switch ($sort) {
            case 'price':
                $sql .= " ORDER BY f.price $order";
                break;
            case 'rating':
                $sql .= " ORDER BY f.avg_rating $order";
                break;
            case 'popularity':
                $sql .= " ORDER BY f.total_reviews $order";
                break;
            default:
                $sql .= " ORDER BY f.name $order";
        }
        
        // Limit results
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getPopularItems($limit = 10) {
        $sql = "SELECT f.*, c.name as category_name 
                FROM food f 
                LEFT JOIN menu_categories c ON f.category_id = c.id 
                WHERE f.is_available = 1 
                ORDER BY f.total_reviews DESC, f.avg_rating DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    public function getFeaturedItems($limit = 6) {
        $sql = "SELECT f.*, c.name as category_name 
                FROM food f 
                LEFT JOIN menu_categories c ON f.category_id = c.id 
                WHERE f.is_featured = 1 AND f.is_available = 1 
                ORDER BY f.avg_rating DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    public function getCategories() {
        $sql = "SELECT * FROM menu_categories ORDER BY name";
        return $this->db->fetchAll($sql);
    }
    
    public function getSuggestions($query, $limit = 5) {
        $sql = "SELECT DISTINCT name FROM food 
                WHERE name LIKE ? AND is_available = 1 
                ORDER BY total_reviews DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, ["%$query%", $limit]);
    }
    
    public function getSearchStats($query) {
        $sql = "SELECT COUNT(*) as total FROM food 
                WHERE (name LIKE ? OR description LIKE ?) 
                AND is_available = 1";
        
        $result = $this->db->fetchOne($sql, ["%$query%", "%$query%"]);
        return $result['total'] ?? 0;
    }
}
?>

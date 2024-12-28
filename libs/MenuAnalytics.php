<?php
/**
 * Menu Analytics Class
 * Provides analytics and insights for menu performance
 */
class MenuAnalytics {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function getPopularItems($limit = 10, $period = '30') {
        $sql = "SELECT f.id, f.name, f.price, f.category_id,
                COUNT(oi.id) as order_count,
                SUM(oi.quantity) as total_quantity,
                AVG(fr.rating) as avg_rating,
                COUNT(fr.id) as review_count
                FROM food f
                LEFT JOIN order_items oi ON f.id = oi.food_id
                LEFT JOIN food_reviews fr ON f.id = fr.food_id
                WHERE f.is_available = 1
                AND (oi.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) OR oi.created_at IS NULL)
                GROUP BY f.id, f.name, f.price, f.category_id
                ORDER BY order_count DESC, total_quantity DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$period, $limit]);
    }
    
    public function getCategoryPerformance($period = '30') {
        $sql = "SELECT c.id, c.name,
                COUNT(DISTINCT f.id) as total_items,
                COUNT(oi.id) as total_orders,
                SUM(oi.quantity * oi.price) as total_revenue,
                AVG(fr.rating) as avg_rating
                FROM menu_categories c
                LEFT JOIN food f ON c.id = f.category_id
                LEFT JOIN order_items oi ON f.id = oi.food_id
                LEFT JOIN food_reviews fr ON f.id = fr.food_id
                WHERE (oi.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) OR oi.created_at IS NULL)
                GROUP BY c.id, c.name
                ORDER BY total_revenue DESC";
        
        return $this->db->fetchAll($sql, [$period]);
    }
    
    public function getSalesTrends($days = 30) {
        $sql = "SELECT DATE(oi.created_at) as date,
                COUNT(DISTINCT o.id) as orders_count,
                SUM(oi.quantity * oi.price) as daily_revenue,
                COUNT(oi.id) as items_sold
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                WHERE oi.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(oi.created_at)
                ORDER BY date DESC";
        
        return $this->db->fetchAll($sql, [$days]);
    }
    
    public function getPeakHours() {
        $sql = "SELECT HOUR(o.created_at) as hour,
                COUNT(*) as order_count,
                AVG(o.total_amount) as avg_order_value
                FROM orders o
                WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY HOUR(o.created_at)
                ORDER BY order_count DESC";
        
        return $this->db->fetchAll($sql);
    }
    
    public function getCustomerPreferences($customerId = null) {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if ($customerId) {
            $whereClause .= " AND o.customer_id = ?";
            $params[] = $customerId;
        }
        
        $sql = "SELECT f.category_id, c.name as category_name,
                COUNT(oi.id) as order_count,
                SUM(oi.quantity) as total_quantity,
                AVG(fr.rating) as avg_rating
                FROM order_items oi
                JOIN food f ON oi.food_id = f.id
                JOIN menu_categories c ON f.category_id = c.id
                JOIN orders o ON oi.order_id = o.id
                LEFT JOIN food_reviews fr ON f.id = fr.food_id
                $whereClause
                GROUP BY f.category_id, c.name
                ORDER BY order_count DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getSeasonalTrends($year = null) {
        $year = $year ?? date('Y');
        
        $sql = "SELECT MONTH(oi.created_at) as month,
                COUNT(oi.id) as items_sold,
                SUM(oi.quantity * oi.price) as revenue,
                COUNT(DISTINCT o.id) as orders_count
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                WHERE YEAR(oi.created_at) = ?
                GROUP BY MONTH(oi.created_at)
                ORDER BY month";
        
        return $this->db->fetchAll($sql, [$year]);
    }
    
    public function getLowPerformingItems($threshold = 5) {
        $sql = "SELECT f.id, f.name, f.price, c.name as category_name,
                COUNT(oi.id) as order_count,
                SUM(oi.quantity) as total_quantity,
                f.stock_quantity
                FROM food f
                LEFT JOIN menu_categories c ON f.category_id = c.id
                LEFT JOIN order_items oi ON f.id = oi.food_id
                WHERE f.is_available = 1
                GROUP BY f.id, f.name, f.price, c.name, f.stock_quantity
                HAVING order_count <= ?
                ORDER BY order_count ASC, total_quantity ASC";
        
        return $this->db->fetchAll($sql, [$threshold]);
    }
    
    public function getProfitabilityAnalysis($period = '30') {
        $sql = "SELECT f.id, f.name, f.price,
                SUM(oi.quantity) as total_sold,
                SUM(oi.quantity * oi.price) as total_revenue,
                AVG(fr.rating) as customer_satisfaction
                FROM food f
                LEFT JOIN order_items oi ON f.id = oi.food_id
                LEFT JOIN food_reviews fr ON f.id = fr.food_id
                WHERE (oi.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) OR oi.created_at IS NULL)
                GROUP BY f.id, f.name, f.price
                HAVING total_sold > 0
                ORDER BY total_revenue DESC";
        
        return $this->db->fetchAll($sql, [$period]);
    }
    
    public function getInventoryTurnover($period = '30') {
        $sql = "SELECT f.id, f.name, f.stock_quantity,
                SUM(oi.quantity) as units_sold,
                ROUND(SUM(oi.quantity) / GREATEST(f.stock_quantity, 1), 2) as turnover_rate
                FROM food f
                LEFT JOIN order_items oi ON f.id = oi.food_id
                WHERE (oi.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) OR oi.created_at IS NULL)
                GROUP BY f.id, f.name, f.stock_quantity
                HAVING units_sold > 0
                ORDER BY turnover_rate DESC";
        
        return $this->db->fetchAll($sql, [$period]);
    }
    
    public function generateReport($type = 'comprehensive', $period = '30') {
        $report = [
            'generated_at' => date('Y-m-d H:i:s'),
            'period_days' => $period,
            'type' => $type
        ];
        
        switch ($type) {
            case 'popular_items':
                $report['data'] = $this->getPopularItems(20, $period);
                break;
                
            case 'category_performance':
                $report['data'] = $this->getCategoryPerformance($period);
                break;
                
            case 'sales_trends':
                $report['data'] = $this->getSalesTrends($period);
                break;
                
            case 'comprehensive':
                $report['popular_items'] = $this->getPopularItems(10, $period);
                $report['category_performance'] = $this->getCategoryPerformance($period);
                $report['sales_trends'] = $this->getSalesTrends($period);
                $report['peak_hours'] = $this->getPeakHours();
                $report['low_performing'] = $this->getLowPerformingItems();
                break;
        }
        
        return $report;
    }
    
    public function exportToCSV($data, $filename = null) {
        if (empty($data)) {
            return false;
        }
        
        $filename = $filename ?? 'menu_analytics_' . date('Y-m-d_H-i-s') . '.csv';
        
        $output = fopen($filename, 'w');
        if (!$output) {
            return false;
        }
        
        // Write headers
        fputcsv($output, array_keys($data[0]));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        return $filename;
    }
}
?>

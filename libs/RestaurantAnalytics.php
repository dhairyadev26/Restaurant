<?php
/**
 * Restaurant Analytics Class
 * Provides comprehensive analytics and insights for restaurant performance
 */
class RestaurantAnalytics {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function getDashboardStats($period = '30') {
        $stats = [
            'orders' => $this->getOrderStats($period),
            'revenue' => $this->getRevenueStats($period),
            'customers' => $this->getCustomerStats($period),
            'menu' => $this->getMenuStats($period),
            'reservations' => $this->getReservationStats($period),
            'staff' => $this->getStaffStats($period)
        ];
        
        return $stats;
    }
    
    private function getOrderStats($period) {
        $sql = "SELECT 
                COUNT(*) as total_orders,
                COUNT(DISTINCT customer_id) as unique_customers,
                AVG(total_amount) as avg_order_value,
                SUM(total_amount) as total_revenue,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders
                FROM orders 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        return $this->db->fetchOne($sql, [$period]);
    }
    
    private function getRevenueStats($period) {
        $sql = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as orders_count,
                SUM(total_amount) as daily_revenue,
                AVG(total_amount) as avg_order_value
                FROM orders 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND status = 'completed'
                GROUP BY DATE(created_at)
                ORDER BY date DESC";
        
        return $this->db->fetchAll($sql, [$period]);
    }
    
    private function getCustomerStats($period) {
        $sql = "SELECT 
                COUNT(DISTINCT c.id) as total_customers,
                COUNT(DISTINCT o.customer_id) as active_customers,
                AVG(customer_orders.order_count) as avg_orders_per_customer,
                AVG(customer_orders.total_spent) as avg_spending_per_customer
                FROM customers c
                LEFT JOIN (
                    SELECT customer_id, COUNT(*) as order_count, SUM(total_amount) as total_spent
                    FROM orders 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    GROUP BY customer_id
                ) customer_orders ON c.id = customer_orders.customer_id";
        
        return $this->db->fetchOne($sql, [$period]);
    }
    
    private function getMenuStats($period) {
        $sql = "SELECT 
                f.id, f.name, f.price, c.name as category_name,
                COUNT(oi.id) as order_count,
                SUM(oi.quantity) as total_quantity,
                SUM(oi.quantity * oi.price) as total_revenue,
                AVG(fr.rating) as avg_rating,
                COUNT(fr.id) as review_count
                FROM food f
                LEFT JOIN menu_categories c ON f.category_id = c.id
                LEFT JOIN order_items oi ON f.id = oi.food_id
                LEFT JOIN orders o ON oi.order_id = o.id
                LEFT JOIN food_reviews fr ON f.id = fr.food_id
                WHERE (o.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) OR o.created_at IS NULL)
                GROUP BY f.id, f.name, f.price, c.name
                ORDER BY total_revenue DESC";
        
        return $this->db->fetchAll($sql, [$period]);
    }
    
    private function getReservationStats($period) {
        $sql = "SELECT 
                COUNT(*) as total_reservations,
                COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_reservations,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_reservations,
                AVG(guests) as avg_guests_per_reservation,
                COUNT(DISTINCT customer_id) as unique_customers
                FROM reservations 
                WHERE reservation_date >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        return $this->db->fetchOne($sql, [$period]);
    }
    
    private function getStaffStats($period) {
        $sql = "SELECT 
                COUNT(*) as total_staff,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_staff,
                AVG(salary) as avg_salary,
                COUNT(DISTINCT department) as departments_count
                FROM staff";
        
        return $this->db->fetchOne($sql);
    }
    
    public function getPeakHoursAnalysis($period = '30') {
        $sql = "SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as order_count,
                AVG(total_amount) as avg_order_value,
                SUM(total_amount) as total_revenue
                FROM orders 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY HOUR(created_at)
                ORDER BY order_count DESC";
        
        return $this->db->fetchAll($sql, [$period]);
    }
    
    public function getWeeklyTrends($weeks = 12) {
        $sql = "SELECT 
                YEARWEEK(created_at) as year_week,
                COUNT(*) as orders_count,
                SUM(total_amount) as weekly_revenue,
                AVG(total_amount) as avg_order_value,
                COUNT(DISTINCT customer_id) as unique_customers
                FROM orders 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? WEEK)
                GROUP BY YEARWEEK(created_at)
                ORDER BY year_week DESC";
        
        return $this->db->fetchAll($sql, [$weeks]);
    }
    
    public function getCustomerSegmentation($period = '30') {
        $sql = "SELECT 
                customer_id,
                COUNT(*) as order_count,
                SUM(total_amount) as total_spent,
                AVG(total_amount) as avg_order_value,
                MAX(created_at) as last_order_date,
                CASE 
                    WHEN COUNT(*) >= 10 THEN 'VIP'
                    WHEN COUNT(*) >= 5 THEN 'Regular'
                    WHEN COUNT(*) >= 2 THEN 'Occasional'
                    ELSE 'New'
                END as customer_segment
                FROM orders 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY customer_id
                ORDER BY total_spent DESC";
        
        return $this->db->fetchAll($sql, [$period]);
    }
    
    public function getInventoryAnalytics() {
        $sql = "SELECT 
                f.id, f.name, f.stock_quantity, f.min_stock_level,
                COUNT(oi.id) as orders_count,
                SUM(oi.quantity) as units_sold,
                ROUND(SUM(oi.quantity) / GREATEST(f.stock_quantity, 1), 2) as turnover_rate,
                CASE 
                    WHEN f.stock_quantity <= f.min_stock_level THEN 'Low Stock'
                    WHEN f.stock_quantity <= f.min_stock_level * 2 THEN 'Medium Stock'
                    ELSE 'Good Stock'
                END as stock_status
                FROM food f
                LEFT JOIN order_items oi ON f.id = oi.food_id
                LEFT JOIN orders o ON oi.order_id = o.id
                WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) OR o.created_at IS NULL
                GROUP BY f.id, f.name, f.stock_quantity, f.min_stock_level
                ORDER BY turnover_rate DESC";
        
        return $this->db->fetchAll($sql);
    }
    
    public function getProfitabilityAnalysis($period = '30') {
        $sql = "SELECT 
                f.id, f.name, f.price,
                COUNT(oi.id) as orders_count,
                SUM(oi.quantity) as units_sold,
                SUM(oi.quantity * oi.price) as total_revenue,
                AVG(fr.rating) as customer_satisfaction,
                ROUND((SUM(oi.quantity * oi.price) / COUNT(oi.id)), 2) as revenue_per_order
                FROM food f
                LEFT JOIN order_items oi ON f.id = oi.food_id
                LEFT JOIN orders o ON oi.order_id = o.id
                LEFT JOIN food_reviews fr ON f.id = fr.food_id
                WHERE (o.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) OR o.created_at IS NULL)
                GROUP BY f.id, f.name, f.price
                HAVING units_sold > 0
                ORDER BY total_revenue DESC";
        
        return $this->db->fetchAll($sql, [$period]);
    }
    
    public function generateComprehensiveReport($period = '30') {
        $report = [
            'generated_at' => date('Y-m-d H:i:s'),
            'period_days' => $period,
            'dashboard_stats' => $this->getDashboardStats($period),
            'peak_hours' => $this->getPeakHoursAnalysis($period),
            'weekly_trends' => $this->getWeeklyTrends(),
            'customer_segmentation' => $this->getCustomerSegmentation($period),
            'inventory_analytics' => $this->getInventoryAnalytics(),
            'profitability_analysis' => $this->getProfitabilityAnalysis($period)
        ];
        
        return $report;
    }
    
    public function exportReportToCSV($report, $filename = null) {
        $filename = $filename ?? 'restaurant_analytics_' . date('Y-m-d_H-i-s') . '.csv';
        
        $output = fopen($filename, 'w');
        if (!$output) {
            return false;
        }
        
        // Export dashboard stats
        fputcsv($output, ['Dashboard Statistics']);
        fputcsv($output, ['Metric', 'Value']);
        foreach ($report['dashboard_stats'] as $category => $data) {
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    fputcsv($output, [$key, $value]);
                }
            }
        }
        
        fputcsv($output, []); // Empty line
        
        // Export peak hours
        fputcsv($output, ['Peak Hours Analysis']);
        if (!empty($report['peak_hours'])) {
            fputcsv($output, array_keys($report['peak_hours'][0]));
            foreach ($report['peak_hours'] as $row) {
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
        return $filename;
    }
}
?>

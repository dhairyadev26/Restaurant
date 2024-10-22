<?php
/**
 * Report Generator for Food Chef Cafe Management System
 * Generates various business reports and analytics
 */

class ReportGenerator {
    
    private $db;
    private $logger;
    
    public function __construct($db, $logger = null) {
        $this->db = $db;
        $this->logger = $logger;
    }
    
    /**
     * Generate sales report
     */
    public function generateSalesReport($startDate, $endDate) {
        try {
            $sql = "SELECT 
                        DATE(o.created_at) as date,
                        COUNT(*) as total_orders,
                        SUM(o.total_amount) as total_revenue,
                        AVG(o.total_amount) as avg_order_value,
                        COUNT(DISTINCT o.customer_email) as unique_customers
                     FROM orders o 
                     WHERE o.created_at BETWEEN ? AND ? 
                     AND o.status != 'cancelled'
                     GROUP BY DATE(o.created_at)
                     ORDER BY date DESC";
            
            $dailyStats = $this->db->query($sql, [$startDate, $endDate])->fetchAll();
            
            // Get top selling items
            $topItems = $this->db->query(
                "SELECT oi.food_name, SUM(oi.quantity) as total_sold, SUM(oi.total_price) as revenue
                 FROM order_items oi 
                 JOIN orders o ON oi.order_id = o.id 
                 WHERE o.created_at BETWEEN ? AND ? AND o.status != 'cancelled'
                 GROUP BY oi.food_id, oi.food_name 
                 ORDER BY total_sold DESC 
                 LIMIT 10",
                [$startDate, $endDate]
            )->fetchAll();
            
            return [
                'daily_stats' => $dailyStats,
                'top_items' => $topItems,
                'period' => ['start' => $startDate, 'end' => $endDate]
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to generate sales report", ['error' => $e->getMessage()]);
            }
            return false;
        }
    }
    
    /**
     * Generate reservation report
     */
    public function generateReservationReport($startDate, $endDate) {
        try {
            $sql = "SELECT 
                        DATE(r.reservation_date) as date,
                        COUNT(*) as total_reservations,
                        COUNT(CASE WHEN r.status = 'confirmed' THEN 1 END) as confirmed,
                        COUNT(CASE WHEN r.status = 'cancelled' THEN 1 END) as cancelled,
                        AVG(r.guests) as avg_guests,
                        SUM(r.guests) as total_guests
                     FROM reservations r 
                     WHERE r.reservation_date BETWEEN ? AND ?
                     GROUP BY DATE(r.reservation_date)
                     ORDER BY date DESC";
            
            $dailyStats = $this->db->query($sql, [$startDate, $endDate])->fetchAll();
            
            // Get popular time slots
            $popularTimes = $this->db->query(
                "SELECT r.reservation_time, COUNT(*) as count
                 FROM reservations r 
                 WHERE r.reservation_date BETWEEN ? AND ? AND r.status = 'confirmed'
                 GROUP BY r.reservation_time 
                 ORDER BY count DESC 
                 LIMIT 5",
                [$startDate, $endDate]
            )->fetchAll();
            
            return [
                'daily_stats' => $dailyStats,
                'popular_times' => $popularTimes,
                'period' => ['start' => $startDate, 'end' => $endDate]
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to generate reservation report", ['error' => $e->getMessage()]);
            }
            return false;
        }
    }
    
    /**
     * Generate inventory report
     */
    public function generateInventoryReport() {
        try {
            // Get current stock levels
            $stockLevels = $this->db->query(
                "SELECT f.name, f.stock_quantity, f.min_stock_level, f.unit, c.name as category
                 FROM food f 
                 LEFT JOIN menu_categories c ON f.category_id = c.id 
                 WHERE f.is_active = 1 
                 ORDER BY f.stock_quantity ASC"
            )->fetchAll();
            
            // Get low stock items
            $lowStock = $this->db->query(
                "SELECT * FROM low_stock_items"
            )->fetchAll();
            
            // Get recent inventory transactions
            $recentTransactions = $this->db->query(
                "SELECT it.*, f.name as food_name 
                 FROM inventory_transactions it 
                 JOIN food f ON it.food_id = f.id 
                 ORDER BY it.created_at DESC 
                 LIMIT 20"
            )->fetchAll();
            
            return [
                'stock_levels' => $stockLevels,
                'low_stock_items' => $lowStock,
                'recent_transactions' => $recentTransactions
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to generate inventory report", ['error' => $e->getMessage()]);
            }
            return false;
        }
    }
    
    /**
     * Generate customer feedback report
     */
    public function generateFeedbackReport($startDate, $endDate) {
        try {
            // Overall feedback stats
            $overallStats = $this->db->query(
                "SELECT 
                    COUNT(*) as total_feedback,
                    AVG(rating) as avg_rating,
                    COUNT(CASE WHEN rating >= 4 THEN 1 END) as positive,
                    COUNT(CASE WHEN rating <= 2 THEN 1 END) as negative
                 FROM customer_feedback 
                 WHERE created_at BETWEEN ? AND ?",
                [$startDate, $endDate]
            )->fetch();
            
            // Feedback by type
            $byType = $this->db->query(
                "SELECT feedback_type, COUNT(*) as count, AVG(rating) as avg_rating
                 FROM customer_feedback 
                 WHERE created_at BETWEEN ? AND ?
                 GROUP BY feedback_type 
                 ORDER BY count DESC",
                [$startDate, $endDate]
            )->fetchAll();
            
            // Recent feedback
            $recentFeedback = $this->db->query(
                "SELECT * FROM customer_feedback 
                 WHERE created_at BETWEEN ? AND ?
                 ORDER BY created_at DESC 
                 LIMIT 20",
                [$startDate, $endDate]
            )->fetchAll();
            
            return [
                'overall_stats' => $overallStats,
                'by_type' => $byType,
                'recent_feedback' => $recentFeedback,
                'period' => ['start' => $startDate, 'end' => $endDate]
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to generate feedback report", ['error' => $e->getMessage()]);
            }
            return false;
        }
    }
    
    /**
     * Generate monthly summary report
     */
    public function generateMonthlySummary($year, $month) {
        try {
            $startDate = "{$year}-{$month}-01";
            $endDate = date('Y-m-t', strtotime($startDate));
            
            $salesReport = $this->generateSalesReport($startDate, $endDate);
            $reservationReport = $this->generateReservationReport($startDate, $endDate);
            $feedbackReport = $this->generateFeedbackReport($startDate, $endDate);
            
            // Calculate totals
            $totalRevenue = 0;
            $totalOrders = 0;
            $totalReservations = 0;
            
            if ($salesReport && isset($salesReport['daily_stats'])) {
                foreach ($salesReport['daily_stats'] as $day) {
                    $totalRevenue += $day['total_revenue'];
                    $totalOrders += $day['total_orders'];
                }
            }
            
            if ($reservationReport && isset($reservationReport['daily_stats'])) {
                foreach ($reservationReport['daily_stats'] as $day) {
                    $totalReservations += $day['total_reservations'];
                }
            }
            
            return [
                'period' => ['year' => $year, 'month' => $month, 'start' => $startDate, 'end' => $endDate],
                'summary' => [
                    'total_revenue' => $totalRevenue,
                    'total_orders' => $totalOrders,
                    'total_reservations' => $totalReservations,
                    'avg_rating' => $feedbackReport['overall_stats']['avg_rating'] ?? 0
                ],
                'sales' => $salesReport,
                'reservations' => $reservationReport,
                'feedback' => $feedbackReport
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to generate monthly summary", ['error' => $e->getMessage()]);
            }
            return false;
        }
    }
    
    /**
     * Export report to CSV
     */
    public function exportToCSV($data, $filename) {
        try {
            $file = fopen($filename, 'w');
            
            if (!$file) {
                throw new Exception("Could not create file");
            }
            
            // Write headers
            if (isset($data['daily_stats']) && !empty($data['daily_stats'])) {
                $headers = array_keys($data['daily_stats'][0]);
                fputcsv($file, $headers);
                
                // Write data
                foreach ($data['daily_stats'] as $row) {
                    fputcsv($file, $row);
                }
            }
            
            fclose($file);
            return true;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to export CSV", ['error' => $e->getMessage()]);
            }
            return false;
        }
    }
}
?>

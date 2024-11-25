<?php
use PDO;

/**
 * Cache Manager for Food Chef Cafe Management System
 * Handles caching for improved performance
 */

class CacheManager {
    
    private $cachePath;
    private $defaultTTL;
    
    public function __construct($cachePath = 'cache/', $defaultTTL = 3600) {
        $this->cachePath = rtrim($cachePath, '/') . '/';
        $this->defaultTTL = $defaultTTL;
        
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }
    
    /**
     * Set cache value
     */
    public function set($key, $value, $ttl = null) {
        if ($ttl === null) {
            $ttl = $this->defaultTTL;
        }
        
        $cacheData = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        
        $filename = $this->cachePath . md5($key) . '.cache';
        return file_put_contents($filename, serialize($cacheData)) !== false;
    }
    
    /**
     * Get cache value
     */
    public function get($key) {
        $filename = $this->cachePath . md5($key) . '.cache';
        
        if (!file_exists($filename)) {
            return false;
        }
        
        $cacheData = unserialize(file_get_contents($filename));
        
        if ($cacheData['expires'] < time()) {
            unlink($filename);
            return false;
        }
        
        return $cacheData['value'];
    }
    
    /**
     * Delete cache
     */
    public function delete($key) {
        $filename = $this->cachePath . md5($key) . '.cache';
        if (file_exists($filename)) {
            return unlink($filename);
        }
        return true;
    }
    
    /**
     * Clear all cache
     */
    public function clear() {
        $files = glob($this->cachePath . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }
    
    /**
     * Cache menu items
     */
    public function cacheMenu($categoryId = null) {
        $key = 'menu_' . ($categoryId ?? 'all');
        $db = new Db();
        
        if ($categoryId) {
            $sql = "SELECT * FROM food WHERE category_id = ? AND is_active = 1 ORDER BY name";
            $menu = $db->query($sql, [$categoryId])->fetchAll();
        } else {
            $sql = "SELECT f.*, c.name as category_name FROM food f 
                    LEFT JOIN menu_categories c ON f.category_id = c.id 
                    WHERE f.is_active = 1 ORDER BY c.sort_order, f.name";
            $menu = $db->query($sql)->fetchAll();
        }
        
        $this->set($key, $menu, 1800); // 30 minutes
        return $menu;
    }
    
    /**
     * Cache popular items
     */
    public function cachePopularItems($limit = 10) {
        $key = 'popular_items_' . $limit;
        $db = new Db();
        
        $sql = "SELECT f.*, COUNT(oi.id) as order_count 
                FROM food f 
                LEFT JOIN order_items oi ON f.id = oi.food_id 
                WHERE f.is_active = 1 
                GROUP BY f.id 
                ORDER BY order_count DESC 
                LIMIT ?";
        
        $popular = $db->query($sql, [$limit])->fetchAll();
        $this->set($key, $popular, 3600); // 1 hour
        
        return $popular;
    }
}
?>

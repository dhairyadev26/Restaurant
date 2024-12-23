<?php
/**
 * Cache Manager Class
 * Provides caching functionality to improve application performance
 */
class CacheManager {
    private $cacheDir;
    private $defaultTTL;
    
    public function __construct($cacheDir = 'cache/', $defaultTTL = 3600) {
        $this->cacheDir = rtrim($cacheDir, '/') . '/';
        $this->defaultTTL = $defaultTTL;
        $this->ensureCacheDirectory();
    }
    
    private function ensureCacheDirectory() {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    public function set($key, $data, $ttl = null) {
        $ttl = $ttl ?? $this->defaultTTL;
        $expiry = time() + $ttl;
        
        $cacheData = [
            'data' => $data,
            'expiry' => $expiry,
            'created' => time()
        ];
        
        $filename = $this->getCacheFilename($key);
        $content = serialize($cacheData);
        
        return file_put_contents($filename, $content, LOCK_EX) !== false;
    }
    
    public function get($key) {
        $filename = $this->getCacheFilename($key);
        
        if (!file_exists($filename)) {
            return false;
        }
        
        $content = file_get_contents($filename);
        if ($content === false) {
            return false;
        }
        
        $cacheData = unserialize($content);
        if ($cacheData === false) {
            return false;
        }
        
        if (time() > $cacheData['expiry']) {
            unlink($filename);
            return false;
        }
        
        return $cacheData['data'];
    }
    
    public function has($key) {
        $filename = $this->getCacheFilename($key);
        
        if (!file_exists($filename)) {
            return false;
        }
        
        $content = file_get_contents($filename);
        if ($content === false) {
            return false;
        }
        
        $cacheData = unserialize($content);
        if ($cacheData === false) {
            return false;
        }
        
        if (time() > $cacheData['expiry']) {
            unlink($filename);
            return false;
        }
        
        return true;
    }
    
    public function delete($key) {
        $filename = $this->getCacheFilename($key);
        
        if (file_exists($filename)) {
            return unlink($filename);
        }
        
        return true;
    }
    
    public function clear() {
        $files = glob($this->cacheDir . '*.cache');
        $deleted = 0;
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    public function clearExpired() {
        $files = glob($this->cacheDir . '*.cache');
        $deleted = 0;
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content !== false) {
                $cacheData = unserialize($content);
                if ($cacheData && time() > $cacheData['expiry']) {
                    if (unlink($file)) {
                        $deleted++;
                    }
                }
            }
        }
        
        return $deleted;
    }
    
    public function getStats() {
        $files = glob($this->cacheDir . '*.cache');
        $totalSize = 0;
        $expiredCount = 0;
        $validCount = 0;
        
        foreach ($files as $file) {
            $totalSize += filesize($file);
            $content = file_get_contents($file);
            
            if ($content !== false) {
                $cacheData = unserialize($content);
                if ($cacheData && time() > $cacheData['expiry']) {
                    $expiredCount++;
                } else {
                    $validCount++;
                }
            }
        }
        
        return [
            'total_files' => count($files),
            'valid_files' => $validCount,
            'expired_files' => $expiredCount,
            'total_size' => $totalSize,
            'cache_dir' => $this->cacheDir
        ];
    }
    
    public function remember($key, $callback, $ttl = null) {
        if ($this->has($key)) {
            return $this->get($key);
        }
        
        $data = $callback();
        $this->set($key, $data, $ttl);
        
        return $data;
    }
    
    public function increment($key, $value = 1) {
        $current = $this->get($key);
        
        if ($current === false) {
            $current = 0;
        }
        
        if (is_numeric($current)) {
            $newValue = $current + $value;
            $this->set($key, $newValue);
            return $newValue;
        }
        
        return false;
    }
    
    public function decrement($key, $value = 1) {
        return $this->increment($key, -$value);
    }
    
    public function tags($tags) {
        if (!is_array($tags)) {
            $tags = [$tags];
        }
        
        return new CacheTagManager($this, $tags);
    }
    
    private function getCacheFilename($key) {
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return $this->cacheDir . $safeKey . '.cache';
    }
}

/**
 * Cache Tag Manager for grouped cache operations
 */
class CacheTagManager {
    private $cacheManager;
    private $tags;
    
    public function __construct($cacheManager, $tags) {
        $this->cacheManager = $cacheManager;
        $this->tags = $tags;
    }
    
    public function flush() {
        foreach ($this->tags as $tag) {
            $this->cacheManager->delete("tag_$tag");
        }
        return true;
    }
    
    public function set($key, $data, $ttl = null) {
        $this->cacheManager->set($key, $data, $ttl);
        
        foreach ($this->tags as $tag) {
            $tagKey = "tag_$tag";
            $taggedKeys = $this->cacheManager->get($tagKey) ?: [];
            $taggedKeys[] = $key;
            $this->cacheManager->set($tagKey, $taggedKeys);
        }
    }
}
?>

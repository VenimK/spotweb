<?php

/**
 * Services_Caching_Manager
 *
 * Manages caching for Spotweb to improve performance
 */
class Services_Caching_Manager
{
    private $_settings;
    private $_cacheMethod;
    private $_cacheEnabled;
    private $_cacheTtl;
    
    /**
     * Constructor
     *
     * @param Services_Settings_Container $settings
     */
    public function __construct(Services_Settings_Container $settings)
    {
        $this->_settings = $settings;
        $this->_cacheEnabled = $settings->get('cache_enabled', false);
        $this->_cacheMethod = $settings->get('cache_method', 'file');
        $this->_cacheTtl = $settings->get('cache_ttl', 3600); // Default: 1 hour
    }
    
    /**
     * Get a value from the cache
     *
     * @param string $key Cache key
     * @return mixed|false The cached value or false if not found
     */
    public function get($key)
    {
        if (!$this->_cacheEnabled) {
            return false;
        }
        
        $key = $this->sanitizeKey($key);
        
        switch ($this->_cacheMethod) {
            case 'apc':
                return $this->getFromApc($key);
            case 'memcached':
                return $this->getFromMemcached($key);
            case 'file':
                return $this->getFromFile($key);
            default:
                return false;
        }
    }
    
    /**
     * Store a value in the cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to store
     * @param int $ttl Time to live in seconds (0 = use default)
     * @return bool Success
     */
    public function set($key, $value, $ttl = 0)
    {
        if (!$this->_cacheEnabled) {
            return false;
        }
        
        $key = $this->sanitizeKey($key);
        $ttl = ($ttl > 0) ? $ttl : $this->_cacheTtl;
        
        switch ($this->_cacheMethod) {
            case 'apc':
                return $this->setInApc($key, $value, $ttl);
            case 'memcached':
                return $this->setInMemcached($key, $value, $ttl);
            case 'file':
                return $this->setInFile($key, $value, $ttl);
            default:
                return false;
        }
    }
    
    /**
     * Delete a value from the cache
     *
     * @param string $key Cache key
     * @return bool Success
     */
    public function delete($key)
    {
        if (!$this->_cacheEnabled) {
            return false;
        }
        
        $key = $this->sanitizeKey($key);
        
        switch ($this->_cacheMethod) {
            case 'apc':
                return $this->deleteFromApc($key);
            case 'memcached':
                return $this->deleteFromMemcached($key);
            case 'file':
                return $this->deleteFromFile($key);
            default:
                return false;
        }
    }
    
    /**
     * Clear all cache
     *
     * @return bool Success
     */
    public function flush()
    {
        if (!$this->_cacheEnabled) {
            return false;
        }
        
        switch ($this->_cacheMethod) {
            case 'apc':
                return apc_clear_cache('user');
            case 'memcached':
                $memcached = $this->getMemcachedInstance();
                return $memcached ? $memcached->flush() : false;
            case 'file':
                return $this->flushFileCache();
            default:
                return false;
        }
    }
    
    /**
     * Sanitize a cache key
     *
     * @param string $key
     * @return string
     */
    private function sanitizeKey($key)
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
    }
    
    /**
     * Get the cache directory path
     *
     * @return string
     */
    private function getCacheDir()
    {
        $cacheDir = __DIR__ . '/../../../cache';
        
        if (!file_exists($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        return $cacheDir;
    }
    
    /**
     * Get a value from APC cache
     *
     * @param string $key
     * @return mixed|false
     */
    private function getFromApc($key)
    {
        if (function_exists('apc_fetch')) {
            return apc_fetch($key);
        }
        
        return false;
    }
    
    /**
     * Set a value in APC cache
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return bool
     */
    private function setInApc($key, $value, $ttl)
    {
        if (function_exists('apc_store')) {
            return apc_store($key, $value, $ttl);
        }
        
        return false;
    }
    
    /**
     * Delete a value from APC cache
     *
     * @param string $key
     * @return bool
     */
    private function deleteFromApc($key)
    {
        if (function_exists('apc_delete')) {
            return apc_delete($key);
        }
        
        return false;
    }
    
    /**
     * Get Memcached instance
     *
     * @return Memcached|false
     */
    private function getMemcachedInstance()
    {
        static $memcached = null;
        
        if ($memcached !== null) {
            return $memcached;
        }
        
        if (!class_exists('Memcached')) {
            return false;
        }
        
        $memcached = new Memcached();
        $memcached->addServer('localhost', 11211);
        
        return $memcached;
    }
    
    /**
     * Get a value from Memcached
     *
     * @param string $key
     * @return mixed|false
     */
    private function getFromMemcached($key)
    {
        $memcached = $this->getMemcachedInstance();
        
        if ($memcached) {
            return $memcached->get($key);
        }
        
        return false;
    }
    
    /**
     * Set a value in Memcached
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return bool
     */
    private function setInMemcached($key, $value, $ttl)
    {
        $memcached = $this->getMemcachedInstance();
        
        if ($memcached) {
            return $memcached->set($key, $value, $ttl);
        }
        
        return false;
    }
    
    /**
     * Delete a value from Memcached
     *
     * @param string $key
     * @return bool
     */
    private function deleteFromMemcached($key)
    {
        $memcached = $this->getMemcachedInstance();
        
        if ($memcached) {
            return $memcached->delete($key);
        }
        
        return false;
    }
    
    /**
     * Get a value from file cache
     *
     * @param string $key
     * @return mixed|false
     */
    private function getFromFile($key)
    {
        $cacheFile = $this->getCacheDir() . '/' . $key . '.cache';
        
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        $content = file_get_contents($cacheFile);
        
        if ($content === false) {
            return false;
        }
        
        $data = unserialize($content);
        
        // Check if cache has expired
        if ($data['expires'] < time()) {
            $this->deleteFromFile($key);
            return false;
        }
        
        return $data['value'];
    }
    
    /**
     * Set a value in file cache
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return bool
     */
    private function setInFile($key, $value, $ttl)
    {
        $cacheFile = $this->getCacheDir() . '/' . $key . '.cache';
        
        $data = [
            'expires' => time() + $ttl,
            'value' => $value
        ];
        
        return file_put_contents($cacheFile, serialize($data)) !== false;
    }
    
    /**
     * Delete a value from file cache
     *
     * @param string $key
     * @return bool
     */
    private function deleteFromFile($key)
    {
        $cacheFile = $this->getCacheDir() . '/' . $key . '.cache';
        
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        
        return true;
    }
    
    /**
     * Flush file cache
     *
     * @return bool
     */
    private function flushFileCache()
    {
        $cacheDir = $this->getCacheDir();
        $files = glob($cacheDir . '/*.cache');
        
        if ($files === false) {
            return false;
        }
        
        $success = true;
        
        foreach ($files as $file) {
            if (!unlink($file)) {
                $success = false;
            }
        }
        
        return $success;
    }
}

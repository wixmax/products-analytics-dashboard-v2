<?php

namespace App\Libraries\Cache;

class ResponseCache
{
    protected string $prefix;
    protected int $defaultTtl;

    public function __construct(string $prefix = 'mcp_cache_', int $defaultTtl = 3600)
    {
        $this->prefix = $prefix;
        $this->defaultTtl = $defaultTtl;
    }

    /**
     * Generate standard cache key from name and parameters
     */
    public function makeKey(string $name, array $params = []): string
    {
        ksort($params);
        return $this->prefix . $name . '_' . md5(json_encode($params));
    }

    /**
     * Get item from cache
     */
    public function get(string $key, $default = null)
    {
        try {
            $cache = \Config\Services::cache();
            $data = $cache->get($key);
            return $data !== null ? $data : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Store item in cache with TTL
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        try {
            $cache = \Config\Services::cache();
            return $cache->save($key, $value, $ttl ?? $this->defaultTtl);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Remember pattern: fetch from cache, or compute, store and return
     */
    public function remember(string $key, callable $callback, ?int $ttl = null)
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        if ($value !== null) {
            $this->set($key, $value, $ttl);
        }

        return $value;
    }

    /**
     * Delete item from cache
     */
    public function delete(string $key): bool
    {
        try {
            $cache = \Config\Services::cache();
            return $cache->delete($key);
        } catch (\Throwable $e) {
            return false;
        }
    }
}

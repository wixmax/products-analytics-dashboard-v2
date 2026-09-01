<?php

namespace App\Libraries\SaaS;

class QuotaManager
{
    /**
     * Default quota limits per action per day (0 = unlimited)
     */
    protected array $defaultLimits = [
        'mcp_calls'       => 1000,
        'vector_searches' => 200,
        'ai_analyses'     => 100,
        'saved_products'  => 500,
    ];

    /**
     * Get quota limits for a tenant from database settings or default
     */
    public function getLimits(int $tenantId): array
    {
        try {
            $db = \Config\Database::connect();
            $row = $db->table('settings')->where('key', 'tenant_quota_' . $tenantId)->get()->getRowArray();
            if ($row && !empty($row['value'])) {
                $custom = json_decode($row['value'], true);
                if (is_array($custom)) {
                    return array_merge($this->defaultLimits, $custom);
                }
            }
        } catch (\Throwable $e) {
            // Graceful fallback
        }

        return $this->defaultLimits;
    }

    /**
     * Get current day's usage for a tenant
     */
    public function getUsage(int $tenantId): array
    {
        $today = date('Ymd');
        $usage = [];

        foreach (array_keys($this->defaultLimits) as $action) {
            $cacheKey = "quota_{$tenantId}_{$today}_{$action}";
            try {
                $cache = \Config\Services::cache();
                $val = $cache->get($cacheKey);
                $usage[$action] = intval($val ?? 0);
            } catch (\Throwable $e) {
                $usage[$action] = 0;
            }
        }

        return $usage;
    }

    /**
     * Check if a tenant can execute a specific action under current quotas
     */
    public function canExecute(int $tenantId, string $action): bool
    {
        // Tenant 1 / Admin workspace has unlimited quota by default
        if ($tenantId === 1) {
            return true;
        }

        $limits = $this->getLimits($tenantId);
        $max = $limits[$action] ?? 0;

        if ($max === 0) {
            return true; // Unlimited
        }

        $currentUsage = $this->getUsage($tenantId);
        $used = $currentUsage[$action] ?? 0;

        return $used < $max;
    }

    /**
     * Record usage increment for an action
     */
    public function recordUsage(int $tenantId, string $action, int $count = 1): int
    {
        $today = date('Ymd');
        $cacheKey = "quota_{$tenantId}_{$today}_{$action}";

        try {
            $cache = \Config\Services::cache();
            $current = intval($cache->get($cacheKey) ?? 0);
            $newTotal = $current + $count;
            // TTL 48 hours to safely expire next days
            $cache->save($cacheKey, $newTotal, 172800);
            return $newTotal;
        } catch (\Throwable $e) {
            return $count;
        }
    }

    /**
     * Get a comprehensive usage summary for a tenant
     */
    public function getUsageSummary(int $tenantId): array
    {
        $limits = $this->getLimits($tenantId);
        $usage  = $this->getUsage($tenantId);
        $summary = [];

        foreach ($limits as $action => $limit) {
            $used = $usage[$action] ?? 0;
            $remaining = ($limit === 0) ? -1 : max(0, $limit - $used);
            $percent = ($limit > 0) ? round(($used / $limit) * 100, 1) : 0;

            $summary[$action] = [
                'limit'     => $limit,
                'used'      => $used,
                'remaining' => $remaining,
                'percent'   => $percent,
                'is_exceeded' => ($limit > 0 && $used >= $limit)
            ];
        }

        return [
            'tenant_id' => $tenantId,
            'date'      => date('Y-m-d'),
            'summary'   => $summary
        ];
    }
}

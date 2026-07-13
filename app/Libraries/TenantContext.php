<?php

namespace App\Libraries;

/**
 * TenantContext - Singleton service to hold the current tenant ID
 * for the authenticated user. This is set by the TenantFilter after
 * successful authentication and used by TenantModel to scope queries.
 */
class TenantContext
{
    private static ?self $instance = null;
    private ?int $tenantId = null;
    private bool $bypassTenant = false;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function setTenantId(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function hasTenant(): bool
    {
        return $this->tenantId !== null;
    }

    /**
     * Allow superadmin to bypass tenant scoping
     */
    public function setBypass(bool $bypass): void
    {
        $this->bypassTenant = $bypass;
    }

    public function shouldBypass(): bool
    {
        return $this->bypassTenant;
    }

    public function reset(): void
    {
        $this->tenantId     = null;
        $this->bypassTenant = false;
    }
}

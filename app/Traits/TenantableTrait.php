<?php

namespace App\Traits;

use App\Libraries\TenantContext;

trait TenantableTrait
{
    /**
     * Whether to bypass tenant scoping for the next query.
     */
    protected bool $bypassTenant = false;

    /**
     * Initialize the tenantable trait.
     * Appends required callbacks and allowed fields.
     */
    protected function initializeTenantableTrait(): void
    {
        // Add tenant_id to allowedFields if not present
        if (!in_array('tenant_id', $this->allowedFields, true)) {
            $this->allowedFields[] = 'tenant_id';
        }

        // Register event callbacks
        $this->beforeInsert[] = 'applyTenantId';
        // Disabled query filter so all users share the same data
        // $this->beforeFind[]   = 'applyTenantFilter';
    }

    /**
     * Temporarily bypass tenant scoping for the next query.
     */
    public function bypassTenant(): self
    {
        $this->bypassTenant = true;
        return $this;
    }

    /**
     * Reset the bypass flag
     */
    protected function resetBypass(): void
    {
        $this->bypassTenant = false;
    }

    /**
     * Automatically assign tenant_id before insertion
     */
    protected function applyTenantId(array $data): array
    {
        $context = TenantContext::getInstance();
        if ($context->hasTenant() && !isset($data['data']['tenant_id'])) {
            $data['data']['tenant_id'] = $context->getTenantId();
        }

        return $data;
    }

    /**
     * Automatically filter queries by tenant_id
     */
    protected function applyTenantFilter(array $data): array
    {
        $context = TenantContext::getInstance();

        // Skip filter if bypassed
        if ($this->bypassTenant || $context->shouldBypass()) {
            $this->resetBypass();
            return $data;
        }

        if ($context->hasTenant()) {
            // Apply tenant filter
            $this->where($this->table . '.tenant_id', $context->getTenantId());
        }

        return $data;
    }

    /**
     * Override delete to respect tenant scope (disabled for shared data)
     */
    public function delete($id = null, bool $purge = false)
    {
        $result = parent::delete($id, $purge);
        $this->resetBypass();

        return $result;
    }

    /**
     * Override update to respect tenant scope (disabled for shared data)
     */
    public function update($id = null, $data = null): bool
    {
        $result = parent::update($id, $data);
        $this->resetBypass();

        return $result;
    }
}

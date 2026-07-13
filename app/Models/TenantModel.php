<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Traits\TenantableTrait;

/**
 * TenantModel - Base Model that automatically scopes all queries
 * by the current tenant_id. Inherits from CodeIgniter\Model.
 *
 * Usage: All application models should extend this class instead of Model.
 */
class TenantModel extends Model
{
    use TenantableTrait;

    public function __construct()
    {
        parent::__construct();
        $this->initializeTenantableTrait();
    }
}

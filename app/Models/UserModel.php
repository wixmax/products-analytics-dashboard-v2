<?php

namespace App\Models;

use CodeIgniter\Shield\Models\UserModel as ShieldUserModel;
use App\Traits\TenantableTrait;

class UserModel extends ShieldUserModel
{
    use TenantableTrait;

    protected function initialize(): void
    {
        parent::initialize();
        $this->initializeTenantableTrait();
    }
}

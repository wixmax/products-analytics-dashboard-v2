<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Google extends BaseConfig
{
    public string $clientId = '';
    public string $clientSecret = '';
    public string $redirectUri = '';

    public function __construct()
    {
        parent::__construct();
        
        $this->clientId = env('google.clientId', '');
        $this->clientSecret = env('google.clientSecret', '');
        $this->redirectUri = base_url('auth/google/callback');
    }
}

<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\TenantContext;

class TenantFilter implements FilterInterface
{
    /**
     * Do whatever processing this filter needs to do before the request.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $context = TenantContext::getInstance();

        if (auth()->loggedIn()) {
            $user = auth()->user();
            if (isset($user->tenant_id) && $user->tenant_id !== null) {
                $context->setTenantId((int)$user->tenant_id);
            }
        }
    }

    /**
     * Allows After filters to inspect and modify the response.
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No action needed after request
    }
}

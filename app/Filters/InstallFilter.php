<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class InstallFilter implements FilterInterface
{
    /**
     * Do whatever processing this filter needs to do.
     * By default it should not return anything during normal execution.
     * However, when an abnormal state is encountered, this method should
     * return a HTTP\ResponseInterface instance and execution of the filter
     * chain will terminate.
     *
     * @param RequestInterface $request
     * @param array|null       $arguments
     *
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $installedFile = WRITEPATH . 'installed.txt';
        $uri = trim($request->getUri()->getPath(), '/');

        // Remove index.php/ or index.php from path if present (common in cPanel/subfolder setups)
        if (strpos($uri, 'index.php/') === 0) {
            $uri = substr($uri, 10);
        } elseif ($uri === 'index.php') {
            $uri = '';
        }

        // Check if the current request is for the installer
        $isInstallerRequest = (
            $uri === 'install' || 
            strpos($uri, 'install/') === 0
        );

        if (!file_exists($installedFile)) {
            // Not installed, redirect to installer if not already there
            if (!$isInstallerRequest) {
                return redirect()->to(base_url('install'));
            }
        } else {
            // Already installed, redirect away from installer if they try to access it
            if ($isInstallerRequest) {
                return redirect()->to(base_url('/'));
            }
        }
    }

    /**
     * Allows After filters to inspect and modify the response
     * object as needed.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     *
     * @return mixed
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No action needed
    }
}

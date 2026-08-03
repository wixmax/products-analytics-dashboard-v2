<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class McpDebugFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $logFile = WRITEPATH . 'logs/mcp_debug_' . date('Y-m-d') . '.log';
        $time    = date('Y-m-d H:i:s');
        $method  = $request->getMethod();
        $uri     = (string)$request->getUri();
        
        $headers = [];
        foreach ($request->headers() as $name => $header) {
            $headers[$name] = $header->getValueLine();
        }

        $rawBody = (string)$request->getBody();

        $logData  = "============================================================\n";
        $logData .= "[MCP DEBUG REQUEST] {$time}\n";
        $logData .= "Method : " . strtoupper($method) . "\n";
        $logData .= "URI    : {$uri}\n";
        $logData .= "Headers: " . json_encode($headers, JSON_UNESCAPED_SLASHES) . "\n";
        $logData .= "Body   : " . ($rawBody !== '' ? $rawBody : '(empty)') . "\n";
        $logData .= "------------------------------------------------------------\n";

        @file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $logFile = WRITEPATH . 'logs/mcp_debug_' . date('Y-m-d') . '.log';
        $time    = date('Y-m-d H:i:s');
        $status  = $response->getStatusCode();
        $body    = (string)$response->getBody();

        $logData  = "[MCP DEBUG RESPONSE] {$time}\n";
        $logData .= "Status : {$status}\n";
        $logData .= "Body   : " . (strlen($body) > 2000 ? substr($body, 0, 2000) . '... [truncated]' : $body) . "\n";
        $logData .= "============================================================\n\n";

        @file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);
    }
}

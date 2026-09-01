<?php

namespace App\Controllers;

use App\Libraries\Mcp\ToolRegistry;
use CodeIgniter\RESTful\ResourceController;

class McpController extends ResourceController
{
    protected $format = 'json';
    protected ToolRegistry $toolRegistry;

    public function __construct()
    {
        $this->toolRegistry = new ToolRegistry();
    }

    /**
     * GET/POST /api/mcp
     * Main MCP Protocol Endpoint (JSON-RPC 2.0 / SSE / Discovery)
     */
    public function handleMcp()
    {
        $response = $this->response;
        $response->setHeader('Access-Control-Allow-Origin', '*');
        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-API-Key, api-key, api_key, x-token');

        $method = strtolower($this->request->getMethod());

        if ($method === 'options') {
            return $response->setStatusCode(204);
        }

        $db = \Config\Database::connect();
        $globalEnabledRow = $db->table('settings')->where('key', 'mcp_global_enabled')->get()->getRowArray();
        $globalEnabled = $globalEnabledRow ? ($globalEnabledRow['value'] === '1') : true;

        if (!$globalEnabled) {
            return $this->respond([
                'jsonrpc' => '2.0',
                'id'      => null,
                'error'   => [
                    'code'    => -32603,
                    'message' => 'MCP server is currently disabled by administrator.'
                ]
            ], 200);
        }
        
        if ($method === 'get') {
            $acceptHeader = strtolower(trim($this->request->getHeaderLine('Accept')));
            if ($acceptHeader === 'text/event-stream' || $this->request->getGet('transport') === 'sse') {
                $token = $this->request->getGet('token');
                $postUrl = base_url('api/mcp') . ($token ? '?token=' . urlencode($token) : '');

                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');
                header('Connection: keep-alive');
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Headers: *');

                echo "event: endpoint\r\n";
                echo "data: " . $postUrl . "\r\n\r\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                exit;
            }

            // GET Discovery / Information Response
            return $this->respond([
                'mcp_server' => 'products-analytics-mcp-php',
                'version'    => '1.0.0',
                'status'     => 'running',
                'endpoint'   => base_url('api/mcp'),
                'capabilities' => [
                    'tools'   => true,
                    'prompts' => true,
                    'jsonrpc' => '2.0'
                ],
                'tools' => $this->toolRegistry->getToolsManifest()
            ], 200);
        }

        $rawBody = $this->request->getBody();
        if (empty($rawBody)) {
            $rawBody = @file_get_contents('php://input');
        }
        if (!empty($rawBody)) {
            $rawBody = preg_replace('/^[\xEF\xBB\xBF\s]+/', '', $rawBody);
        }
        $input = !empty($rawBody) ? json_decode($rawBody, true) : null;

        if (!$input) {
            try {
                $input = $this->request->getJSON(true);
            } catch (\Throwable $e) {
                $input = null;
            }
        }

        if (!$input) {
            return $this->respond([
                'jsonrpc' => '2.0',
                'id'      => null,
                'error'   => [
                    'code'    => -32600,
                    'message' => 'Invalid Request. Empty or malformed JSON.'
                ]
            ], 200);
        }

        // Handle JSON-RPC Batch Request (array of requests)
        if (is_array($input) && isset($input[0]) && is_array($input[0])) {
            $batchResponses = [];
            foreach ($input as $singleRequest) {
                $res = $this->processSingleJsonRpcRequest($singleRequest);
                if ($res !== null) {
                    $batchResponses[] = $res;
                }
            }
            if (empty($batchResponses)) {
                return $this->response->setStatusCode(202);
            }
            return $this->respond($batchResponses, 200);
        }

        // Single Request
        $singleResponse = $this->processSingleJsonRpcRequest($input);
        if ($singleResponse === null) {
            return $this->response->setStatusCode(202);
        }

        return $this->respond($singleResponse, 200);
    }

    /**
     * Process a single JSON-RPC 2.0 request or notification
     */
    private function processSingleJsonRpcRequest($input)
    {
        if (!is_array($input) || !isset($input['method'])) {
            return [
                'jsonrpc' => '2.0',
                'id'      => $input['id'] ?? null,
                'error'   => [
                    'code'    => -32600,
                    'message' => 'Invalid Request. Missing method.'
                ]
            ];
        }

        $jsonrpcId = $input['id'] ?? null;
        $mcpMethod = $input['method'];
        $params    = $input['params'] ?? [];

        // Handle notifications (no JSON response body expected per JSON-RPC 2.0 spec)
        if (str_starts_with($mcpMethod, 'notifications/') || !array_key_exists('id', $input)) {
            return null;
        }

        switch ($mcpMethod) {
            case 'initialize':
                $clientProtocol = $params['protocolVersion'] ?? '2025-06-18';
                return [
                    'jsonrpc' => '2.0',
                    'id'      => $jsonrpcId,
                    'result'  => [
                        'protocolVersion' => $clientProtocol,
                        'capabilities'    => [
                            'tools'   => ['listChanged' => false],
                            'prompts' => ['listChanged' => false]
                        ],
                        'serverInfo'      => [
                            'name'    => 'products-analytics-mcp',
                            'version' => '1.0.0'
                        ],
                        'instructions'    => $this->toolRegistry->getSystemPrompt()
                    ]
                ];

            case 'prompts/list':
                $skills = $this->toolRegistry->getDynamicSkills();
                $prompts = [];
                foreach ($skills as $sId => $skill) {
                    if (empty($skill['enabled'])) continue;
                    $prompts[] = [
                        'name'        => str_replace('-', '_', $sId),
                        'description' => $skill['description'] ?? $skill['title'],
                        'arguments'   => [
                            [
                                'name'        => 'product_name',
                                'description' => 'Optional name or title of the product',
                                'required'    => false
                            ],
                            [
                                'name'        => 'product_image_url',
                                'description' => 'Optional product image reference URL',
                                'required'    => false
                            ],
                            [
                                'name'        => 'language',
                                'description' => 'Target language (e.g. Arabic, Moroccan Darija, French)',
                                'required'    => false
                            ]
                        ]
                    ];
                }

                // Default aliases
                $prompts[] = [
                    'name'        => 'ecommerce_analytics_assistant',
                    'description' => 'System prompt and skill instructions for e-commerce COD product analysis and winning ads discovery.',
                    'arguments'   => []
                ];

                return [
                    'jsonrpc' => '2.0',
                    'id'      => $jsonrpcId,
                    'result'  => [
                        'prompts' => $prompts
                    ]
                ];

            case 'prompts/get':
                $promptName = $params['name'] ?? 'ecommerce_analytics_assistant';
                $promptArgs = $params['arguments'] ?? [];

                $skills = $this->toolRegistry->getDynamicSkills();
                foreach ($skills as $sId => $skill) {
                    $slug = str_replace('-', '_', $sId);
                    if ($promptName === $slug || $promptName === $sId) {
                        $tool = new \App\Libraries\Mcp\Tools\DynamicSkillTool($skill, $this->toolRegistry->getSystemPrompt());
                        $res = $tool->execute($promptArgs);
                        $instructions = $res['skill_instructions'] ?? '';

                        return [
                            'jsonrpc' => '2.0',
                            'id'      => $jsonrpcId,
                            'result'  => [
                                'description' => $skill['title'] ?? 'Skill prompt',
                                'messages'    => [
                                    [
                                        'role'    => 'user',
                                        'content' => [
                                            'type' => 'text',
                                            'text' => $instructions
                                        ]
                                    ]
                                ]
                            ]
                        ];
                    }
                }

                return [
                    'jsonrpc' => '2.0',
                    'id'      => $jsonrpcId,
                    'result'  => [
                        'description' => 'System prompt and skill instructions for e-commerce product analysis.',
                        'messages'    => [
                            [
                                'role'    => 'user',
                                'content' => [
                                    'type' => 'text',
                                    'text' => $this->toolRegistry->getSystemPrompt()
                                ]
                            ]
                        ]
                    ]
                ];

            case 'tools/list':
                return [
                    'jsonrpc' => '2.0',
                    'id'      => $jsonrpcId,
                    'result'  => [
                        'tools' => $this->toolRegistry->getToolsManifest()
                    ]
                ];

            case 'tools/call':
                $toolName = $params['name'] ?? '';
                $toolArgs = $params['arguments'] ?? [];

                if (is_string($toolArgs)) {
                    $decodedArgs = json_decode($toolArgs, true);
                    if (is_array($decodedArgs)) {
                        $toolArgs = $decodedArgs;
                    }
                }
                if (!is_array($toolArgs)) {
                    $toolArgs = [];
                }
                
                try {
                    $context = [
                        'user' => $this->resolveAuthenticatedUser()
                    ];
                    $resultData = $this->toolRegistry->execute($toolName, $toolArgs, $context);
                    $isError    = is_array($resultData) && (isset($resultData['error']) || ($resultData['status'] ?? '') === 'error');
                    
                    return [
                        'jsonrpc' => '2.0',
                        'id'      => $jsonrpcId,
                        'result'  => [
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => is_string($resultData) ? $resultData : json_encode($resultData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                                ]
                            ],
                            'isError' => $isError
                        ]
                    ];
                } catch (\Throwable $e) {
                    return [
                        'jsonrpc' => '2.0',
                        'id'      => $jsonrpcId,
                        'result'  => [
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => json_encode(['status' => 'error', 'error' => 'Internal error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                                ]
                            ],
                            'isError' => true
                        ]
                    ];
                }

            case 'resources/list':
                return [
                    'jsonrpc' => '2.0',
                    'id'      => $jsonrpcId,
                    'result'  => [
                        'resources' => []
                    ]
                ];

            case 'roots/list':
                return [
                    'jsonrpc' => '2.0',
                    'id'      => $jsonrpcId,
                    'result'  => [
                        'roots' => []
                    ]
                ];

            case 'ping':
                return [
                    'jsonrpc' => '2.0',
                    'id'      => $jsonrpcId,
                    'result'  => (object)[]
                ];

            default:
                return [
                    'jsonrpc' => '2.0',
                    'id'      => $jsonrpcId,
                    'error'   => [
                        'code'    => -32601,
                        'message' => 'Method not found: ' . $mcpMethod
                    ]
                ];
        }
    }

    /**
     * Resolve user & tenant_id from API Token or active session
     */
    private function resolveAuthenticatedUser()
    {
        $token = $this->request->getGet('token') 
              ?? $this->request->getGet('api_key') 
              ?? $this->request->getGet('api-key');

        if (empty($token)) {
            $authHeader = $this->request->getHeaderLine('Authorization');
            if (!empty($authHeader)) {
                if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                    $token = trim($matches[1]);
                } else {
                    $token = trim($authHeader);
                }
            }
        }

        if (empty($token)) {
            $token = $this->request->getHeaderLine('api-key');
        }

        if (empty($token)) {
            $token = $this->request->getHeaderLine('X-API-Key');
        }

        if (empty($token)) {
            $token = $this->request->getHeaderLine('api_key');
        }

        if (empty($token)) {
            $token = $this->request->getHeaderLine('x-token');
        }

        if (!empty($token)) {
            $db = \Config\Database::connect();
            $userRow = $db->table('users')->where('api_token', $token)->get()->getRowArray();
            if ($userRow) {
                return $userRow;
            }
        }

        // Fallback to active logged in session
        if (function_exists('auth') && auth()->loggedIn()) {
            $user = auth()->user();
            return [
                'id'        => $user->id,
                'username'  => $user->username,
                'tenant_id' => $user->tenant_id ?? 1
            ];
        }

        return null;
    }
}

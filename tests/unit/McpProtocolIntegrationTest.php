<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Libraries\Mcp\ToolRegistry;

/**
 * @internal
 */
final class McpProtocolIntegrationTest extends CIUnitTestCase
{
    protected ToolRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ToolRegistry();
    }

    /**
     * Simulate JSON-RPC single request processor
     */
    private function simulateJsonRpcRequest(array $requestPayload): ?array
    {
        if (!isset($requestPayload['method'])) {
            return [
                'jsonrpc' => '2.0',
                'id'      => $requestPayload['id'] ?? null,
                'error'   => [
                    'code'    => -32600,
                    'message' => 'Invalid Request. Missing method.'
                ]
            ];
        }

        $jsonrpcId = $requestPayload['id'] ?? null;
        $mcpMethod = $requestPayload['method'];
        $params    = $requestPayload['params'] ?? [];

        if (str_starts_with($mcpMethod, 'notifications/') || !array_key_exists('id', $requestPayload)) {
            return null;
        }

        switch ($mcpMethod) {
            case 'initialize':
                return [
                    'jsonrpc' => '2.0',
                    'id'      => $jsonrpcId,
                    'result'  => [
                        'protocolVersion' => $params['protocolVersion'] ?? '2025-06-18',
                        'capabilities'    => [
                            'tools'   => ['listChanged' => false],
                            'prompts' => ['listChanged' => false]
                        ],
                        'serverInfo'      => [
                            'name'    => 'products-analytics-mcp',
                            'version' => '1.0.0'
                        ],
                        'instructions'    => $this->registry->getSystemPrompt()
                    ]
                ];

            case 'prompts/list':
                $skills = $this->registry->getDynamicSkills();
                $prompts = [];
                foreach ($skills as $sId => $skill) {
                    if (empty($skill['enabled'])) continue;
                    $prompts[] = [
                        'name'        => str_replace('-', '_', $sId),
                        'description' => $skill['description'] ?? $skill['title'],
                    ];
                }
                return [
                    'jsonrpc' => '2.0',
                    'id'      => $jsonrpcId,
                    'result'  => ['prompts' => $prompts]
                ];

            case 'tools/list':
                return [
                    'jsonrpc' => '2.0',
                    'id'      => $jsonrpcId,
                    'result'  => [
                        'tools' => $this->registry->getToolsManifest()
                    ]
                ];

            case 'tools/call':
                $toolName = $params['name'] ?? '';
                $toolArgs = $params['arguments'] ?? [];
                try {
                    $resultData = $this->registry->execute($toolName, $toolArgs, ['user' => ['id' => 1, 'username' => 'TestAdmin', 'tenant_id' => 1]]);
                    $isError = is_array($resultData) && (isset($resultData['error']) || ($resultData['status'] ?? '') === 'error');
                    return [
                        'jsonrpc' => '2.0',
                        'id'      => $jsonrpcId,
                        'result'  => [
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => is_string($resultData) ? $resultData : json_encode($resultData, JSON_UNESCAPED_UNICODE)
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
                                    'text' => json_encode(['status' => 'error', 'error' => $e->getMessage()])
                                ]
                            ],
                            'isError' => true
                        ]
                    ];
                }

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

    public function testInitializeHandshake(): void
    {
        $response = $this->simulateJsonRpcRequest([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'initialize',
            'params'  => ['protocolVersion' => '2025-06-18']
        ]);

        $this->assertIsArray($response);
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(1, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('products-analytics-mcp', $response['result']['serverInfo']['name']);
        $this->assertArrayHasKey('instructions', $response['result']);
    }

    public function testToolsListReturnsTools(): void
    {
        $response = $this->simulateJsonRpcRequest([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'method'  => 'tools/list'
        ]);

        $this->assertIsArray($response);
        $this->assertEquals(2, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertNotEmpty($response['result']['tools']);
    }

    public function testToolsCallDynamicSkill(): void
    {
        $response = $this->simulateJsonRpcRequest([
            'jsonrpc' => '2.0',
            'id'      => 3,
            'method'  => 'tools/call',
            'params'  => [
                'name'      => 'get_nano_banana_pro_instructions',
                'arguments' => ['product_name' => 'Magic Blender']
            ]
        ]);

        $this->assertIsArray($response);
        $this->assertEquals(3, $response['id']);
        $this->assertFalse($response['result']['isError']);
        $this->assertNotEmpty($response['result']['content']);
        $this->assertStringContainsString('Magic Blender', $response['result']['content'][0]['text']);
    }

    public function testUnknownMethodReturns32601Error(): void
    {
        $response = $this->simulateJsonRpcRequest([
            'jsonrpc' => '2.0',
            'id'      => 4,
            'method'  => 'invalid_method_xyz'
        ]);

        $this->assertIsArray($response);
        $this->assertEquals(4, $response['id']);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32601, $response['error']['code']);
    }

    public function testNotificationReturnsNull(): void
    {
        $response = $this->simulateJsonRpcRequest([
            'jsonrpc' => '2.0',
            'method'  => 'notifications/initialized'
        ]);

        $this->assertNull($response, "Notifications without ID should return null");
    }

    public function testPingReturnsEmptyObject(): void
    {
        $response = $this->simulateJsonRpcRequest([
            'jsonrpc' => '2.0',
            'id'      => 5,
            'method'  => 'ping'
        ]);

        $this->assertIsArray($response);
        $this->assertEquals(5, $response['id']);
        $this->assertArrayHasKey('result', $response);
    }
}

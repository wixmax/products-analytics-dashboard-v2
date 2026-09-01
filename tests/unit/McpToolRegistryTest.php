<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Libraries\Mcp\ToolRegistry;
use App\Libraries\Ai\PromptBuilder;

/**
 * @internal
 */
final class McpToolRegistryTest extends CIUnitTestCase
{
    protected ToolRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ToolRegistry();
    }

    public function testGetToolsManifestReturnsExpectedTools(): void
    {
        $manifest = $this->registry->getToolsManifest();
        $this->assertIsArray($manifest);
        $this->assertNotEmpty($manifest);

        $toolNames = array_column($manifest, 'name');
        $this->assertContains('get_saved_products', $toolNames);
        $this->assertContains('list_snapshots', $toolNames);
        $this->assertContains('filter_winning_products', $toolNames);
        $this->assertContains('semantic_search_products', $toolNames);
        $this->assertContains('facebook_search_ads', $toolNames);

        // Verify JSON Schema structure
        foreach ($manifest as $toolDef) {
            $this->assertArrayHasKey('name', $toolDef);
            $this->assertArrayHasKey('description', $toolDef);
            $this->assertArrayHasKey('inputSchema', $toolDef);
            $this->assertEquals('object', $toolDef['inputSchema']['type']);
        }
    }

    public function testExecuteDynamicSkill(): void
    {
        $res = $this->registry->execute('get_nano_banana_pro_instructions', [
            'product_name' => 'Portable Blender',
            'language'     => 'French'
        ]);

        $this->assertIsArray($res);
        $this->assertEquals('success', $res['status']);
        $this->assertArrayHasKey('skill_instructions', $res);
    }

    public function testExecuteUnknownToolThrowsException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unknown tool: non_existent_tool_12345');

        $this->registry->execute('non_existent_tool_12345', []);
    }

    public function testPromptBuilderConstructsValidPrompt(): void
    {
        $products = [
            [
                'index' => 1,
                'title' => 'Test Product',
                'price' => 199,
                'country' => 'MA'
            ]
        ];
        $params = [
            'ad_budget_total' => 3000,
            'season' => 'Summer'
        ];

        $prompt = PromptBuilder::buildScreeningPrompt($products, $params, 'screening');
        $this->assertIsString($prompt);
        $this->assertStringContainsString('Test Product', $prompt);
        $this->assertStringContainsString('3000 DH', $prompt);
    }
}

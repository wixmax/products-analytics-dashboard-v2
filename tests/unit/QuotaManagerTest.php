<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Libraries\SaaS\QuotaManager;

/**
 * @internal
 */
final class QuotaManagerTest extends CIUnitTestCase
{
    protected QuotaManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new QuotaManager();
    }

    public function testGetLimitsReturnsValidArray(): void
    {
        $limits = $this->manager->getLimits(2);
        $this->assertIsArray($limits);
        $this->assertArrayHasKey('mcp_calls', $limits);
        $this->assertArrayHasKey('vector_searches', $limits);
        $this->assertArrayHasKey('ai_analyses', $limits);
    }

    public function testTenant1AlwaysAllowed(): void
    {
        // Tenant 1 (Admin workspace) is unlimited
        $this->assertTrue($this->manager->canExecute(1, 'mcp_calls'));
        $this->assertTrue($this->manager->canExecute(1, 'vector_searches'));
    }

    public function testRecordUsageAndCanExecute(): void
    {
        $tenantId = 999;
        $initialAllowed = $this->manager->canExecute($tenantId, 'ai_analyses');
        $this->assertTrue($initialAllowed);

        $newTotal = $this->manager->recordUsage($tenantId, 'ai_analyses', 5);
        $this->assertGreaterThanOrEqual(5, $newTotal);

        $usage = $this->manager->getUsage($tenantId);
        $this->assertGreaterThanOrEqual(5, $usage['ai_analyses']);
    }

    public function testGetUsageSummaryReturnsBreakdown(): void
    {
        $tenantId = 888;
        $this->manager->recordUsage($tenantId, 'mcp_calls', 10);

        $summary = $this->manager->getUsageSummary($tenantId);
        $this->assertIsArray($summary);
        $this->assertEquals($tenantId, $summary['tenant_id']);
        $this->assertArrayHasKey('summary', $summary);
        $this->assertArrayHasKey('mcp_calls', $summary['summary']);

        $mcpSummary = $summary['summary']['mcp_calls'];
        $this->assertArrayHasKey('limit', $mcpSummary);
        $this->assertArrayHasKey('used', $mcpSummary);
        $this->assertArrayHasKey('remaining', $mcpSummary);
        $this->assertArrayHasKey('percent', $mcpSummary);
        $this->assertGreaterThanOrEqual(10, $mcpSummary['used']);
    }
}

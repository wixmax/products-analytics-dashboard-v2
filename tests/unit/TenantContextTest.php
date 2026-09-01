<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Libraries\TenantContext;

/**
 * @internal
 */
final class TenantContextTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::getInstance()->reset();
    }

    protected function tearDown(): void
    {
        TenantContext::getInstance()->reset();
        parent::tearDown();
    }

    public function testSingletonInstanceReturnsSameObject(): void
    {
        $instance1 = TenantContext::getInstance();
        $instance2 = TenantContext::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function testSetAndGetTenantId(): void
    {
        $context = TenantContext::getInstance();
        $this->assertFalse($context->hasTenant());
        $this->assertNull($context->getTenantId());

        $context->setTenantId(42);
        $this->assertTrue($context->hasTenant());
        $this->assertEquals(42, $context->getTenantId());
    }

    public function testBypassTenantFlag(): void
    {
        $context = TenantContext::getInstance();
        $this->assertFalse($context->shouldBypass());

        $context->setBypass(true);
        $this->assertTrue($context->shouldBypass());

        $context->setBypass(false);
        $this->assertFalse($context->shouldBypass());
    }

    public function testResetRestoresInitialState(): void
    {
        $context = TenantContext::getInstance();
        $context->setTenantId(99);
        $context->setBypass(true);

        $context->reset();

        $this->assertFalse($context->hasTenant());
        $this->assertNull($context->getTenantId());
        $this->assertFalse($context->shouldBypass());
    }
}

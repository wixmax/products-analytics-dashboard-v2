<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Libraries\Storage\SnapshotStorageHelper;
use App\Libraries\Cache\ResponseCache;
use App\Libraries\Queue\BackgroundTaskRunner;

/**
 * @internal
 */
final class PerformanceAndQueueTest extends CIUnitTestCase
{
    public function testSnapshotStorageCompressionAndDecompression(): void
    {
        $largeData = [];
        for ($i = 1; $i <= 100; $i++) {
            $largeData[] = [
                'id' => $i,
                'title' => "Winning E-Commerce Product #{$i} with extensive marketing copy",
                'ad_body' => "Discover the ultimate solution for COD shipping in Morocco and GCC regions. Order now for 50% discount!",
                'price' => rand(100, 500),
                'country' => 'MA'
            ];
        }

        $rawJson = json_encode($largeData, JSON_PRETTY_PRINT);
        $originalLength = strlen($rawJson);

        // Compress
        $compressed = SnapshotStorageHelper::compress($rawJson);
        $compressedLength = strlen($compressed);

        $this->assertTrue(SnapshotStorageHelper::isCompressed($compressed));
        $this->assertLessThan($originalLength, $compressedLength, "Compressed data should be significantly smaller than raw JSON");

        // Decompress
        $decompressed = SnapshotStorageHelper::decompress($compressed);
        $this->assertEquals($rawJson, $decompressed);

        // Test backward compatibility on uncompressed legacy string
        $legacyPlain = '{"legacy": "data", "status": true}';
        $this->assertFalse(SnapshotStorageHelper::isCompressed($legacyPlain));
        $this->assertEquals($legacyPlain, SnapshotStorageHelper::decompress($legacyPlain));
    }

    public function testResponseCacheOperations(): void
    {
        $cache = new ResponseCache('test_mcp_', 60);
        $key = $cache->makeKey('products_list', ['country' => 'MA', 'limit' => 10]);

        $this->assertStringStartsWith('test_mcp_products_list_', $key);

        // Set & Get
        $testPayload = ['total' => 2, 'items' => ['A', 'B']];
        $cache->set($key, $testPayload);

        $fetched = $cache->get($key);
        $this->assertEquals($testPayload, $fetched);

        // Remember pattern
        $computed = $cache->remember($key . '_rem', function() {
            return ['computed' => true, 'timestamp' => 123456];
        });
        $this->assertEquals(['computed' => true, 'timestamp' => 123456], $computed);

        // Delete
        $cache->delete($key);
        $this->assertNull($cache->get($key));
    }

    public function testBackgroundTaskRunnerStructure(): void
    {
        $runner = new BackgroundTaskRunner();
        $this->assertIsArray($runner->listRecentTasks());

        $status = $runner->getTaskStatus('non_existent_task_id_9999');
        $this->assertEquals('not_found', $status['status']);
    }
}

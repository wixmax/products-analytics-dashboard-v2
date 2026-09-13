<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\SyncService;
use App\Services\CloudflareVectorService;
use App\Models\ProductModel;

class CronDaily extends BaseCommand
{
    protected $group       = 'Cron';
    protected $name        = 'cron:daily';
    protected $description = 'Automated daily data synchronization for products, snapshots, and optional vector indexing';
    protected $usage       = 'cron:daily [options]';
    protected $options     = [
        '--date'      => 'Specific target date to fetch (format: YYYY-MM-DD, defaults to today)',
        '--vectorize' => 'Automatically vectorize new unindexed products after sync',
        '--quiet'     => 'Quiet mode - suppress CLI output except critical errors',
    ];

    public function run(array $params)
    {
        $startTime = microtime(true);
        $quiet = CLI::getOption('quiet') !== null;

        $targetDate = CLI::getOption('date') ?: date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            CLI::error("Invalid date format: '{$targetDate}'. Expected YYYY-MM-DD.");
            return;
        }

        if (!$quiet) {
            CLI::write("==================================================", 'cyan');
            CLI::write("🚀 Starting Automated Daily Cron Sync", 'green');
            CLI::write("📅 Target Date: {$targetDate}", 'yellow');
            CLI::write("⏰ Started At:  " . date('Y-m-d H:i:s'), 'white');
            CLI::write("==================================================", 'cyan');
        }

        // 1. Run full sync across origins with daily cron trigger
        $syncService = new SyncService();
        $stats = $syncService->run($targetDate, 'cron');

        $totalInserted = 0;
        $totalUpdated  = 0;
        $hasFailure    = false;

        foreach ($stats as $origin => $stat) {
            $inserted = $stat['inserted'] ?? 0;
            $updated  = $stat['updated'] ?? 0;
            $failed   = !empty($stat['failed']);

            $totalInserted += $inserted;
            $totalUpdated  += $updated;

            if ($failed) {
                $hasFailure = true;
                if (!$quiet) {
                    CLI::error("❌ [{$origin}] sync failed!");
                }
            } else {
                if (!$quiet) {
                    CLI::write("✅ [{$origin}] +{$inserted} inserted, ~{$updated} updated", 'green');
                }
            }
        }

        // 2. Optional: Vectorize new unindexed products
        $vectorizeOption = CLI::getOption('vectorize');
        if ($vectorizeOption !== null && $totalInserted > 0) {
            if (!$quiet) {
                CLI::write("🧠 Vectorizing new unindexed products...", 'blue');
            }
            try {
                $vectorService = new CloudflareVectorService();
                if ($vectorService->isConfigured()) {
                    $productModel = new ProductModel();
                    $unindexed = $productModel->select('id, title, ad_title, ad_body, country, origin')
                                              ->where('origin', 'Winning')
                                              ->orderBy('id', 'DESC')
                                              ->limit(min($totalInserted, 100))
                                              ->findAll();
                    if (!empty($unindexed)) {
                        $vecStats = $vectorService->bulkIndexProducts($unindexed, 25);
                        if (!$quiet) {
                            CLI::write("🧠 Vectorized {$vecStats['indexed']} products successfully.", 'green');
                        }
                    }
                } elseif (!$quiet) {
                    CLI::write("ℹ️ Cloudflare Vectorize not fully configured - skipping vectorization.", 'yellow');
                }
            } catch (\Throwable $ve) {
                log_message('error', 'CronDaily Vectorize error: ' . $ve->getMessage());
                if (!$quiet) {
                    CLI::error("⚠️ Vectorize warning: " . $ve->getMessage());
                }
            }
        }

        $duration = round(microtime(true) - $startTime, 2);

        if (!$quiet) {
            CLI::newLine();
            CLI::write("==================================================", 'cyan');
            if ($hasFailure) {
                CLI::write("⚠️ Daily Cron completed with some warnings/failures.", 'yellow');
            } else {
                CLI::write("🎉 Daily Cron completed successfully!", 'green');
            }
            CLI::write("📊 Summary: +{$totalInserted} inserted, ~{$totalUpdated} updated | Duration: {$duration}s", 'cyan');
            CLI::write("==================================================", 'cyan');
        }
    }
}

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
    protected $description = 'Automated daily data synchronization for winning products (المنتجات الرابحة)';
    protected $usage       = 'cron:daily [options]';
    protected $options     = [
        '--date'  => 'Specific target date to fetch (format: YYYY-MM-DD, defaults to today)',
        '--quiet' => 'Quiet mode - suppress CLI output except critical errors',
    ];

    public function run(array $params)
    {
        $startTime = microtime(true);
        $quiet = CLI::getOption('quiet') !== null;

        $targetDate = CLI::getOption('date');
        if (empty($targetDate) && !empty($params)) {
            foreach ($params as $p) {
                if (preg_match('/^--date=(.*)$/', $p, $m)) {
                    $targetDate = $m[1];
                    break;
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $p)) {
                    $targetDate = $p;
                    break;
                }
            }
        }
        if (empty($targetDate)) {
            $targetDate = date('Y-m-d');
        }

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

        // 1. Run sync for Winning products ONLY (رابحة فقط) with daily cron trigger
        $syncService = new SyncService();
        $stats = $syncService->run($targetDate, 'cron', ['Winning']);

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
                    $reason = !empty($stat['error']) ? " (السبب: {$stat['error']})" : "";
                    CLI::error("❌ [{$origin}] sync failed!{$reason}");
                }
            } else {
                if (!$quiet) {
                    CLI::write("✅ [{$origin}] +{$inserted} inserted, ~{$updated} updated", 'green');
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

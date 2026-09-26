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
        '--date'      => 'Specific target date to fetch (format: YYYY-MM-DD, defaults to today)',
        '--countries' => 'Comma-separated 2-letter country codes (e.g. MA,SA,DZ). Defaults to database setting or default COD countries',
        '--quiet'     => 'Quiet mode - suppress CLI output except critical errors',
    ];

    public function run(array $params)
    {
        $startTime = microtime(true);
        $quiet = CLI::getOption('quiet') !== null;

        $targetDate = CLI::getOption('date');
        $countriesOpt = CLI::getOption('countries');

        // Check options for key=value syntax (e.g. --countries=MA,SA)
        foreach (CLI::getOptions() as $k => $v) {
            if (empty($targetDate) && str_starts_with($k, 'date=')) {
                $targetDate = substr($k, 5);
            }
            if (empty($countriesOpt) && str_starts_with($k, 'countries=')) {
                $countriesOpt = substr($k, 10);
            }
        }

        // Check raw argv as additional fallback
        if (!empty($_SERVER['argv'])) {
            foreach ($_SERVER['argv'] as $arg) {
                if (empty($targetDate) && preg_match('/^--date=(.*)$/', $arg, $m)) {
                    $targetDate = trim($m[1], "'\"");
                }
                if (empty($countriesOpt) && preg_match('/^--countries=(.*)$/', $arg, $m)) {
                    $countriesOpt = trim($m[1], "'\"");
                }
            }
        }

        if (!empty($params)) {
            foreach ($params as $p) {
                if (empty($targetDate)) {
                    if (preg_match('/^--date=(.*)$/', $p, $m)) {
                        $targetDate = trim($m[1], "'\"");
                    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $p)) {
                        $targetDate = $p;
                    }
                }
                if (empty($countriesOpt) && preg_match('/^--countries=(.*)$/', $p, $m)) {
                    $countriesOpt = trim($m[1], "'\"");
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

        $countries = null;
        if (!empty($countriesOpt)) {
            $parts = explode(',', str_replace(';', ',', $countriesOpt));
            $countries = array_filter(array_map('trim', $parts));
        }

        if (!$quiet) {
            CLI::write("==================================================", 'cyan');
            CLI::write("🚀 Starting Automated Daily Cron Sync", 'green');
            CLI::write("📅 Target Date: {$targetDate}", 'yellow');
            if (!empty($countries)) {
                CLI::write("🌍 Target Countries: " . implode(', ', $countries), 'magenta');
            } else {
                CLI::write("🌍 Target Countries: Auto (from Settings/Defaults)", 'magenta');
            }
            CLI::write("⏰ Started At:  " . date('Y-m-d H:i:s'), 'white');
            CLI::write("==================================================", 'cyan');
        }

        // 1. Run sync for Winning products ONLY (رابحة فقط) with daily cron trigger
        $syncService = new SyncService();
        $stats = $syncService->run($targetDate, 'cron', ['Winning'], $countries);

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

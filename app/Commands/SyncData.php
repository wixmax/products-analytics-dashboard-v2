<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\SyncService;

class SyncData extends BaseCommand
{
    protected $group       = 'Sync';
    protected $name        = 'sync:data';
    protected $description = 'Syncs product and ad data from overviewdata.io tRPC endpoints to PostgreSQL';

    public function run(array $params)
    {
        CLI::write('Starting data sync from overviewdata.io via SyncService...', 'blue');
        
        $syncService = new SyncService();
        $stats = $syncService->run();

        foreach ($stats as $origin => $stat) {
            if ($stat['failed']) {
                CLI::error("{$origin} sync failed!");
            } else {
                CLI::write("{$origin}: Imported {$stat['inserted']} new, Updated {$stat['updated']} products.", 'green');
            }
        }

        CLI::write('Data synchronization completed successfully!', 'green');
    }
}

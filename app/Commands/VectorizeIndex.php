<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\CloudflareVectorService;
use App\Models\ProductModel;

class VectorizeIndex extends BaseCommand
{
    protected $group       = 'Vectorize';
    protected $name        = 'vectorize:index';
    protected $description = 'Index products from database into Cloudflare Vectorize for semantic search';
    protected $usage       = 'vectorize:index [options]';
    protected $options     = [
        '--batch'  => 'Batch size for embeddings and upsert (default: 25)',
        '--origin' => 'Filter by origin (winning, new, china, japan, etc.)',
        '--limit'  => 'Limit number of products to index',
        '--status' => 'Check Vectorize connection and index status only',
    ];

    public function run(array $params)
    {
        $vectorService = new CloudflareVectorService();

        if (CLI::getOption('status')) {
            CLI::write('Checking Cloudflare Workers AI and Vectorize Index connection...', 'yellow');
            $status = $vectorService->testConnection();
            if ($status['success']) {
                CLI::write("Status: SUCCESS", 'green');
                CLI::write("Message: " . $status['message'], 'green');
                CLI::write("Embedding Dimensions: " . ($status['dimensions'] ?? 'N/A'), 'cyan');
                if (!empty($status['index_info'])) {
                    CLI::write(json_encode($status['index_info'], JSON_PRETTY_PRINT));
                }
            } else {
                CLI::error("Status: FAILED - " . $status['message']);
            }
            return;
        }

        CLI::write('Testing Cloudflare connection first...', 'yellow');
        $status = $vectorService->testConnection();
        if (!$status['success']) {
            CLI::error('Cannot proceed: ' . $status['message']);
            CLI::write('Please configure your credentials in .env or app/Config/Cloudflare.php', 'red');
            return;
        }

        CLI::write('Connection verified successfully! Fetching products from database...', 'green');

        $productModel = new ProductModel();
        $builder = $productModel->select('id, title, ad_title, ad_body, country, origin');

        $origin = CLI::getOption('origin');
        if ($origin) {
            $builder->where('origin', $origin);
        }

        $limit = CLI::getOption('limit');
        if ($limit) {
            $builder->limit((int)$limit);
        }

        $products = $builder->findAll();
        $total = count($products);

        if ($total === 0) {
            CLI::write('No products found to index.', 'yellow');
            return;
        }

        $batchSize = (int)(CLI::getOption('batch') ?? 25);
        CLI::write("Found {$total} products. Starting vectorization in batches of {$batchSize}...", 'cyan');

        $stats = $vectorService->bulkIndexProducts($products, $batchSize);

        CLI::newLine();
        CLI::write("Indexing complete!", 'green');
        CLI::write("Total Processed: {$stats['total']}");
        CLI::write("Successfully Indexed: {$stats['indexed']}", 'green');
        if ($stats['failed'] > 0) {
            CLI::write("Failed: {$stats['failed']}", 'red');
        }
    }
}

<?php

namespace App\Controllers\Traits;

use App\Services\CloudflareVectorService;

trait VectorizeTrait
{
    /**
     * Get semantically similar products for a given product ID
     */
    public function similarProducts($id = null)
    {
        if (empty($id)) {
            return $this->fail('Product ID is required', 400);
        }

        $limit = intval($this->request->getVar('limit') ?? 8);
        $vectorService = new CloudflareVectorService();

        if (!$vectorService->isConfigured()) {
            return $this->respond([
                'success' => false,
                'message' => 'Cloudflare Vectorize is not configured.',
                'data'    => []
            ]);
        }

        $similar = $vectorService->findSimilarProducts((int)$id, $limit);

        return $this->respond([
            'success' => true,
            'count'   => count($similar),
            'data'    => $similar
        ]);
    }

    /**
     * Get Cloudflare Vectorize status and index info
     */
    public function vectorizeStatus()
    {
        $vectorService = new CloudflareVectorService();
        $status = $vectorService->testConnection();

        return $this->respond($status);
    }

    /**
     * Get Cloudflare Vectorize status and local indexing statistics
     */
    public function vectorizeStats()
    {
        $vectorService = new CloudflareVectorService();
        $stats = $vectorService->getIndexingStats();
        $connection = $vectorService->testConnection();

        return $this->respond([
            'success'    => true,
            'stats'      => $stats,
            'connection' => $connection,
        ]);
    }

    /**
     * Run batch indexing from web UI
     */
    public function vectorizeRun()
    {
        $json = $this->request->getJSON(true) ?? [];
        $mode = $json['mode'] ?? $this->request->getVar('mode') ?? 'unindexed';
        $limit = intval($json['limit'] ?? $this->request->getVar('limit') ?? 50);
        $batchSize = intval($json['batch_size'] ?? $this->request->getVar('batch_size') ?? 25);

        if ($limit <= 0 || $limit > 500) {
            $limit = 50;
        }
        if ($batchSize <= 0 || $batchSize > 50) {
            $batchSize = 25;
        }

        $vectorService = new CloudflareVectorService();
        if (!$vectorService->isConfigured()) {
            return $this->fail('Cloudflare credentials not configured in .env or app/Config/Cloudflare.php', 400);
        }

        $result = $vectorService->indexBatch($mode, $limit, $batchSize);

        return $this->respond($result);
    }

    /**
     * Test semantic search or vector generation
     */
    public function vectorizeTest()
    {
        $query = $this->request->getVar('query') ?? 'kitchen accessories portable';
        $vectorService = new CloudflareVectorService();

        if (!$vectorService->isConfigured()) {
            return $this->fail('Cloudflare credentials not configured', 400);
        }

        $matches = $vectorService->searchSemantic($query, 10);

        return $this->respond([
            'success' => true,
            'query'   => $query,
            'matches' => $matches
        ]);
    }

    /**
     * Dedicated REST API for Semantic Product Search
     */
    public function semanticSearch()
    {
        if ($this->response === null) {
            $this->response = response();
        }
        if ($this->request === null) {
            $this->request = request();
        }

        $response = $this->response;
        $request  = $this->request;

        $response->setHeader('Access-Control-Allow-Origin', '*');
        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-API-Key, api-key, api_key, x-token');

        if (strtolower($request->getMethod()) === 'options') {
            return $response->setStatusCode(204);
        }

        $json = (method_exists($request, 'getJSON') ? ($request->getJSON(true) ?? []) : []);

        $getParam = function(string $key) use ($request, $json) {
            if (isset($json[$key])) return $json[$key];
            if (method_exists($request, 'getVar')) {
                $val = $request->getVar($key);
                if ($val !== null) return $val;
            }
            return $_REQUEST[$key] ?? $_POST[$key] ?? $_GET[$key] ?? null;
        };

        $query    = trim((string)($getParam('query') ?? $getParam('search_query') ?? $getParam('prompt') ?? ''));
        $country  = trim((string)($getParam('country') ?? ''));
        $origin   = trim((string)($getParam('origin') ?? ''));
        $limit    = intval($getParam('limit') ?? 5);
        $minScore = floatval($getParam('min_score') ?? 0);

        if (empty($query)) {
            return $this->respond([
                'success' => false,
                'error'   => 'Parameter "query" or "prompt" is required for semantic search.'
            ], 400);
        }

        if ($limit <= 0 || $limit > 50) {
            $limit = 5;
        }

        $vectorService = new CloudflareVectorService();
        if (!$vectorService->isConfigured()) {
            return $this->respond([
                'success' => false,
                'error'   => 'Cloudflare Vectorize is not configured on the server.'
            ], 500);
        }

        // Fetch top candidates from Vectorize
        $fetchK = max($limit * 4, 20);
        $matches = $vectorService->searchSemantic($query, $fetchK);

        if (empty($matches)) {
            return $this->respond([
                'success'     => true,
                'query'       => $query,
                'total_found' => 0,
                'best_match'  => null,
                'products'    => [],
                'message'     => 'No matching products found.'
            ]);
        }

        $productIds = array_column($matches, 'product_id');
        $scoreMap   = array_column($matches, 'score', 'product_id');

        $db = \Config\Database::connect();
        $builder = $db->table('products')->whereIn('id', $productIds);

        if (!empty($country) && strtoupper($country) !== 'ALL') {
            $builder->like('country', strtoupper($country));
        }
        if (!empty($origin) && strtolower($origin) !== 'all') {
            $builder->where('origin', $origin);
        }

        $dbProducts = $builder->get()->getResultArray();
        $enriched = [];

        foreach ($dbProducts as $p) {
            $rawScore = $scoreMap[$p['id']] ?? 0;
            $pctScore = round($rawScore * 100, 1);

            if ($minScore > 0 && $pctScore < $minScore) {
                continue;
            }

            $images = !empty($p['ad_image_urls']) ? array_filter(array_map('trim', explode(';', $p['ad_image_urls']))) : [];
            $videos = !empty($p['ad_video_urls']) ? array_filter(array_map('trim', explode(';', $p['ad_video_urls']))) : [];

            $currency = (strtoupper($p['country'] ?? '') === 'MA') ? 'DH' : ((strtoupper($p['country'] ?? '') === 'SA') ? 'SAR' : '');

            $enriched[] = [
                'id'                => (int)$p['id'],
                'title'             => $p['title'] ?? 'بدون عنوان',
                'ad_title'          => $p['ad_title'] ?? '',
                'ad_body'           => $p['ad_body'] ?? '',
                'price'             => $p['price_1'] ?? '0',
                'formatted_price'   => ($p['price_1'] ?? '0') . ($currency ? ' ' . $currency : ''),
                'country'           => $p['country'] ?? '',
                'origin'            => $p['origin'] ?? '',
                'product_url'       => $p['product_url'] ?? '',
                'thumbnail_url'     => !empty($images) ? reset($images) : ($p['product_url'] ?? ''),
                'ad_image_urls'     => array_values($images),
                'ad_video_urls'     => array_values($videos),
                'ads_count'         => (int)($p['ads_count'] ?? 0),
                'similarity_score'  => $pctScore,
                'raw_score'         => round($rawScore, 4)
            ];
        }

        usort($enriched, function ($a, $b) {
            return ($b['similarity_score'] <=> $a['similarity_score']);
        });

        $finalProducts = array_slice($enriched, 0, $limit);

        return $this->respond([
            'success'     => true,
            'query'       => $query,
            'total_found' => count($finalProducts),
            'best_match'  => !empty($finalProducts) ? $finalProducts[0] : null,
            'products'    => $finalProducts
        ]);
    }
}

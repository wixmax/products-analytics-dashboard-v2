<?php

namespace App\Services;

use Config\Cloudflare;
use App\Models\ProductModel;

class CloudflareVectorService
{
    protected Cloudflare $config;
    protected $client;
    protected ProductModel $productModel;

    public function __construct(?Cloudflare $config = null)
    {
        $this->config = $config ?? new \Config\Cloudflare();
        $this->client = \Config\Services::curlrequest([
            'timeout' => 30,
            'http_errors' => false
        ]);
        $this->productModel = new ProductModel();
    }

    /**
     * Check if Cloudflare credentials and integration are enabled and configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->config->accountId) && !empty($this->config->apiToken);
    }

    /**
     * Prepare a concise, rich textual representation of a product for semantic embedding
     */
    public function buildProductEmbeddingText(array $product): string
    {
        $parts = [];

        if (!empty($product['title'])) {
            $parts[] = 'Product Title: ' . trim($product['title']);
        }

        if (!empty($product['ad_title']) && $product['ad_title'] !== ($product['title'] ?? '')) {
            $parts[] = 'Ad Headline: ' . trim($product['ad_title']);
        }

        if (!empty($product['ad_body'])) {
            // Trim long ad bodies to reasonable length (e.g. 500 chars)
            $adBody = mb_substr(strip_tags($product['ad_body']), 0, 500);
            $parts[] = 'Ad Copy: ' . trim($adBody);
        }

        if (!empty($product['country'])) {
            $parts[] = 'Market: ' . $product['country'];
        }

        if (!empty($product['origin'])) {
            $parts[] = 'Category: ' . $product['origin'];
        }

        return implode("\n", $parts);
    }

    /**
     * Generate text embeddings using Cloudflare Workers AI
     *
     * @param string|array $text Single text or array of texts
     * @return array|null Returns array of float vectors or null on failure
     */
    public function generateEmbeddings(string|array $text): ?array
    {
        if (!$this->isConfigured()) {
            log_message('warning', 'CloudflareVectorService: Account ID or API Token not configured.');
            return null;
        }

        $endpoint = "https://api.cloudflare.com/client/v4/accounts/{$this->config->accountId}/ai/run/{$this->config->embeddingModel}";

        $payload = is_array($text) ? ['text' => array_values($text)] : ['text' => [$text]];

        try {
            $response = $this->client->post($endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->config->apiToken,
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody(), true);

            if ($statusCode >= 200 && $statusCode < 300 && !empty($body['success'])) {
                // Workers AI returns { result: { shape: [...], data: [[...]] } }
                return $body['result']['data'] ?? null;
            }

            log_message('error', 'Cloudflare Workers AI Error: ' . ($body['errors'][0]['message'] ?? 'Status ' . $statusCode));
            return null;
        } catch (\Throwable $e) {
            log_message('error', 'Cloudflare Workers AI Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate a single embedding vector for a text query
     */
    public function generateSingleEmbedding(string $text): ?array
    {
        $res = $this->generateEmbeddings($text);
        if ($res && isset($res[0]) && is_array($res[0])) {
            return $res[0];
        }
        return null;
    }

    /**
     * Upsert vectors into Cloudflare Vectorize Index
     *
     * @param array $vectors Array of items: [['id' => 'prod_1', 'values' => [...], 'metadata' => [...]], ...]
     */
    public function upsertVectors(array $vectors): bool
    {
        if (!$this->isConfigured() || empty($vectors)) {
            return false;
        }

        $endpoint = "https://api.cloudflare.com/client/v4/accounts/{$this->config->accountId}/vectorize/v2/indexes/{$this->config->vectorizeIndex}/upsert";

        // Cloudflare Vectorize v2 accepts NDJSON payload
        $ndjsonLines = [];
        foreach ($vectors as $vec) {
            $ndjsonLines[] = json_encode($vec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $payload = implode("\n", $ndjsonLines);

        try {
            $response = $this->client->post($endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->config->apiToken,
                    'Content-Type'  => 'application/x-ndjson',
                ],
                'body' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody(), true);

            if ($statusCode >= 200 && $statusCode < 300 && !empty($body['success'])) {
                return true;
            }

            log_message('error', 'Cloudflare Vectorize Upsert Error: ' . json_encode($body['errors'] ?? $response->getBody()));
            return false;
        } catch (\Throwable $e) {
            log_message('error', 'Cloudflare Vectorize Upsert Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Index a single product into Vectorize
     */
    public function indexProduct(array $product): bool
    {
        $productId = $product['id'] ?? null;
        if (!$productId) {
            return false;
        }

        $text = $this->buildProductEmbeddingText($product);
        if (empty(trim($text))) {
            return false;
        }

        $vector = $this->generateSingleEmbedding($text);
        if (!$vector) {
            return false;
        }

        $metadata = [
            'product_id' => (int)$productId,
            'origin'     => (string)($product['origin'] ?? ''),
            'country'    => (string)($product['country'] ?? ''),
            'title'      => mb_substr((string)($product['title'] ?? ''), 0, 100),
        ];

        return $this->upsertVectors([[
            'id'       => (string)$productId,
            'values'   => $vector,
            'metadata' => $metadata,
        ]]);
    }

    /**
     * Bulk index products in batches
     */
    public function bulkIndexProducts(array $products, int $batchSize = 25): array
    {
        $stats = ['total' => count($products), 'indexed' => 0, 'failed' => 0];

        $chunks = array_chunk($products, $batchSize);
        foreach ($chunks as $chunk) {
            $texts = [];
            $validProducts = [];

            foreach ($chunk as $p) {
                $text = $this->buildProductEmbeddingText($p);
                if (!empty(trim($text))) {
                    $texts[] = $text;
                    $validProducts[] = $p;
                }
            }

            if (empty($texts)) {
                continue;
            }

            $embeddings = $this->generateEmbeddings($texts);
            if (!$embeddings || count($embeddings) !== count($validProducts)) {
                $stats['failed'] += count($chunk);
                continue;
            }

            $vectorsToUpsert = [];
            foreach ($validProducts as $i => $p) {
                $vectorsToUpsert[] = [
                    'id'       => (string)$p['id'],
                    'values'   => $embeddings[$i],
                    'metadata' => [
                        'product_id' => (int)$p['id'],
                        'origin'     => (string)($p['origin'] ?? ''),
                        'country'    => (string)($p['country'] ?? ''),
                        'title'      => mb_substr((string)($p['title'] ?? ''), 0, 100),
                    ],
                ];
            }

            if ($this->upsertVectors($vectorsToUpsert)) {
                $stats['indexed'] += count($vectorsToUpsert);
            } else {
                $stats['failed'] += count($chunk);
            }
        }

        return $stats;
    }

    /**
     * Query Vectorize for semantically similar products
     *
     * @param string $queryText Search text in any language
     * @param int $topK Maximum number of results
     * @param array $filter Optional metadata filter
     * @return array List of matches with [ 'product_id' => int, 'score' => float, 'metadata' => array ]
     */
    public function searchSemantic(string $queryText, int $topK = 20, array $filter = []): array
    {
        if (!$this->isConfigured() || empty(trim($queryText))) {
            return [];
        }

        $queryVector = $this->generateSingleEmbedding($queryText);
        if (!$queryVector) {
            return [];
        }

        return $this->queryByVector($queryVector, $topK, $filter);
    }

    /**
     * Query Vectorize with a raw vector
     */
    public function queryByVector(array $vector, int $topK = 20, array $filter = []): array
    {
        $endpoint = "https://api.cloudflare.com/client/v4/accounts/{$this->config->accountId}/vectorize/v2/indexes/{$this->config->vectorizeIndex}/query";

        $payload = [
            'vector'         => $vector,
            'topK'           => $topK,
            'returnValues'   => false,
            'returnMetadata' => 'all',
        ];

        if (!empty($filter)) {
            $payload['filter'] = $filter;
        }

        try {
            $response = $this->client->post($endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->config->apiToken,
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
            ]);

            $body = json_decode($response->getBody(), true);
            if (!empty($body['success']) && !empty($body['result']['matches'])) {
                $results = [];
                foreach ($body['result']['matches'] as $match) {
                    $prodId = $match['metadata']['product_id'] ?? (int)$match['id'];
                    $results[] = [
                        'product_id' => (int)$prodId,
                        'score'      => (float)($match['score'] ?? 0.0),
                        'metadata'   => $match['metadata'] ?? [],
                    ];
                }
                return $results;
            }

            return [];
        } catch (\Throwable $e) {
            log_message('error', 'Cloudflare Vectorize Query Exception: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Find products similar to a given product ID
     */
    public function findSimilarProducts(int $productId, int $limit = 8): array
    {
        $product = $this->productModel->find($productId);
        if (!$product) {
            return [];
        }

        $matches = $this->searchSemantic($this->buildProductEmbeddingText($product), $limit + 1);

        // Filter out the product itself
        $filteredMatches = array_filter($matches, function ($m) use ($productId) {
            return (int)$m['product_id'] !== (int)$productId;
        });

        $productIds = array_column($filteredMatches, 'product_id');
        if (empty($productIds)) {
            return [];
        }

        // Fetch full product details from local DB
        $dbProducts = $this->productModel->whereIn('id', $productIds)->findAll();
        
        // Preserve similarity score order
        $scoreMap = [];
        foreach ($filteredMatches as $m) {
            $scoreMap[$m['product_id']] = $m['score'];
        }

        foreach ($dbProducts as &$p) {
            $p['similarity_score'] = round(($scoreMap[$p['id']] ?? 0) * 100, 1);
        }

        usort($dbProducts, function ($a, $b) {
            return ($b['similarity_score'] ?? 0) <=> ($a['similarity_score'] ?? 0);
        });

        return array_slice($dbProducts, 0, $limit);
    }

    /**
     * Test connection and retrieve index details
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Cloudflare Account ID or API Token is missing in configuration or .env',
            ];
        }

        // 1. Test Workers AI with a simple prompt
        $testVector = $this->generateSingleEmbedding('Winning e-commerce dropshipping product test');
        if (!$testVector) {
            return [
                'success' => false,
                'message' => 'Failed to generate embedding via Workers AI. Check your API Token and permissions.',
            ];
        }

        // 2. Test Vectorize Index details
        $endpoint = "https://api.cloudflare.com/client/v4/accounts/{$this->config->accountId}/vectorize/v2/indexes/{$this->config->vectorizeIndex}";

        try {
            $response = $this->client->get($endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->config->apiToken,
                    'Content-Type'  => 'application/json',
                ],
            ]);

            $body = json_decode($response->getBody(), true);
            if (!empty($body['success'])) {
                return [
                    'success'    => true,
                    'message'    => 'Connection successful! Workers AI & Vectorize Index are ready.',
                    'dimensions' => count($testVector),
                    'index_info' => $body['result'] ?? [],
                ];
            }

            return [
                'success' => false,
                'message' => 'Workers AI works, but Vectorize Index was not found or inaccessible: ' . ($body['errors'][0]['message'] ?? 'Unknown error'),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Error querying Vectorize: ' . $e->getMessage(),
            ];
        }
    }
}

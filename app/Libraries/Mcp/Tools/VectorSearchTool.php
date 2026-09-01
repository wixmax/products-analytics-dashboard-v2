<?php

namespace App\Libraries\Mcp\Tools;

use App\Libraries\Mcp\ToolInterface;
use App\Services\CloudflareVectorService;

class VectorSearchTool implements ToolInterface
{
    protected string $action;

    public function __construct(string $action = 'semantic_search_products')
    {
        $this->action = $action;
    }

    public function getName(): string
    {
        return $this->action;
    }

    public function getDescription(): string
    {
        if ($this->action === 'find_similar_products') {
            return 'Find semantically and visually similar winning products/competitors for a given product ID using Cloudflare Vectorize vector distance.';
        }
        return 'Perform AI-powered semantic and vector search on products using Cloudflare Vectorize. Understands multilingual queries (Arabic, English, French) and matches problem-solving concepts, ad angles, and niches.';
    }

    public function getInputSchema(): array
    {
        if ($this->action === 'find_similar_products') {
            return [
                'type' => 'object',
                'properties' => [
                    'product_id' => ['type' => 'number', 'description' => 'The ID of the reference product.'],
                    'limit'      => ['type' => 'number', 'description' => 'Max similar products to return (default: 8)']
                ],
                'required' => ['product_id'],
                'additionalProperties' => false
            ];
        }

        return [
            'type' => 'object',
            'properties' => [
                'query'   => ['type' => 'string', 'description' => 'The semantic search text, product idea, problem description, or marketing hook.'],
                'country' => ['type' => 'string', 'description' => 'Optional 2-letter country code filter (e.g. MA, SA, DZ)'],
                'origin'  => ['type' => 'string', 'description' => 'Optional category/origin (e.g. Winning, Local, China, Japan)'],
                'limit'   => ['type' => 'number', 'description' => 'Max number of products to return (default: 20)']
            ],
            'required' => ['query'],
            'additionalProperties' => false
        ];
    }

    public function execute(array $args, ?array $context = null): array
    {
        $db = \Config\Database::connect();
        $vectorService = new CloudflareVectorService();

        if ($this->action === 'find_similar_products') {
            $productId = intval($args['product_id'] ?? 0);
            $limit     = intval($args['limit'] ?? 8);

            if ($productId <= 0) {
                return ['error' => 'Valid product_id is required'];
            }

            if (!$vectorService->isConfigured()) {
                return [
                    'status'  => 'error',
                    'message' => 'Cloudflare Vectorize is not configured on the server.'
                ];
            }

            $similar = $vectorService->findSimilarProducts($productId, $limit);

            return [
                'status'           => 'success',
                'product_id'       => $productId,
                'returned_count'   => count($similar),
                'similar_products' => $similar
            ];
        }

        // semantic_search_products
        $query   = $args['query'] ?? '';
        $origin  = $args['origin'] ?? null;
        $country = $args['country'] ?? null;
        $limit   = intval($args['limit'] ?? 20);

        if (empty(trim($query))) {
            return ['error' => 'Query parameter is required for semantic search'];
        }

        if (!$vectorService->isConfigured()) {
            return [
                'status'  => 'error',
                'message' => 'Cloudflare Vectorize is not configured on the server.'
            ];
        }

        $matches = $vectorService->searchSemantic($query, $limit * 2);
        if (empty($matches)) {
            return [
                'status'         => 'success',
                'query'          => $query,
                'total'          => 0,
                'returned_count' => 0,
                'products'       => []
            ];
        }

        $productIds = array_column($matches, 'product_id');
        $scoreMap   = array_column($matches, 'score', 'product_id');

        $builder = $db->table('products')->whereIn('id', $productIds);
        if (!empty($origin)) {
            $builder->where('origin', $origin);
        }
        if (!empty($country)) {
            $builder->like('country', strtoupper($country));
        }

        $products = $builder->get()->getResultArray();
        foreach ($products as &$p) {
            $p['similarity_score'] = round(($scoreMap[$p['id']] ?? 0) * 100, 1);
        }

        usort($products, function ($a, $b) {
            return ($b['similarity_score'] ?? 0) <=> ($a['similarity_score'] ?? 0);
        });

        $products = array_slice($products, 0, $limit);

        return [
            'status'         => 'success',
            'query'          => $query,
            'total'          => count($products),
            'returned_count' => count($products),
            'products'       => $products
        ];
    }
}

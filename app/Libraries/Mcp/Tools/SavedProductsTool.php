<?php

namespace App\Libraries\Mcp\Tools;

use App\Libraries\Mcp\ToolInterface;
use App\Models\ProductModel;

class SavedProductsTool implements ToolInterface
{
    protected string $action;

    public function __construct(string $action = 'get_saved_products')
    {
        $this->action = $action;
    }

    public function getName(): string
    {
        return $this->action;
    }

    public function getDescription(): string
    {
        if ($this->action === 'save_product' || $this->action === 'save_ad') {
            return 'Save or update a product in saved-ads (المحفوظات) for the authenticated user based on their MCP API token.';
        }
        return 'Retrieve products saved specifically by the authenticated user/tenant, with options for collection, country, search, and sorting.';
    }

    public function getInputSchema(): array
    {
        if ($this->action === 'save_product' || $this->action === 'save_ad') {
            return [
                'type' => 'object',
                'properties' => [
                    'product_id'  => ['type' => 'number', 'description' => 'Database ID of the product if existing in the system'],
                    'product_url' => ['type' => 'string', 'description' => 'URL of the product to save (required if product_id is not provided)'],
                    'collection'  => ['type' => 'string', 'description' => 'Collection name (e.g. عامة, ملابس, إلكترونيات). Default is "عامة"'],
                    'notes'       => ['type' => 'string', 'description' => 'Optional user notes for the saved ad'],
                    'rating'      => ['type' => 'number', 'description' => 'Optional rating (0-5)'],
                    'title'       => ['type' => 'string', 'description' => 'Product title (optional)'],
                    'country'     => ['type' => 'string', 'description' => 'Country code (e.g. MA)']
                ],
                'additionalProperties' => false
            ];
        }

        return [
            'type' => 'object',
            'properties' => [
                'collection'   => ['type' => 'string', 'description' => 'Filter by collection name (e.g. عامة, ملابس, إلكترونيات)'],
                'country'      => ['type' => 'string', 'description' => '2-letter country code (e.g. MA, SA)'],
                'saved_status' => ['type' => 'string', 'description' => 'Status: active or inactive'],
                'search_query' => ['type' => 'string', 'description' => 'Search term in title, body, or notes'],
                'sort_by'      => ['type' => 'string', 'enum' => ['saved_at', 'rating', 'created_at', 'title']],
                'sort_order'   => ['type' => 'string', 'enum' => ['ASC', 'DESC']],
                'limit'        => ['type' => 'number', 'description' => 'Max products to return (default 50)'],
                'offset'       => ['type' => 'number', 'description' => 'Offset for pagination (default 0)']
            ],
            'additionalProperties' => false
        ];
    }

    public function execute(array $args, ?array $context = null): array
    {
        $authUser = $context['user'] ?? null;
        if (!$authUser) {
            return [
                'status' => 'error',
                'error'  => 'Unauthorized: Invalid or missing API token. Please pass a valid token in the URL or Authorization header.'
            ];
        }

        $tenantId = $authUser['tenant_id'] ?? 1;
        $productModel = new ProductModel();

        if ($this->action === 'save_product' || $this->action === 'save_ad') {
            $productId  = isset($args['product_id']) ? intval($args['product_id']) : null;
            $productUrl = $args['product_url'] ?? null;
            $collection = !empty($args['collection']) ? $args['collection'] : 'عامة';
            $notes      = $args['notes'] ?? '';
            $rating     = isset($args['rating']) ? intval($args['rating']) : 0;

            $targetProduct = null;
            if ($productId) {
                $targetProduct = $productModel->find($productId);
                if ($targetProduct && empty($productUrl)) {
                    $productUrl = $targetProduct['product_url'] ?? null;
                }
            }

            $existingTenantProduct = null;
            if (!empty($productUrl)) {
                $existingTenantProduct = $productModel->where('product_url', $productUrl)
                                                     ->where('tenant_id', $tenantId)
                                                     ->first();
            }

            if ($existingTenantProduct) {
                $updateData = [
                    'is_saved'     => true,
                    'saved_at'     => date('Y-m-d H:i:s'),
                    'saved_status' => 'active',
                    'collection'   => $collection ?: ($existingTenantProduct['collection'] ?: 'عامة'),
                ];
                if (!empty($notes)) {
                    $updateData['notes'] = $notes;
                }
                if ($rating > 0) {
                    $updateData['rating'] = $rating;
                }
                if (!empty($args['title'])) {
                    $updateData['title'] = $args['title'];
                }

                $productModel->update($existingTenantProduct['id'], $updateData);
                $savedRecord = $productModel->find($existingTenantProduct['id']);

                return [
                    'status'   => 'success',
                    'action'   => 'updated_saved',
                    'message'  => 'تم حفظ وتحديث المنتج بنجاح في المحفوظات! ⭐',
                    'user'     => [
                        'id'        => $authUser['id'] ?? null,
                        'username'  => $authUser['username'] ?? 'User',
                        'tenant_id' => $tenantId
                    ],
                    'product'  => $savedRecord
                ];
            }

            $globalProduct = null;
            if (!empty($productUrl)) {
                $globalProduct = $productModel->where('product_url', $productUrl)->first();
            } elseif ($targetProduct) {
                $globalProduct = $targetProduct;
                $productUrl    = $targetProduct['product_url'] ?? '';
            }

            $source = $globalProduct ?: ($targetProduct ?: []);
            if (empty($productUrl) && empty($source)) {
                return [
                    'status' => 'error',
                    'error'  => 'Product not found. Please provide a valid product_id or product_url.'
                ];
            }

            $origin = $args['origin'] ?? $source['origin'] ?? 'Winning';
            $dataToInsert = [
                'title'              => $args['title'] ?? $source['title'] ?? 'بدون عنوان',
                'product_url'        => $productUrl ?: ($source['product_url'] ?? ''),
                'country'            => $args['country'] ?? $source['country'] ?? '',
                'algo'               => $args['algo'] ?? $source['algo'] ?? 'winning',
                'ad_start_date'      => $args['ad_start_date'] ?? $source['ad_start_date'] ?? date('Y-m-d'),
                'ads_count'          => intval($args['ads_count'] ?? $source['ads_count'] ?? 0),
                'unique_image_count' => intval($args['unique_image_count'] ?? $source['unique_image_count'] ?? 0),
                'unique_video_count' => intval($args['unique_video_count'] ?? $source['unique_video_count'] ?? 0),
                'avg_creatives'      => floatval($args['avg_creatives'] ?? $source['avg_creatives'] ?? 1),
                'ads_per_unique_url' => floatval($args['ads_per_unique_url'] ?? $source['ads_per_unique_url'] ?? 1),
                'ad_title'           => $args['ad_title'] ?? $source['ad_title'] ?? '',
                'ad_body'            => $args['ad_body'] ?? $source['ad_body'] ?? '',
                'ad_image_urls'      => is_array($args['ad_image_urls'] ?? null) ? implode(';', $args['ad_image_urls']) : ($args['ad_image_urls'] ?? $source['ad_image_urls'] ?? ''),
                'ad_video_urls'      => is_array($args['ad_video_urls'] ?? null) ? implode(';', $args['ad_video_urls']) : ($args['ad_video_urls'] ?? $source['ad_video_urls'] ?? ''),
                'price_1'            => strval($args['price'] ?? $args['price_1'] ?? $source['price_1'] ?? '0'),
                'active_ads'         => true,
                'origin'             => $origin,
                'api_version'        => $args['api_version'] ?? $source['api_version'] ?? '',
                'is_saved'           => true,
                'saved_at'           => date('Y-m-d H:i:s'),
                'collection'         => $collection,
                'saved_status'       => 'active',
                'rating'             => $rating,
                'notes'              => $notes,
                'tenant_id'          => $tenantId
            ];

            $newId = $productModel->insert($dataToInsert);
            $savedRecord = $productModel->find($newId);

            return [
                'status'   => 'success',
                'action'   => 'saved',
                'message'  => 'تم حفظ المنتج بنجاح في المحفوظات! ⭐',
                'user'     => [
                    'id'        => $authUser['id'] ?? null,
                    'username'  => $authUser['username'] ?? 'User',
                    'tenant_id' => $tenantId
                ],
                'product'  => $savedRecord
            ];
        }

        // Default action: get_saved_products
        $collection    = $args['collection'] ?? null;
        $status        = $args['saved_status'] ?? null;
        $countryFilter = isset($args['country']) ? strtoupper($args['country']) : null;
        $searchQuery   = isset($args['search_query']) ? strtolower($args['search_query']) : null;
        $sortBy        = $args['sort_by'] ?? 'saved_at';
        $sortOrder     = strtoupper($args['sort_order'] ?? 'DESC');
        $limit         = intval($args['limit'] ?? 50);
        $offset        = intval($args['offset'] ?? 0);

        $builder = $productModel->where('tenant_id', $tenantId)
                               ->where('is_saved', true);

        if (!empty($collection)) {
            $builder->where('collection', $collection);
        }
        if (!empty($status)) {
            $builder->where('saved_status', $status);
        }
        if (!empty($countryFilter)) {
            $builder->like('country', $countryFilter);
        }
        if (!empty($searchQuery)) {
            $builder->groupStart()
                    ->like('title', $searchQuery)
                    ->orLike('ad_title', $searchQuery)
                    ->orLike('ad_body', $searchQuery)
                    ->orLike('notes', $searchQuery)
                    ->groupEnd();
        }

        if (in_array($sortBy, ['saved_at', 'rating', 'created_at', 'title'])) {
            $builder->orderBy($sortBy, $sortOrder);
        } else {
            $builder->orderBy('saved_at', 'DESC');
        }

        $savedProducts = $builder->findAll($limit, $offset);

        return [
            'status'          => 'success',
            'total'           => count($savedProducts),
            'user'            => [
                'username'  => $authUser['username'] ?? 'User',
                'tenant_id' => $tenantId
            ],
            'total_returned'  => count($savedProducts),
            'filters_applied' => [
                'collection'   => $collection,
                'saved_status' => $status,
                'country'      => $countryFilter,
                'search_query' => $searchQuery,
                'sort_by'      => $sortBy,
                'sort_order'   => $sortOrder,
            ],
            'products' => $savedProducts
        ];
    }
}

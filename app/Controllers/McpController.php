<?php

namespace App\Controllers;

use App\Models\ProductModel;
use App\Models\SnapshotModel;
use CodeIgniter\RESTful\ResourceController;

class McpController extends ResourceController
{
    protected $format = 'json';

    /**
     * Helper to parse raw_json string from data_snapshots table
     */
    private function parseSnapshotEntries($rawJsonStr)
    {
        if (empty($rawJsonStr)) {
            return [];
        }
        try {
            $parsed = is_string($rawJsonStr) ? json_decode($rawJsonStr, true) : $rawJsonStr;
            if (is_array($parsed) && isset($parsed[0])) {
                $jsonObj = $parsed[0]['result']['data']['json'] ?? null;
                if ($jsonObj) {
                    if (isset($jsonObj['results']) && is_array($jsonObj['results'])) {
                        return $jsonObj['results'];
                    }
                    if (isset($jsonObj['productsEntries']) && is_array($jsonObj['productsEntries'])) {
                        return $jsonObj['productsEntries'];
                    }
                    if (isset($jsonObj['products']) && is_array($jsonObj['products'])) {
                        return $jsonObj['products'];
                    }
                }
            }
            if (is_array($parsed)) {
                return $parsed;
            }
            return [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * GET/POST /api/mcp
     * Main MCP Protocol Endpoint (JSON-RPC 2.0)
     */
    public function handleMcp()
    {
        $response = $this->response;
        $response->setHeader('Access-Control-Allow-Origin', '*');
        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');

        $method = strtolower($this->request->getMethod());

        if ($method === 'options') {
            return $response->setStatusCode(204);
        }
        
        if ($method === 'get') {
            // GET Discovery / Information Response
            return $this->respond([
                'mcp_server' => 'products-analytics-mcp-php',
                'version'    => '1.0.0',
                'status'     => 'running',
                'endpoint'   => site_url('api/mcp'),
                'capabilities' => [
                    'tools' => true,
                    'jsonrpc' => '2.0'
                ],
                'tools' => $this->getToolsManifest()
            ]);
        }

        $input = $this->request->getJSON(true) ?: json_decode($this->request->getBody(), true);
        
        if (!$input || !isset($input['method'])) {
            return $this->respond([
                'jsonrpc' => '2.0',
                'id'      => $input['id'] ?? null,
                'error'   => [
                    'code'    => -32600,
                    'message' => 'Invalid Request. Missing method.'
                ]
            ], 400);
        }

        $jsonrpcId = $input['id'] ?? null;
        $mcpMethod = $input['method'];
        $params    = $input['params'] ?? [];

        switch ($mcpMethod) {
            case 'initialize':
                return $this->respond([
                    'jsonrpc' => '2.0',
                    'id'      => $jsonrpcId,
                    'result'  => [
                        'protocolVersion' => '2024-11-05',
                        'capabilities'    => ['tools' => (object)[]],
                        'serverInfo'      => [
                            'name'    => 'products-analytics-mcp',
                            'version' => '1.0.0'
                        ]
                    ]
                ]);

            case 'tools/list':
                return $this->respond([
                    'jsonrpc' => '2.0',
                    'id'      => $jsonrpcId,
                    'result'  => [
                        'tools' => $this->getToolsManifest()
                    ]
                ]);

            case 'tools/call':
                $toolName = $params['name'] ?? '';
                $toolArgs = $params['arguments'] ?? [];
                
                try {
                    $resultText = $this->executeTool($toolName, $toolArgs);
                    return $this->respond([
                        'jsonrpc' => '2.0',
                        'id'      => $jsonrpcId,
                        'result'  => [
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => is_string($resultText) ? $resultText : json_encode($resultText, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                                ]
                            ]
                        ]
                    ]);
                } catch (\Throwable $e) {
                    return $this->respond([
                        'jsonrpc' => '2.0',
                        'id'      => $jsonrpcId,
                        'error'   => [
                            'code'    => -32603,
                            'message' => 'Internal error: ' . $e->getMessage()
                        ]
                    ]);
                }

            default:
                return $this->respond([
                    'jsonrpc' => '2.0',
                    'id'      => $jsonrpcId,
                    'error'   => [
                        'code'    => -32601,
                        'message' => 'Method not found: ' . $mcpMethod
                    ]
                ], 404);
        }
    }

    /**
     * Resolve user & tenant_id from API Token or active session
     */
    private function resolveAuthenticatedUser()
    {
        $token = $this->request->getGet('token');

        if (empty($token)) {
            $authHeader = $this->request->getHeaderLine('Authorization');
            if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = trim($matches[1]);
            }
        }

        if (empty($token)) {
            $token = $this->request->getHeaderLine('X-API-Key');
        }

        if (!empty($token)) {
            $db = \Config\Database::connect();
            $userRow = $db->table('users')->where('api_token', $token)->get()->getRowArray();
            if ($userRow) {
                return $userRow;
            }
        }

        // Fallback to active logged in session
        if (auth()->loggedIn()) {
            $user = auth()->user();
            return [
                'id'        => $user->id,
                'username'  => $user->username,
                'tenant_id' => $user->tenant_id ?? 1
            ];
        }

        return null;
    }

    /**
     * Get array of registered MCP tools and JSON schemas
     */
    private function getToolsManifest()
    {
        return [
            [
                'name'        => 'get_saved_products',
                'description' => 'Retrieve products saved specifically by the authenticated user/tenant, with options for collection, country, search, and sorting.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'collection'   => ['type' => 'string', 'description' => 'Filter by collection name (e.g. عامة, ملابس, إلكترونيات)'],
                        'country'      => ['type' => 'string', 'description' => '2-letter country code (e.g. MA, SA)'],
                        'saved_status' => ['type' => 'string', 'description' => 'Status: active or inactive'],
                        'search_query' => ['type' => 'string', 'description' => 'Search term in title, body, or notes'],
                        'sort_by'      => ['type' => 'string', 'enum' => ['saved_at', 'rating', 'created_at', 'title']],
                        'sort_order'   => ['type' => 'string', 'enum' => ['ASC', 'DESC']],
                        'limit'        => ['type' => 'number', 'description' => 'Max products to return (default 50)'],
                        'offset'       => ['type' => 'number', 'description' => 'Offset for pagination (default 0)']
                    ]
                ]
            ],
            [
                'name'        => 'list_snapshots',
                'description' => 'List available data snapshots stored in the system, with optional origin filtering and pagination.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'origin' => ['type' => 'string', 'description' => 'Filter by origin (Winning, China, Japan, Competitor, Local)'],
                        'limit'  => ['type' => 'number', 'description' => 'Limit results (default 20)'],
                        'offset' => ['type' => 'number', 'description' => 'Offset results (default 0)']
                    ]
                ]
            ],
            [
                'name'        => 'get_snapshot_by_date',
                'description' => 'Request product data snapshot entries by date string, api_version, or snapshot_id.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'date'        => ['type' => 'string', 'description' => 'Date or api_version substring (e.g. 2026-07-26)'],
                        'snapshot_id' => ['type' => 'number', 'description' => 'Exact snapshot ID'],
                        'origin'      => ['type' => 'string', 'description' => 'Origin category (default Winning)'],
                        'country'     => ['type' => 'string', 'description' => 'Country code (e.g. MA, SA)'],
                        'limit'       => ['type' => 'number', 'description' => 'Max items to return (default 100)']
                    ]
                ]
            ],
            [
                'name'        => 'filter_winning_products',
                'description' => 'Filter snapshot and DB data specifically for Winning Products (origin = Winning) using tRPC API filters.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'snapshot_id'     => ['type' => 'number'],
                        'date'            => ['type' => 'string'],
                        'country'         => ['type' => 'string', 'description' => '2-letter country code'],
                        'min_ads'         => ['type' => 'number'],
                        'max_ads'         => ['type' => 'number'],
                        'min_price'       => ['type' => 'number'],
                        'max_price'       => ['type' => 'number'],
                        'search_query'    => ['type' => 'string'],
                        'active_ads_only' => ['type' => 'boolean'],
                        'sort_by'         => ['type' => 'string', 'enum' => ['ads_count', 'title', 'price', 'date']],
                        'sort_order'      => ['type' => 'string', 'enum' => ['ASC', 'DESC']],
                        'limit'           => ['type' => 'number'],
                        'offset'          => ['type' => 'number']
                    ]
                ]
            ],
            [
                'name'        => 'get_products',
                'description' => 'Fetch single or multiple products by IDs, or search products by name/title.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'ids'     => ['type' => 'array', 'items' => ['type' => 'number']],
                        'name'    => ['type' => 'string'],
                        'origin'  => ['type' => 'string'],
                        'country' => ['type' => 'string'],
                        'limit'   => ['type' => 'number']
                    ]
                ]
            ],
            [
                'name'        => 'get_product_full_json',
                'description' => 'Retrieve complete unredacted JSON object of a product by ID or title.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'product_id' => ['type' => 'number'],
                        'title'      => ['type' => 'string']
                    ]
                ]
            ]
        ];
    }

    /**
     * Dispatch tool execution
     */
    private function executeTool($name, $args)
    {
        $snapshotModel = new SnapshotModel();
        $productModel  = new ProductModel();

        if ($name === 'get_saved_products') {
            $authUser = $this->resolveAuthenticatedUser();
            if (!$authUser) {
                return ['error' => 'Unauthorized: Invalid or missing API token. Please generate a token in your profile settings.'];
            }

            $tenantId      = $authUser['tenant_id'] ?? 1;
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
                $builder->where('country', $countryFilter);
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

        if ($name === 'list_snapshots') {
            $origin = $args['origin'] ?? null;
            $limit  = intval($args['limit'] ?? 20);
            $offset = intval($args['offset'] ?? 0);

            $builder = $snapshotModel->select('id, origin, api_version, product_count, data_hash, created_at, updated_at');
            if (!empty($origin)) {
                $builder->where('origin', $origin);
            }
            $snapshots = $builder->orderBy('id', 'DESC')->findAll($limit, $offset);

            return [
                'count'     => count($snapshots),
                'snapshots' => $snapshots
            ];
        }

        if ($name === 'get_snapshot_by_date') {
            $dateStr       = $args['date'] ?? null;
            $snapshotId    = $args['snapshot_id'] ?? null;
            $origin        = $args['origin'] ?? 'Winning';
            $countryFilter = isset($args['country']) ? strtoupper($args['country']) : null;
            $limit         = intval($args['limit'] ?? 100);

            $snapshotRow = null;
            if ($snapshotId) {
                $snapshotRow = $snapshotModel->find($snapshotId);
            } elseif (!empty($dateStr)) {
                $snapshotRow = $snapshotModel->groupStart()
                                             ->like('api_version', $dateStr)
                                             ->orLike('created_at', $dateStr)
                                           ->groupEnd()
                                           ->where('origin', $origin)
                                           ->orderBy('id', 'DESC')
                                           ->first();
            } else {
                $snapshotRow = $snapshotModel->where('origin', $origin)->orderBy('id', 'DESC')->first();
            }

            if (!$snapshotRow) {
                return ['error' => 'No snapshot found matching criteria'];
            }

            $entries = $this->parseSnapshotEntries($snapshotRow['raw_json'] ?? '');
            if ($countryFilter) {
                $entries = array_values(array_filter($entries, function($e) use ($countryFilter) {
                    return strtoupper($e['country'] ?? '') === $countryFilter;
                }));
            }

            $total = count($entries);
            $entries = array_slice($entries, 0, $limit);

            return [
                'snapshot' => [
                    'id'            => $snapshotRow['id'],
                    'origin'        => $snapshotRow['origin'],
                    'api_version'   => $snapshotRow['api_version'],
                    'product_count' => $snapshotRow['product_count'],
                    'created_at'    => $snapshotRow['created_at']
                ],
                'returned_count'    => count($entries),
                'total_in_snapshot' => $total,
                'products'          => $entries
            ];
        }

        if ($name === 'filter_winning_products') {
            $snapshotId    = $args['snapshot_id'] ?? null;
            $dateStr       = $args['date'] ?? null;
            $countryFilter = isset($args['country']) ? strtoupper($args['country']) : null;
            $minAds        = isset($args['min_ads']) ? floatval($args['min_ads']) : null;
            $maxAds        = isset($args['max_ads']) ? floatval($args['max_ads']) : null;
            $minPrice      = isset($args['min_price']) ? floatval($args['min_price']) : null;
            $maxPrice      = isset($args['max_price']) ? floatval($args['max_price']) : null;
            $searchQuery   = isset($args['search_query']) ? strtolower($args['search_query']) : null;
            $activeAdsOnly = !empty($args['active_ads_only']);
            $sortBy        = $args['sort_by'] ?? 'ads_count';
            $sortOrder     = strtoupper($args['sort_order'] ?? 'DESC');
            $limit         = intval($args['limit'] ?? 50);
            $offset        = intval($args['offset'] ?? 0);

            $snapshotRow = null;
            if ($snapshotId) {
                $snapshotRow = $snapshotModel->where('id', $snapshotId)->where('origin', 'Winning')->first();
            } elseif (!empty($dateStr)) {
                $snapshotRow = $snapshotModel->where('origin', 'Winning')
                                             ->groupStart()
                                                 ->like('api_version', $dateStr)
                                                 ->orLike('created_at', $dateStr)
                                             ->groupEnd()
                                             ->orderBy('id', 'DESC')
                                             ->first();
            } else {
                $snapshotRow = $snapshotModel->where('origin', 'Winning')->orderBy('id', 'DESC')->first();
            }

            $entries = [];
            if ($snapshotRow) {
                $entries = $this->parseSnapshotEntries($snapshotRow['raw_json'] ?? '');
            } else {
                $entries = $productModel->where('origin', 'Winning')->findAll();
            }

            // Filter entries
            $filtered = array_filter($entries, function($item) use ($countryFilter, $minAds, $maxAds, $minPrice, $maxPrice, $searchQuery, $activeAdsOnly) {
                $c = strtoupper($item['country'] ?? '');
                if ($countryFilter && $c !== $countryFilter) return false;

                $adsCount = intval($item['ads_count'] ?? $item['adsCount'] ?? 0);
                if ($minAds !== null && $adsCount < $minAds) return false;
                if ($maxAds !== null && $adsCount > $maxAds) return false;

                $price = floatval($item['price_1'] ?? $item['actualPrice'] ?? $item['price'] ?? 0);
                if ($minPrice !== null && $price < $minPrice) return false;
                if ($maxPrice !== null && $price > maxPrice) return false;

                if ($activeAdsOnly) {
                    $active = isset($item['active_ads']) ? $item['active_ads'] : true;
                    if (!$active) return false;
                }

                if ($searchQuery) {
                    $title   = strtolower($item['product_title'] ?? $item['title'] ?? '');
                    $adTitle = strtolower($item['ad_title'] ?? '');
                    $adBody  = strtolower($item['ad_body'] ?? '');
                    if (strpos($title, $searchQuery) === false && strpos($adTitle, $searchQuery) === false && strpos($adBody, $searchQuery) === false) {
                        return false;
                    }
                }

                return true;
            });

            // Sort entries
            usort($filtered, function($a, $b) use ($sortBy, $sortOrder) {
                if ($sortBy === 'ads_count') {
                    $valA = intval($a['ads_count'] ?? $a['adsCount'] ?? 0);
                    $valB = intval($b['ads_count'] ?? $b['adsCount'] ?? 0);
                } elseif ($sortBy === 'price') {
                    $valA = floatval($a['price_1'] ?? $a['actualPrice'] ?? $a['price'] ?? 0);
                    $valB = floatval($b['price_1'] ?? $b['actualPrice'] ?? $b['price'] ?? 0);
                } elseif ($sortBy === 'title') {
                    $valA = strtolower($a['product_title'] ?? $a['title'] ?? '');
                    $valB = strtolower($b['product_title'] ?? $b['title'] ?? '');
                } else {
                    $valA = $a['ad_start_date'] ?? $a['created_at'] ?? '';
                    $valB = $b['ad_start_date'] ?? $b['created_at'] ?? '';
                }

                if ($valA == $valB) return 0;
                if ($sortOrder === 'ASC') {
                    return ($valA < $valB) ? -1 : 1;
                }
                return ($valA > $valB) ? -1 : 1;
            });

            $totalMatching = count($filtered);
            $paginated = array_slice($filtered, $offset, $limit);

            return [
                'snapshot_info' => $snapshotRow ? [
                    'id'          => $snapshotRow['id'],
                    'api_version' => $snapshotRow['api_version'],
                    'date'        => $snapshotRow['created_at']
                ] : 'Database Products Fallback',
                'filters_applied' => [
                    'origin'          => 'Winning',
                    'country'         => $countryFilter,
                    'min_ads'         => $minAds,
                    'max_ads'         => $maxAds,
                    'min_price'       => $minPrice,
                    'max_price'       => $maxPrice,
                    'search_query'    => $searchQuery,
                    'active_ads_only' => $activeAdsOnly,
                    'sort_by'         => $sortBy,
                    'sort_order'      => $sortOrder
                ],
                'total_matching' => $totalMatching,
                'returned_count' => count($paginated),
                'products'       => $paginated
            ];
        }

        if ($name === 'get_products') {
            $ids       = $args['ids'] ?? [];
            $nameQuery = $args['name'] ?? null;
            $origin    = $args['origin'] ?? null;
            $country   = $args['country'] ?? null;
            $limit     = intval($args['limit'] ?? 20);

            $builder = $productModel;
            if (!empty($ids) && is_array($ids)) {
                $builder = $builder->whereIn('id', $ids);
            }
            if (!empty($nameQuery)) {
                $builder = $builder->groupStart()
                                   ->like('title', $nameQuery)
                                   ->orLike('ad_title', $nameQuery)
                                 ->groupEnd();
            }
            if (!empty($origin)) {
                $builder = $builder->where('origin', $origin);
            }
            if (!empty($country)) {
                $builder = $builder->where('country', strtoupper($country));
            }

            $products = $builder->orderBy('id', 'DESC')->findAll($limit);
            return [
                'returned_count' => count($products),
                'products'       => $products
            ];
        }

        if ($name === 'get_product_full_json') {
            $productId  = $args['product_id'] ?? null;
            $titleQuery = $args['title'] ?? null;

            if (!$productId && !$titleQuery) {
                return ['error' => 'Must provide either product_id or title'];
            }

            $productRow = null;
            if ($productId) {
                $productRow = $productModel->find($productId);
            } else {
                $productRow = $productModel->like('title', $titleQuery)->orderBy('id', 'DESC')->first();
            }

            // Raw tRPC JSON search from snapshot
            $rawTrpcObject = null;
            $recentSnapshots = $snapshotModel->orderBy('id', 'DESC')->findAll(10);
            foreach ($recentSnapshots as $snap) {
                $entries = $this->parseSnapshotEntries($snap['raw_json'] ?? '');
                foreach ($entries as $entry) {
                    if ($productId && (($entry['id'] ?? null) == $productId || ($entry['product_id'] ?? null) == $productId)) {
                        $rawTrpcObject = $entry;
                        break 2;
                    }
                    if ($titleQuery) {
                        $pTitle = strtolower($entry['product_title'] ?? $entry['title'] ?? '');
                        if (strpos($pTitle, strtolower($titleQuery)) !== false) {
                            $rawTrpcObject = $entry;
                            break 2;
                        }
                    }
                }
            }

            return [
                'product_id'              => $productRow['id'] ?? null,
                'product_database_record' => $productRow,
                'raw_trpc_json'           => $rawTrpcObject
            ];
        }

        throw new \Exception("Unknown tool: {$name}");
    }
}

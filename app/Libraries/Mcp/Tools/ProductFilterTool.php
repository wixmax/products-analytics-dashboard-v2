<?php

namespace App\Libraries\Mcp\Tools;

use App\Libraries\Mcp\ToolInterface;

class ProductFilterTool implements ToolInterface
{
    protected string $action;

    public function __construct(string $action = 'filter_winning_products')
    {
        $this->action = $action;
    }

    public function getName(): string
    {
        return $this->action;
    }

    public function getDescription(): string
    {
        switch ($this->action) {
            case 'filter_winning_products':
                return 'Filter snapshot and DB data specifically for Winning Products (origin = Winning) using tRPC API filters.';
            case 'fetch_new_data':
                return 'Fetch new product data entries filtered by date and country, with classification/origin defaulting to all (ككل / all).';
            case 'get_products':
                return 'Fetch single or multiple products by IDs, or search products by name/title.';
            case 'get_product_full_json':
                return 'Retrieve complete unredacted JSON object of a product by ID or title.';
            default:
                return 'Product filter and query tool.';
        }
    }

    public function getInputSchema(): array
    {
        switch ($this->action) {
            case 'filter_winning_products':
                return [
                    'type' => 'object',
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
                    ],
                    'additionalProperties' => false
                ];

            case 'fetch_new_data':
                return [
                    'type' => 'object',
                    'properties' => [
                        'date'           => ['type' => 'string', 'description' => 'Date string (e.g. YYYY-MM-DD), date range (today, yesterday, 7days, 30days), or api_version string'],
                        'country'        => ['type' => 'string', 'description' => '2-letter country code (e.g. MA, SA, DZ) or "all" (default all)'],
                        'classification' => ['type' => 'string', 'description' => 'Data classification/origin filter (e.g. Winning, Local, China, Japan, or "all" / "ككل"). Defaults to "all" (ككل).'],
                        'search_query'   => ['type' => 'string', 'description' => 'Search term for title or ad content'],
                        'sort_by'        => ['type' => 'string', 'enum' => ['date', 'ads_count', 'title', 'price']],
                        'sort_order'     => ['type' => 'string', 'enum' => ['ASC', 'DESC']],
                        'limit'          => ['type' => 'number', 'description' => 'Max products to return (default 50)'],
                        'offset'         => ['type' => 'number', 'description' => 'Offset for pagination (default 0)']
                    ],
                    'additionalProperties' => false
                ];

            case 'get_products':
                return [
                    'type' => 'object',
                    'properties' => [
                        'ids'      => ['type' => 'array', 'items' => ['type' => 'number']],
                        'name'     => ['type' => 'string'],
                        'semantic' => ['type' => 'boolean', 'description' => 'Enable AI semantic search for the name query'],
                        'origin'   => ['type' => 'string'],
                        'country'  => ['type' => 'string'],
                        'limit'    => ['type' => 'number']
                    ],
                    'additionalProperties' => false
                ];

            case 'get_product_full_json':
                return [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => ['type' => 'number'],
                        'title'      => ['type' => 'string']
                    ],
                    'additionalProperties' => false
                ];

            default:
                return ['type' => 'object', 'properties' => []];
        }
    }

    public function execute(array $args, ?array $context = null): array
    {
        $db = \Config\Database::connect();

        if ($this->action === 'filter_winning_products') {
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
                $snapshotRow = $db->table('data_snapshots')->where('id', $snapshotId)->where('origin', 'Winning')->get()->getRowArray();
            } elseif (!empty($dateStr)) {
                $escapedDate = $db->escapeLikeString($dateStr);
                $snapshotRow = $db->table('data_snapshots')
                                  ->where('origin', 'Winning')
                                  ->groupStart()
                                      ->like('api_version', $dateStr)
                                      ->orWhere("CAST(created_at AS TEXT) LIKE '%{$escapedDate}%'")
                                  ->groupEnd()
                                  ->orderBy('id', 'DESC')
                                  ->get()
                                  ->getRowArray();
            } else {
                $snapshotRow = $db->table('data_snapshots')->where('origin', 'Winning')->orderBy('id', 'DESC')->get()->getRowArray();
            }

            $entries = [];
            if ($snapshotRow) {
                $entries = SnapshotTools::parseSnapshotEntries($snapshotRow['raw_json'] ?? '');
            } else {
                $entries = $db->table('products')->where('origin', 'Winning')->get()->getResultArray();
            }

            // Filter entries
            $filtered = array_filter($entries, function($item) use ($countryFilter, $minAds, $maxAds, $minPrice, $maxPrice, $searchQuery, $activeAdsOnly) {
                $cList = array_map('trim', explode(';', strtoupper($item['country'] ?? '')));
                if ($countryFilter && !in_array($countryFilter, $cList, true)) return false;

                $adsCount = intval($item['ads_count'] ?? $item['adsCount'] ?? 0);
                if ($minAds !== null && $adsCount < $minAds) return false;
                if ($maxAds !== null && $adsCount > $maxAds) return false;

                $price = floatval($item['price_1'] ?? $item['actualPrice'] ?? $item['price'] ?? 0);
                if ($minPrice !== null && $price < $minPrice) return false;
                if ($maxPrice !== null && $price > $maxPrice) return false;

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
                'status'         => 'success',
                'total'          => $totalMatching,
                'snapshot_info'  => $snapshotRow ? [
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

        if ($this->action === 'get_products') {
            $ids       = $args['ids'] ?? [];
            $nameQuery = $args['name'] ?? null;
            $semantic  = !empty($args['semantic']);
            $origin    = $args['origin'] ?? null;
            $country   = $args['country'] ?? null;
            $limit     = intval($args['limit'] ?? 20);

            if ($semantic && !empty($nameQuery)) {
                $vectorService = new \App\Services\CloudflareVectorService();
                if ($vectorService->isConfigured()) {
                    $matches = $vectorService->searchSemantic($nameQuery, $limit);
                    if (!empty($matches)) {
                        $ids = array_column($matches, 'product_id');
                    }
                }
            }

            $builder = $db->table('products');
            if (!empty($ids) && is_array($ids)) {
                $builder->whereIn('id', $ids);
            } elseif (!empty($nameQuery) && !$semantic) {
                $builder->groupStart()
                        ->like('title', $nameQuery)
                        ->orLike('ad_title', $nameQuery)
                        ->groupEnd();
            }
            if (!empty($origin)) {
                $builder->where('origin', $origin);
            }
            if (!empty($country)) {
                $builder->like('country', strtoupper($country));
            }

            $products = $builder->orderBy('id', 'DESC')->limit($limit)->get()->getResultArray();
            return [
                'status'         => 'success',
                'total'          => count($products),
                'returned_count' => count($products),
                'products'       => $products
            ];
        }

        if ($this->action === 'get_product_full_json') {
            $productId  = $args['product_id'] ?? null;
            $titleQuery = $args['title'] ?? null;

            if (!$productId && !$titleQuery) {
                return ['error' => 'Must provide either product_id or title'];
            }

            $productRow = null;
            if ($productId) {
                $productRow = $db->table('products')->where('id', $productId)->get()->getRowArray();
            } else {
                $productRow = $db->table('products')->like('title', $titleQuery)->orderBy('id', 'DESC')->get()->getRowArray();
            }

            // Raw tRPC JSON search from snapshot
            $rawTrpcObject = null;
            $recentSnapshots = $db->table('data_snapshots')->orderBy('id', 'DESC')->limit(10)->get()->getResultArray();
            foreach ($recentSnapshots as $snap) {
                $entries = SnapshotTools::parseSnapshotEntries($snap['raw_json'] ?? '');
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

        if ($this->action === 'fetch_new_data') {
            $dateStr        = $args['date'] ?? null;
            $countryFilter  = isset($args['country']) ? strtoupper(trim($args['country'])) : 'ALL';
            $classification = isset($args['classification']) ? trim($args['classification']) : ($args['origin'] ?? 'all');
            $searchQuery    = isset($args['search_query']) ? strtolower(trim($args['search_query'])) : null;
            $sortBy         = $args['sort_by'] ?? 'date';
            $sortOrder      = strtoupper($args['sort_order'] ?? 'DESC');
            $limit          = intval($args['limit'] ?? 50);
            $offset         = intval($args['offset'] ?? 0);

            $isAllClassifications = empty($classification) || strtolower($classification) === 'all' || $classification === 'ككل';

            $builder = $db->table('products');
            $builder->groupStart()
                        ->where('is_saved', false)
                        ->orWhere('is_saved IS NULL')
                    ->groupEnd();

            if (!$isAllClassifications) {
                $builder->where('origin', $classification);
            }

            if ($countryFilter !== 'ALL' && !empty($countryFilter) && $countryFilter !== 'ككل') {
                $builder->like('country', $countryFilter);
            }

            if (!empty($dateStr) && $dateStr !== 'all' && $dateStr !== 'ككل') {
                $today = date('Y-m-d');
                if ($dateStr === 'today') {
                    $escapedToday = $db->escapeLikeString($today);
                    $builder->groupStart()
                            ->where('ad_start_date', $today)
                            ->orWhere("api_version LIKE '%{$escapedToday}%'")
                            ->groupEnd();
                } elseif ($dateStr === 'yesterday') {
                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                    $escapedYesterday = $db->escapeLikeString($yesterday);
                    $builder->groupStart()
                            ->where('ad_start_date', $yesterday)
                            ->orWhere("api_version LIKE '%{$escapedYesterday}%'")
                            ->groupEnd();
                } elseif ($dateStr === '7days') {
                    $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
                    $builder->groupStart()
                            ->where('ad_start_date >=', $sevenDaysAgo)
                            ->groupEnd();
                } elseif ($dateStr === '30days') {
                    $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
                    $builder->groupStart()
                            ->where('ad_start_date >=', $thirtyDaysAgo)
                            ->groupEnd();
                } else {
                    $escapedDate = $db->escapeLikeString($dateStr);
                    $builder->groupStart()
                            ->where('ad_start_date', $dateStr)
                            ->orWhere("api_version LIKE '%{$escapedDate}%'")
                            ->groupEnd();
                }
            }

            if (!empty($searchQuery)) {
                $builder->groupStart()
                        ->like('title', $searchQuery)
                        ->orLike('ad_title', $searchQuery)
                        ->orLike('ad_body', $searchQuery)
                        ->groupEnd();
            }

            if ($sortBy === 'ads_count') {
                $builder->orderBy('ads_count', $sortOrder);
            } elseif ($sortBy === 'title') {
                $builder->orderBy('title', $sortOrder);
            } elseif ($sortBy === 'price') {
                $builder->orderBy('CAST(price_1 AS NUMERIC)', $sortOrder);
            } else {
                $builder->orderBy('ad_start_date', $sortOrder)
                        ->orderBy('id', $sortOrder);
            }

            $totalMatching = $builder->countAllResults(false);
            $products = $builder->limit($limit, $offset)->get()->getResultArray();

            $forceLiveSync = !empty($args['force_live_sync']) || !empty($args['live_fetch']);

            // Fallback to data_snapshots
            if ($totalMatching === 0 && !$forceLiveSync && !empty($dateStr) && $dateStr !== 'all' && $dateStr !== 'ككل') {
                $escapedDate = $db->escapeLikeString($dateStr);
                $snapBuilder = $db->table('data_snapshots')
                                  ->groupStart()
                                      ->like('api_version', $dateStr)
                                      ->orWhere("CAST(created_at AS TEXT) LIKE '%{$escapedDate}%'")
                                  ->groupEnd();
                if (!$isAllClassifications) {
                    $snapBuilder->where('origin', $classification);
                }
                $snapshotRows = $snapBuilder->orderBy('id', 'DESC')->limit(10)->get()->getResultArray();
                $allEntries = [];
                foreach ($snapshotRows as $snapRow) {
                    $entries = SnapshotTools::parseSnapshotEntries($snapRow['raw_json'] ?? '');
                    foreach ($entries as $e) {
                        if ($countryFilter !== 'ALL' && !empty($countryFilter) && $countryFilter !== 'ككل') {
                            if (!empty($e['country'])) {
                                $cList = array_map('trim', explode(';', strtoupper($e['country'])));
                                if (!in_array($countryFilter, $cList, true)) continue;
                            }
                        }
                        if ($searchQuery) {
                            $title = strtolower($e['product_title'] ?? $e['title'] ?? '');
                            if (strpos($title, $searchQuery) === false) continue;
                        }
                        $allEntries[] = $e;
                    }
                }
                if (!empty($allEntries)) {
                    $totalMatching = count($allEntries);
                    $paginated = array_slice($allEntries, $offset, $limit);
                    return [
                        'status'          => 'success',
                        'total'           => $totalMatching,
                        'source'          => 'data_snapshots',
                        'filters_applied' => [
                            'date'           => $dateStr,
                            'country'        => $countryFilter,
                            'classification' => $isAllClassifications ? 'all (ككل)' : $classification,
                            'search_query'   => $searchQuery,
                        ],
                        'returned_count' => count($paginated),
                        'products'       => $paginated
                    ];
                }
            }

            // Fallback to Live Sync if needed
            if ($totalMatching === 0 || $forceLiveSync) {
                try {
                    $syncService = new \App\Services\SyncService();
                    $countryParam = ($countryFilter !== 'ALL' && $countryFilter !== 'ككل' && !empty($countryFilter))
                        ? $countryFilter
                        : "DZ;TN;MA;LY;EG;SA;QA;EA;OM;BH;KW;GB;IE;FR;BE;LU;CH;DE;AT;ES;IT;NL;PT;NG;CI;SN;KE";

                    $targetDateStr = date('Y-m-d');
                    if (!empty($dateStr) && $dateStr !== 'all' && $dateStr !== 'ككل') {
                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
                            $targetDateStr = $dateStr;
                        } elseif ($dateStr === 'yesterday') {
                            $targetDateStr = date('Y-m-d', strtotime('-1 day'));
                        }
                    }
                    $winningVersion = "1.10-1" . $targetDateStr;

                    $normClass = strtolower($classification);
                    if ($normClass === 'winning' || $isAllClassifications) {
                        $inputObj = [
                            "0" => [
                                "json" => [
                                    "category" => "Popular;Electronics;Home & Garden;Health & Beauty;Apparel & Accessories;Tools;Baby & Toddler",
                                    "country"  => $countryParam,
                                    "v"        => $winningVersion
                                ]
                            ]
                        ];
                        $trpcUrl = 'https://www.overviewdata.io/api/trpc/data.winingProducts?batch=1&input=' . urlencode(json_encode($inputObj, JSON_FORCE_OBJECT));
                        $syncService->fetchAndSaveTrpcUrl($trpcUrl);

                        if ($isAllClassifications) {
                            $inputInsights = [
                                "0" => [
                                    "json" => [
                                        "title"          => "",
                                        "category"       => "Popular;Electronics;Home & Garden;Health & Beauty;Apparel & Accessories;Tools;Baby & Toddler",
                                        "priceFrom"      => -1,
                                        "priceTo"        => -1,
                                        "weeks"          => 12,
                                        "country"        => $countryParam,
                                        "transformation" => "none",
                                        "v"              => "1.3--5"
                                    ]
                                ]
                            ];
                            $trpcInsightsUrl = 'https://www.overviewdata.io/api/trpc/data.insights?batch=1&input=' . urlencode(json_encode($inputInsights, JSON_FORCE_OBJECT));
                            $syncService->fetchAndSaveTrpcUrl($trpcInsightsUrl);
                        }
                    } elseif ($normClass === 'china') {
                        $inputObj = [
                            "0" => [
                                "json" => null,
                                "meta" => ["values" => ["undefined"]]
                            ]
                        ];
                        $trpcUrl = 'https://www.overviewdata.io/api/trpc/data.chinaProducts?batch=1&input=' . urlencode(json_encode($inputObj, JSON_FORCE_OBJECT));
                        $syncService->fetchAndSaveTrpcUrl($trpcUrl);
                    } elseif ($normClass === 'japan') {
                        $inputObj = [
                            "0" => [
                                "json" => null,
                                "meta" => ["values" => ["undefined"]]
                            ]
                        ];
                        $trpcUrl = 'https://www.overviewdata.io/api/trpc/data.japanProducts?batch=1&input=' . urlencode(json_encode($inputObj, JSON_FORCE_OBJECT));
                        $syncService->fetchAndSaveTrpcUrl($trpcUrl);
                    } else {
                        $inputObj = [
                            "0" => [
                                "json" => [
                                    "title"          => "",
                                    "category"       => "Popular;Electronics;Home & Garden;Health & Beauty;Apparel & Accessories;Tools;Baby & Toddler",
                                    "priceFrom"      => -1,
                                    "priceTo"        => -1,
                                    "weeks"          => 12,
                                    "country"        => $countryParam,
                                    "transformation" => "none",
                                    "v"              => "1.3--5"
                                ]
                            ]
                        ];
                        $trpcUrl = 'https://www.overviewdata.io/api/trpc/data.insights?batch=1&input=' . urlencode(json_encode($inputObj, JSON_FORCE_OBJECT));
                        $syncService->fetchAndSaveTrpcUrl($trpcUrl);
                    }

                    // Re-run DB Query
                    $builder = $db->table('products');
                    $builder->groupStart()
                                ->where('is_saved', false)
                                ->orWhere('is_saved IS NULL')
                            ->groupEnd();

                    if (!$isAllClassifications) {
                        $builder->where('origin', $classification);
                    }

                    if ($countryFilter !== 'ALL' && !empty($countryFilter) && $countryFilter !== 'ككل') {
                        $builder->like('country', $countryFilter);
                    }

                    if (!empty($dateStr) && $dateStr !== 'all' && $dateStr !== 'ككل') {
                        $today = date('Y-m-d');
                        if ($dateStr === 'today') {
                            $escapedToday = $db->escapeLikeString($today);
                            $builder->groupStart()
                                    ->where('ad_start_date', $today)
                                    ->orWhere("api_version LIKE '%{$escapedToday}%'")
                                    ->groupEnd();
                        } elseif ($dateStr === 'yesterday') {
                            $yesterday = date('Y-m-d', strtotime('-1 day'));
                            $escapedYesterday = $db->escapeLikeString($yesterday);
                            $builder->groupStart()
                                    ->where('ad_start_date', $yesterday)
                                    ->orWhere("api_version LIKE '%{$escapedYesterday}%'")
                                    ->groupEnd();
                        } elseif ($dateStr === '7days') {
                            $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
                            $builder->groupStart()
                                    ->where('ad_start_date >=', $sevenDaysAgo)
                                    ->groupEnd();
                        } elseif ($dateStr === '30days') {
                            $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
                            $builder->groupStart()
                                    ->where('ad_start_date >=', $thirtyDaysAgo)
                                    ->groupEnd();
                        } else {
                            $escapedDate = $db->escapeLikeString($dateStr);
                            $builder->groupStart()
                                    ->where('ad_start_date', $dateStr)
                                    ->orWhere("api_version LIKE '%{$escapedDate}%'")
                                    ->groupEnd();
                        }
                    }

                    if (!empty($searchQuery)) {
                        $builder->groupStart()
                                ->like('title', $searchQuery)
                                ->orLike('ad_title', $searchQuery)
                                ->orLike('ad_body', $searchQuery)
                                ->groupEnd();
                    }

                    if ($sortBy === 'ads_count') {
                        $builder->orderBy('ads_count', $sortOrder);
                    } elseif ($sortBy === 'title') {
                        $builder->orderBy('title', $sortOrder);
                    } elseif ($sortBy === 'price') {
                        $builder->orderBy('CAST(price_1 AS NUMERIC)', $sortOrder);
                    } else {
                        $builder->orderBy('ad_start_date', $sortOrder)
                                ->orderBy('id', $sortOrder);
                    }

                    $totalMatching = $builder->countAllResults(false);
                    $products = $builder->limit($limit, $offset)->get()->getResultArray();

                    if ($totalMatching === 0) {
                        $fallbackBuilder = $db->table('products');
                        $fallbackBuilder->groupStart()
                                            ->where('is_saved', false)
                                            ->orWhere('is_saved IS NULL')
                                        ->groupEnd();

                        if (!$isAllClassifications) {
                            $fallbackBuilder->where('origin', $classification);
                        }

                        if ($countryFilter !== 'ALL' && !empty($countryFilter) && $countryFilter !== 'ككل') {
                            $fallbackBuilder->like('country', $countryFilter);
                        }

                        if (!empty($searchQuery)) {
                            $fallbackBuilder->groupStart()
                                            ->like('title', $searchQuery)
                                            ->orLike('ad_title', $searchQuery)
                                            ->orLike('ad_body', $searchQuery)
                                            ->groupEnd();
                        }

                        if ($sortBy === 'ads_count') {
                            $fallbackBuilder->orderBy('ads_count', $sortOrder);
                        } elseif ($sortBy === 'title') {
                            $fallbackBuilder->orderBy('title', $sortOrder);
                        } elseif ($sortBy === 'price') {
                            $fallbackBuilder->orderBy('CAST(price_1 AS NUMERIC)', $sortOrder);
                        } else {
                            $fallbackBuilder->orderBy('ad_start_date', $sortOrder)
                                            ->orderBy('id', $sortOrder);
                        }

                        $totalMatching = $fallbackBuilder->countAllResults(false);
                        $products = $fallbackBuilder->limit($limit, $offset)->get()->getResultArray();
                    }
                } catch (\Throwable $e) {
                    log_message('error', 'MCP fetch_new_data SyncService error: ' . $e->getMessage());
                }
            }

            return [
                'status'          => 'success',
                'total'           => $totalMatching,
                'filters_applied' => [
                    'date'           => $dateStr ?? 'all',
                    'country'        => $countryFilter,
                    'classification' => $isAllClassifications ? 'all (ككل)' : $classification,
                    'search_query'   => $searchQuery,
                    'sort_by'        => $sortBy,
                    'sort_order'     => $sortOrder,
                ],
                'returned_count' => count($products),
                'products'       => $products
            ];
        }

        throw new \Exception("Unknown product filter action: {$this->action}");
    }
}

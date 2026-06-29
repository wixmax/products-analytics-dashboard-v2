<?php

namespace App\Controllers;

use App\Models\ProductModel;
use App\Models\SnapshotModel;
use CodeIgniter\RESTful\ResourceController;
use App\Services\SyncService;

class Products extends ResourceController
{
    protected $format = 'json';

    public function index()
    {
        $model = new ProductModel();
        
        $origin = $this->request->getVar('origin') ?? 'Winning';
        $search = $this->request->getVar('search');
        $country = $this->request->getVar('country');
        $status = $this->request->getVar('status');
        $dateFilter = $this->request->getVar('date');
        $sort = $this->request->getVar('sort') ?? 'ads-desc';
        $page = intval($this->request->getVar('page') ?? 1);
        $perPage = intval($this->request->getVar('per_page') ?? 12);

        $builder = $model->where('origin', $origin);

        // Search
        if (!empty($search)) {
            $builder->groupStart()
                    ->like('title', $search)
                    ->orLike('ad_body', $search)
                    ->orLike('ad_title', $search)
                    ->orLike('product_url', $search)
                    ->groupEnd();
        }

        // Country
        if (!empty($country) && $country !== 'all') {
            $builder->where('country', $country);
        }

        // API version filter
        $apiVersion = $this->request->getVar('api_version');
        if (!empty($apiVersion)) {
            $builder->where('api_version', $apiVersion);
        }

        // Status
        if (!empty($status) && $status !== 'all') {
            $builder->where('active_ads', $status === 'active');
        }

        // Date filter
        if (!empty($dateFilter) && $dateFilter !== 'all') {
            $today = date('Y-m-d');
            if ($dateFilter === 'today') {
                $builder->where('ad_start_date', $today);
            } elseif ($dateFilter === 'yesterday') {
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $builder->where('ad_start_date', $yesterday);
            } elseif ($dateFilter === '7days') {
                $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
                $builder->where('ad_start_date >=', $sevenDaysAgo);
            } elseif ($dateFilter === '30days') {
                $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
                $builder->where('ad_start_date >=', $thirtyDaysAgo);
            }
        }

        // Sorting
        switch ($sort) {
            case 'ads-desc':
                $builder->orderBy('ads_count', 'DESC');
                break;
            case 'ads-asc':
                $builder->orderBy('ads_count', 'ASC');
                break;
            case 'date-desc':
                $builder->orderBy('ad_start_date', 'DESC');
                break;
            case 'date-asc':
                $builder->orderBy('ad_start_date', 'ASC');
                break;
            case 'title-asc':
                $builder->orderBy('title', 'ASC');
                break;
            default:
                $builder->orderBy('ads_count', 'DESC');
                break;
        }

        // Pagination
        $total = $builder->countAllResults(false);
        $offset = ($page - 1) * $perPage;
        $products = $builder->limit($perPage, $offset)->get()->getResultArray();

        return $this->respond([
            'results' => $products,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ]);
    }

    public function stats()
    {
        $model = new ProductModel();
        $origin = $this->request->getVar('origin') ?? 'Winning';
        
        $totalProducts = $model->where('origin', $origin)->countAllResults();
        
        // Sum ads count
        $totalAdsResult = $model->where('origin', $origin)->selectSum('ads_count')->first();
        $totalAds = intval($totalAdsResult['ads_count'] ?? 0);
        
        // Video ads count
        $videoAds = $model->where('origin', $origin)
                          ->groupStart()
                            ->where('unique_video_count >', 0)
                            ->orWhere("ad_video_urls != ''")
                          ->groupEnd()
                          ->countAllResults();
                          
        // Avg creatives
        $avgCreativesResult = $model->where('origin', $origin)->selectAvg('avg_creatives')->first();
        $avgCreatives = round(floatval($avgCreativesResult['avg_creatives'] ?? 1), 1);

        return $this->respond([
            'total_products' => $totalProducts,
            'total_ads' => $totalAds,
            'video_ads' => $videoAds,
            'avg_creatives' => $avgCreatives
        ]);
    }

    public function insightsCharts()
    {
        $db = \Config\Database::connect();
        $origin = $this->request->getVar('origin') ?? 'Winning';

        // 1. Weekly new listings (last 12 weeks) based on created_at
        $weeklyData = [];
        for ($i = 11; $i >= 0; $i--) {
            $weekStart = date('Y-m-d', strtotime("-{$i} weeks Monday"));
            $weekEnd = date('Y-m-d', strtotime("-{$i} weeks Sunday"));
            
            $count = $db->table('products')
                ->where('origin', $origin)
                ->where('created_at >=', $weekStart)
                ->where('created_at <=', $weekEnd . ' 23:59:59')
                ->countAllResults();
            
            $weeklyData[] = $count;
        }

        // 2. Supply momentum: compare last 4 weeks average vs previous 4 weeks average
        $recent4 = array_sum(array_slice($weeklyData, -4));
        $previous4 = array_sum(array_slice($weeklyData, -8, 4));
        $hasSupplyMomentum = $recent4 > $previous4;

        // 3. Active stores: count unique domains from product_url

        $domains = [];
        $previousDomains = [];
        $fourWeeksAgo = date('Y-m-d', strtotime('-4 weeks'));

        $allProducts = $db->table('products')
            ->where('origin', $origin)
            ->select('product_url, created_at')
            ->get()
            ->getResultArray();

        foreach ($allProducts as $p) {
            $url = $p['product_url'] ?? '';
            if (empty($url)) continue;
            try {
                $host = parse_url($url, PHP_URL_HOST);
                if ($host) {
                    $host = preg_replace('/^www\./', '', $host);
                    $domains[$host] = true;
                    // Track stores from before 4 weeks for trend calculation
                    if (isset($p['created_at']) && $p['created_at'] < $fourWeeksAgo) {
                        $previousDomains[$host] = true;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        $currentShopCount = count($domains);
        $previousShopCount = max(count($previousDomains), 1); // Avoid division by zero

        // 4. Total products and ads stats
        $totalProducts = $db->table('products')->where('origin', $origin)->countAllResults();
        $activeAds = $db->table('products')
            ->where('origin', $origin)
            ->where('active_ads', true)
            ->countAllResults();

        return $this->respond([
            'newListings' => [
                'weeklyData' => $weeklyData,
                'hasSupplyMomentum' => $hasSupplyMomentum,
                'totalListings' => array_sum($weeklyData),
            ],
            'totalShops' => [
                'current' => $currentShopCount,
                'previous' => $previousShopCount,
            ],
            'summary' => [
                'totalProducts' => $totalProducts,
                'activeAds' => $activeAds,
            ]
        ]);
    }

    public function countries()
    {
        $model = new ProductModel();
        $origin = $this->request->getVar('origin') ?? 'Winning';
        $results = $model->where('origin', $origin)
                         ->select('country')
                         ->distinct()
                         ->where("country != ''")
                         ->findAll();
                         
        $countries = array_column($results, 'country');
        return $this->respond($countries);
    }

    public function versions()
    {
        $model = new ProductModel();
        $origin = $this->request->getVar('origin') ?? '';
        $builder = $model->select('api_version')
                         ->distinct()
                         ->where('api_version !=', '')
                         ->where('api_version IS NOT NULL');
        if (!empty($origin)) {
            $builder->where('origin', $origin);
        }
        $results = $builder->findAll();
        $versions = array_column($results, 'api_version');
        return $this->respond($versions);
    }

    public function snapshots()
    {
        $snapshotModel = new SnapshotModel();
        $origin = $this->request->getVar('origin') ?? '';

        $builder = $snapshotModel->orderBy('created_at', 'DESC');
        if (!empty($origin)) {
            $builder->where('origin', $origin);
        }
        $snapshots = $builder->findAll();

        // Remove raw_json from listing for performance, include only metadata
        $result = array_map(function ($s) {
            unset($s['raw_json']);
            return $s;
        }, $snapshots);

        return $this->respond($result);
    }

    public function getSnapshot($id = null)
    {
        if (!$id) {
            $id = $this->request->getVar('id');
        }
        if (!$id) {
            return $this->fail('Snapshot ID is required');
        }

        $snapshotModel = new SnapshotModel();
        $snapshot = $snapshotModel->find($id);
        if (!$snapshot) {
            return $this->failNotFound('Snapshot not found');
        }

        return $this->respond($snapshot);
    }

    public function restoreSnapshot($id = null)
    {
        if (!$id) {
            $id = $this->request->getVar('id');
        }
        if (!$id) {
            return $this->fail('Snapshot ID is required');
        }

        $snapshotModel = new SnapshotModel();
        $snapshot = $snapshotModel->find($id);
        if (!$snapshot) {
            return $this->failNotFound('Snapshot not found');
        }

        $rawJson = $snapshot['raw_json'];
        if (empty($rawJson)) {
            return $this->fail('Snapshot has no raw JSON data');
        }

        $data = json_decode($rawJson, true);
        if (empty($data)) {
            return $this->fail('Invalid JSON in snapshot');
        }

        // Re-import using the importJson logic
        $base = is_array($data) ? ($data[0] ?? null) : $data;
        $targetData = $base['result']['data']['json'] ?? null;
        if (!$targetData) {
            return $this->fail('Unrecognized snapshot JSON structure');
        }

        $rawList = $targetData['productsEntries'] ?? $targetData['results'] ?? [];
        if (!is_array($rawList)) {
            $rawList = is_array($targetData) ? $targetData : [];
        }

        $origin = $snapshot['origin'];
        $model = new ProductModel();
        $inserted = 0;
        $updated = 0;

        foreach ($rawList as $p) {
            $productUrl = $p['productUrl'] ?? $p['product_url'] ?? '';
            if (empty($productUrl)) continue;
            $title = $p['title'] ?? $p['product_title'] ?? 'بدون عنوان';

            $existing = $model->where('product_url', $productUrl)
                              ->where('origin', $origin)
                              ->first();

            $dataToSave = [
                'title' => $title,
                'product_url' => $productUrl,
                'country' => $p['country'] ?? '',
                'algo' => $p['algorithm'] ?? $p['algo'] ?? ($origin === 'Winning' ? 'winning' : 'new'),
                'ad_start_date' => $this->cleanDateStr($p['ad_start_date'] ?? null),
                'ads_count' => intval($p['ads_count'] ?? 0),
                'unique_image_count' => intval($p['unique_image_count'] ?? 0),
                'unique_video_count' => intval($p['unique_video_count'] ?? 0),
                'avg_creatives' => floatval($p['avg_creatives'] ?? 1),
                'ads_per_unique_url' => floatval($p['ads_per_unique_url'] ?? 1),
                'ad_title' => $p['ad_title'] ?? '',
                'ad_body' => $p['ad_body'] ?? '',
                'ad_image_urls' => is_array($p['ad_image_urls'] ?? null) ? implode(';', $p['ad_image_urls']) : ($p['ad_image_urls'] ?? ''),
                'ad_video_urls' => is_array($p['ad_video_urls'] ?? null) ? implode(';', $p['ad_video_urls']) : ($p['ad_video_urls'] ?? ''),
                'price_1' => strval($p['price_1'] ?? $p['actualPrice'] ?? $p['price'] ?? '0'),
                'active_ads' => isset($p['active_ads']) ? (bool)$p['active_ads'] : true,
                'origin' => $origin,
                'api_version' => $snapshot['api_version'],
                'snapshot_id' => intval($id),
            ];

            if ($origin === 'Winning') {
                $dataToSave['badge_algorithm'] = $p['badge_algorithm'] ?? 'winning';
            }

            if ($existing) {
                $model->update($existing['id'], $dataToSave);
                $updated++;
            } else {
                $model->insert($dataToSave);
                $inserted++;
            }
        }

        return $this->respond([
            'success' => true,
            'message' => "Snapshot #{$id} restored: {$inserted} inserted, {$updated} updated",
            'inserted' => $inserted,
            'updated' => $updated
        ]);
    }

    public function sync()
    {
        $syncService = new SyncService();
        $stats = $syncService->run();
        
        return $this->respond([
            'success' => true,
            'message' => 'Data synced successfully',
            'stats' => $stats
        ]);
    }

    public function syncTrpc()
    {
        $url = $this->request->getVar('url');
        if (empty($url)) {
            return $this->fail('URL is required');
        }

        // Determine origin from URL
        $origin = 'Local';
        if (strpos($url, 'winingProducts') !== false) {
            $origin = 'Winning';
        }

        $settingModel = new \App\Models\SettingModel();
        $dataSourceSetting = $settingModel->find('data-source');
        $dataSource = $dataSourceSetting ? $dataSourceSetting['value'] : 'database';

        $model = new ProductModel();
        
        // Check if we have products of this origin in the database
        if ($dataSource === 'database') {
            $count = $model->where('origin', $origin)->countAllResults();
            if ($count > 0) {
            // Fetch from database instead of calling the external API
            $products = $model->where('origin', $origin)
                              ->orderBy('ads_count', 'DESC')
                              ->findAll();

            $formatted = [
                'result' => [
                    'data' => [
                        'json' => [
                            'productsEntries' => array_map(function($p) {
                                return [
                                    'title' => $p['title'],
                                    'productUrl' => $p['product_url'],
                                    'country' => $p['country'],
                                    'algorithm' => $p['algo'],
                                    'ad_start_date' => $p['ad_start_date'] ?: '--',
                                    'ads_count' => intval($p['ads_count']),
                                    'avg_creatives' => floatval($p['avg_creatives']),
                                    'ad_title' => $p['ad_title'],
                                    'ad_body' => $p['ad_body'],
                                    'ad_image_urls' => $p['ad_image_urls'],
                                    'ad_video_urls' => $p['ad_video_urls'],
                                    'actualPrice' => floatval($p['price_1']),
                                    'active_ads' => (bool)$p['active_ads'],
                                ];
                            }, $products)
                        ]
                    ]
                ]
            ];

            // Cache adaptedResult if present
            $cachePath = WRITEPATH . 'cache/adapted_result.json';
            if (file_exists($cachePath)) {
                $formatted['result']['data']['json']['adaptedResult'] = json_decode(file_get_contents($cachePath), true);
            }

            // Return database data in trpc format wrapped in a batch array with source info
            $formatted['source'] = 'database';
            return $this->respond([$formatted]);
        }
    }

        // If no products in DB or data-source is 'api', fetch from API
        $syncService = new SyncService();
        $data = $syncService->fetchAndSaveTrpcUrl($url);
        
        if ($data === null) {
            return $this->fail('Failed to fetch or parse tRPC data');
        }

        // Add source indicator to distinguish from database responses
        if (is_array($data) && isset($data[0])) {
            $data[0]['source'] = 'api';
        }

        return $this->respond($data);
    }

    public function importJson()
    {
        $rawData = $this->request->getJSON(true);
        if (empty($rawData)) {
            $rawData = $this->request->getVar('data');
            if (empty($rawData)) {
                return $this->fail('No JSON data provided');
            }
            $rawData = json_decode($rawData, true);
        }

        if (empty($rawData)) {
            return $this->fail('Invalid JSON structure');
        }

        $base = is_array($rawData) ? ($rawData[0] ?? null) : $rawData;
        $targetData = $base['result']['data']['json'] ?? null;
        if (!$targetData) {
            $targetData = $base['data']['json'] ?? $base['json'] ?? $base;
        }

        $rawList = $targetData['productsEntries'] ?? $targetData['results'] ?? [];
        if (!is_array($rawList)) {
            $rawList = is_array($targetData) ? $targetData : [];
        }

        $origin = $this->request->getVar('origin') ?? 'Local';
        $model = new ProductModel();
        $inserted = 0;
        $updated = 0;

        foreach ($rawList as $p) {
            $productUrl = $p['productUrl'] ?? $p['product_url'] ?? '';
            if (empty($productUrl)) continue;
            $title = $p['title'] ?? $p['product_title'] ?? 'بدون عنوان';

            $existing = $model->where('product_url', $productUrl)
                              ->where('origin', $origin)
                              ->first();

            $dataToSave = [
                'title' => $title,
                'product_url' => $productUrl,
                'country' => $p['country'] ?? '',
                'algo' => $p['algorithm'] ?? $p['algo'] ?? ($origin === 'Winning' ? 'winning' : 'new'),
                'ad_start_date' => $this->cleanDateStr($p['ad_start_date'] ?? null),
                'ads_count' => intval($p['ads_count'] ?? 0),
                'unique_image_count' => intval($p['unique_image_count'] ?? 0),
                'unique_video_count' => intval($p['unique_video_count'] ?? 0),
                'avg_creatives' => floatval($p['avg_creatives'] ?? 1),
                'ads_per_unique_url' => floatval($p['ads_per_unique_url'] ?? 1),
                'ad_title' => $p['ad_title'] ?? '',
                'ad_body' => $p['ad_body'] ?? '',
                'ad_image_urls' => is_array($p['ad_image_urls'] ?? null) ? implode(';', $p['ad_image_urls']) : ($p['ad_image_urls'] ?? ''),
                'ad_video_urls' => is_array($p['ad_video_urls'] ?? null) ? implode(';', $p['ad_video_urls']) : ($p['ad_video_urls'] ?? ''),
                'price_1' => strval($p['price_1'] ?? $p['actualPrice'] ?? $p['price'] ?? '0'),
                'active_ads' => isset($p['active_ads']) ? (bool)$p['active_ads'] : true,
                'origin' => $origin
            ];

            if ($origin === 'Winning') {
                $dataToSave['badge_algorithm'] = $p['badge_algorithm'] ?? 'winning';
            }

            if ($existing) {
                $model->update($existing['id'], $dataToSave);
                $updated++;
            } else {
                $model->insert($dataToSave);
                $inserted++;
            }
        }

        // Cache adaptedResult if present
        if (isset($targetData['adaptedResult'])) {
            $cachePath = WRITEPATH . 'cache/adapted_result.json';
            if (!is_dir(dirname($cachePath))) {
                mkdir(dirname($cachePath), 0777, true);
            }
            file_put_contents($cachePath, json_encode($targetData['adaptedResult']));
        }

        return $this->respond([
            'success' => true,
            'inserted' => $inserted,
            'updated' => $updated
        ]);
    }

    public function saved()
    {
        $model = new ProductModel();
        
        $search = $this->request->getVar('search');
        $status = $this->request->getVar('status');
        $collection = $this->request->getVar('collection');
        $sort = $this->request->getVar('sort') ?? 'newest';

        $builder = $model->where('is_saved', true);

        // Search
        if (!empty($search)) {
            $builder->groupStart()
                    ->like('title', $search)
                    ->orLike('ad_body', $search)
                    ->orLike('ad_title', $search)
                    ->groupEnd();
        }

        // Status
        if (!empty($status) && $status !== 'all') {
            $builder->where('saved_status', $status);
        }

        // Collection
        if (!empty($collection) && $collection !== 'all') {
            $builder->where('collection', $collection);
        }

        // Sorting
        switch ($sort) {
            case 'newest':
                $builder->orderBy('saved_at', 'DESC');
                break;
            case 'oldest':
                $builder->orderBy('saved_at', 'ASC');
                break;
            case 'rating-desc':
                $builder->orderBy('rating', 'DESC');
                break;
            case 'rating-asc':
                $builder->orderBy('rating', 'ASC');
                break;
            default:
                $builder->orderBy('saved_at', 'DESC');
                break;
        }

        $savedProducts = $builder->findAll();

        return $this->respond($savedProducts);
    }

    public function toggleSave()
    {
        $model = new ProductModel();
        
        // Accept payload
        $productUrl = $this->request->getVar('product_url');
        if (empty($productUrl)) {
            $json = $this->request->getJSON(true);
            $productUrl = $json['product_url'] ?? $json['productUrl'] ?? null;
            $product = $json;
        } else {
            $product = $this->request->getPost();
        }

        if (empty($productUrl)) {
            return $this->fail('Product URL is required');
        }

        // Try to find existing product by product_url
        $existing = $model->where('product_url', $productUrl)->first();

        if ($existing) {
            // PostgreSQL returns boolean as 't'/'f' strings, PHP needs explicit check
            $currentlySaved = filter_var($existing['is_saved'], FILTER_VALIDATE_BOOLEAN);
            $newSavedState = !$currentlySaved;
            $model->update($existing['id'], [
                'is_saved' => $newSavedState,
                'saved_at' => $newSavedState ? date('Y-m-d H:i:s') : null,
                'collection' => $newSavedState ? ($existing['collection'] ?: 'عامة') : $existing['collection'],
            ]);
            return $this->respond([
                'success' => true,
                'is_saved' => $newSavedState,
                'action' => $newSavedState ? 'saved' : 'unsaved',
                'message' => $newSavedState ? 'تم حفظ المنتج بنجاح! ⭐' : 'تمت إزالة المنتج من المحفوظات.',
            ]);
        } else {
            // If the product doesn't exist, we insert it!
            $origin = $product['origin'] ?? 'Winning';
            $dataToInsert = [
                'title' => $product['title'] ?? 'بدون عنوان',
                'product_url' => $productUrl,
                'country' => $product['country'] ?? '',
                'algo' => $product['algorithm'] ?? $product['algo'] ?? ($origin === 'Winning' ? 'winning' : 'new'),
                'ad_start_date' => $this->cleanDateStr($product['ad_start_date'] ?? null),
                'ads_count' => intval($product['ads_count'] ?? 0),
                'unique_image_count' => intval($product['unique_image_count'] ?? 0),
                'unique_video_count' => intval($product['unique_video_count'] ?? 0),
                'avg_creatives' => floatval($product['avg_creatives'] ?? 1),
                'ads_per_unique_url' => floatval($product['ads_per_unique_url'] ?? 1),
                'ad_title' => $product['ad_title'] ?? '',
                'ad_body' => $product['ad_body'] ?? '',
                'ad_image_urls' => is_array($product['ad_image_urls'] ?? null) ? implode(';', $product['ad_image_urls']) : ($product['ad_image_urls'] ?? ''),
                'ad_video_urls' => is_array($product['ad_video_urls'] ?? null) ? implode(';', $product['ad_video_urls']) : ($product['ad_video_urls'] ?? ''),
                'price_1' => strval($product['price_1'] ?? $product['actualPrice'] ?? $product['price'] ?? '0'),
                'active_ads' => isset($product['active_ads']) ? (bool)$product['active_ads'] : true,
                'origin' => $origin,
                'is_saved' => true,
                'saved_at' => date('Y-m-d H:i:s'),
                'collection' => $product['collection'] ?? 'عامة',
                'saved_status' => 'active',
                'rating' => 0,
                'notes' => ''
            ];

            if ($origin === 'Winning') {
                $dataToInsert['badge_algorithm'] = $product['badge_algorithm'] ?? 'winning';
            }
            // Insert new product into the database
            $model->insert($dataToInsert);

            return $this->respond([
                'success' => true,
                'is_saved' => true,
                'action' => 'saved',
                'message' => 'تم حفظ المنتج بنجاح! ⭐'
            ]);
        }
    }

    public function updateRating()
    {
        $model = new ProductModel();
        $productUrl = $this->request->getVar('product_url');
        $rating = intval($this->request->getVar('rating'));

        if (empty($productUrl)) {
            $json = $this->request->getJSON(true);
            $productUrl = $json['product_url'] ?? null;
            $rating = intval($json['rating'] ?? 0);
        }

        $existing = $model->where('product_url', $productUrl)->first();
        if (!$existing) {
            return $this->failNotFound('Product not found');
        }

        $model->update($existing['id'], ['rating' => $rating]);
        return $this->respond(['success' => true]);
    }

    public function updateNotes()
    {
        $model = new ProductModel();
        $productUrl = $this->request->getVar('product_url');
        $notes = $this->request->getVar('notes');

        if (empty($productUrl)) {
            $json = $this->request->getJSON(true);
            $productUrl = $json['product_url'] ?? null;
            $notes = $json['notes'] ?? '';
        }

        $existing = $model->where('product_url', $productUrl)->first();
        if (!$existing) {
            return $this->failNotFound('Product not found');
        }

        $model->update($existing['id'], ['notes' => $notes]);
        return $this->respond(['success' => true]);
    }

    public function updateStatus()
    {
        $model = new ProductModel();
        $productUrl = $this->request->getVar('product_url');
        $status = $this->request->getVar('status');

        if (empty($productUrl)) {
            $json = $this->request->getJSON(true);
            $productUrl = $json['product_url'] ?? null;
            $status = $json['status'] ?? 'active';
        }

        $existing = $model->where('product_url', $productUrl)->first();
        if (!$existing) {
            return $this->failNotFound('Product not found');
        }

        $model->update($existing['id'], ['saved_status' => $status]);
        return $this->respond(['success' => true]);
    }

    public function updateCollection()
    {
        $model = new ProductModel();
        $productUrl = $this->request->getVar('product_url');
        $collection = $this->request->getVar('collection');

        if (empty($productUrl)) {
            $json = $this->request->getJSON(true);
            $productUrl = $json['product_url'] ?? null;
            $collection = $json['collection'] ?? 'عامة';
        }

        $existing = $model->where('product_url', $productUrl)->first();
        if (!$existing) {
            return $this->failNotFound('Product not found');
        }

        $model->update($existing['id'], ['collection' => $collection]);
        return $this->respond(['success' => true]);
    }

    public function clearSaved()
    {
        $model = new ProductModel();
        $model->where('is_saved', true)->set([
            'is_saved' => false,
            'saved_at' => null,
            'rating' => 0,
            'notes' => '',
            'saved_status' => 'active',
            'collection' => 'عامة'
        ])->update();
        return $this->respond(['success' => true]);
    }

    public function collections()
    {
        $model = new \App\Models\CollectionModel();
        $collections = $model->orderBy('id', 'ASC')->findAll();
        return $this->respond(array_column($collections, 'name'));
    }

    public function addCollection()
    {
        $model = new \App\Models\CollectionModel();
        $name = $this->request->getVar('name');

        if (empty($name)) {
            $json = $this->request->getJSON(true);
            $name = $json['name'] ?? null;
        }

        if (empty($name)) {
            return $this->fail('Collection name is required');
        }

        $existing = $model->where('name', $name)->first();
        if ($existing) {
            return $this->respond(['success' => true, 'message' => 'Collection already exists']);
        }

        $model->insert(['name' => $name]);
        return $this->respond(['success' => true]);
    }

    public function deleteCollection()
    {
        $model = new \App\Models\CollectionModel();
        $name = $this->request->getVar('name');

        if (empty($name)) {
            $json = $this->request->getJSON(true);
            $name = $json['name'] ?? null;
        }

        if (empty($name)) {
            return $this->fail('Collection name is required');
        }

        if ($name === 'عامة') {
            return $this->fail('Cannot delete default collection');
        }

        $existing = $model->where('name', $name)->first();
        if (!$existing) {
            return $this->failNotFound('Collection not found');
        }

        $model->delete($existing['id']);

        // Update products under this collection to 'عامة'
        $productModel = new ProductModel();
        $productModel->where('collection', $name)->set(['collection' => 'عامة'])->update();

        return $this->respond(['success' => true]);
    }

    public function watchlist()
    {
        $model = new \App\Models\WatchedStoreModel();
        $stores = $model->findAll();
        return $this->respond(array_column($stores, 'domain'));
    }

    public function toggleWatchlist()
    {
        $model = new \App\Models\WatchedStoreModel();
        $domain = $this->request->getVar('domain');

        if (empty($domain)) {
            $json = $this->request->getJSON(true);
            $domain = $json['domain'] ?? null;
        }

        if (empty($domain)) {
            return $this->fail('Domain is required');
        }

        $existing = $model->where('domain', $domain)->first();
        if ($existing) {
            $model->delete($existing['id']);
            return $this->respond([
                'success' => true,
                'is_watched' => false,
                'action' => 'removed'
            ]);
        } else {
            $model->insert(['domain' => $domain]);
            return $this->respond([
                'success' => true,
                'is_watched' => true,
                'action' => 'added'
            ]);
        }
    }

    public function getSetting($key)
    {
        $model = new \App\Models\SettingModel();
        $setting = $model->find($key);
        return $this->respond($setting ?: ['key' => $key, 'value' => null]);
    }

    public function saveSetting()
    {
        $model = new \App\Models\SettingModel();
        $key = $this->request->getVar('key');
        $value = $this->request->getVar('value');

        if (empty($key)) {
            $json = $this->request->getJSON(true);
            $key = $json['key'] ?? null;
            $value = $json['value'] ?? null;
        }

        if (empty($key)) {
            return $this->fail('Key is required');
        }

        $existing = $model->find($key);
        if ($existing) {
            $model->update($key, [
                'value' => $value,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } else {
            $model->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        return $this->respond(['success' => true]);
    }

    public function clearDatabaseData()
    {
        $type = $this->request->getVar('type');
        if (empty($type)) {
            $json = $this->request->getJSON(true);
            $type = $json['type'] ?? '';
        }

        $db = \Config\Database::connect();

        switch ($type) {
            case 'fetched':
                // Delete all products that are NOT bookmarked/saved
                $db->table('products')->where('is_saved', false)->delete();
                break;
            case 'saved':
                // Reset all saved products
                $db->table('products')->where('is_saved', true)->update([
                    'is_saved' => false,
                    'saved_at' => null,
                    'rating' => 0,
                    'notes' => '',
                    'saved_status' => 'active',
                    'collection' => 'عامة'
                ]);
                break;
            case 'collections':
                // Clear custom collections and reset products' collections
                $db->table('collections')->delete();
                $db->table('products')->update(['collection' => 'عامة']);
                break;
            case 'watchlist':
                // Clear watched stores
                $db->table('watched_stores')->delete();
                break;
            case 'all':
                // Delete all products, collections, watched stores
                $db->table('products')->delete();
                $db->table('collections')->delete();
                $db->table('watched_stores')->delete();
                // Reset settings to default
                $db->table('settings')->where('key', 'app-theme')->update(['value' => 'light']);
                $db->table('settings')->where('key', 'data-source')->update(['value' => 'database']);
                break;
            default:
                return $this->fail('Invalid clear type: ' . $type);
        }

        return $this->respond(['success' => true]);
    }

    private function cleanDateStr($dateStr)
    {
        if (empty($dateStr) || $dateStr === '--') {
            return null;
        }
        $timestamp = strtotime($dateStr);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }
}

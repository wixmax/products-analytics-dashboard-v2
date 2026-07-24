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

        // Exclude tenant-saved copies from the main list so we only query master synced/imported rows
        $builder->groupStart()
                    ->where('is_saved', false)
                    ->orWhere('tenant_id IS NULL')
                ->groupEnd();

        // Search
        if (!empty($search)) {
            $builder->groupStart()
                    ->like('title', $search)
                    ->orLike('ad_body', $search)
                    ->orLike('ad_title', $search)
                    ->orLike('product_url', $search)
                    ->groupEnd();
        }

        // Country (semicolon-separated for multiple selection)
        if (!empty($country) && $country !== 'all') {
            $countries = explode(';', $country);
            if (count($countries) > 1) {
                $builder->whereIn('country', $countries);
            } else {
                $builder->where('country', $country);
            }
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

        $context = \App\Libraries\TenantContext::getInstance();
        $tenantId = $context->getTenantId();
        
        $savedUrls = [];
        $savedRatings = [];
        $savedNotes = [];
        $savedPrices = [];
        $savedStatuses = [];
        $savedCollections = [];

        if ($tenantId !== null) {
            $savedRows = $model->where('tenant_id', $tenantId)
                               ->where('is_saved', true)
                               ->findAll();
            foreach ($savedRows as $row) {
                $savedUrls[] = $row['product_url'];
                $savedRatings[$row['product_url']] = intval($row['rating']);
                $savedNotes[$row['product_url']] = $row['notes'];
                $savedPrices[$row['product_url']] = $row['price_1'];
                $savedStatuses[$row['product_url']] = $row['saved_status'];
                $savedCollections[$row['product_url']] = $row['collection'];
            }
        }

        foreach ($products as &$p) {
            $url = $p['product_url'];
            $isSaved = in_array($url, $savedUrls, true);
            $p['is_saved'] = $isSaved;
            if ($isSaved) {
                $p['rating'] = $savedRatings[$url] ?? 0;
                $p['notes'] = $savedNotes[$url] ?? '';
                $p['price_1'] = $savedPrices[$url] ?? $p['price_1'];
                $p['saved_status'] = $savedStatuses[$url] ?? 'active';
                $p['collection'] = $savedCollections[$url] ?? 'عامة';
            } else {
                $p['rating'] = 0;
                $p['notes'] = '';
                $p['saved_status'] = 'active';
                $p['collection'] = 'عامة';
            }
            $p['actualPrice'] = $p['price_1'];
        }

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
        $snapshotId = $this->request->getVar('snapshot_id');

        // Fetch analytics scope setting ('snapshot' or 'global')
        $settingModel = new \App\Models\SettingModel();
        $scopeSetting = $settingModel->where('key', 'analytics-scope')->first();
        $scope = $scopeSetting['value'] ?? 'snapshot'; // Default to snapshot-scoped

        // Closure to produce filtered query builder according to scope setting and snapshot_id
        $getBaseQuery = function() use ($db, $origin, $snapshotId, $scope) {
            $builder = $db->table('products')->where('origin', $origin);
            if ($scope === 'snapshot') {
                if (!empty($snapshotId)) {
                    $builder->where('snapshot_id', $snapshotId);
                } else {
                    // Fallback to latest snapshot_id for this origin if available
                    $latest = $db->table('data_snapshots')
                        ->where('origin', $origin)
                        ->orderBy('id', 'DESC')
                        ->limit(1)
                        ->get()
                        ->getRowArray();
                    if ($latest && !empty($latest['id'])) {
                        $builder->where('snapshot_id', $latest['id']);
                    }
                }
            }
            return $builder;
        };

        // 1. Weekly new listings (last 12 weeks)
        $weeklyData = [];
        for ($i = 11; $i >= 0; $i--) {
            $dt = new \DateTime();
            $dt->modify("-{$i} weeks");
            $dtStart = clone $dt;
            $dtStart->modify('monday this week');
            $dtEnd = clone $dt;
            $dtEnd->modify('sunday this week');

            $weekStart = $dtStart->format('Y-m-d');
            $weekEnd = $dtEnd->format('Y-m-d');

            $countAdStart = $getBaseQuery()
                ->where('ad_start_date >=', $weekStart)
                ->where('ad_start_date <=', $weekEnd)
                ->countAllResults();

            $countCreatedAt = $getBaseQuery()
                ->where('created_at >=', $weekStart)
                ->where('created_at <=', $weekEnd . ' 23:59:59')
                ->countAllResults();

            $weeklyData[] = max($countAdStart, $countCreatedAt);
        }

        // 2. Supply momentum: compare last 4 weeks average vs previous 4 weeks average
        $recent4 = array_sum(array_slice($weeklyData, -4));
        $previous4 = array_sum(array_slice($weeklyData, -8, 4));
        $hasSupplyMomentum = $recent4 > $previous4;

        // 3. Active stores: count unique domains from product_url
        $domains = [];
        $previousDomains = [];
        $fourWeeksAgo = date('Y-m-d', strtotime('-4 weeks'));

        $allProducts = $getBaseQuery()
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
                    if (isset($p['created_at']) && $p['created_at'] < $fourWeeksAgo) {
                        $previousDomains[$host] = true;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        $currentShopCount = count($domains);
        $previousShopCount = max(count($previousDomains), 1);

        // 4. Total products and ads stats
        $totalProducts = $getBaseQuery()->countAllResults();
        $activeAds = $getBaseQuery()
            ->where('active_ads', true)
            ->countAllResults();

        return $this->respond([
            'scope' => $scope,
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
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            return $this->failForbidden('عذراً، الوصول إلى لقطات البيانات مخصص للمشرفين والمسؤولين فقط.');
        }

        $snapshotModel = new SnapshotModel();
        $origin = $this->request->getVar('origin') ?? '';
        $includeRaw = $this->request->getVar('include_raw') === '1';

        $builder = $snapshotModel->orderBy('created_at', 'DESC');
        if (!empty($origin)) {
            $builder->where('origin', $origin);
        }
        $snapshots = $builder->findAll();

        if (!$includeRaw) {
            // Remove raw_json from listing for performance, include only metadata
            $result = array_map(function ($s) {
                unset($s['raw_json']);
                return $s;
            }, $snapshots);
        } else {
            $result = $snapshots;
        }

        return $this->respond($result);
    }

    public function getSnapshot($id = null)
    {
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            return $this->failForbidden('عذراً، الوصول إلى لقطات البيانات مخصص للمشرفين والمسؤولين فقط.');
        }

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
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            return $this->failForbidden('عذراً، استعادة لقطات البيانات مخصصة للمشرفين والمسؤولين فقط.');
        }

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
        $rawList = [];
        if (is_array($data)) {
            $isAssoc = false;
            if (count($data) > 0) {
                $keys = array_keys($data);
                $isAssoc = (array_keys($keys) !== $keys);
            }

            if ($isAssoc) {
                $targetData = $data['result']['data']['json'] ?? $data['data']['json'] ?? $data['json'] ?? $data;
                $rawList = $targetData['productsEntries'] ?? $targetData['results'] ?? [];
                if (!is_array($rawList)) {
                    $rawList = is_array($targetData) ? $targetData : [$data];
                }
            } else {
                if (count($data) > 0) {
                    $first = $data[0];
                    if (is_array($first) && (isset($first['productUrl']) || isset($first['product_url']) || isset($first['title']) || isset($first['product_title']))) {
                        $rawList = $data;
                    } else if (is_array($first) && (isset($first['result']) || isset($first['data']) || isset($first['json']))) {
                        $targetData = $first['result']['data']['json'] ?? $first['data']['json'] ?? $first['json'] ?? $first;
                        $rawList = $targetData['productsEntries'] ?? $targetData['results'] ?? [];
                        if (!is_array($rawList)) {
                            $rawList = is_array($targetData) ? $targetData : [];
                        }
                    } else {
                        $rawList = $data;
                    }
                }
            }
        }

        if (empty($rawList)) {
            return $this->fail('Unrecognized snapshot JSON structure or empty snapshot');
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

    public function importSnapshot()
    {
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            return $this->failForbidden('عذراً، استيراد لقطات البيانات مخصص للمشرفين والمسؤولين فقط.');
        }

        $json = $this->request->getJSON(true);
        if (empty($json)) {
            return $this->fail('Invalid or empty JSON payload');
        }

        $snapshotModel = new SnapshotModel();

        // 1. Bulk import: check if payload is a list of snapshots
        if (is_array($json) && isset($json[0]) && is_array($json[0]) && (isset($json[0]['raw_json']) || isset($json[0]['origin']))) {
            $importedCount = 0;
            foreach ($json as $item) {
                $rawJson = $item['raw_json'] ?? '';
                if (empty($rawJson)) continue;

                $origin = $item['origin'] ?? 'Local';
                $apiVersion = $item['api_version'] ?? '';

                // Try to determine product count from structure
                $decoded = json_decode($rawJson, true);
                $productCount = 0;
                if (is_array($decoded)) {
                    $rawList = [];
                    $isAssoc = false;
                    if (count($decoded) > 0) {
                        $keys = array_keys($decoded);
                        $isAssoc = (array_keys($keys) !== $keys);
                    }

                    if ($isAssoc) {
                        $targetData = $decoded['result']['data']['json'] ?? $decoded['data']['json'] ?? $decoded['json'] ?? $decoded;
                        $rawList = $targetData['productsEntries'] ?? $targetData['results'] ?? [];
                        if (!is_array($rawList)) {
                            $rawList = is_array($targetData) ? $targetData : [$decoded];
                        }
                    } else {
                        if (count($decoded) > 0) {
                            $first = $decoded[0];
                            if (is_array($first) && (isset($first['productUrl']) || isset($first['product_url']) || isset($first['title']) || isset($first['product_title']))) {
                                $rawList = $decoded;
                            } else if (is_array($first) && (isset($first['result']) || isset($first['data']) || isset($first['json']))) {
                                $targetData = $first['result']['data']['json'] ?? $first['data']['json'] ?? $first['json'] ?? $first;
                                $rawList = $targetData['productsEntries'] ?? $targetData['results'] ?? [];
                                if (!is_array($rawList)) {
                                    $rawList = is_array($targetData) ? $targetData : [];
                                }
                            } else {
                                $rawList = $decoded;
                            }
                        }
                    }
                    $productCount = count($rawList);
                }

                $dataToSave = [
                    'origin' => $origin,
                    'api_version' => $apiVersion,
                    'raw_json' => $rawJson,
                    'product_count' => $productCount,
                ];

                $snapshotModel->insert($dataToSave);
                $importedCount++;
            }

            return $this->respond([
                'success' => true,
                'message' => "تم استيراد عدد {$importedCount} من لقطات البيانات بنجاح",
                'bulk' => true
            ]);
        }

        // 2. Single snapshot import (fallback/original logic)
        $rawJson = $json['raw_json'] ?? $this->request->getVar('raw_json') ?? '';
        if (empty($rawJson)) {
            return $this->fail('raw_json is required');
        }

        $origin = $json['origin'] ?? $this->request->getVar('origin') ?? 'Local';
        $apiVersion = $json['api_version'] ?? $this->request->getVar('api_version') ?? '';

        // Validate JSON
        $decoded = json_decode($rawJson, true);
        if ($decoded === null) {
            return $this->fail('Invalid JSON');
        }

        $productCount = 0;
        $rawList = [];
        if (is_array($decoded)) {
            $isAssoc = false;
            if (count($decoded) > 0) {
                $keys = array_keys($decoded);
                $isAssoc = (array_keys($keys) !== $keys);
            }

            if ($isAssoc) {
                $targetData = $decoded['result']['data']['json'] ?? $decoded['data']['json'] ?? $decoded['json'] ?? $decoded;
                $rawList = $targetData['productsEntries'] ?? $targetData['results'] ?? [];
                if (!is_array($rawList)) {
                    $rawList = is_array($targetData) ? $targetData : [$decoded];
                }
            } else {
                if (count($decoded) > 0) {
                    $first = $decoded[0];
                    if (is_array($first) && (isset($first['productUrl']) || isset($first['product_url']) || isset($first['title']) || isset($first['product_title']))) {
                        $rawList = $decoded;
                    } else if (is_array($first) && (isset($first['result']) || isset($first['data']) || isset($first['json']))) {
                        $targetData = $first['result']['data']['json'] ?? $first['data']['json'] ?? $first['json'] ?? $first;
                        $rawList = $targetData['productsEntries'] ?? $targetData['results'] ?? [];
                        if (!is_array($rawList)) {
                            $rawList = is_array($targetData) ? $targetData : [];
                        }
                    } else {
                        $rawList = $decoded;
                    }
                }
            }
        }
        $productCount = count($rawList);

        $dataToSave = [
            'origin' => $origin,
            'api_version' => $apiVersion,
            'raw_json' => $rawJson,
            'product_count' => $productCount,
        ];

        $snapshotModel->insert($dataToSave);

        return $this->respond([
            'success' => true,
            'message' => "Snapshot imported: {$productCount} products",
            'id' => $snapshotModel->getInsertID()
        ]);
    }

    public function importSavedAds()
    {
        $json = $this->request->getJSON(true);
        $rawJson = $json['raw_json'] ?? $this->request->getVar('raw_json') ?? '';
        if (empty($rawJson)) {
            return $this->fail('raw_json is required');
        }

        $decoded = json_decode($rawJson, true);
        if ($decoded === null) {
            return $this->fail('Invalid JSON');
        }

        // Expecting an array of product objects (like saved products export)
        $isAssoc = false;
        if (is_array($decoded) && count($decoded) > 0) {
            $keys = array_keys($decoded);
            $isAssoc = (array_keys($keys) !== $keys);
        }

        $products = [];
        if (is_array($decoded)) {
            if ($isAssoc) {
                $products = [$decoded];
            } else {
                $products = $decoded;
            }
        } else if ($decoded !== null) {
            $products = [$decoded];
        }
        $model = new ProductModel();
        $inserted = 0;
        $updated = 0;

        $context = \App\Libraries\TenantContext::getInstance();
        $tenantId = $context->getTenantId();

        foreach ($products as $p) {
            $productUrl = $p['productUrl'] ?? $p['product_url'] ?? '';
            if (empty($productUrl)) continue;

            $existing = $model->where('product_url', $productUrl)
                              ->where('tenant_id', $tenantId)
                              ->first();

            $dataToSave = [
                'title' => $p['title'] ?? 'بدون عنوان',
                'product_url' => $productUrl,
                'country' => $p['country'] ?? '',
                'algo' => $p['algorithm'] ?? $p['algo'] ?? 'new',
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
                'api_version' => $p['api_version'] ?? '',
                'is_saved' => true,
                'saved_at' => date('Y-m-d H:i:s'),
                'collection' => $p['collection'] ?? 'عامة',
                'saved_status' => 'active',
                'rating' => intval($p['rating'] ?? 0),
                'notes' => $p['notes'] ?? '',
                'tenant_id' => $tenantId
            ];

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
            'message' => "Imported {$inserted} new, updated {$updated} existing",
            'inserted' => $inserted,
            'updated' => $updated
        ]);
    }

    public function deleteSnapshot($id = null)
    {
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            return $this->failForbidden('Only admins are allowed to delete snapshots. / لا يسمح بحذف لقطات البيانات إلا للمسؤولين.');
        }

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

        $snapshotModel->delete($id);

        return $this->respond([
            'success' => true,
            'message' => "Snapshot #{$id} deleted"
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

        // Extract filters from URL input if present
        $requestedVersion = null;
        $requestedCountry = null;
        $parsedUrl = parse_url($url, PHP_URL_QUERY);
        if ($parsedUrl) {
            parse_str($parsedUrl, $queryParams);
            if (isset($queryParams['input'])) {
                $inputDecoded = json_decode($queryParams['input'], true);
                if (is_array($inputDecoded)) {
                    $firstKey = array_key_first($inputDecoded);
                    $json = $inputDecoded[$firstKey]['json'] ?? [];
                    if (isset($json['v'])) {
                        $requestedVersion = $json['v'];
                    }
                    if (isset($json['country'])) {
                        $requestedCountry = $json['country'];
                    }
                }
            }
        }

        $isRegularUser = false;
        if (auth()->loggedIn() && !auth()->user()->inGroup('superadmin', 'admin')) {
            $isRegularUser = true;
        }

        $settingModel = new \App\Models\SettingModel();
        $dataSourceSetting = $settingModel->where('key', 'data-source')->first();
        $dataSource = $dataSourceSetting ? $dataSourceSetting['value'] : 'database';

        $model = new ProductModel();
        $snapshotModel = new \App\Models\SnapshotModel();

        // Security Protection: Validate past date requests to prevent tampering
        if ($requestedVersion !== null && preg_match('/(\d{4}-\d{2}-\d{2})/', $requestedVersion, $matches)) {
            $extractedDate = $matches[1];
            $today = date('Y-m-d');
            if ($extractedDate < $today) {
                $cleanVer = ltrim($requestedVersion, 'v');
                $snapCheck = $snapshotModel->where('origin', $origin)
                                          ->groupStart()
                                            ->where('api_version', $requestedVersion)
                                            ->orWhere('api_version', 'v' . $cleanVer)
                                            ->orWhere('api_version', $cleanVer)
                                            ->orWhere("api_version LIKE '%{$extractedDate}%'")
                                          ->groupEnd()
                                          ->first();
                $prodCheck = $model->where('origin', $origin)
                                   ->groupStart()
                                     ->where('api_version', $requestedVersion)
                                     ->orWhere("api_version LIKE '%{$extractedDate}%'")
                                   ->groupEnd()
                                   ->first();
                if (!$snapCheck && !$prodCheck) {
                    return $this->failForbidden('⚠️ غير مسموح بجلب تاريخ سابق غير مسجل في قاعدة البيانات. تم رفض الطلب لحماية النظام.');
                }
            }
        }
        
        // 1. Check if the requested version exists in products table or data_snapshots table
        if ($requestedVersion !== null && $requestedVersion !== '') {
            $versionProducts = $model->where('origin', $origin)
                                     ->where('api_version', $requestedVersion)
                                     ->orderBy('ads_count', 'DESC')
                                     ->findAll();
            
            if (!empty($versionProducts)) {
                $finalProducts = $versionProducts;
                if ($requestedCountry !== null && $requestedCountry !== '') {
                    $countries = explode(';', $requestedCountry);
                    $filtered = array_values(array_filter($versionProducts, function($p) use ($countries) {
                        return in_array($p['country'], $countries);
                    }));
                    if (!empty($filtered)) {
                        $finalProducts = $filtered;
                    }
                }

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
                                        'api_version' => $p['api_version'] ?? '',
                                    ];
                                }, $finalProducts)
                            ]
                        ]
                    ]
                ];

                $cachePath = WRITEPATH . 'cache/adapted_result.json';
                if (file_exists($cachePath)) {
                    $formatted['result']['data']['json']['adaptedResult'] = json_decode(file_get_contents($cachePath), true);
                }

                $formatted['source'] = 'database';
                return $this->respond([$formatted]);
            }

            // 2. Check if a snapshot with this api_version exists in data_snapshots table
            $cleanVer = ltrim($requestedVersion, 'v');
            $snapshot = $snapshotModel->where('origin', $origin)
                                      ->groupStart()
                                        ->where('api_version', $requestedVersion)
                                        ->orWhere('api_version', 'v' . $cleanVer)
                                        ->orWhere('api_version', $cleanVer)
                                      ->groupEnd()
                                      ->orderBy('id', 'DESC')
                                      ->first();

            if ($snapshot && !empty($snapshot['raw_json'])) {
                $decodedData = json_decode($snapshot['raw_json'], true);
                if (is_array($decodedData) && isset($decodedData[0])) {
                    $decodedData[0]['source'] = 'database';
                    $decodedData[0]['is_duplicate'] = true;
                    return $this->respond($decodedData);
                }
            }
        } else {
            // General query (no version specified)
            $originProducts = $model->where('origin', $origin)->orderBy('ads_count', 'DESC')->findAll();
            if (!empty($originProducts)) {
                $finalProducts = $originProducts;
                if ($requestedCountry !== null && $requestedCountry !== '') {
                    $countries = explode(';', $requestedCountry);
                    $filtered = array_values(array_filter($originProducts, function($p) use ($countries) {
                        return in_array($p['country'], $countries);
                    }));
                    if (!empty($filtered)) {
                        $finalProducts = $filtered;
                    }
                }

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
                                        'api_version' => $p['api_version'] ?? '',
                                    ];
                                }, $finalProducts)
                            ]
                        ]
                    ]
                ];

                $cachePath = WRITEPATH . 'cache/adapted_result.json';
                if (file_exists($cachePath)) {
                    $formatted['result']['data']['json']['adaptedResult'] = json_decode(file_get_contents($cachePath), true);
                }

                $formatted['source'] = 'database';
                return $this->respond([$formatted]);
            }
        }

        // 3. Option 3: Smart On-Demand Auto Sync (when requested version is missing from DB)
        $syncService = new SyncService();
        $data = $syncService->fetchAndSaveTrpcUrl($url);

        if (is_array($data) && isset($data[0])) {
            $data[0]['source'] = 'api';
            if ($requestedVersion) {
                $entries = &$data[0]['result']['data']['json']['productsEntries'] ?? [];
                if (is_array($entries)) {
                    foreach ($entries as &$entry) {
                        $entry['api_version'] = $requestedVersion;
                    }
                }
            }
            return $this->respond($data);
        }

        // 4. Fallback if external API is unreachable or returned error: return available DB products
        $fallbackProducts = $model->where('origin', $origin)->orderBy('ads_count', 'DESC')->findAll();
        if (empty($fallbackProducts)) {
            $fallbackProducts = $model->orderBy('ads_count', 'DESC')->findAll();
        }

        if (!empty($fallbackProducts)) {
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
                                    'api_version' => $p['api_version'] ?? '',
                                ];
                            }, $fallbackProducts)
                        ]
                    ]
                ]
            ];
            $formatted['source'] = 'database_fallback';
            return $this->respond([$formatted]);
        }

        return $this->fail('Failed to fetch or parse tRPC data from external API and no database data available');

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

        $rawList = [];
        if (is_array($rawData)) {
            $isAssoc = false;
            if (count($rawData) > 0) {
                $keys = array_keys($rawData);
                $isAssoc = (array_keys($keys) !== $keys);
            }

            if ($isAssoc) {
                // Wrapper object or single product object
                $targetData = $rawData['result']['data']['json'] ?? $rawData['data']['json'] ?? $rawData['json'] ?? $rawData;
                $rawList = $targetData['productsEntries'] ?? $targetData['results'] ?? [];
                if (!is_array($rawList)) {
                    $rawList = is_array($targetData) ? $targetData : [$targetData];
                }
            } else {
                // Sequential array
                if (count($rawData) > 0) {
                    $first = $rawData[0];
                    if (is_array($first) && (isset($first['productUrl']) || isset($first['product_url']) || isset($first['title']) || isset($first['product_title']))) {
                        // Direct list of products!
                        $rawList = $rawData;
                    } else if (is_array($first) && (isset($first['result']) || isset($first['data']) || isset($first['json']))) {
                        // Wrapped batch array
                        $targetData = $first['result']['data']['json'] ?? $first['data']['json'] ?? $first['json'] ?? $first;
                        $rawList = $targetData['productsEntries'] ?? $targetData['results'] ?? [];
                        if (!is_array($rawList)) {
                            $rawList = is_array($targetData) ? $targetData : [];
                        }
                    } else {
                        $rawList = $rawData;
                    }
                }
            }
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

        $context = \App\Libraries\TenantContext::getInstance();
        if ($context->hasTenant()) {
            $builder->where('tenant_id', $context->getTenantId());
        }

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
        foreach ($savedProducts as &$p) {
            $p['actualPrice'] = $p['price_1'];
        }

        return $this->respond($savedProducts);
    }

    public function toggleSave()
    {
        $model = new ProductModel();
        
        // Accept payload
        $json = $this->request->getJSON(true);
        if (!empty($json)) {
            $productUrl = $json['product_url'] ?? $json['productUrl'] ?? null;
            $product = $json;
        } else {
            $productUrl = $this->request->getVar('product_url');
            $product = $this->request->getPost();
        }

        if (empty($productUrl)) {
            return $this->fail('Product URL is required');
        }

        $context = \App\Libraries\TenantContext::getInstance();
        $tenantId = $context->getTenantId();

        // Try to find existing product by product_url and tenant_id
        $existing = $model->where('product_url', $productUrl)
                          ->where('tenant_id', $tenantId)
                          ->first();

        if ($existing) {
            // PostgreSQL returns boolean as 't'/'f' strings, PHP needs explicit check
            $currentlySaved = ($existing['is_saved'] === true || $existing['is_saved'] === 't' || $existing['is_saved'] === 1 || $existing['is_saved'] === '1' || $existing['is_saved'] === 'true');
            $newSavedState = !$currentlySaved;
            $updateData = [
                'is_saved' => $newSavedState,
                'saved_at' => $newSavedState ? date('Y-m-d H:i:s') : null,
                'collection' => $newSavedState ? ($existing['collection'] ?: 'عامة') : $existing['collection'],
            ];

            if ($newSavedState) {
                // Update with complete details from the request when saving
                $origin = $product['origin'] ?? $existing['origin'] ?? 'Winning';
                
                if (isset($product['title'])) $updateData['title'] = $product['title'];
                if (isset($product['country'])) $updateData['country'] = $product['country'];
                if (isset($product['algorithm']) || isset($product['algo'])) {
                    $updateData['algo'] = $product['algorithm'] ?? $product['algo'];
                }
                if (isset($product['ad_start_date'])) {
                    $updateData['ad_start_date'] = $this->cleanDateStr($product['ad_start_date']);
                }
                if (isset($product['ads_count'])) $updateData['ads_count'] = intval($product['ads_count']);
                if (isset($product['unique_image_count'])) $updateData['unique_image_count'] = intval($product['unique_image_count']);
                if (isset($product['unique_video_count'])) $updateData['unique_video_count'] = intval($product['unique_video_count']);
                if (isset($product['avg_creatives'])) $updateData['avg_creatives'] = floatval($product['avg_creatives']);
                if (isset($product['ads_per_unique_url'])) $updateData['ads_per_unique_url'] = floatval($product['ads_per_unique_url']);
                
                if (isset($product['ad_title'])) $updateData['ad_title'] = $product['ad_title'];
                if (isset($product['ad_body'])) $updateData['ad_body'] = $product['ad_body'];
                
                if (isset($product['ad_image_urls'])) {
                    $updateData['ad_image_urls'] = is_array($product['ad_image_urls']) ? implode(';', $product['ad_image_urls']) : $product['ad_image_urls'];
                }
                if (isset($product['ad_video_urls'])) {
                    $updateData['ad_video_urls'] = is_array($product['ad_video_urls']) ? implode(';', $product['ad_video_urls']) : $product['ad_video_urls'];
                }
                
                if (isset($product['price_1']) || isset($product['actualPrice']) || isset($product['price'])) {
                    $updateData['price_1'] = strval($product['price_1'] ?? $product['actualPrice'] ?? $product['price']);
                }
                if (isset($product['active_ads'])) $updateData['active_ads'] = (bool)$product['active_ads'];
                if (isset($product['origin'])) $updateData['origin'] = $product['origin'];
                
                if ($origin === 'Winning' && isset($product['badge_algorithm'])) {
                    $updateData['badge_algorithm'] = $product['badge_algorithm'];
                }
            }

            if (!empty($product['api_version'])) {
                $updateData['api_version'] = $product['api_version'];
            }
            
            $model->update($existing['id'], $updateData);
            return $this->respond([
                'success' => true,
                'is_saved' => $newSavedState,
                'action' => $newSavedState ? 'saved' : 'unsaved',
                'message' => $newSavedState ? 'تم حفظ المنتج بنجاح! ⭐' : 'تمت إزالة المنتج من المحفوظات.',
            ]);
        } else {
            // If the product doesn't exist for this tenant, we create a tenant-specific saved row.
            // Let's first search for a global or any product row with this product_url to copy its basic details
            $globalProduct = $model->where('product_url', $productUrl)->first();
            
            $origin = $product['origin'] ?? $globalProduct['origin'] ?? 'Winning';
            $dataToInsert = [
                'title' => $product['title'] ?? $globalProduct['title'] ?? 'بدون عنوان',
                'product_url' => $productUrl,
                'country' => $product['country'] ?? $globalProduct['country'] ?? '',
                'algo' => $product['algorithm'] ?? $product['algo'] ?? $globalProduct['algo'] ?? ($origin === 'Winning' ? 'winning' : 'new'),
                'ad_start_date' => $this->cleanDateStr($product['ad_start_date'] ?? $globalProduct['ad_start_date'] ?? null),
                'ads_count' => intval($product['ads_count'] ?? $globalProduct['ads_count'] ?? 0),
                'unique_image_count' => intval($product['unique_image_count'] ?? $globalProduct['unique_image_count'] ?? 0),
                'unique_video_count' => intval($product['unique_video_count'] ?? $globalProduct['unique_video_count'] ?? 0),
                'avg_creatives' => floatval($product['avg_creatives'] ?? $globalProduct['avg_creatives'] ?? 1),
                'ads_per_unique_url' => floatval($product['ads_per_unique_url'] ?? $globalProduct['ads_per_unique_url'] ?? 1),
                'ad_title' => $product['ad_title'] ?? $globalProduct['ad_title'] ?? '',
                'ad_body' => $product['ad_body'] ?? $globalProduct['ad_body'] ?? '',
                'ad_image_urls' => is_array($product['ad_image_urls'] ?? null) ? implode(';', $product['ad_image_urls']) : ($product['ad_image_urls'] ?? $globalProduct['ad_image_urls'] ?? ''),
                'ad_video_urls' => is_array($product['ad_video_urls'] ?? null) ? implode(';', $product['ad_video_urls']) : ($product['ad_video_urls'] ?? $globalProduct['ad_video_urls'] ?? ''),
                'price_1' => strval($product['price_1'] ?? $product['actualPrice'] ?? $product['price'] ?? $globalProduct['price_1'] ?? '0'),
                'active_ads' => isset($product['active_ads']) ? (bool)$product['active_ads'] : (isset($globalProduct['active_ads']) ? (bool)$globalProduct['active_ads'] : true),
                'origin' => $origin,
                'api_version' => $product['api_version'] ?? $globalProduct['api_version'] ?? '',
                'is_saved' => true,
                'saved_at' => date('Y-m-d H:i:s'),
                'collection' => $product['collection'] ?? 'عامة',
                'saved_status' => 'active',
                'rating' => 0,
                'notes' => '',
                'tenant_id' => $tenantId
            ];

            if ($origin === 'Winning') {
                $dataToInsert['badge_algorithm'] = $product['badge_algorithm'] ?? $globalProduct['badge_algorithm'] ?? 'winning';
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

        $context = \App\Libraries\TenantContext::getInstance();
        $tenantId = $context->getTenantId();

        $existing = $model->where('product_url', $productUrl)
                          ->where('tenant_id', $tenantId)
                          ->first();
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

        $context = \App\Libraries\TenantContext::getInstance();
        $tenantId = $context->getTenantId();

        $existing = $model->where('product_url', $productUrl)
                          ->where('tenant_id', $tenantId)
                          ->first();
        if (!$existing) {
            return $this->failNotFound('Product not found');
        }

        $model->update($existing['id'], ['notes' => $notes]);
        return $this->respond(['success' => true]);
    }

    public function updatePrice()
    {
        $model = new ProductModel();
        $productUrl = $this->request->getVar('product_url');
        $price = $this->request->getVar('price');

        if (empty($productUrl)) {
            $json = $this->request->getJSON(true);
            $productUrl = $json['product_url'] ?? null;
            $price = $json['price'] ?? '0';
        }

        $context = \App\Libraries\TenantContext::getInstance();
        $tenantId = $context->getTenantId();

        $existing = $model->where('product_url', $productUrl)
                          ->where('tenant_id', $tenantId)
                          ->first();
        if (!$existing) {
            return $this->failNotFound('Product not found');
        }

        $model->update($existing['id'], ['price_1' => strval($price)]);
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

        $context = \App\Libraries\TenantContext::getInstance();
        $tenantId = $context->getTenantId();

        $existing = $model->where('product_url', $productUrl)
                          ->where('tenant_id', $tenantId)
                          ->first();
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

        $context = \App\Libraries\TenantContext::getInstance();
        $tenantId = $context->getTenantId();

        $existing = $model->where('product_url', $productUrl)
                          ->where('tenant_id', $tenantId)
                          ->first();
        if (!$existing) {
            return $this->failNotFound('Product not found');
        }

        $model->update($existing['id'], ['collection' => $collection]);
        return $this->respond(['success' => true]);
    }

    public function clearSaved()
    {
        $model = new ProductModel();
        
        $context = \App\Libraries\TenantContext::getInstance();
        if ($context->hasTenant()) {
            $model->where('tenant_id', $context->getTenantId());
        }

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
        // Bypass tenant scoping so collections are always accessible
        $collections = $model->bypassTenant()->orderBy('id', 'ASC')->findAll();

        // Seed default collections if the table is empty
        if (empty($collections)) {
            $defaults = ['عامة', 'ملابس', 'إلكترونيات', 'أدوات منزلية'];
            foreach ($defaults as $name) {
                $exists = $model->bypassTenant()->where('name', $name)->first();
                if (!$exists) {
                    $model->insert(['name' => $name]);
                }
            }
            $collections = $model->bypassTenant()->orderBy('id', 'ASC')->findAll();
        }

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
        
        $context = \App\Libraries\TenantContext::getInstance();
        if ($context->hasTenant()) {
            $productModel->where('tenant_id', $context->getTenantId());
        }

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
        $setting = $model->where('key', $key)->first();
        return $this->respond($setting ?: ['key' => $key, 'value' => null]);
    }

    public function saveSetting()
    {
        $model = new \App\Models\SettingModel();
        
        $rawInput = file_get_contents('php://input');
        $json = !empty($rawInput) ? (json_decode($rawInput, true) ?: []) : [];

        $key = $this->request->getPost('key') ?? ($json['key'] ?? $this->request->getGet('key'));
        $value = $this->request->getPost('value') ?? ($json['value'] ?? $this->request->getGet('value'));

        if (empty($key)) {
            return $this->fail('Key is required');
        }

        // Allow app-theme for all users, restrict system settings to superadmin/admin
        if ($key !== 'app-theme') {
            if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
                return $this->failForbidden('عذراً، تعديل هذه الإعدادات مخصص للمشرفين والمسؤولين فقط.');
            }
        }

        $existing = $model->where('key', $key)->first();
        if ($existing) {
            $model->update($existing['id'], [
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
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            return $this->failForbidden('عذراً، عمليات تنظيف وتصفير قاعدة البيانات مخصصة للمشرفين والمسؤولين فقط.');
        }

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
                // Reset all saved products for current tenant
                $context = \App\Libraries\TenantContext::getInstance();
                $tenantId = $context->getTenantId();
                $query = $db->table('products')->where('is_saved', true);
                if ($tenantId !== null) {
                    $query->where('tenant_id', $tenantId);
                }
                $query->update([
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
                $context = \App\Libraries\TenantContext::getInstance();
                $tenantId = $context->getTenantId();
                
                $collectionsQuery = $db->table('collections');
                if ($tenantId !== null) {
                    $collectionsQuery->where('tenant_id', $tenantId);
                }
                $collectionsQuery->delete();
                
                $productsQuery = $db->table('products');
                if ($tenantId !== null) {
                    $productsQuery->where('tenant_id', $tenantId);
                }
                $productsQuery->update(['collection' => 'عامة']);
                break;
            case 'watchlist':
                // Clear watched stores
                $context = \App\Libraries\TenantContext::getInstance();
                $tenantId = $context->getTenantId();
                
                $watchedQuery = $db->table('watched_stores');
                if ($tenantId !== null) {
                    $watchedQuery->where('tenant_id', $tenantId);
                }
                $watchedQuery->delete();
                break;
            case 'all':
                // Delete all products, collections, watched stores belonging to tenant
                $context = \App\Libraries\TenantContext::getInstance();
                $tenantId = $context->getTenantId();
                
                $productsQuery = $db->table('products');
                $collectionsQuery = $db->table('collections');
                $watchedQuery = $db->table('watched_stores');
                
                if ($tenantId !== null) {
                    $productsQuery->where('tenant_id', $tenantId);
                    $collectionsQuery->where('tenant_id', $tenantId);
                    $watchedQuery->where('tenant_id', $tenantId);
                }
                
                $productsQuery->delete();
                $collectionsQuery->delete();
                $watchedQuery->delete();
                
                // Reset settings to default for tenant
                $settingsQuery = $db->table('settings');
                if ($tenantId !== null) {
                    $settingsQuery->where('tenant_id', $tenantId);
                }
                $settingsQuery->where('key', 'app-theme')->update(['value' => 'light']);
                
                $settingsQuery2 = $db->table('settings');
                if ($tenantId !== null) {
                    $settingsQuery2->where('tenant_id', $tenantId);
                }
                $settingsQuery2->where('key', 'data-source')->update(['value' => 'database']);
                break;
            default:
                return $this->fail('Invalid clear type: ' . $type);
        }

        return $this->respond(['success' => true]);
    }

    // داخل كلاس Products في app/Controllers/Products.php

public function activity()
{
    $productUrl = $this->request->getVar('product_url');
    $refresh = $this->request->getVar('refresh') === '1';

    if (empty($productUrl)) {
        $json = $this->request->getJSON(true);
        $productUrl = $json['product_url'] ?? null;
        $refresh = $refresh || ($json['refresh'] ?? false);
    }
    if (empty($productUrl)) {
        return $this->fail('product_url is required');
    }

    $model = new ProductModel();
    $product = $model->where('product_url', $productUrl)->first();

    $activity = [];
    $source = 'cache';

    // التحقق من الكاش أو جلب البيانات الخارجية
    if (!$refresh && $product && !empty($product['activity_data'])) {
        $activity = json_decode($product['activity_data'], true);
    }

    if (empty($activity)) {
        $inputObj = ['0' => ['json' => ['product_url' => $productUrl]]];
        $apiUrl = 'https://www.overviewdata.io/api/trpc/data.getAdActivity?batch=1&input=' . urlencode(json_encode($inputObj));

        try {
            $client = \Config\Services::curlrequest();
            $response = $client->request('GET', $apiUrl, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'application/json',
                ],
                'timeout' => 30,
            ]);

            if ($response->getStatusCode() === 200) {
                $rawBody = $response->getBody();
                $parsed = json_decode($rawBody, true);
                $base = is_array($parsed) ? ($parsed[0] ?? null) : $parsed;
                $activity = $base['result']['data']['json'] ?? [];

                // Save to database
                if ($product) {
                    $model->update($product['id'], ['activity_data' => json_encode($activity)]);
                }
                $source = 'api';
            } else {
                $source = 'error';
            }
        } catch (\Exception $e) {
            log_message('error', 'Failed to fetch activity from external API: ' . $e->getMessage());
            $source = 'error';
        }
    }

    // ⭐ توليد تحليل الاستراتيجية الواقعي بناءً على بيانات المنتج والنشاط
    $strategyAnalysis = $this->generateLiveStrategy($product, $activity);

    return $this->respond([
        'source' => $source,
        'activity' => $activity,
        'strategy_analysis' => $strategyAnalysis
    ]);
}

/**
 * دالة ذكية لتوليد تحليل تسويقي واقعي مبني على الأرقام الحقيقية للمنتج
 */
private function generateLiveStrategy($product, $activity)
{
    if (!$product) return "لم يتم العثور على بيانات كافية لتحليل هذا المنتج.";

    $adsCount = intval($product['ads_count'] ?? 0);
    $avgCreatives = floatval($product['avg_creatives'] ?? 1);
    $isActive = filter_var($product['active_ads'], FILTER_VALIDATE_BOOLEAN);
    $hasVideo = intval($product['unique_video_count'] ?? 0) > 0 || !empty($product['ad_video_urls']);
    
    $analysis = [];
    $badge = "تحليل أولي";

    // 1. تحليل حجم الميزانية والزخم الإعلاني (Scaling vs Testing)
    if ($adsCount >= 30) {
        $analysis[] = "المعلن يقوم بعملية توسيع ضخمة (Aggressive Scaling) للمنتج من خلال تشغيل عدد كبير من الإعلانات المتزامنة ($adsCount إعلان)، مما يثبت تحقيق عائد إيجابي ممتاز (ROI) حالياً.";
        $badge = "توسيع مكثف (Scaling)";
    } elseif ($adsCount >= 10) {
        $analysis[] = "المنتج يمر بمرحلة نمو مستقر وتحسين (Optimization)، حيث يعتمد المعلن على ميزانية متوسطة مع تصفية الزوايا الإعلانية الخاسرة.";
        $badge = "منتج رابح مستقر";
    } else {
        $analysis[] = "المنتج في مرحلة الاختبار الأولي (Initial Testing) أو أن المنافسة عليه منخفضة، حيث يتم تشغيل حملات محدودة قياسية لاستكشاف السوق.";
        $badge = "مرحلة الاختبار (Testing)";
    }

    // 2. تحليل المحتوى الإبداعي (Creatives Quality)
    if ($avgCreatives > 4) {
        $analysis[] = "يلاحظ وجود تنوع كبير في استخدام العناصر الإبداعية والمشاهد الإعلانية (متوسط {$avgCreatives} لكل رابط)، وهي استراتيجية ذكية لتفادي \"عقم الإعلانات\" (Ad Fatigue) واستهداف اهتمامات متعددة للجمهور.";
    }
    if ($hasVideo) {
        $analysis[] = "يركز المعلن بشكل أساسي على الإعلانات المرئية (Video Ads)، وهو الأسلوب الأنجح لرفع نسب النقر (CTR) وتحسين التحويل في نماذج الدفع عند الاستلام (COD).";
    }

    // 3. تحليل حالة النشاط من خلال الجدول الزمني (Reactivation & Out of stock)
    if (!$isActive) {
        $analysis[] = "الحملات الإعلانية متوقفة حالياً بالكامل؛ قد يعود السبب إما لانتهاء موجة الطلب على المنتج، أو بسبب نفاد المخزون (Out of stock) بانتظار إعادة التوريد.";
    }

    // التحقق من وجود أحداث إعادة تنشيط في مصفوفة الـ activity
    // (مثال: إذا كانت الإحصائيات المرجعة تحتوي على مؤشر رصد التوقف والعودة)
    if (isset($activity['reactivations']) && intval($activity['reactivations']) > 0) {
        $analysis[] = "تم رصد أحداث إعادة تنشيط (Reactivations) بعد فترات خمول، وهي إشارة ذهبية تؤكد تفوق هذا المنتج تسويقياً واضطرار المنافس لإعادة تشغيله فور وصول شحنات جديدة.";
    }

    return [
        'badge' => $badge,
        'text' => implode(" ", $analysis)
    ];
}

    private function cleanDateStr($dateStr)
    {
        if (empty($dateStr) || $dateStr === '--') {
            return null;
        }
        $timestamp = strtotime($dateStr);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    public function getAvailableDates()
    {
        $db = \Config\Database::connect();

        // Optional origin filter passed from frontend (e.g. "Winning", "Local")
        $origin = $this->request->getVar('origin') ?? '';

        // ─── snapshotDates: dates from api_version of data_snapshots, filtered by origin ───
        $snapshotDatesMap = [];

        if ($db->tableExists('data_snapshots')) {
            $builder = $db->table('data_snapshots')->select('api_version');

            // Filter by origin if provided
            if (!empty($origin)) {
                $builder->where('origin', $origin);
            }

            $snapshots = $builder->get()->getResultArray();

            foreach ($snapshots as $row) {
                // Extract YYYY-MM-DD from api_version (e.g. "1.10-1-2026-07-13" → "2026-07-13")
                if (!empty($row['api_version']) && preg_match('/(\d{4}-\d{2}-\d{2})/', $row['api_version'], $m)) {
                    $snapshotDatesMap[$m[1]] = true;
                }
            }
        }

        $snapshotDates = array_keys($snapshotDatesMap);
        sort($snapshotDates);

        // ─── allDates: snapshot dates for this origin + today (always selectable) ───
        $today = date('Y-m-d');
        $allDatesMap = $snapshotDatesMap;
        $allDatesMap[$today] = true;
        $allDates = array_keys($allDatesMap);
        sort($allDates);

        return $this->respond([
            // Dates with a real snapshot for this origin — green badge in calendar
            'snapshotDates' => array_values($snapshotDates),
            // All selectable calendar dates (snapshot dates + today)
            'dates'         => array_values($allDates),
        ]);
    }
}

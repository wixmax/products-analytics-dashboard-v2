<?php

namespace App\Controllers;

use App\Models\ProductModel;
use App\Models\SnapshotModel;
use App\Models\SettingModel;
use CodeIgniter\RESTful\ResourceController;
use App\Controllers\Traits\SnapshotTrait;
use App\Controllers\Traits\SavedAdsTrait;
use App\Controllers\Traits\AiAnalysisTrait;
use App\Controllers\Traits\VectorizeTrait;
use App\Controllers\Traits\SyncTrait;
use App\Controllers\Traits\SettingsTrait;

class Products extends ResourceController
{
    use SnapshotTrait;
    use SavedAdsTrait;
    use AiAnalysisTrait;
    use VectorizeTrait;
    use SyncTrait;
    use SettingsTrait;

    protected $format = 'json';

    public function index()
    {
        $model = new ProductModel();
        
        $origin = $this->request->getVar('origin') ?? 'all';
        $search = $this->request->getVar('search');
        $country = $this->request->getVar('country');
        $status = $this->request->getVar('status');
        $dateFilter = $this->request->getVar('date');
        $sort = $this->request->getVar('sort') ?? 'ads-desc';
        $page = intval($this->request->getVar('page') ?? 1);
        $perPage = intval($this->request->getVar('per_page') ?? 12);

        $builder = $model;
        if (!empty($origin) && $origin !== 'all' && $origin !== 'ككل') {
            $builder = $builder->where('origin', $origin);
        }

        // Exclude tenant-saved copies from the main list so we only query master synced/imported rows
        $builder->groupStart()
                    ->where('is_saved', false)
                    ->orWhere('tenant_id IS NULL')
                ->groupEnd();

        // Search
        $semantic = filter_var($this->request->getVar('semantic'), FILTER_VALIDATE_BOOLEAN);
        if (!empty($search)) {
            $appliedSemantic = false;
            if ($semantic) {
                try {
                    $vectorService = new \App\Services\CloudflareVectorService();
                    if ($vectorService->isConfigured()) {
                        $matches = $vectorService->searchSemantic($search, 100);
                        if (!empty($matches)) {
                            $semanticIds = array_column($matches, 'product_id');
                            $semanticIds = array_filter(array_map('intval', $semanticIds));
                            if (!empty($semanticIds)) {
                                $builder->whereIn('id', $semanticIds);
                                $appliedSemantic = true;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    log_message('error', 'Semantic search error in Products::index: ' . $e->getMessage());
                }
            }

            if (!$appliedSemantic) {
                $builder->groupStart()
                        ->like('title', $search)
                        ->orLike('ad_body', $search)
                        ->orLike('ad_title', $search)
                        ->orLike('product_url', $search)
                        ->groupEnd();
            }
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

        $settingModel = new SettingModel();
        $scopeSetting = $settingModel->where('key', 'analytics-scope')->first();
        $scope = $scopeSetting['value'] ?? 'snapshot';

        $getBaseQuery = function() use ($db, $origin, $snapshotId, $scope) {
            $builder = $db->table('products')->where('origin', $origin);
            if ($scope === 'snapshot') {
                if (!empty($snapshotId)) {
                    $builder->where('snapshot_id', $snapshotId);
                } else {
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

    public function availableCountries()
    {
        $origin = $this->request->getVar('origin') ?? 'Winning';
        $date = $this->request->getVar('date');

        $countryCounts = [];
        $snapshotModel = new SnapshotModel();

        $snapshot = null;
        if (!empty($date)) {
            $snapshot = $snapshotModel->where('origin', $origin)
                                      ->groupStart()
                                        ->like('created_at', $date, 'after')
                                        ->orLike('api_version', $date)
                                      ->groupEnd()
                                      ->orderBy('id', 'DESC')
                                      ->first();
        }

        if (!$snapshot) {
            $snapshot = $snapshotModel->where('origin', $origin)->orderBy('id', 'DESC')->first();
        }

        if ($snapshot && !empty($snapshot['raw_json'])) {
            $decoded = json_decode(\App\Libraries\Storage\SnapshotStorageHelper::decompress($snapshot['raw_json']), true);
            $target = $decoded[0] ?? $decoded;
            $jsonTarget = $target['result']['data']['json'] ?? $target['data']['json'] ?? $target['json'] ?? $target;
            $entries = $jsonTarget['productsEntries'] ?? $jsonTarget['results'] ?? (is_array($jsonTarget) ? $jsonTarget : []);

            foreach ($entries as $p) {
                $c = is_array($p) ? ($p['country'] ?? '') : ($p->country ?? '');
                if (!empty($c)) {
                    $countryCounts[$c] = ($countryCounts[$c] ?? 0) + 1;
                }
            }
        } else {
            $productModel = new ProductModel();
            $builder = $productModel->where('origin', $origin);
            if (!empty($date)) {
                $builder->where('ad_start_date', $date);
            }
            $products = $builder->findAll();
            foreach ($products as $p) {
                if (!empty($p['country'])) {
                    $countryCounts[$p['country']] = ($countryCounts[$p['country']] ?? 0) + 1;
                }
            }
        }

        return $this->respond([
            'success'       => true,
            'date'          => $date,
            'countryCounts' => $countryCounts
        ]);
    }

    public function getAvailableDates()
    {
        $db = \Config\Database::connect();
        $origin = $this->request->getVar('origin') ?? '';

        $snapshotDatesMap = [];

        if ($db->tableExists('data_snapshots')) {
            $builder = $db->table('data_snapshots')->select('api_version');

            if (!empty($origin)) {
                $builder->where('origin', $origin);
            }

            $snapshots = $builder->get()->getResultArray();

            foreach ($snapshots as $row) {
                if (!empty($row['api_version']) && preg_match('/(\d{4}-\d{2}-\d{2})/', $row['api_version'], $m)) {
                    $snapshotDatesMap[$m[1]] = true;
                }
            }
        }

        $snapshotDates = array_keys($snapshotDatesMap);
        sort($snapshotDates);

        $today = date('Y-m-d');
        $allDatesMap = $snapshotDatesMap;
        $allDatesMap[$today] = true;
        $allDates = array_keys($allDatesMap);
        sort($allDates);

        return $this->respond([
            'snapshotDates' => array_values($snapshotDates),
            'dates'         => array_values($allDates),
        ]);
    }

    private function cleanDateStr($dateStr)
    {
        if (empty($dateStr) || $dateStr === '--') {
            return null;
        }
        $timestamp = strtotime($dateStr);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private function filterSnapshotByCountries(&$decodedData, array $countries)
    {
        if (empty($decodedData) || empty($countries)) return;

        $targetCountries = array_map('strtoupper', array_map('trim', $countries));

        $filterItem = function(&$item) use ($targetCountries) {
            if (!is_array($item)) return;

            $filterEntries = function(array $entries) use ($targetCountries) {
                return array_values(array_filter($entries, function($p) use ($targetCountries) {
                    $c = is_array($p) ? ($p['country'] ?? '') : ($p->country ?? '');
                    if (empty($c)) return false;
                    $itemCountries = array_map('strtoupper', array_map('trim', explode(';', $c)));
                    return !empty(array_intersect($itemCountries, $targetCountries));
                }));
            };

            if (isset($item['result']['data']['json']['productsEntries']) && is_array($item['result']['data']['json']['productsEntries'])) {
                $item['result']['data']['json']['productsEntries'] = $filterEntries($item['result']['data']['json']['productsEntries']);
            } elseif (isset($item['data']['json']['productsEntries']) && is_array($item['data']['json']['productsEntries'])) {
                $item['data']['json']['productsEntries'] = $filterEntries($item['data']['json']['productsEntries']);
            } elseif (isset($item['json']['productsEntries']) && is_array($item['json']['productsEntries'])) {
                $item['json']['productsEntries'] = $filterEntries($item['json']['productsEntries']);
            } elseif (isset($item['productsEntries']) && is_array($item['productsEntries'])) {
                $item['productsEntries'] = $filterEntries($item['productsEntries']);
            }
        };

        if (is_array($decodedData)) {
            if (isset($decodedData[0])) {
                foreach ($decodedData as &$item) {
                    $filterItem($item);
                }
            } else {
                $filterItem($decodedData);
            }
        }
    }
}

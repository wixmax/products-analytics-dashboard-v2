<?php

namespace App\Services;

use App\Models\ProductModel;
use App\Models\SnapshotModel;

class SyncService
{
    protected $client;
    protected $model;
    protected $snapshotModel;

    public function __construct()
    {
        $this->client = \Config\Services::curlrequest();
        $this->model = new ProductModel();
        $this->snapshotModel = new SnapshotModel();
    }

    public function run(): array
    {
        $stats = [
            'Local' => ['inserted' => 0, 'updated' => 0, 'failed' => false],
            'Winning' => ['inserted' => 0, 'updated' => 0, 'failed' => false],
            'China' => ['inserted' => 0, 'updated' => 0, 'failed' => false],
            'Japan' => ['inserted' => 0, 'updated' => 0, 'failed' => false],
        ];

        // 1. Fetch Insights (Local Products)
        $stats['Local'] = $this->syncInsights();

        // 2. Fetch Winning Products
        $stats['Winning'] = $this->syncWinningProducts();

        // 3. Fetch China Products
        $stats['China'] = $this->syncInternationalProducts('China');

        // 4. Fetch Japan Products
        $stats['Japan'] = $this->syncInternationalProducts('Japan');

        return $stats;
    }

    private function syncInsights(): array
    {
        $stats = ['inserted' => 0, 'updated' => 0, 'failed' => false];
        $input = [
            "0" => [
                "json" => [
                    "title" => "",
                    "category" => "Popular;Electronics;Home & Garden;Health & Beauty;Apparel & Accessories;Tools;Baby & Toddler",
                    "priceFrom" => -1,
                    "priceTo" => -1,
                    "weeks" => 12,
                    "country" => "DZ;TN;MA;LY;EG;SA;QA;EA;OM;BH;KW;GB;IE;FR;BE;LU;CH;DE;AT;ES;IT;NL;PT;NG;CI;SN;KE",
                    "transformation" => "none",
                    "v" => "1.3--5"
                ]
            ]
        ];

        $apiVersion = $this->extractVersion($input);
        $url = 'https://www.overviewdata.io/api/trpc/data.insights?batch=1&input=' . urlencode(json_encode($input));

        try {
            $response = $this->client->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'application/json'
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                $rawBody = $response->getBody();
                $data = json_decode($rawBody, true);
                $base = is_array($data) ? ($data[0] ?? null) : $data;
                $targetData = $base['result']['data']['json'] ?? null;
                
                if ($targetData) {
                    $rawList = $targetData['productsEntries'] ?? $targetData['results'] ?? [];

                    // Save snapshot before upserting products
                    $snapshotId = $this->saveSnapshot('Local', $apiVersion, $rawBody, count($rawList));

                    foreach ($rawList as $p) {
                        $productUrl = $p['productUrl'] ?? $p['product_url'] ?? '';
                        $title = $p['title'] ?? $p['product_title'] ?? 'بدون عنوان';
                        
                        $existing = $this->model->where('product_url', $productUrl)
                                          ->where('origin', 'Local')
                                          ->first();

                        $dataToSave = [
                            'title' => $title,
                            'product_url' => $productUrl,
                            'country' => $p['country'] ?? '',
                            'algo' => $p['algorithm'] ?? $p['algo'] ?? 'new',
                            'ad_start_date' => $this->cleanDate($p['ad_start_date'] ?? null),
                            'ads_count' => intval($p['ads_count'] ?? 0),
                            'unique_image_count' => intval($p['unique_image_count'] ?? 0),
                            'unique_video_count' => intval($p['unique_video_count'] ?? 0),
                            'avg_creatives' => floatval($p['avg_creatives'] ?? 1),
                            'ads_per_unique_url' => floatval($p['ads_per_unique_url'] ?? 1),
                            'ad_title' => $p['ad_title'] ?? '',
                            'ad_body' => $p['ad_body'] ?? '',
                            'ad_image_urls' => is_array($p['ad_image_urls'] ?? null) ? implode(';', $p['ad_image_urls']) : ($p['ad_image_urls'] ?? ''),
                            'ad_video_urls' => is_array($p['ad_video_urls'] ?? null) ? implode(';', $p['ad_video_urls']) : ($p['ad_video_urls'] ?? ''),
                            'price_1' => strval($p['price_1'] ?? $p['actualPrice'] ?? '0'),
                            'active_ads' => isset($p['active_ads']) ? (bool)$p['active_ads'] : true,
                            'origin' => 'Local',
                            'api_version' => $apiVersion,
                            'snapshot_id' => $snapshotId,
                        ];

                        if ($existing) {
                            $this->model->update($existing['id'], $dataToSave);
                            $stats['updated']++;
                        } else {
                            $this->model->insert($dataToSave);
                            $stats['inserted']++;
                        }
                    }

                    if (isset($targetData['adaptedResult'])) {
                        $cachePath = WRITEPATH . 'cache/adapted_result.json';
                        if (!is_dir(dirname($cachePath))) {
                            mkdir(dirname($cachePath), 0777, true);
                        }
                        file_put_contents($cachePath, json_encode($targetData['adaptedResult']));
                    }
                }
            } else {
                $stats['failed'] = true;
            }
        } catch (\Exception $e) {
            $stats['failed'] = true;
        }
        return $stats;
    }

    private function syncWinningProducts(): array
    {
        $stats = ['inserted' => 0, 'updated' => 0, 'failed' => false];
        $input = [
            "0" => [
                "json" => [
                    "category" => "Popular;Electronics;Home & Garden;Health & Beauty;Apparel & Accessories;Tools;Baby & Toddler",
                    "country" => "DZ;TN;MA;LY;EG;SA;QA;EA;OM;BH;KW;GB;IE;FR;BE;LU;CH;DE;AT;ES;IT;NL;PT;NG;CI;SN;KE",
                    "v" => "1.10-12026-05-15"
                ]
            ]
        ];

        $apiVersion = $this->extractVersion($input);
        $url = 'https://www.overviewdata.io/api/trpc/data.winingProducts?batch=1&input=' . urlencode(json_encode($input));

        try {
            $response = $this->client->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'application/json'
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                $rawBody = $response->getBody();
                $data = json_decode($rawBody, true);
                $base = is_array($data) ? ($data[0] ?? null) : $data;
                $targetData = $base['result']['data']['json'] ?? null;

                if ($targetData) {
                    $rawList = $targetData['productsEntries'] ?? $targetData['results'] ?? $targetData;
                    if (!is_array($rawList) && isset($targetData['results'])) {
                        $rawList = $targetData['results'];
                    }
                    if (!is_array($rawList)) {
                        $rawList = [];
                    }

                    // Save snapshot before upserting products
                    $snapshotId = $this->saveSnapshot('Winning', $apiVersion, $rawBody, count($rawList));

                    foreach ($rawList as $p) {
                        $productUrl = $p['productUrl'] ?? $p['product_url'] ?? '';
                        $title = $p['title'] ?? $p['product_title'] ?? 'بدون عنوان';

                        $existing = $this->model->where('product_url', $productUrl)
                                          ->where('origin', 'Winning')
                                          ->first();

                        $dataToSave = [
                            'title' => $title,
                            'product_url' => $productUrl,
                            'country' => $p['country'] ?? '',
                            'algo' => $p['algorithm'] ?? $p['algo'] ?? 'winning',
                            'ad_start_date' => $this->cleanDate($p['ad_start_date'] ?? null),
                            'ads_count' => intval($p['ads_count'] ?? 0),
                            'unique_image_count' => intval($p['unique_image_count'] ?? 0),
                            'unique_video_count' => intval($p['unique_video_count'] ?? 0),
                            'avg_creatives' => floatval($p['avg_creatives'] ?? 1),
                            'ads_per_unique_url' => floatval($p['ads_per_unique_url'] ?? 1),
                            'ad_title' => $p['ad_title'] ?? '',
                            'ad_body' => $p['ad_body'] ?? '',
                            'ad_image_urls' => is_array($p['ad_image_urls'] ?? null) ? implode(';', $p['ad_image_urls']) : ($p['ad_image_urls'] ?? ''),
                            'ad_video_urls' => is_array($p['ad_video_urls'] ?? null) ? implode(';', $p['ad_video_urls']) : ($p['ad_video_urls'] ?? ''),
                            'price_1' => strval($p['price_1'] ?? $p['price'] ?? '0'),
                            'badge_algorithm' => $p['badge_algorithm'] ?? 'winning',
                            'active_ads' => isset($p['active_ads']) ? (bool)$p['active_ads'] : true,
                            'origin' => 'Winning',
                            'api_version' => $apiVersion,
                            'snapshot_id' => $snapshotId,
                        ];

                        if ($existing) {
                            $this->model->update($existing['id'], $dataToSave);
                            $stats['updated']++;
                        } else {
                            $this->model->insert($dataToSave);
                            $stats['inserted']++;
                        }
                    }
                }
            } else {
                $stats['failed'] = true;
            }
        } catch (\Exception $e) {
            $stats['failed'] = true;
        }
        return $stats;
    }

    private function syncInternationalProducts($origin): array
    {
        $stats = ['inserted' => 0, 'updated' => 0, 'failed' => false];
        $input = [
            "0" => [
                "json" => null,
                "meta" => [
                    "values" => ["undefined"]
                ]
            ]
        ];

        $apiVersion = $this->extractVersion($input);
        $endpoint = ($origin === 'Japan') ? 'data.japanProducts' : 'data.chinaProducts';
        $url = "https://www.overviewdata.io/api/trpc/{$endpoint}?batch=1&input=" . urlencode(json_encode($input));

        try {
            $response = $this->client->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'application/json'
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                $rawBody = $response->getBody();
                $data = json_decode($rawBody, true);
                $base = is_array($data) ? ($data[0] ?? null) : $data;
                $rawList = $base['result']['data']['json'] ?? [];

                if (is_array($rawList)) {
                    // Save snapshot before upserting products
                    $snapshotId = $this->saveSnapshot($origin, $apiVersion, $rawBody, count($rawList));

                    foreach ($rawList as $p) {
                        $productUrl = $p['product_url'] ?? $p['projectUrl'] ?? $p['productUrl'] ?? $p['url'] ?? '';
                        if (!empty($productUrl) && !str_starts_with($productUrl, 'http')) {
                            $productUrl = 'https://' . $productUrl;
                        }
                        $title = $p['product_title'] ?? $p['title'] ?? $p['name'] ?? "منتج من {$origin}";

                        $existing = $this->model->where('product_url', $productUrl)
                                          ->where('origin', $origin)
                                          ->first();

                        $dataToSave = [
                            'title' => $title,
                            'product_url' => $productUrl,
                            'origin' => $origin,
                            'api_version' => $apiVersion,
                            'snapshot_id' => $snapshotId,
                            'country' => ($origin === 'Japan') ? 'JP' : 'CN',
                            'ad_image_urls' => $p['product_image'] ?? $p['product_image_url'] ?? $p['imageUrl'] ?? $p['image'] ?? '',
                            'active_ads' => true
                        ];

                        if ($origin === 'Japan') {
                            $dataToSave['collected_money'] = strval($p['collected_money'] ?? '');
                            $dataToSave['collected_supporter'] = strval($p['collected_supporter'] ?? '');
                            $dataToSave['remaining_days'] = strval($p['remaining_days'] ?? '');
                            $dataToSave['price_1'] = strval($p['collected_money'] ?? '');
                        } else {
                            $dataToSave['price_1'] = strval($p['product_price'] ?? '');
                            $dataToSave['sold'] = strval($p['sold'] ?? '');
                            $dataToSave['moq'] = strval($p['moq'] ?? '');
                        }

                        if ($existing) {
                            $this->model->update($existing['id'], $dataToSave);
                            $stats['updated']++;
                        } else {
                            $this->model->insert($dataToSave);
                            $stats['inserted']++;
                        }
                    }
                }
            } else {
                $stats['failed'] = true;
            }
        } catch (\Exception $e) {
            $stats['failed'] = true;
        }
        return $stats;
    }

    public function fetchAndSaveTrpcUrl(string $url): ?array
    {
        try {
            $response = $this->client->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'application/json'
                ]
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $rawBody = $response->getBody();
            $data = json_decode($rawBody, true);
            
            // Now parse it and save it to the DB
            $base = is_array($data) ? ($data[0] ?? null) : $data;
            $targetData = $base['result']['data']['json'] ?? null;
            
            if ($targetData) {
                // Determine origin
                $origin = 'Local';
                if (strpos($url, 'winingProducts') !== false) {
                    $origin = 'Winning';
                }
                
                // Try to extract version from the URL query param or data
                $apiVersion = null;
                parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $queryParams);
                if (isset($queryParams['input'])) {
                    $inputDecoded = json_decode($queryParams['input'], true);
                    if (is_array($inputDecoded)) {
                        $apiVersion = $this->extractVersion($inputDecoded);
                    }
                }
                
                $rawList = $targetData['productsEntries'] ?? $targetData['results'] ?? $targetData;
                if (!is_array($rawList) && isset($targetData['results'])) {
                    $rawList = $targetData['results'];
                }
                if (is_array($rawList)) {
                    // Save snapshot before upserting products
                    $snapshotId = $this->saveSnapshot($origin, $apiVersion, $rawBody, count($rawList));

                    foreach ($rawList as $p) {
                        $productUrl = $p['productUrl'] ?? $p['product_url'] ?? '';
                        if (empty($productUrl)) continue;
                        $title = $p['title'] ?? $p['product_title'] ?? 'بدون عنوان';
                        
                        $existing = $this->model->where('product_url', $productUrl)
                                          ->where('origin', $origin)
                                          ->first();
                                           
                        $dataToSave = [
                            'title' => $title,
                            'product_url' => $productUrl,
                            'country' => $p['country'] ?? '',
                            'algo' => $p['algorithm'] ?? $p['algo'] ?? ($origin === 'Winning' ? 'winning' : 'new'),
                            'ad_start_date' => $this->cleanDate($p['ad_start_date'] ?? null),
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
                            'api_version' => $apiVersion,
                            'snapshot_id' => $snapshotId,
                        ];
                        
                        if ($origin === 'Winning') {
                            $dataToSave['badge_algorithm'] = $p['badge_algorithm'] ?? 'winning';
                        }
                        
                        if ($existing) {
                            $this->model->update($existing['id'], $dataToSave);
                        } else {
                            $this->model->insert($dataToSave);
                        }
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
            }
            
            return $data;
        } catch (\Exception $e) {
            log_message('error', 'Error in fetchAndSaveTrpcUrl: ' . $e->getMessage());
            return null;
        }
    }

    private function extractVersion(array $input): ?string
    {
        if (isset($input[0]['json']['v'])) {
            return $input[0]['json']['v'];
        }
        return null;
    }

    private function saveSnapshot(string $origin, ?string $apiVersion, string $rawJson, int $productCount): ?int
    {
        if ($apiVersion !== null) {
            // Dedup by origin + api_version
            $existing = $this->snapshotModel
                ->where('origin', $origin)
                ->where('api_version', $apiVersion)
                ->first();
            if ($existing) {
                // Update raw_json and product_count in case data changed
                $this->snapshotModel->update($existing['id'], [
                    'raw_json'      => $rawJson,
                    'product_count' => $productCount,
                ]);
                return $existing['id'];
            }
        } else {
            // No version: replace the latest null-version snapshot for this origin
            $existing = $this->snapshotModel
                ->where('origin', $origin)
                ->where('api_version IS NULL')
                ->orderBy('id', 'DESC')
                ->first();
            if ($existing) {
                $this->snapshotModel->update($existing['id'], [
                    'raw_json'      => $rawJson,
                    'product_count' => $productCount,
                ]);
                return $existing['id'];
            }
        }

        $data = [
            'origin'        => $origin,
            'api_version'   => $apiVersion,
            'raw_json'      => $rawJson,
            'product_count' => $productCount,
        ];
        return $this->snapshotModel->insert($data) ? $this->snapshotModel->getInsertID() : null;
    }

    private function cleanDate($dateStr)
    {
        if (empty($dateStr) || $dateStr === '--') {
            return null;
        }
        $timestamp = strtotime($dateStr);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }
}

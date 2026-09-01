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
        $url = 'https://www.overviewdata.io/api/trpc/data.insights?batch=1&input=' . urlencode(json_encode($input, JSON_FORCE_OBJECT));

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
                    $snapInfo = $this->saveSnapshot('Local', $apiVersion, $rawBody, count($rawList));
                    if (!empty($snapInfo['is_duplicate'])) {
                        return $stats; // Skip products table inserts and updates if duplicate
                    }
                    $snapshotId = $snapInfo['id'] ?? null;

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
        $url = 'https://www.overviewdata.io/api/trpc/data.winingProducts?batch=1&input=' . urlencode(json_encode($input, JSON_FORCE_OBJECT));

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
                    $snapInfo = $this->saveSnapshot('Winning', $apiVersion, $rawBody, count($rawList));
                    if (!empty($snapInfo['is_duplicate'])) {
                        return $stats; // Skip products table inserts and updates if duplicate
                    }
                    $snapshotId = $snapInfo['id'] ?? null;

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
        $url = "https://www.overviewdata.io/api/trpc/{$endpoint}?batch=1&input=" . urlencode(json_encode($input, JSON_FORCE_OBJECT));

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
                    $snapInfo = $this->saveSnapshot($origin, $apiVersion, $rawBody, count($rawList));
                    if (!empty($snapInfo['is_duplicate'])) {
                        return $stats; // Skip products table inserts and updates if duplicate
                    }
                    $snapshotId = $snapInfo['id'] ?? null;

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
                $body = $response->getBody();
                log_message('error', 'fetchAndSaveTrpcUrl: External API returned HTTP ' . $response->getStatusCode() . ' - ' . substr($body, 0, 300));
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
                
                // Try to extract version and country from the URL query param or data
                $apiVersion = null;
                $requestedCountry = null;
                parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $queryParams);
                if (isset($queryParams['input'])) {
                    $inputDecoded = json_decode($queryParams['input'], true);
                    if (is_array($inputDecoded)) {
                        $apiVersion = $this->extractVersion($inputDecoded);
                        $firstKey = array_key_first($inputDecoded);
                        if ($firstKey !== null && isset($inputDecoded[$firstKey]['json']) && is_array($inputDecoded[$firstKey]['json'])) {
                            $requestedCountry = $inputDecoded[$firstKey]['json']['country'] ?? null;
                        }
                    }
                }
                if (empty($requestedCountry) && isset($queryParams['country'])) {
                    $requestedCountry = $queryParams['country'];
                }
                if (empty($apiVersion) && isset($queryParams['v'])) {
                    $apiVersion = $queryParams['v'];
                }
                
                $rawList = $targetData['productsEntries'] ?? $targetData['results'] ?? $targetData;
                if (!is_array($rawList) && isset($targetData['results'])) {
                    $rawList = $targetData['results'];
                }
                if (is_array($rawList)) {
                    // Enrich items with country if missing from individual product objects
                    if (!empty($requestedCountry)) {
                        foreach ($rawList as &$item) {
                            if (is_array($item) && empty($item['country'])) {
                                $item['country'] = $requestedCountry;
                            }
                        }
                        unset($item);

                        if (is_array($data) && isset($data[0])) {
                            if (isset($data[0]['result']['data']['json']['productsEntries'])) {
                                $data[0]['result']['data']['json']['productsEntries'] = $rawList;
                            } elseif (isset($data[0]['data']['json']['productsEntries'])) {
                                $data[0]['data']['json']['productsEntries'] = $rawList;
                            }
                        }
                        $rawBody = json_encode($data, JSON_UNESCAPED_UNICODE);
                    }

                    // Save snapshot with content hash deduplication
                    $snapInfo = $this->saveSnapshot($origin, $apiVersion, $rawBody, count($rawList));
                    $snapshotId = $snapInfo['id'];

                    // Only upsert product details if this is a new snapshot/hash or first time / merged
                    if (!$snapInfo['is_duplicate']) {
                        foreach ($rawList as $p) {
                            $productUrl = $p['productUrl'] ?? $p['product_url'] ?? '';
                            if (empty($productUrl)) continue;
                            $title = $p['title'] ?? $p['product_title'] ?? 'بدون عنوان';
                            
                            $existing = $this->model->where('product_url', $productUrl)
                                              ->where('origin', $origin)
                                              ->first();

                            $newCountry = $p['country'] ?? '';
                            if ($existing && !empty($existing['country']) && !empty($newCountry)) {
                                $existingCountries = array_map('trim', explode(';', $existing['country']));
                                if (!in_array($newCountry, $existingCountries, true)) {
                                    $newCountry = $existing['country'] . ';' . $newCountry;
                                }
                            }
                                               
                            $dataToSave = [
                                'title' => $title,
                                'product_url' => $productUrl,
                                'country' => $newCountry,
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
            
            if (is_array($data) && isset($data[0])) {
                $data[0]['is_duplicate'] = $snapInfo['is_duplicate'] ?? false;
                $data[0]['snapshot_id'] = $snapInfo['id'] ?? null;
            }

            return $data;
        } catch (\Throwable $e) {
            log_message('error', 'Error in fetchAndSaveTrpcUrl: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return null;
        }
    }

    private function extractVersion(array $input): ?string
    {
        $firstKey = array_key_first($input);
        if ($firstKey !== null && isset($input[$firstKey]['json']['v'])) {
            return $input[$firstKey]['json']['v'];
        }
        if (isset($input[0]['json']['v'])) {
            return $input[0]['json']['v'];
        }
        return null;
    }

    private function saveSnapshot(string $origin, ?string $apiVersion, string $rawJson, int $productCount): array
    {
        $dataHash = md5($rawJson);

        // 1. Check if a snapshot with matching origin + api_version exists (flexible v prefix matching)
        if ($apiVersion !== null && $apiVersion !== '') {
            $cleanVer = ltrim($apiVersion, 'v');
            $existingVersion = $this->snapshotModel
                ->where('origin', $origin)
                ->groupStart()
                    ->where('api_version', $apiVersion)
                    ->orWhere('api_version', 'v' . $cleanVer)
                    ->orWhere('api_version', $cleanVer)
                ->groupEnd()
                ->first();
            if ($existingVersion) {
                // Merge new rawJson into existing raw_json
                $existingRawJson = \App\Libraries\Storage\SnapshotStorageHelper::decompress($existingVersion['raw_json'] ?? '');
                $mergedJson = $this->mergeRawJsonSnapshots($existingRawJson, $rawJson);

                $decodedMerged = json_decode($mergedJson, true);
                $target = is_array($decodedMerged) ? ($decodedMerged[0] ?? $decodedMerged) : [];
                $targetJson = $target['result']['data']['json'] ?? $target['data']['json'] ?? $target['json'] ?? $target;
                $entries = $targetJson['productsEntries'] ?? $targetJson['results'] ?? [];
                $newProductCount = is_array($entries) ? count($entries) : $productCount;

                $newHash = md5($mergedJson);

                $this->snapshotModel->update($existingVersion['id'], [
                    'raw_json'      => \App\Libraries\Storage\SnapshotStorageHelper::compress($mergedJson),
                    'product_count' => $newProductCount,
                    'data_hash'     => $newHash,
                ]);

                return [
                    'id' => (int)$existingVersion['id'],
                    'is_duplicate' => false,
                    'is_merged' => true,
                    'data_hash' => $newHash
                ];
            }
        }

        // 2. Check if a snapshot with identical content hash exists
        $existingHash = $this->snapshotModel
            ->where('origin', $origin)
            ->where('data_hash', $dataHash)
            ->first();

        if ($existingHash) {
            // DO NOT overwrite existing snapshot's api_version label if it already has one!
            if (!empty($apiVersion) && empty($existingHash['api_version'])) {
                $this->snapshotModel->update($existingHash['id'], [
                    'api_version'   => $apiVersion,
                    'product_count' => $productCount,
                ]);
            }
            return [
                'id' => (int)$existingHash['id'],
                'is_duplicate' => true,
                'data_hash' => $dataHash
            ];
        }

        // 3. Create a new snapshot for this version
        $data = [
            'origin'        => $origin,
            'api_version'   => $apiVersion,
            'raw_json'      => \App\Libraries\Storage\SnapshotStorageHelper::compress($rawJson),
            'product_count' => $productCount,
            'data_hash'     => $dataHash,
        ];
        $inserted = $this->snapshotModel->insert($data);
        $newId = $inserted ? $this->snapshotModel->getInsertID() : null;

        return [
            'id' => $newId,
            'is_duplicate' => false,
            'data_hash' => $dataHash
        ];
    }

    private function mergeRawJsonSnapshots(string $existingRawJson, string $newRawJson): string
    {
        $existingRawJson = \App\Libraries\Storage\SnapshotStorageHelper::decompress($existingRawJson);
        $existingDecoded = json_decode($existingRawJson, true);
        $newDecoded = json_decode($newRawJson, true);

        if (!is_array($existingDecoded) || !is_array($newDecoded)) {
            return !empty($newRawJson) ? $newRawJson : $existingRawJson;
        }

        $extractEntries = function($decoded) {
            $base = is_array($decoded) && isset($decoded[0]) ? $decoded[0] : $decoded;
            
            if (isset($base['result']['data']['json'])) {
                $target = $base['result']['data']['json'];
                if (isset($target['productsEntries']) && is_array($target['productsEntries'])) {
                    return ['entries' => $target['productsEntries'], 'path' => 'result.data.json.productsEntries'];
                }
                if (isset($target['results']) && is_array($target['results'])) {
                    return ['entries' => $target['results'], 'path' => 'result.data.json.results'];
                }
            }
            if (isset($base['data']['json'])) {
                $target = $base['data']['json'];
                if (isset($target['productsEntries']) && is_array($target['productsEntries'])) {
                    return ['entries' => $target['productsEntries'], 'path' => 'data.json.productsEntries'];
                }
                if (isset($target['results']) && is_array($target['results'])) {
                    return ['entries' => $target['results'], 'path' => 'data.json.results'];
                }
            }
            if (isset($base['json'])) {
                $target = $base['json'];
                if (isset($target['productsEntries']) && is_array($target['productsEntries'])) {
                    return ['entries' => $target['productsEntries'], 'path' => 'json.productsEntries'];
                }
                if (isset($target['results']) && is_array($target['results'])) {
                    return ['entries' => $target['results'], 'path' => 'json.results'];
                }
            }
            if (isset($base['productsEntries']) && is_array($base['productsEntries'])) {
                return ['entries' => $base['productsEntries'], 'path' => 'productsEntries'];
            }
            if (isset($base['results']) && is_array($base['results'])) {
                return ['entries' => $base['results'], 'path' => 'results'];
            }
            return ['entries' => [], 'path' => null];
        };

        $existingData = $extractEntries($existingDecoded);
        $newData = $extractEntries($newDecoded);

        $existingEntries = $existingData['entries'];
        $newEntries = $newData['entries'];
        $path = $existingData['path'] ?? $newData['path'] ?? 'result.data.json.productsEntries';

        if (empty($newEntries)) {
            return $existingRawJson;
        }

        $productMap = [];
        foreach ($existingEntries as $p) {
            $url = $p['productUrl'] ?? $p['product_url'] ?? null;
            if ($url) {
                $productMap[$url] = $p;
            } else {
                $productMap[] = $p;
            }
        }

        foreach ($newEntries as $p) {
            $url = $p['productUrl'] ?? $p['product_url'] ?? null;
            if ($url && isset($productMap[$url])) {
                $oldCountry = $productMap[$url]['country'] ?? '';
                $newCountry = $p['country'] ?? '';
                if (!empty($oldCountry) && !empty($newCountry)) {
                    $oldList = array_map('trim', explode(';', $oldCountry));
                    if (!in_array($newCountry, $oldList, true)) {
                        $p['country'] = $oldCountry . ';' . $newCountry;
                    } else {
                        $p['country'] = $oldCountry;
                    }
                } elseif (empty($newCountry) && !empty($oldCountry)) {
                    $p['country'] = $oldCountry;
                }
                $productMap[$url] = array_merge($productMap[$url], $p);
            } elseif ($url) {
                $productMap[$url] = $p;
            } else {
                $productMap[] = $p;
            }
        }

        $mergedEntries = array_values($productMap);

        $parts = explode('.', $path);
        if (is_array($existingDecoded) && isset($existingDecoded[0])) {
            $curr = &$existingDecoded[0];
        } else {
            $curr = &$existingDecoded;
        }
        foreach ($parts as $pKey) {
            if (!isset($curr[$pKey]) || !is_array($curr[$pKey])) {
                $curr[$pKey] = [];
            }
            $curr = &$curr[$pKey];
        }
        $curr = $mergedEntries;

        return json_encode($existingDecoded, JSON_UNESCAPED_UNICODE);
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

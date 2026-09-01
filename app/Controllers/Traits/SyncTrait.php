<?php

namespace App\Controllers\Traits;

use App\Models\ProductModel;
use App\Models\SnapshotModel;
use App\Models\SettingModel;
use App\Services\SyncService;
use App\Libraries\Storage\SnapshotStorageHelper;

trait SyncTrait
{
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

    private function isCountryDataAvailableInDbOrSnapshot(string $origin, string $requestedVersion, ?string $requestedCountry, $model, $snapshotModel): bool
    {
        if (empty($requestedVersion)) {
            return false;
        }

        $requestedCountries = !empty($requestedCountry)
            ? array_map('strtoupper', array_map('trim', explode(';', $requestedCountry)))
            : [];

        if (empty($requestedCountries)) {
            $hasProd = $model->where('origin', $origin)->where('api_version', $requestedVersion)->first();
            if ($hasProd) return true;
            
            $cleanVer = ltrim($requestedVersion, 'v');
            $hasSnap = $snapshotModel->where('origin', $origin)
                                     ->groupStart()
                                       ->where('api_version', $requestedVersion)
                                       ->orWhere('api_version', 'v' . $cleanVer)
                                       ->orWhere('api_version', $cleanVer)
                                     ->groupEnd()
                                     ->first();
            return (bool)$hasSnap;
        }

        // 1. Check products table for requested countries
        $cleanVer = ltrim($requestedVersion, 'v');
        $dbProducts = $model->where('origin', $origin)
                            ->groupStart()
                              ->where('api_version', $requestedVersion)
                              ->orWhere('api_version', 'v' . $cleanVer)
                              ->orWhere('api_version', $cleanVer)
                            ->groupEnd()
                            ->findAll();

        $foundCountriesInDb = [];
        if (!empty($dbProducts)) {
            foreach ($dbProducts as $p) {
                $pCountries = array_map('strtoupper', array_map('trim', explode(';', $p['country'] ?? '')));
                foreach ($requestedCountries as $reqC) {
                    if (in_array($reqC, $pCountries, true)) {
                        $foundCountriesInDb[$reqC] = true;
                    }
                }
            }
            if (count($foundCountriesInDb) === count($requestedCountries)) {
                return true;
            }
        }

        // 2. Check data_snapshots raw_json for requested countries
        $snapshot = $snapshotModel->where('origin', $origin)
                                  ->groupStart()
                                    ->where('api_version', $requestedVersion)
                                    ->orWhere('api_version', 'v' . $cleanVer)
                                    ->orWhere('api_version', $cleanVer)
                                  ->groupEnd()
                                  ->orderBy('id', 'DESC')
                                  ->first();

        if ($snapshot && !empty($snapshot['raw_json'])) {
            $decoded = json_decode(SnapshotStorageHelper::decompress($snapshot['raw_json']), true);
            $base = is_array($decoded) && isset($decoded[0]) ? $decoded[0] : $decoded;
            $target = $base['result']['data']['json'] ?? $base['data']['json'] ?? $base['json'] ?? $base;
            $entries = $target['productsEntries'] ?? $target['results'] ?? [];

            if (is_array($entries) && !empty($entries)) {
                $foundCountriesInSnap = [];
                foreach ($entries as $entry) {
                    $entryCountry = strtoupper(trim($entry['country'] ?? ''));
                    if (!empty($entryCountry)) {
                        $entryCountries = array_map('trim', explode(';', $entryCountry));
                        foreach ($requestedCountries as $reqC) {
                            if (in_array($reqC, $entryCountries, true)) {
                                $foundCountriesInSnap[$reqC] = true;
                            }
                        }
                    }
                }
                if (count($foundCountriesInSnap) === count($requestedCountries)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function syncTrpc()
    {
        $url = $this->request->getVar('url');
        if (empty($url)) {
            return $this->fail('URL is required');
        }

        $origin = 'Local';
        if (strpos($url, 'winingProducts') !== false) {
            $origin = 'Winning';
        }

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

        $settingModel = new SettingModel();
        $dataSourceSetting = $settingModel->where('key', 'data-source')->first();
        $dataSource = $dataSourceSetting ? $dataSourceSetting['value'] : 'database';

        $model = new ProductModel();
        $snapshotModel = new SnapshotModel();

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
        
        // 1. Check if the requested version AND requested countries ALREADY exist in local products DB or data_snapshots
        if ($requestedVersion !== null && $requestedVersion !== '') {
            $hasLocalData = $this->isCountryDataAvailableInDbOrSnapshot($origin, $requestedVersion, $requestedCountry, $model, $snapshotModel);

            if ($hasLocalData) {
                $cleanVer = ltrim($requestedVersion, 'v');
                $versionProducts = $model->where('origin', $origin)
                                         ->groupStart()
                                           ->where('api_version', $requestedVersion)
                                           ->orWhere('api_version', 'v' . $cleanVer)
                                           ->orWhere('api_version', $cleanVer)
                                         ->groupEnd()
                                         ->orderBy('ads_count', 'DESC')
                                         ->findAll();
                
                if (!empty($versionProducts)) {
                    $finalProducts = $versionProducts;
                    if ($requestedCountry !== null && $requestedCountry !== '') {
                        $requestedCountries = array_map('strtoupper', array_map('trim', explode(';', $requestedCountry)));
                        $finalProducts = array_values(array_filter($versionProducts, function($p) use ($requestedCountries) {
                            $prodCountries = array_map('strtoupper', array_map('trim', explode(';', $p['country'] ?? '')));
                            return !empty(array_intersect($prodCountries, $requestedCountries));
                        }));
                    }

                    if (!empty($finalProducts)) {
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

                // Check if a snapshot with this api_version exists in data_snapshots table
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
                    $decodedData = json_decode(SnapshotStorageHelper::decompress($snapshot['raw_json']), true);
                    if (is_array($decodedData) && isset($decodedData[0])) {
                        if (!empty($requestedCountry)) {
                            $countries = explode(';', $requestedCountry);
                            $this->filterSnapshotByCountries($decodedData, $countries);
                        }

                        $jsonTarget = $decodedData[0]['result']['data']['json'] ?? $decodedData[0]['data']['json'] ?? $decodedData[0]['json'] ?? $decodedData[0];
                        $entries = $jsonTarget['productsEntries'] ?? $jsonTarget['results'] ?? [];

                        if (!empty($entries)) {
                            $decodedData[0]['source'] = 'database';
                            $decodedData[0]['is_duplicate'] = true;
                            return $this->respond($decodedData);
                        }
                    }
                }
            }
        }

        // 2. Data for requested country/version is missing locally -> Fetch from External API
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
            if (!empty($requestedCountry)) {
                $countries = explode(';', $requestedCountry);
                $this->filterSnapshotByCountries($data, $countries);
            }
            return $this->respond($data);
        }

        // 3. Fallback if no version match: return available DB products
        $fallbackProducts = $model->where('origin', $origin)->orderBy('ads_count', 'DESC')->findAll();
        if (empty($fallbackProducts)) {
            $fallbackProducts = $model->orderBy('ads_count', 'DESC')->findAll();
        }

        if (!empty($fallbackProducts) && !empty($requestedCountry)) {
            $requestedCountries = array_map('strtoupper', array_map('trim', explode(';', $requestedCountry)));
            $fallbackProducts = array_values(array_filter($fallbackProducts, function($p) use ($requestedCountries) {
                $prodCountries = array_map('strtoupper', array_map('trim', explode(';', $p['country'] ?? '')));
                return !empty(array_intersect($prodCountries, $requestedCountries));
            }));
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
            $formatted['source'] = 'database';
            return $this->respond([$formatted]);
        }

        return $this->fail('Failed to fetch or parse tRPC data from external API and no database data available');
    }
}

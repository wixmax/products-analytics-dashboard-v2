<?php

namespace App\Controllers\Traits;

use App\Models\ProductModel;
use App\Models\SnapshotModel;

trait SnapshotTrait
{
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

        // Decompress if compressed
        if (!empty($snapshot['raw_json'])) {
            $snapshot['raw_json'] = \App\Libraries\Storage\SnapshotStorageHelper::decompress($snapshot['raw_json']);
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

        $rawJson = \App\Libraries\Storage\SnapshotStorageHelper::decompress($snapshot['raw_json'] ?? '');
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

                $decoded = json_decode(\App\Libraries\Storage\SnapshotStorageHelper::decompress($rawJson), true);
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

                $dataHash = $item['data_hash'] ?? $item['hash_md5'] ?? (!empty($rawJson) ? md5($rawJson) : null);

                // Prevent inserting duplicate snapshot
                $existing = null;
                if (!empty($dataHash)) {
                    $existing = $snapshotModel->where('origin', $origin)->where('data_hash', $dataHash)->first();
                }
                if (!$existing && !empty($apiVersion)) {
                    $existing = $snapshotModel->where('origin', $origin)->where('api_version', $apiVersion)->first();
                }
                if ($existing) {
                    continue; // Skip duplicate snapshot import
                }

                $dataToSave = [
                    'origin' => $origin,
                    'api_version' => $apiVersion,
                    'raw_json' => \App\Libraries\Storage\SnapshotStorageHelper::compress($rawJson),
                    'product_count' => $productCount,
                    'data_hash' => $dataHash,
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

        // 2. Single snapshot import
        $rawJson = $json['raw_json'] ?? $this->request->getVar('raw_json') ?? '';
        if (empty($rawJson)) {
            return $this->fail('raw_json is required');
        }

        $origin = $json['origin'] ?? $this->request->getVar('origin') ?? 'Local';
        $apiVersion = $json['api_version'] ?? $this->request->getVar('api_version') ?? '';

        // Validate JSON
        $decoded = json_decode(\App\Libraries\Storage\SnapshotStorageHelper::decompress($rawJson), true);
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

        $dataHash = $json['data_hash'] ?? $json['hash_md5'] ?? (!empty($rawJson) ? md5($rawJson) : null);

        // Prevent inserting duplicate snapshot
        $existing = null;
        if (!empty($dataHash)) {
            $existing = $snapshotModel->where('origin', $origin)->where('data_hash', $dataHash)->first();
        }
        if (!$existing && !empty($apiVersion)) {
            $existing = $snapshotModel->where('origin', $origin)->where('api_version', $apiVersion)->first();
        }
        if ($existing) {
            return $this->respond([
                'success' => true,
                'is_duplicate' => true,
                'message' => 'هذه اللقطة موجودة مسبقاً وتعتبر مكررة، لم يتم إضافة أي بيانات.',
                'id' => $existing['id']
            ]);
        }

        $dataToSave = [
            'origin' => $origin,
            'api_version' => $apiVersion,
            'raw_json' => \App\Libraries\Storage\SnapshotStorageHelper::compress($rawJson),
            'product_count' => $productCount,
            'data_hash' => $dataHash,
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

        $decoded = json_decode(\App\Libraries\Storage\SnapshotStorageHelper::decompress($rawJson), true);
        if ($decoded === null) {
            return $this->fail('Invalid JSON');
        }

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

        // Delete associated products that are not saved by users
        $productModel = new ProductModel();
        $productModel->where('snapshot_id', $id)
                     ->groupStart()
                         ->where('is_saved', false)
                         ->orWhere('is_saved IS NULL')
                     ->groupEnd()
                     ->delete();

        $snapshotModel->delete($id);

        return $this->respond([
            'success' => true,
            'message' => "Snapshot #{$id} and its associated products deleted"
        ]);
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
}

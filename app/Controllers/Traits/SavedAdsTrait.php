<?php

namespace App\Controllers\Traits;

use App\Models\ProductModel;
use App\Models\CollectionModel;
use App\Models\WatchedStoreModel;
use App\Libraries\TenantContext;

trait SavedAdsTrait
{
    public function saved()
    {
        $model = new ProductModel();
        
        $search = $this->request->getVar('search');
        $status = $this->request->getVar('status');
        $collection = $this->request->getVar('collection');
        $sort = $this->request->getVar('sort') ?? 'newest';

        $builder = $model->where('is_saved', true);

        $context = TenantContext::getInstance();
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

        $context = TenantContext::getInstance();
        $tenantId = $context->getTenantId();

        $existing = $model->where('product_url', $productUrl)
                          ->where('tenant_id', $tenantId)
                          ->first();

        if ($existing) {
            $currentlySaved = ($existing['is_saved'] === true || $existing['is_saved'] === 't' || $existing['is_saved'] === 1 || $existing['is_saved'] === '1' || $existing['is_saved'] === 'true');
            $newSavedState = !$currentlySaved;
            $updateData = [
                'is_saved' => $newSavedState,
                'saved_at' => $newSavedState ? date('Y-m-d H:i:s') : null,
                'collection' => $newSavedState ? ($existing['collection'] ?: 'عامة') : $existing['collection'],
            ];

            if ($newSavedState) {
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

        $context = TenantContext::getInstance();
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

        $context = TenantContext::getInstance();
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

        $context = TenantContext::getInstance();
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

        $context = TenantContext::getInstance();
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

        $context = TenantContext::getInstance();
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
        
        $context = TenantContext::getInstance();
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
        $model = new CollectionModel();
        $collections = $model->bypassTenant()->orderBy('id', 'ASC')->findAll();

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
        $model = new CollectionModel();
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
        $model = new CollectionModel();
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

        $productModel = new ProductModel();
        $context = TenantContext::getInstance();
        if ($context->hasTenant()) {
            $productModel->where('tenant_id', $context->getTenantId());
        }

        $productModel->where('collection', $name)->set(['collection' => 'عامة'])->update();

        return $this->respond(['success' => true]);
    }

    public function watchlist()
    {
        $model = new WatchedStoreModel();
        $stores = $model->findAll();
        return $this->respond(array_column($stores, 'domain'));
    }

    public function toggleWatchlist()
    {
        $model = new WatchedStoreModel();
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

    public function saveThumbnail()
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $productId = $json['product_id'] ?? null;
        $productUrl = $json['product_url'] ?? null;
        $imageData = $json['image_data'] ?? null;

        if (empty($imageData) || (empty($productId) && empty($productUrl))) {
            return $this->fail('product_id or product_url and image_data are required.');
        }

        if (!preg_match('/^data:image\/([\w\+\-]+);base64,/', $imageData, $type)) {
            return $this->fail('Invalid base64 image format.');
        }

        $rawExt = strtolower($type[1]);
        $ext = ($rawExt === 'jpeg') ? 'jpg' : $rawExt;

        $data = substr($imageData, strpos($imageData, ',') + 1);
        $data = base64_decode($data);
        if ($data === false) {
            return $this->fail('Base64 decode failed.');
        }

        $uploadDir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'video' . DIRECTORY_SEPARATOR . 'thumbnails';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $identifier = !empty($productId) ? 'id_' . $productId : 'url_' . md5($productUrl);
        $filename = 'thumb_' . $identifier . '.' . $ext;
        $filePath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

        file_put_contents($filePath, $data);

        $publicUrl = base_url('uploads/video/thumbnails/' . $filename);

        $model = new ProductModel();
        if (!empty($productId)) {
            $p = $model->find($productId);
            if ($p) {
                $existingImages = array_filter(explode(';', $p['ad_image_urls'] ?? ''));
                if (empty($existingImages)) {
                    $model->update($productId, ['ad_image_urls' => $publicUrl]);
                }
            }
        } elseif (!empty($productUrl) && trim($productUrl) !== '') {
            $products = $model->where('product_url', $productUrl)->findAll();
            foreach ($products as $p) {
                $existingImages = array_filter(explode(';', $p['ad_image_urls'] ?? ''));
                if (empty($existingImages)) {
                    $model->update($p['id'], ['ad_image_urls' => $publicUrl]);
                }
            }
        }

        return $this->respond([
            'success' => true,
            'thumbnail_url' => $publicUrl
        ]);
    }
}

<?php

namespace App\Controllers\Traits;

use App\Models\SettingModel;
use App\Models\SnapshotModel;
use App\Libraries\TenantContext;

trait SettingsTrait
{
    public function getSetting($key)
    {
        $model = new SettingModel();
        $setting = $model->where('key', $key)->first();
        if ($setting && !empty($setting['value']) && is_string($setting['value'])) {
            $decoded = json_decode($setting['value'], true);
            if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                $setting['value'] = $decoded;
            }
        }
        return $this->respond($setting ?: ['key' => $key, 'value' => null]);
    }

    public function saveSetting()
    {
        $model = new SettingModel();
        
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

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
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
                $context = TenantContext::getInstance();
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
                $context = TenantContext::getInstance();
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
                $context = TenantContext::getInstance();
                $tenantId = $context->getTenantId();
                
                $watchedQuery = $db->table('watched_stores');
                if ($tenantId !== null) {
                    $watchedQuery->where('tenant_id', $tenantId);
                }
                $watchedQuery->delete();
                break;
            case 'all':
                // Delete all products, collections, watched stores belonging to tenant
                $context = TenantContext::getInstance();
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

    public function deleteByDate()
    {
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            return $this->failForbidden('عذراً، هذه الخاصية مخصصة للمشرفين والمسؤولين فقط.');
        }

        $json = $this->request->getJSON(true);
        $targetDate = trim($json['date'] ?? $this->request->getVar('date') ?? '');

        if (empty($targetDate)) {
            return $this->fail('يرجى تحديد التاريخ بشكل صحيح.');
        }

        $db = \Config\Database::connect();
        $snapshotModel = new SnapshotModel();

        // 1. Get snapshots where api_version contains or equals targetDate
        $snapshots = $db->table('data_snapshots')
            ->groupStart()
                ->where('api_version', $targetDate)
                ->orLike('api_version', $targetDate)
            ->groupEnd()
            ->get()
            ->getResultArray();

        $snapshotIds = array_column($snapshots, 'id');

        // 2. Delete non-saved products where api_version matches OR snapshot_id is in snapshotIds
        $builder = $db->table('products');
        $builder->groupStart()
                    ->where('is_saved', false)
                    ->orWhere('is_saved IS NULL')
                ->groupEnd()
                ->groupStart()
                    ->where('api_version', $targetDate)
                    ->orLike('api_version', $targetDate);

        if (!empty($snapshotIds)) {
            $builder->orWhereIn('snapshot_id', $snapshotIds);
        }
        $builder->groupEnd();

        $deletedProducts = $builder->delete();

        // 3. Delete matching snapshots
        $deletedSnapshots = 0;
        if (!empty($snapshotIds)) {
            $snapshotModel->whereIn('id', $snapshotIds)->delete();
            $deletedSnapshots = count($snapshotIds);
        }

        return $this->respond([
            'success' => true,
            'message' => "تم حذف {$deletedProducts} منتج و {$deletedSnapshots} لقطة بيانات المرتبطة بالإصدار/التاريخ ({$targetDate}) بنجاح.",
            'deleted_products' => $deletedProducts,
            'deleted_snapshots' => $deletedSnapshots
        ]);
    }
}

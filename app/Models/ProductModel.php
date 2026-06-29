<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductModel extends Model
{
    protected $table            = 'products';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'title', 'product_url', 'price_1', 'price_2', 'price_3', 'price_4', 
        'algo', 'ads_count', 'unique_image_count', 'unique_video_count', 
        'avg_creatives', 'ads_per_unique_url', 'country', 'ad_start_date', 
        'ad_title', 'ad_body', 'ad_image_urls', 'ad_video_urls', 
        'badge_algorithm', 'active_ads', 'origin', 'collected_money', 
        'collected_supporter', 'remaining_days', 'sold', 'moq', 
        'is_saved', 'saved_at', 'rating', 'notes', 'saved_status', 'collection',
        'api_version', 'snapshot_id',
        'created_at', 'updated_at'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];
}

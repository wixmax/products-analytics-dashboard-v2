<?php

namespace App\Models;

use CodeIgniter\Model;

class AiProductAnalysisModel extends Model
{
    protected $table            = 'ai_product_analyses';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id', 'tenant_id', 'title', 'analysis_mode',
        'parameters_json', 'summary_stats_json', 'results_json',
        'snapshot_date', 'snapshot_id', 'provider',
        'created_at', 'updated_at'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}

<?php

namespace App\Controllers\Traits;

use App\Models\ProductModel;
use App\Models\AiProductAnalysisModel;
use App\Libraries\AiService;

trait AiAnalysisTrait
{
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

        // توليد تحليل الاستراتيجية الواقعي بناءً على بيانات المنتج والنشاط
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

        if (isset($activity['reactivations']) && intval($activity['reactivations']) > 0) {
            $analysis[] = "تم رصد أحداث إعادة تنشيط (Reactivations) بعد فترات خمول، وهي إشارة ذهبية تؤكد تفوق هذا المنتج تسويقياً واضطرار المنافس لإعادة تشغيله فور وصول شحنات جديدة.";
        }

        return [
            'badge' => $badge,
            'text' => implode(" ", $analysis)
        ];
    }

    public function aiAnalyze()
    {
        $aiService = new AiService();
        try {
            $userId = auth()->user()->id ?? auth()->id() ?? 1;
            $tenantId = session()->get('tenant_id') ?? null;

            $json = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];
            
            $provider = trim($json['provider'] ?? 'auto');
            $modelName = trim($json['model'] ?? '');
            $analysisMode = trim($json['analysis_mode'] ?? 'comprehensive');

            $params = [
                'provider' => $provider,
                'model' => $modelName,
                'analysis_mode' => $analysisMode,
                'ad_budget_total' => floatval($json['ad_budget_total'] ?? 5000),
                'season' => trim($json['season'] ?? 'auto'),
                'c_shipping_default' => floatval($json['c_shipping_default'] ?? 35),
                'return_rate' => floatval($json['return_rate'] ?? 0.20),
            ];

            @set_time_limit(180);
            $productsInput = $json['products'] ?? [];
            if (empty($productsInput)) {
                $model = new ProductModel();
                $productsInput = $model->where('origin', 'Winning')->findAll(30);
            }

            $requestedCountry = $json['requested_country'] ?? $json['country'] ?? $this->request->getVar('country');
            if (!empty($requestedCountry) && $requestedCountry !== 'all') {
                $countries = explode(';', $requestedCountry);
                $productsInput = array_values(array_filter($productsInput, function($p) use ($countries) {
                    $c = is_array($p) ? ($p['country'] ?? '') : ($p->country ?? '');
                    return in_array($c, $countries, true);
                }));
            }

            if (empty($productsInput)) {
                return $this->fail('لا توجد منتجات متاحة للتحليل في الوقت الحالي للدولة المحددة.');
            }

            $analysisModel = new AiProductAnalysisModel();
            $analysisOutput = $aiService->evaluateProducts($productsInput, $params);

            $evaluations = $analysisOutput['evaluations'] ?? [];
            $summaryStats = $analysisOutput['summary'] ?? [];
            $aiPoweredBy = $analysisOutput['ai_powered_by'] ?? 'Internal Engine';
            $summaryStats['ai_powered_by'] = $aiPoweredBy;

            $snapshotDate = trim($json['snapshot_date'] ?? '');
            if (empty($snapshotDate) || !preg_match('/^\d{4}-\d{2}-\d{2}/', $snapshotDate)) {
                $snapshotDate = date('Y-m-d');
            } else {
                $snapshotDate = substr($snapshotDate, 0, 10);
            }
            $summaryStats['snapshot_date'] = $snapshotDate;
            $detectedSeason = $summaryStats['detected_season'] ?? 'عام';

            $modeTitles = [
                'seasonal' => 'تقييم مواسم السوق',
                'ad_volume' => 'تقييم كثرة الإعلانات والطلب',
                'max_margin' => 'تقييم أعلى هامش ربح',
                'easy_logistics' => 'تقييم سهولة اللوجستيك',
                'comprehensive' => 'تقييم شامل 100 نقطة'
            ];
            $modeName = $modeTitles[$analysisMode] ?? 'تقييم شامل';
            $analysisTitle = "تحليل {$modeName} ({$detectedSeason}) - [{$snapshotDate}]";

            $savedId = null;
            try {
                $savedId = $analysisModel->insert([
                    'user_id' => $userId,
                    'tenant_id' => $tenantId,
                    'title' => $analysisTitle,
                    'analysis_mode' => $analysisMode,
                    'parameters_json' => json_encode($params, JSON_UNESCAPED_UNICODE),
                    'summary_stats_json' => json_encode($summaryStats, JSON_UNESCAPED_UNICODE),
                    'results_json' => json_encode($evaluations, JSON_UNESCAPED_UNICODE),
                    'snapshot_date' => $snapshotDate,
                    'provider' => $aiPoweredBy,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable $dbEx) {
                log_message('error', 'Failed to save AI analysis history: ' . $dbEx->getMessage());
            }

            return $this->respond([
                'success' => true,
                'analysis_id' => $savedId,
                'title' => $analysisTitle,
                'summary' => $summaryStats,
                'evaluations' => $evaluations,
                'ai_powered_by' => $analysisOutput['ai_powered_by'] ?? 'Internal Engine',
                'raw_input_payload' => $analysisOutput['raw_input_payload'] ?? null,
                'raw_output_response' => $analysisOutput['raw_output_response'] ?? null
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'AI Analyze Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return $this->respond([
                'success' => false,
                'error' => 'فشل إجراء التحليل بالذكاء الاصطناعي: ' . $e->getMessage(),
                'raw_input_payload' => $aiService->getLastInputPayload() ?? null,
                'raw_output_response' => $aiService->getLastOutputResponse() ?? null
            ], 400);
        }
    }

    public function aiHistory()
    {
        try {
            $userId = auth()->user()->id ?? auth()->id() ?? 1;
            $dateFilter = trim($this->request->getGet('date') ?? '');

            $db = \Config\Database::connect();
            $builder = $db->table('ai_product_analyses');
            $builder->where('user_id', $userId);

            if (!empty($dateFilter) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
                $startOfDay = $dateFilter . ' 00:00:00';
                $endOfDay   = $dateFilter . ' 23:59:59';

                $builder->groupStart()
                        ->where('snapshot_date', $dateFilter)
                        ->orGroupStart()
                            ->where('created_at >=', $startOfDay)
                            ->where('created_at <=', $endOfDay)
                        ->groupEnd()
                        ->orLike('parameters_json', $dateFilter)
                        ->orLike('summary_stats_json', $dateFilter)
                        ->groupEnd();
            }

            $history = $builder->orderBy('created_at', 'DESC')
                               ->limit(50)
                               ->get()
                               ->getResultArray();

            $list = array_map(function($item) {
                $summary = json_decode($item['summary_stats_json'] ?? '{}', true);
                $aiPoweredBy = $item['provider'] ?? $summary['ai_powered_by'] ?? 'Internal Engine';
                return [
                    'id' => $item['id'],
                    'title' => $item['title'],
                    'analysis_mode' => $item['analysis_mode'],
                    'parameters' => json_decode($item['parameters_json'] ?? '{}', true),
                    'summary' => $summary,
                    'ai_powered_by' => $aiPoweredBy,
                    'snapshot_date' => $item['snapshot_date'] ?? $summary['snapshot_date'] ?? null,
                    'created_at' => $item['created_at'],
                ];
            }, $history);

            return $this->respond([
                'success' => true,
                'history' => $list,
                'filter_date' => $dateFilter
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Error in aiHistory: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'error' => $e->getMessage(),
                'history' => []
            ], 500);
        }
    }

    public function aiHistoryDetail($id = null)
    {
        $userId = auth()->user()->id ?? auth()->id() ?? 1;
        $model = new AiProductAnalysisModel();
        $item = $model->where('id', $id)->where('user_id', $userId)->first();

        if (!$item) {
            return $this->failNotFound('Analysis record not found or unauthorized.');
        }

        return $this->respond([
            'success' => true,
            'analysis' => [
                'id' => $item['id'],
                'title' => $item['title'],
                'analysis_mode' => $item['analysis_mode'],
                'parameters' => json_decode($item['parameters_json'] ?? '{}', true),
                'summary' => json_decode($item['summary_stats_json'] ?? '{}', true),
                'evaluations' => json_decode($item['results_json'] ?? '[]', true),
                'created_at' => $item['created_at'],
            ]
        ]);
    }

    public function aiDeleteHistory($id = null)
    {
        $userId = auth()->user()->id ?? auth()->id() ?? 1;
        $model = new AiProductAnalysisModel();
        $item = $model->where('id', $id)->where('user_id', $userId)->first();

        if (!$item) {
            return $this->failNotFound('Analysis record not found.');
        }

        $model->delete($id);

        return $this->respond([
            'success' => true,
            'message' => 'Analysis record deleted successfully.'
        ]);
    }

    public function aiDeepAnalyze()
    {
        try {
            $json = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];

            $product = $json['product'] ?? [];
            if (empty($product) && !empty($json['product_id'])) {
                $productModel = new ProductModel();
                $foundProduct = $productModel->find($json['product_id']);
                if ($foundProduct) {
                    $product = is_array($foundProduct) ? $foundProduct : (array)$foundProduct;
                }
            }

            if (empty($product) && (!empty($json['product_title']) || !empty($json['title']))) {
                $titleToSearch = trim($json['product_title'] ?? $json['title'] ?? '');
                if ($titleToSearch !== '') {
                    $productModel = new ProductModel();
                    $foundProduct = $productModel->where('title', $titleToSearch)->orWhere('name', $titleToSearch)->first();
                    if ($foundProduct) {
                        $product = is_array($foundProduct) ? $foundProduct : (array)$foundProduct;
                    } else {
                        $product = [
                            'id' => $json['product_id'] ?? 0,
                            'title' => $titleToSearch,
                            'name' => $titleToSearch,
                            'price' => floatval($json['price_selling'] ?? 0),
                        ];
                    }
                }
            }

            if (empty($product)) {
                return $this->fail('لم يتم العثور على بيانات المنتج المطلوب تحليله.', 400);
            }

            $params = [
                'provider'             => trim($json['provider'] ?? 'auto'),
                'model'                => trim($json['model'] ?? ''),
                'price_selling'        => floatval($json['price_selling'] ?? $json['price'] ?? $product['price'] ?? 0),
                'c_wholesale'          => floatval($json['c_wholesale'] ?? 0),
                'c_shipping'           => floatval($json['c_shipping'] ?? 35),
                'c_packaging'          => floatval($json['c_packaging'] ?? 10),
                'stock_quantity'       => intval($json['stock_quantity'] ?? $json['stock_qty'] ?? 100),
                'total_ad_budget'      => floatval($json['total_ad_budget'] ?? $json['ad_budget'] ?? 1000),
                'target_daily_orders'  => isset($json['target_daily_orders']) ? intval($json['target_daily_orders']) : 0,
                'return_rate'          => floatval($json['return_rate'] ?? 0.20),
                'extra_notes'          => trim($json['extra_notes'] ?? ''),
            ];

            @set_time_limit(180);
            $aiService = new AiService();
            $result = $aiService->evaluateSingleProductDeep($product, $params);

            $savedId = null;
            try {
                $session = session();
                $userId = $session->get('user_id') ?? 1;
                $tenantId = $session->get('tenant_id') ?? 1;
                
                $analysisModel = new AiProductAnalysisModel();
                $productTitle = $product['title'] ?? $product['name'] ?? 'منتج غير معرف';
                $aiPoweredBy = $result['ai_powered_by'] ?? 'Internal Engine';

                $snapshotDate = trim($json['snapshot_date'] ?? '');
                if (empty($snapshotDate) || !preg_match('/^\d{4}-\d{2}-\d{2}/', $snapshotDate)) {
                    $snapshotDate = date('Y-m-d');
                } else {
                    $snapshotDate = substr($snapshotDate, 0, 10);
                }
                
                $saveData = [
                    'user_id'            => $userId,
                    'tenant_id'          => $tenantId,
                    'title'              => 'تحليل تفصيلي (Phase 2): ' . $productTitle,
                    'analysis_mode'      => 'phase2',
                    'summary_stats_json' => json_encode([
                        'phase'         => 2,
                        'product_title' => $productTitle,
                        'ai_powered_by' => $aiPoweredBy,
                        'financials'    => $result['financial_model'] ?? []
                    ], JSON_UNESCAPED_UNICODE),
                    'results_json'       => json_encode($result, JSON_UNESCAPED_UNICODE),
                    'snapshot_date'      => $snapshotDate,
                    'provider'           => $aiPoweredBy,
                    'created_at'         => date('Y-m-d H:i:s')
                ];
                
                $savedId = $analysisModel->insert($saveData);
            } catch (\Throwable $ex) {
                log_message('warning', 'Could not save Phase 2 analysis history: ' . $ex->getMessage());
            }

            return $this->respond([
                'success'  => true,
                'saved_id' => $savedId,
                'result'   => $result
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Exception in aiDeepAnalyze controller: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'error'   => 'فشل إجراء الدراسة التفصيلية للمنتج: ' . $e->getMessage()
            ], 500);
        }
    }

    public function aiPhase2History()
    {
        try {
            $session = session();
            $userId = $session->get('user_id') ?? 1;
            $tenantId = $session->get('tenant_id') ?? 1;

            $productTitle = trim($this->request->getGet('title') ?? '');
            $productId    = trim($this->request->getGet('product_id') ?? '');

            $analysisModel = new AiProductAnalysisModel();
            
            $items = $analysisModel->where('tenant_id', $tenantId)
                                   ->like('title', 'Phase 2')
                                   ->orderBy('created_at', 'DESC')
                                   ->findAll(100);

            $formatted = [];
            foreach ($items as $item) {
                $results = !empty($item['results_json']) ? json_decode($item['results_json'], true) : null;
                $summary = !empty($item['summary_stats_json']) ? json_decode($item['summary_stats_json'], true) : null;
                $params  = !empty($item['parameters_json']) ? json_decode($item['parameters_json'], true) : null;
                
                $itemProductId = $summary['product_id'] ?? $params['product_id'] ?? null;
                $itemProductTitle = $summary['product_title'] ?? str_replace('تحليل تفصيلي (Phase 2): ', '', $item['title']);
                $aiPoweredBy = $results['ai_powered_by'] ?? $summary['ai_powered_by'] ?? 'Internal Engine';

                $formatted[] = [
                    'id'            => $item['id'],
                    'title'         => $item['title'],
                    'product_title' => $itemProductTitle,
                    'product_id'    => $itemProductId,
                    'ai_powered_by' => $aiPoweredBy,
                    'created_at'    => $item['created_at'],
                    'summary'       => $summary,
                    'result'        => $results
                ];
            }

            $matchedForTitle = [];
            $hasFilter = (!empty($productTitle) || !empty($productId));

            if ($hasFilter && count($formatted) > 0) {
                $cleanTitle = mb_strtolower(trim(preg_replace('/^تحليل\s+تفصيلي\s*\(Phase\s*2\):\s*/ui', '', $productTitle)));

                foreach ($formatted as $f) {
                    $isMatch = false;

                    if (!empty($productId) && !empty($f['product_id']) && (string)$productId === (string)$f['product_id']) {
                        $isMatch = true;
                    }

                    if (!$isMatch && !empty($cleanTitle)) {
                        $itemCleanTitle = mb_strtolower(trim(preg_replace('/^تحليل\s+تفصيلي\s*\(Phase\s*2\):\s*/ui', '', $f['product_title'] ?? $f['title'] ?? '')));
                        
                        if ($itemCleanTitle === $cleanTitle) {
                            $isMatch = true;
                        } elseif (mb_strlen($cleanTitle) >= 3 && (str_contains($itemCleanTitle, $cleanTitle) || str_contains($cleanTitle, $itemCleanTitle))) {
                            $isMatch = true;
                        } elseif (mb_strlen($cleanTitle) >= 6 && str_contains($itemCleanTitle, mb_substr($cleanTitle, 0, 6))) {
                            $isMatch = true;
                        }
                    }

                    if ($isMatch) {
                        $matchedForTitle[] = $f;
                    }
                }
            }

            $historyToReturn = $hasFilter ? $matchedForTitle : $formatted;

            return $this->respond([
                'success'           => true,
                'filter_title'      => $productTitle,
                'filter_product_id' => $productId,
                'matched_for_title' => $matchedForTitle,
                'history'           => $historyToReturn,
                'all_history'       => $formatted
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'success' => false,
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}

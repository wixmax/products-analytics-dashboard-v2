<?php

namespace App\Libraries;

use App\Models\ProductModel;

class AiService
{
    protected string $provider;
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $config = $this->resolveConfig([]);
        $this->provider = $config['provider'];
        $this->apiKey   = $config['api_key'];
        $this->model    = $config['model'];
    }

    protected string $lastCallError = '';
    protected ?array $lastInputPayload = null;
    protected ?array $lastOutputResponse = null;

    public function getLastInputPayload(): ?array
    {
        return $this->lastInputPayload;
    }

    public function getLastOutputResponse(): ?array
    {
        return $this->lastOutputResponse;
    }

    /**
     * Check if an API key is non-empty and not a default placeholder like 'your-gemini-key-here'
     */
    protected function isValidApiKey(?string $key): bool
    {
        if (empty($key)) {
            return false;
        }
        $key = trim($key);
        if (str_starts_with($key, 'your-') || str_starts_with($key, 'your_') || str_starts_with($key, 'placeholder') || strlen($key) < 10) {
            return false;
        }
        return true;
    }

    /**
     * Resolve provider, API Key, and model from request params or specific ENV variables.
     * Note: API keys are strictly mapped per provider to prevent cross-provider key leakage.
     */
    public function resolveConfig(array $params = []): array
    {
        $settingModel = new \App\Models\SettingModel();
        $dbConfigRow = $settingModel->where('key', 'ai_providers_config')->first();
        $dbConfig = [];
        if (!empty($dbConfigRow['value'])) {
            $dbConfig = is_array($dbConfigRow['value']) ? $dbConfigRow['value'] : (json_decode($dbConfigRow['value'], true) ?: []);
        }

        $requestedProvider = strtolower(trim($params['provider'] ?? 'auto'));

        if ($requestedProvider === 'auto' || empty($requestedProvider)) {
            $provider = strtolower(trim($dbConfig['active_provider'] ?? env('AI_PROVIDER', 'openrouter')));
        } else {
            $provider = $requestedProvider;
        }

        if ($provider === 'internal') {
            return [
                'provider' => 'internal',
                'api_key'  => '',
                'model'    => 'internal-engine'
            ];
        }

        $providerDbConfig = $dbConfig['providers'][$provider] ?? [];

        // Get API Key based strictly on requested provider
        $apiKey = trim($params['api_key'] ?? '');
        if (!$this->isValidApiKey($apiKey)) {
            if ($this->isValidApiKey($providerDbConfig['api_key'] ?? '')) {
                $apiKey = trim($providerDbConfig['api_key']);
            } else {
                switch ($provider) {
                    case 'openrouter':
                        $apiKey = trim(env('OPENROUTER_API_KEY', ''));
                        break;
                    case 'openai':
                        $apiKey = trim(env('OPENAI_API_KEY', ''));
                        break;
                    case 'gemini':
                        $apiKey = trim(env('GEMINI_API_KEY', ''));
                        break;
                    case 'deepseek':
                        $apiKey = trim(env('DEEPSEEK_API_KEY', ''));
                        break;
                    case 'apiyi':
                        $apiKey = trim(env('APIYI_API_KEY', ''));
                        break;
                    default:
                        $apiKey = trim(env('AI_API_KEY', ''));
                        break;
                }
            }
        }

        // Get Model based on provider
        $model = trim($params['model'] ?? '');
        if (empty($model)) {
            if (!empty($providerDbConfig['active_model'])) {
                $model = trim($providerDbConfig['active_model']);
            } else {
                switch ($provider) {
                    case 'openrouter':
                        $model = trim(env('OPENROUTER_MODEL', env('AI_MODEL', 'openai/gpt-4o-mini')));
                        break;
                    case 'openai':
                        $model = trim(env('OPENAI_MODEL', env('AI_MODEL', 'gpt-4o-mini')));
                        break;
                    case 'gemini':
                        $model = trim(env('GEMINI_MODEL', env('AI_MODEL', 'gemini-2.5-flash')));
                        break;
                    case 'deepseek':
                        $model = trim(env('DEEPSEEK_MODEL', env('AI_MODEL', 'deepseek-chat')));
                        break;
                    case 'apiyi':
                        $model = trim(env('APIYI_MODEL', env('AI_MODEL', 'claude-sonnet-5')));
                        break;
                    default:
                        $model = trim(env('AI_MODEL', 'gpt-4o-mini'));
                        break;
                }
            }
        }

        $allowProviderFailover = isset($params['allow_provider_failover'])
            ? (bool)$params['allow_provider_failover']
            : (isset($dbConfig['allow_provider_failover']) ? (bool)$dbConfig['allow_provider_failover'] : false);

        return [
            'provider' => $provider,
            'api_key'  => $apiKey,
            'model'    => $model,
            'allow_provider_failover' => $allowProviderFailover,
            'allow_internal_fallback' => isset($dbConfig['allow_internal_fallback']) ? (bool)$dbConfig['allow_internal_fallback'] : true
        ];
    }

    /**
     * Sanitize product image fields to strip out base64 data URIs completely.
     * Keep ONLY valid HTTP/HTTPS URLs or relative paths.
     */
    protected function sanitizeProductImageUrls(array $products): array
    {
        foreach ($products as &$p) {
            if (!is_array($p)) continue;
            
            // Clean ad_image_urls array or string
            if (isset($p['ad_image_urls'])) {
                $rawUrls = is_array($p['ad_image_urls']) 
                    ? $p['ad_image_urls'] 
                    : explode(';', strval($p['ad_image_urls']));

                $cleanUrls = array_values(array_filter($rawUrls, function($url) {
                    if (!is_string($url)) return false;
                    $url = trim($url);
                    if (empty($url)) return false;
                    if (str_starts_with($url, 'data:image') || str_starts_with($url, 'data:') || str_contains($url, 'data:image')) return false;
                    return (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '/'));
                }));

                $p['ad_image_urls'] = $cleanUrls;
            }

            // Clean images array
            if (isset($p['images']) && is_array($p['images'])) {
                $p['images'] = array_values(array_filter($p['images'], function($url) {
                    if (!is_string($url)) return false;
                    $url = trim($url);
                    if (str_starts_with($url, 'data:image') || str_starts_with($url, 'data:')) return false;
                    return (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '/'));
                }));
            }

            // Clean single image_url / image
            if (isset($p['image_url']) && is_string($p['image_url'])) {
                $url = trim($p['image_url']);
                if (str_starts_with($url, 'data:image') || str_starts_with($url, 'data:')) {
                    $p['image_url'] = '';
                }
            }
            if (isset($p['image']) && is_string($p['image'])) {
                $url = trim($p['image']);
                if (str_starts_with($url, 'data:image') || str_starts_with($url, 'data:')) {
                    $p['image'] = '';
                }
            }
        }
        return $products;
    }

    /**
     * Main entry point to evaluate products via LLM API (Phase 1 Batch Screening) or Fallback Heuristic Engine
     */
    public function evaluateProducts(array $products, array $params): array
    {
        @set_time_limit(300);
        $products = $this->sanitizeProductImageUrls($products);
        $totalInputCount = count($products);

        if ($totalInputCount === 0) {
            return $this->buildEmptyResponse($params);
        }

        $config = $this->resolveConfig($params);
        $provider = $config['provider'];
        $apiKey   = $config['api_key'];
        $model    = $config['model'];
        $allowProviderFailover = $config['allow_provider_failover'] ?? false;
        $allowFallback         = $config['allow_internal_fallback'] ?? true;
        $adBudget              = floatval($params['ad_budget'] ?? $params['ad_budget_total'] ?? 5000);

        // 1) Configure batch size safely between 3 and 15 (default 10)
        $batchSize = intval($params['ai_batch_size'] ?? 10);
        $batchSize = max(3, min(15, $batchSize));

        // Assign explicit 1-based index to each product if not present
        foreach ($products as $idx => &$p) {
            if (is_array($p) && !isset($p['index'])) {
                $p['index'] = $idx + 1;
            }
        }
        unset($p);

        $batches = array_chunk($products, $batchSize);

        $allEvaluations = [];
        $providersUsed  = [];
        $overallErrors  = [];

        if ($this->isValidApiKey($apiKey) && $provider !== 'internal') {
            $this->provider = $provider;
            $this->apiKey   = $apiKey;
            $this->model    = $model;

            foreach ($batches as $bIdx => $batch) {
                $batchResult = $this->executeSingleBatchWithRetryAndFailover($batch, $params, $provider, $apiKey, $model, $allowProviderFailover);

                if ($batchResult && !empty($batchResult['evaluations'])) {
                    foreach ($batchResult['evaluations'] as $eval) {
                        $allEvaluations[] = $eval;
                    }
                    if (!empty($batchResult['provider_used'])) {
                        $providersUsed[] = $batchResult['provider_used'];
                    }
                } else {
                    if (!empty($this->lastCallError)) {
                        $overallErrors[] = "دفعة #" . ($bIdx + 1) . ": " . $this->lastCallError;
                    }
                }
            }
        } else {
            $overallErrors[] = "مفتاح API الخاص بالمزود '{$provider}' غير مفعّل أو غائب.";
        }

        // If we collected evaluations for products via LLMs
        if (!empty($allEvaluations)) {
            $effectiveProvider = !empty($providersUsed) ? implode(' / ', array_unique($providersUsed)) : strtoupper($provider) . " ({$model})";
            return $this->aggregateBatchResults($allEvaluations, $products, $params, $adBudget, $effectiveProvider);
        }

        // If no LLM evaluations were produced, handle errors & fallback
        $lastError = !empty($overallErrors) ? implode(' | ', array_unique($overallErrors)) : ($this->lastCallError ?: "تعذر تحليل الدفعات عبر المزود المختار.");

        if (!$allowFallback && $provider !== 'internal') {
            throw new \RuntimeException("فشل إجراء التحليل عبر المزود الخارجي (" . strtoupper($provider) . " / " . $model . "). (المحرك المحلي الاحتياطي Internal Market Engine معطل في إعدادات النظام). تفاصيل الخطأ: " . $lastError);
        }

        // Fallback to local heuristic engine
        log_message('warning', "AI Service: Falling back to Internal Market Engine due to: " . $lastError);
        $fallback = $this->runLocalHeuristicEngine($products, $params);
        $fallback['ai_powered_by'] = 'Internal Market Engine (Offline Fallback)';
        $fallback['raw_input_payload'] = [
            'provider' => 'internal',
            'engine' => 'Internal Market Heuristic Engine (Rule-based)',
            'products_count' => $totalInputCount,
            'params' => $params
        ];
        $fallback['raw_output_response'] = [
            'status' => 'local_fallback_executed',
            'evaluations_count' => count($fallback['evaluations'] ?? []),
            'last_error' => $lastError
        ];
        $this->normalizeBudgetFitFlags($fallback, $adBudget);
        return $fallback;
    }

    /**
     * Execute a single batch with single compact retry and optional provider failover
     */
    protected function executeSingleBatchWithRetryAndFailover(
        array $batch,
        array $params,
        string $primaryProvider,
        string $primaryKey,
        string $primaryModel,
        bool $allowProviderFailover
    ): ?array {
        // Step 1: Call Primary Provider
        $result = $this->callProviderForBatch($primaryProvider, $primaryKey, $primaryModel, $batch, $params, 'screening');

        // Step 2: Single Compact Retry if primary provider failed due to MAX_TOKENS or JSON parse error
        if (!$result) {
            log_message('info', "AI Service: Retrying batch with compact prompt for provider [{$primaryProvider}]. Reason: " . $this->lastCallError);
            $result = $this->callProviderForBatch($primaryProvider, $primaryKey, $primaryModel, $batch, $params, 'compact_retry');
        }

        if ($result && !empty($result['evaluations'])) {
            $result['provider_used'] = strtoupper($primaryProvider) . ' (' . $primaryModel . ')';
            return $result;
        }

        // Step 3: External Provider Failover ONLY IF explicitly enabled in system settings
        if ($allowProviderFailover) {
            $openrouterKey = trim(env('OPENROUTER_API_KEY', ''));
            $apiyiKey      = trim(env('APIYI_API_KEY', ''));

            // Failover Option 1: OpenRouter (with model google/gemini-2.5-flash)
            if ($this->isValidApiKey($openrouterKey)) {
                log_message('warning', "AI Service: Provider [{$primaryProvider}] call failed ({$this->lastCallError}). Attempting failover to OpenRouter...");
                $this->apiKey   = $openrouterKey;
                $this->provider = 'openrouter';
                $this->model    = 'google/gemini-2.5-flash';

                $failoverResult = $this->callOpenAiCompatibleApi(
                    'https://openrouter.ai/api/v1/chat/completions',
                    $batch,
                    $params,
                    [
                        'HTTP-Referer: ' . (env('app.baseURL', 'http://localhost:9090')),
                        'X-Title: Product Analytics Dashboard'
                    ],
                    'compact_retry'
                );

                if ($failoverResult && !empty($failoverResult['evaluations'])) {
                    $failoverResult['provider_used'] = 'OPENROUTER (google/gemini-2.5-flash)';
                    return $failoverResult;
                }
            }

            // Failover Option 2: APIyi
            if ($this->isValidApiKey($apiyiKey)) {
                log_message('warning', "AI Service: OpenRouter failover failed/unavailable. Attempting failover to APIyi...");
                $this->apiKey   = $apiyiKey;
                $this->provider = 'apiyi';
                $this->model    = 'deepseek-v4-flash';

                $failoverResult = $this->callOpenAiCompatibleApi(
                    'https://api.apiyi.com/v1/chat/completions',
                    $batch,
                    $params,
                    [],
                    'compact_retry'
                );

                if ($failoverResult && !empty($failoverResult['evaluations'])) {
                    $failoverResult['provider_used'] = 'APIYI (deepseek-v4-flash)';
                    return $failoverResult;
                }
            }
        }

        return null;
    }

    /**
     * Dispatcher to call specific provider for a batch
     */
    protected function callProviderForBatch(
        string $provider,
        string $apiKey,
        string $model,
        array $batch,
        array $params,
        string $promptMode = 'screening'
    ): ?array {
        $this->provider = $provider;
        $this->apiKey   = $apiKey;
        $this->model    = $model;

        if ($provider === 'gemini') {
            return $this->callGeminiApi($batch, $params, $promptMode);
        } elseif ($provider === 'deepseek') {
            return $this->callOpenAiCompatibleApi('https://api.deepseek.com/chat/completions', $batch, $params, [], $promptMode);
        } elseif ($provider === 'apiyi') {
            return $this->callOpenAiCompatibleApi('https://api.apiyi.com/v1/chat/completions', $batch, $params, [], $promptMode);
        } elseif ($provider === 'openrouter') {
            return $this->callOpenAiCompatibleApi(
                'https://openrouter.ai/api/v1/chat/completions',
                $batch,
                $params,
                [
                    'HTTP-Referer: ' . (env('app.baseURL', 'http://localhost:9090')),
                    'X-Title: Product Analytics Dashboard'
                ],
                $promptMode
            );
        } elseif ($provider === 'custom') {
            $endpoint = env('CUSTOM_AI_ENDPOINT', 'http://localhost:11434/v1/chat/completions');
            return $this->callOpenAiCompatibleApi($endpoint, $batch, $params, [], $promptMode);
        } else { // default to openai
            return $this->callOpenAiCompatibleApi('https://api.openai.com/v1/chat/completions', $batch, $params, [], $promptMode);
        }
    }

    /**
     * Merge evaluations from all batches, deduplicate, sort by score descending,
     * compute accurate local summary, and apply budget fit flags.
     */
    protected function aggregateBatchResults(
        array $allEvaluations,
        array $originalProducts,
        array $params,
        float $adBudgetTotal,
        string $effectiveProvider
    ): array {
        $totalInputCount = count($originalProducts);

        // 1) Deduplicate evaluations based on id, then index, then title
        $uniqueEvaluations = [];
        $seenKeys = [];

        foreach ($allEvaluations as $eval) {
            if (!is_array($eval)) continue;

            $id    = trim(strval($eval['id'] ?? ''));
            $index = intval($eval['index'] ?? 0);
            $title = trim(strval($eval['title'] ?? ''));

            if (!empty($id)) {
                $dedupKey = "id_" . $id;
            } elseif ($index > 0) {
                $dedupKey = "idx_" . $index;
            } else {
                $dedupKey = "title_" . mb_strtolower($title);
            }

            if (isset($seenKeys[$dedupKey])) {
                continue;
            }
            $seenKeys[$dedupKey] = true;

            // Ensure basic schema fields
            $eval['score'] = min(100, max(0, intval($eval['score'] ?? 50)));
            $eval['verdict'] = in_array($eval['verdict'] ?? '', ['winning', 'promising', 'risk'], true) 
                ? $eval['verdict'] 
                : ($eval['score'] >= 75 ? 'winning' : ($eval['score'] >= 50 ? 'promising' : 'risk'));
            
            if (empty($eval['verdict_label'])) {
                $eval['verdict_label'] = $eval['verdict'] === 'winning' 
                    ? '🟢 منتج رابح ممتاز' 
                    : ($eval['verdict'] === 'promising' ? '🟡 منتج واعد قابل للاختبار' : '🔴 خطر مرتفع / غير منصوح');
            }

            if (empty($eval['reason'])) {
                $eval['reason'] = 'تم تقييم المنتج بناءً على معايير الطلب والموسمية والتنافسية.';
            }
            if (empty($eval['recommendation'])) {
                $eval['recommendation'] = $eval['score'] >= 75 ? 'ينصح باختباره فوراً بميزانية تجريبية.' : 'يرجى مراجعة مؤشرات المنتج قبل الإطلاق.';
            }

            $uniqueEvaluations[] = $eval;
        }

        // 2) Sort evaluations descending by score
        usort($uniqueEvaluations, function ($a, $b) {
            return floatval($b['score'] ?? 0) <=> floatval($a['score'] ?? 0);
        });

        // 3) Calculate local summary statistics
        $winnersCount   = 0;
        $promisingCount = 0;
        $riskCount      = 0;
        $sumScore       = 0;

        foreach ($uniqueEvaluations as $e) {
            $verdict = $e['verdict'] ?? '';
            if ($verdict === 'winning') {
                $winnersCount++;
            } elseif ($verdict === 'promising') {
                $promisingCount++;
            } else {
                $riskCount++;
            }
            $sumScore += floatval($e['score'] ?? 0);
        }

        $evalCount = count($uniqueEvaluations);
        $avgScore = $evalCount > 0 ? round($sumScore / $evalCount, 1) : 0;

        // Season detection
        $seasonInput = trim($params['season'] ?? 'auto');
        $detectedSeason = $seasonInput;
        if ($seasonInput === 'auto' || empty($seasonInput)) {
            $currentMonth = intval(date('n'));
            if ($currentMonth >= 6 && $currentMonth <= 8) {
                $detectedSeason = 'الصيف (Summer)';
            } elseif ($currentMonth >= 9 && $currentMonth <= 10) {
                $detectedSeason = 'الدخول المدرسي (Back to School)';
            } elseif ($currentMonth == 3 || $currentMonth == 4) {
                $detectedSeason = 'رمضان والعيد (Ramadan & Eid)';
            } elseif ($currentMonth >= 11 || $currentMonth <= 2) {
                $detectedSeason = 'الشتاء والبرد (Winter)';
            } else {
                $detectedSeason = 'مواسم عامة (General Season)';
            }
        }

        // Budget recommended count logic
        if ($adBudgetTotal >= 7000) {
            $recommendedCount = 3;
        } elseif ($adBudgetTotal >= 3500) {
            $recommendedCount = 2;
        } else {
            $recommendedCount = 1;
        }
        $recommendedCount = min($recommendedCount, max(1, $totalInputCount));

        $topWinner = !empty($uniqueEvaluations) ? $uniqueEvaluations[0] : null;

        $budgetSummary = "بناءً على الميزانية الإجمالية المحددة ({$adBudgetTotal} DH)، يوصي الذكاء الاصطناعي باختبار أفضل " . ($recommendedCount == 1 ? "منتج 1 رابح فقط" : "{$recommendedCount} منتجات رابحة") . " لعدم تشتيت رأس المال.";

        $result = [
            'title' => 'تقييم المنتجات بالذكاء الاصطناعي',
            'summary' => [
                'total_analyzed' => $totalInputCount, // Real input products count!
                'winners_count' => $winnersCount,
                'promising_count' => $promisingCount,
                'risk_count' => $riskCount,
                'avg_score' => $avgScore,
                'detected_season' => $detectedSeason,
                'budget_recommended_count' => $recommendedCount,
                'budget_allocation_summary' => $budgetSummary,
                'top_winner' => $topWinner
            ],
            'evaluations' => $uniqueEvaluations,
            'ai_powered_by' => $effectiveProvider,
            'raw_input_payload' => $this->lastInputPayload,
            'raw_output_response' => $this->lastOutputResponse
        ];

        // 4) Apply budget fit flags across all merged evaluations globally
        $this->normalizeBudgetFitFlags($result, $adBudgetTotal);

        return $result;
    }

    /**
     * Build empty response structure for empty product list
     */
    protected function buildEmptyResponse(array $params): array
    {
        $adBudget = floatval($params['ad_budget'] ?? $params['ad_budget_total'] ?? 5000);
        return [
            'title' => 'تقييم المنتجات بالذكاء الاصطناعي',
            'summary' => [
                'total_analyzed' => 0,
                'winners_count' => 0,
                'promising_count' => 0,
                'risk_count' => 0,
                'avg_score' => 0,
                'detected_season' => $params['season'] ?? 'auto',
                'budget_recommended_count' => 1,
                'budget_allocation_summary' => "لم يتم تقديم منتجات للتحليل.",
                'top_winner' => null
            ],
            'evaluations' => [],
            'ai_powered_by' => 'System'
        ];
    }

    /**
     * Clean raw string response from LLMs (strip markdown ```json ... ``` wrappers) and decode to array
     */
    protected function cleanAndDecodeJson(?string $content): ?array
    {
        if (empty($content)) {
            return null;
        }

        $content = trim($content);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $content, $matches)) {
            $content = trim($matches[1]);
        } else {
            $first = strpos($content, '{');
            $last  = strrpos($content, '}');
            if ($first !== false && $last !== false && $last > $first) {
                $content = substr($content, $first, $last - $first + 1);
            }
        }
        
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        log_message('error', 'AI Service JSON Parse Failed: ' . json_last_error_msg() . ' | Content: ' . substr($content, 0, 500));
        return null;
    }

    /**
     * Call OpenAI, DeepSeek, APIyi, or OpenRouter API (OpenAI Chat Completions Compatible)
     */
    protected function callOpenAiCompatibleApi(
        string $endpoint,
        array $products,
        array $params,
        array $extraHeaders = [],
        string $promptMode = 'screening'
    ): ?array {
        @set_time_limit(180);
        $prompt = $this->buildPrompt($products, $params, $promptMode);

        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'أنت خبير واستشاري متقدم في التجارة الإلكترونية بنظام الدفع عند الاستلام (COD) في المغرب والوطن العربي. مهمتك تحليل قائمة المنتجات وتحديد المنتجات الرابحة وإرجاع النتائج فقط بصيغة JSON نظيفة تلتزم بالهيكل المطلوب تماماً دون أي Markdown.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.2,
            'max_tokens' => 3500
        ];

        $headers = array_merge([
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ], $extraHeaders);

        $isProd = defined('ENVIRONMENT') && ENVIRONMENT === 'production';

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => $isProd,
            CURLOPT_SSL_VERIFYHOST => $isProd ? 2 : 0,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrNo = curl_errno($ch);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        $inputPayloadLog = [
            'endpoint' => $endpoint,
            'provider' => $this->provider,
            'model'    => $this->model,
            'payload'  => $payload
        ];
        $this->lastInputPayload = $inputPayloadLog;

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            $content = $data['choices'][0]['message']['content'] ?? '';
            $finishReason = $data['choices'][0]['finish_reason'] ?? null;

            if ($finishReason === 'length' || $finishReason === 'max_tokens') {
                $this->lastCallError = "Response truncated because max_tokens reached";
                $this->lastOutputResponse = [
                    'http_code' => $httpCode,
                    'finish_reason' => $finishReason,
                    'curl_errno' => $curlErrNo,
                    'curl_error' => $curlErr,
                    'error' => 'Response truncated because max_tokens reached'
                ];
                log_message('warning', "AI Service [{$this->provider}/{$this->model}]: Response truncated due to max_tokens.");
                return null;
            }

            $decoded = $this->cleanAndDecodeJson($content);
            $this->lastOutputResponse = [
                'http_code' => $httpCode,
                'finish_reason' => $finishReason,
                'content'   => $data ?? $response
            ];
            if ($decoded !== null && isset($decoded['evaluations']) && is_array($decoded['evaluations'])) {
                $decoded['raw_input_payload'] = $inputPayloadLog;
                $decoded['raw_output_response'] = $this->lastOutputResponse;
                return $decoded;
            } else {
                $this->lastCallError = "الموديل ({$this->model}) أرجع استجابة لكن يتعذر استخراج هيكل JSON المطلوب (مصفوفة evaluations).";
            }
        } else {
            $errData = json_decode($response, true);
            $apiErrMsg = $errData['error']['message'] ?? $errData['message'] ?? (!empty($curlErr) ? "خطأ الاتصال (cURL {$curlErrNo}): {$curlErr}" : (substr((string)$response, 0, 300) ?: "فشل الاتصال الخارجي (HTTP {$httpCode})"));
            $this->lastCallError = "خطأ من مزود الخدمة [{$this->provider}/{$this->model}] (رمز HTTP {$httpCode}): " . $apiErrMsg;
            $this->lastOutputResponse = [
                'http_code' => $httpCode,
                'curl_errno' => $curlErrNo,
                'curl_error' => $curlErr,
                'error'     => $apiErrMsg,
                'raw_body'  => $errData ?? (is_string($response) ? substr($response, 0, 500) : $curlErr)
            ];
            log_message('error', "AI Service LLM [{$endpoint}] HTTP Code {$httpCode} [cURL {$curlErrNo}]: " . ($curlErr ?: substr((string)$response, 0, 500)));
        }

        return null;
    }

    protected function normalizeGeminiModel(string $model): string
    {
        $model = trim($model);
        if (str_starts_with(strtolower($model), 'google/')) {
            $model = substr($model, 7);
        }
        if (empty($model)) {
            return 'gemini-2.0-flash';
        }
        return $model;
    }

    /**
     * Call Google Gemini REST API
     */
    protected function callGeminiApi(array $products, array $params, string $promptMode = 'screening'): ?array
    {
        @set_time_limit(180);
        $targetModel = $this->normalizeGeminiModel($this->model);
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$targetModel}:generateContent?key={$this->apiKey}";
        $prompt = $this->buildPrompt($products, $params, $promptMode);

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => "أنت خبير واستشاري التجارة الإلكترونية بنظام الدفع عند الاستلام (COD) بالمغرب. قم بتحليل المنتجات التالية وأرجع النتائج حصراً بصيغة JSON بدون أي نص خارجي.\n\n" . $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => 0.2,
                'maxOutputTokens' => 4096
            ]
        ];

        $isProd = defined('ENVIRONMENT') && ENVIRONMENT === 'production';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => $isProd,
            CURLOPT_SSL_VERIFYHOST => $isProd ? 2 : 0,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrNo = curl_errno($ch);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        $inputPayloadLog = [
            'endpoint' => "https://generativelanguage.googleapis.com/v1beta/models/{$targetModel}:generateContent",
            'provider' => 'gemini',
            'model'    => $targetModel,
            'payload'  => $payload
        ];
        $this->lastInputPayload = $inputPayloadLog;

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            $finishReason = $data['candidates'][0]['finishReason'] ?? null;

            if ($finishReason === 'MAX_TOKENS') {
                $this->lastCallError = "Gemini response truncated because MAX_TOKENS";
                $this->lastOutputResponse = [
                    'http_code' => $httpCode,
                    'finish_reason' => 'MAX_TOKENS',
                    'curl_errno' => $curlErrNo,
                    'curl_error' => $curlErr,
                    'error' => 'Gemini response truncated because MAX_TOKENS'
                ];
                log_message('warning', "AI Service Gemini: Response truncated due to MAX_TOKENS on batch call.");
                return null;
            }

            $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $decoded = $this->cleanAndDecodeJson($content);
            $this->lastOutputResponse = [
                'http_code' => $httpCode,
                'finish_reason' => $finishReason,
                'content'   => $data ?? $response
            ];

            if ($decoded !== null && isset($decoded['evaluations']) && is_array($decoded['evaluations'])) {
                $decoded['raw_input_payload'] = $inputPayloadLog;
                $decoded['raw_output_response'] = $this->lastOutputResponse;
                return $decoded;
            } else {
                $this->lastCallError = "موديل Gemini أرجع نصاً غير متوافق مع صيغة JSON المطلوبة أو تفتقر لمصفوفة evaluations.";
            }
        } else {
            $errData = json_decode($response, true);
            $apiErrMsg = $errData['error']['message'] ?? $errData['message'] ?? (!empty($curlErr) ? "خطأ الاتصال (cURL {$curlErrNo}): {$curlErr}" : (substr((string)$response, 0, 300) ?: "فشل الاتصال بـ Gemini (HTTP {$httpCode})"));
            $this->lastCallError = "خطأ من Google Gemini (رمز HTTP {$httpCode}): " . $apiErrMsg;
            $this->lastOutputResponse = [
                'http_code' => $httpCode,
                'curl_errno' => $curlErrNo,
                'curl_error' => $curlErr,
                'error'     => $apiErrMsg,
                'raw_body'  => $errData ?? (is_string($response) ? substr($response, 0, 500) : $curlErr)
            ];
            log_message('error', "Gemini API HTTP Error {$httpCode} [cURL {$curlErrNo}]: " . ($curlErr ?: substr((string)$response, 0, 500)));
        }

        return null;
    }


    /**
     * Build Prompt for LLMs (Screening Phase 1 or Compact Retry)
     */
    protected function buildPrompt(array $products, array $params, string $mode = 'screening'): string
    {
        return \App\Libraries\Ai\PromptBuilder::buildScreeningPrompt($products, $params, $mode);
    }

    /**
     * Local Heuristic Engine (Fallback)
     */
    public function runLocalHeuristicEngine(array $productsInput, array $params): array
    {
        $mode = $params['analysis_mode'] ?? 'comprehensive';
        $adBudgetTotal = floatval($params['ad_budget_total'] ?? 5000);
        $seasonInput = trim($params['season'] ?? 'auto');
        $cShippingDefault = floatval($params['c_shipping_default'] ?? 35);
        $returnRate = 0.20;

        $currentMonth = intval(date('n'));
        $detectedSeason = 'الصيف';
        if ($seasonInput === 'auto' || empty($seasonInput)) {
            if ($currentMonth >= 6 && $currentMonth <= 8) {
                $detectedSeason = 'الصيف (Summer)';
            } elseif ($currentMonth >= 9 && $currentMonth <= 10) {
                $detectedSeason = 'الدخول المدرسي (Back to School)';
            } elseif ($currentMonth == 3 || $currentMonth == 4) {
                $detectedSeason = 'رمضان والعيد (Ramadan & Eid)';
            } elseif ($currentMonth >= 11 || $currentMonth <= 2) {
                $detectedSeason = 'الشتاء والبرد (Winter)';
            } else {
                $detectedSeason = 'مواسم عامة (General Season)';
            }
        } else {
            $detectedSeason = $seasonInput;
        }

        $evaluations = [];
        $winnersCount = 0;
        $promisingCount = 0;
        $riskCount = 0;
        $totalScore = 0;
        $topWinner = null;
        $maxScore = -1;

        $seasonalKeywords = [
            'الصيف' => ['صيف', 'شاطئ', 'مسبح', 'حرارة', 'تبريد', 'مروحة', 'مكيف', 'ماء', 'نظارات', 'سفر'],
            'الشتاء' => ['شتاء', 'تدفئة', 'برد', 'سخان', 'معطف', 'صوف', 'بطانية', 'حراري'],
            'رمضان' => ['رمضان', 'مطبخ', 'عصارة', 'فرن', 'طهي', 'حلويات', 'ديكور', 'صلاة', 'سجادة'],
            'الدخول المدرسي' => ['مدرسة', 'حقيبة', 'أدوات', 'أطفال', 'دراسة', 'كتب', 'مكتب']
        ];

        foreach ($productsInput as $idx => $prod) {
            $title = is_array($prod) ? ($prod['title'] ?? $prod['name'] ?? "منتج #" . ($idx + 1)) : "منتج #" . ($idx + 1);
            $url = is_array($prod) ? ($prod['url'] ?? $prod['link'] ?? '') : '';
            $imageUrl = is_array($prod) ? ($prod['image_url'] ?? $prod['image'] ?? $prod['thumbnail'] ?? '') : '';
            $id = is_array($prod) ? ($prod['id'] ?? $idx + 1) : $idx + 1;

            $hasVideo = is_array($prod) && (!empty($prod['video_path']) || !empty($prod['video']) || !empty($prod['video_url']));
            $adsCount = is_array($prod) ? intval($prod['ads_count'] ?? $prod['active_ads'] ?? 12) : 10;

            // 1) Demand Score (40 points base)
            $demandScore = 20;
            if ($hasVideo) $demandScore += 12;
            if ($adsCount >= 15) $demandScore += 8;
            elseif ($adsCount >= 8) $demandScore += 5;
            $demandScore = max(5, min(40, $demandScore));

            // 2) Season Score (30 points base)
            $seasonScore = 15;
            $titleLower = mb_strtolower($title);
            foreach ($seasonalKeywords as $seasonName => $keywords) {
                if (str_contains($detectedSeason, $seasonName) || $seasonInput === $seasonName) {
                    foreach ($keywords as $kw) {
                        if (str_contains($titleLower, $kw)) {
                            $seasonScore += 12;
                            break 2;
                        }
                    }
                }
            }
            $seasonScore = max(5, min(30, $seasonScore));

            // 3) Logistics Score (20 points base)
            $logisticsScore = 15;
            $fragileKeywords = ['زجاج', 'كسر', 'كبير', 'ثقيل', 'سائل'];
            foreach ($fragileKeywords as $fkw) {
                if (str_contains($titleLower, $fkw)) {
                    $logisticsScore -= 6;
                }
            }
            $logisticsScore = max(5, min(20, $logisticsScore));

            // 4) Financials
            $sellingPrice = floatval(is_array($prod) ? ($prod['price'] ?? $prod['selling_price'] ?? 250) : 250);
            $cWholesale = floatval(is_array($prod) ? ($prod['wholesale_price'] ?? $prod['price_wholesale'] ?? round($sellingPrice * 0.28)) : round($sellingPrice * 0.28));
            if ($cWholesale <= 0) $cWholesale = round($sellingPrice * 0.28);
            
            $cShipping = floatval(is_array($prod) ? ($prod['shipping_cost'] ?? $cShippingDefault) : $cShippingDefault);
            if ($cShipping <= 0) $cShipping = $cShippingDefault;

            $realShipping = $cShipping / (1 - $returnRate);
            $baseCost = $cWholesale + $realShipping;

            $budgetScore = 8;
            if ($cWholesale > ($adBudgetTotal * 0.1)) {
                $budgetScore -= 3;
            }
            $budgetScore = max(2, min(10, $budgetScore));

            // Apply Mode Multipliers
            if ($mode === 'seasonal') {
                $seasonScore = min(40, round($seasonScore * 1.3));
            } elseif ($mode === 'ad_volume') {
                $demandScore = min(45, round($demandScore * 1.25));
            } elseif ($mode === 'easy_logistics') {
                $logisticsScore = min(30, round($logisticsScore * 1.5));
            } elseif ($mode === 'max_margin') {
                $budgetScore = min(20, round($budgetScore * 2.0));
            }

            $rawScore = $demandScore + $seasonScore + $logisticsScore + $budgetScore;
            $score = min(100, max(15, round($rawScore)));

            $estimatedCpa = round(min(120, max(30, $baseCost * 0.75)), 2);
            $competitorPrice = floatval(is_array($prod) ? ($prod['price'] ?? $prod['selling_price'] ?? ($baseCost * 2.8)) : ($baseCost * 2.8));
            $targetPrice = round(max($competitorPrice, $baseCost + $estimatedCpa + 50), 2);
            $netProfit = round($targetPrice - $cWholesale - $realShipping - $estimatedCpa, 2);
            $netMarginPct = round(($netProfit / $targetPrice) * 100, 1);

            if ($score >= 75) {
                $verdict = 'winning';
                $verdictLabel = '🟢 منتج رابح ممتاز';
                $winnersCount++;
            } elseif ($score >= 50) {
                $verdict = 'promising';
                $verdictLabel = '🟡 منتج واعد قابل للاختبار';
                $promisingCount++;
            } else {
                $verdict = 'risk';
                $verdictLabel = '🔴 خطر مرتفع / غير منصوح';
                $riskCount++;
            }

            $totalScore += $score;

            $evalItem = [
                'id' => $id,
                'title' => $title,
                'url' => $url,
                'image_url' => $imageUrl,
                'score' => $score,
                'verdict' => $verdict,
                'verdict_label' => $verdictLabel,
                'reason' => $hasVideo ? "يتوفر على محتوى إعلاني فيديو جاهز ومناسب لموسم {$detectedSeason}" : "خارج الذروة الموسمية الحالية أو يحتاج إبداعات فيديو جديدة",
                'recommendation' => $score >= 75 
                    ? "ينصح بالبدء في اختبار هذا المنتج فوراً بميزانية 200 DH يومياً عبر TikTok / Facebook Ads."
                    : ($score >= 50 ? "منتج واعد، يفضل تحسين زاوية التسويق وإنشاء فيديو إعلاني احترافي قبل إطلاقه." : "غير منصوح باختباره حالياً لارتفاع مخاطر التوصيل أو ضعف الطلب.")
            ];

            if ($score > $maxScore) {
                $maxScore = $score;
                $topWinner = $evalItem;
            }

            $evaluations[] = $evalItem;
        }

        $totalAnalyzed = count($productsInput);
        $avgScore = $totalAnalyzed > 0 ? round($totalScore / $totalAnalyzed, 1) : 0;

        // Calculate Budget Allocation Count & Flag Top Products Fitting Budget
        $recommendedCount = 1;
        if ($adBudgetTotal >= 7000) {
            $recommendedCount = 3;
        } elseif ($adBudgetTotal >= 3500) {
            $recommendedCount = 2;
        } else {
            $recommendedCount = 1;
        }
        $recommendedCount = min($recommendedCount, max(1, count($evaluations)));

        // Sort evaluations by score descending to mark top N for budget fit
        usort($evaluations, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        for ($i = 0; $i < count($evaluations); $i++) {
            if ($i < $recommendedCount) {
                $evaluations[$i]['is_budget_fit'] = true;
                $evaluations[$i]['budget_allocation_note'] = "موصى باختباره مباشرة ضمن سقف ميزانيتك الإعلانية ({$adBudgetTotal} DH)";
            } else {
                $evaluations[$i]['is_budget_fit'] = false;
                $evaluations[$i]['budget_allocation_note'] = "يتطلب ميزانية إضافية لاختباره بالتوازي";
            }
        }

        $budgetSummary = "بناءً على ميزانيتك الإعلانية الإجمالية ({$adBudgetTotal} DH)، يوصي الذكاء الاصطناعي باختبار أفضل " . ($recommendedCount == 1 ? "منتج 1 رابح فقط" : "{$recommendedCount} منتجات رابحة") . " لتفادي تشتيت رأس المال قبل تحقيق عوائد مستقرة.";

        return [
            'title' => 'تقييم المنتجات بالذكاء الاصطناعي',
            'summary' => [
                'total_analyzed' => $totalAnalyzed,
                'winners_count' => $winnersCount,
                'promising_count' => $promisingCount,
                'risk_count' => $riskCount,
                'avg_score' => $avgScore,
                'detected_season' => $detectedSeason,
                'budget_recommended_count' => $recommendedCount,
                'budget_allocation_summary' => $budgetSummary,
                'top_winner' => $topWinner
            ],
            'evaluations' => $evaluations
        ];
    }

    /**
     * Post-process evaluation results to ensure budget_fit flags strictly match budget recommendation count.
     */
    protected function normalizeBudgetFitFlags(array &$result, float $adBudgetTotal): void
    {
        if (empty($result['evaluations']) || !is_array($result['evaluations'])) {
            return;
        }

        $evaluations = &$result['evaluations'];

        $recommendedCount = 1;
        if ($adBudgetTotal >= 7000) {
            $recommendedCount = 3;
        } elseif ($adBudgetTotal >= 3500) {
            $recommendedCount = 2;
        } else {
            $recommendedCount = 1;
        }
        $recommendedCount = min($recommendedCount, max(1, count($evaluations)));

        // Create an array of indices sorted by score descending
        $indices = array_keys($evaluations);
        usort($indices, function ($i1, $i2) use ($evaluations) {
            $s1 = floatval($evaluations[$i1]['score'] ?? 0);
            $s2 = floatval($evaluations[$i2]['score'] ?? 0);
            return $s2 <=> $s1;
        });

        // Mark top N as budget fit and remainder as false
        for ($rank = 0; $rank < count($indices); $rank++) {
            $idx = $indices[$rank];
            if ($rank < $recommendedCount) {
                $evaluations[$idx]['is_budget_fit'] = true;
                $evaluations[$idx]['budget_allocation_note'] = "موصى باختباره مباشرة ضمن سقف ميزانيتك الإعلانية ({$adBudgetTotal} DH)";
            } else {
                $evaluations[$idx]['is_budget_fit'] = false;
                $evaluations[$idx]['budget_allocation_note'] = "يتطلب ميزانية إضافية لاختباره بالتوازي";
            }
        }

        if (!isset($result['summary']) || !is_array($result['summary'])) {
            $result['summary'] = [];
        }
        $result['summary']['budget_recommended_count'] = $recommendedCount;
        $result['summary']['budget_allocation_summary'] = "بناءً على الميزانية الإجمالية المحددة ({$adBudgetTotal} DH) يوصي الذكاء الاصطناعي باختبار أفضل {$recommendedCount} منتجات فقط لعدم تشتيت رأس المال.";
    }

    /**
     * Phase 2: Single Product Deep-Dive Strategy & Financial Blueprint
     */
    public function evaluateSingleProductDeep(array $product, array $params): array
    {
        $sanitizedArr = $this->sanitizeProductImageUrls([$product]);
        $product = $sanitizedArr[0] ?? $product;
        $config = $this->resolveConfig($params);
        $provider = $config['provider'];
        $apiKey   = $config['api_key'];
        $model    = $config['model'];

        $this->apiKey = $apiKey;
        $this->model  = $model;
        $allowFallback = (bool)($config['allow_internal_fallback'] ?? true);
        $lastError = '';

        $title            = trim($product['title'] ?? $product['name'] ?? 'منتج بدون عنوان');
        $competitorPrice  = floatval($params['price_selling'] ?? $product['price'] ?? 0);
        $cWholesale       = floatval($params['c_wholesale'] ?? 0);
        $cShipping        = floatval($params['c_shipping'] ?? 35);
        $cPackaging       = floatval($params['c_packaging'] ?? 10);
        $stockQuantity    = intval($params['stock_quantity'] ?? 100);
        $totalAdBudget    = floatval($params['total_ad_budget'] ?? 1000);
        $targetDailyOrders= isset($params['target_daily_orders']) && intval($params['target_daily_orders']) > 0 ? intval($params['target_daily_orders']) : 0;
        $returnRate       = floatval($params['return_rate'] ?? 0.20);
        $extraNotes       = trim($params['extra_notes'] ?? '');

        if ($competitorPrice <= 0) {
            $competitorPrice = floatval($product['price'] ?? 250);
        }

        if (!empty($apiKey) && $provider !== 'internal') {
            try {
                $prompt = "أنت خبير واستشاري التجارة الإلكترونية والإعلانات بنظام الدفع عند الاستلام (COD) بالمغرب.\n"
                    . "قم بإعداد دراسة جدوى وتكتيكات إطلاق تفصيلية ومخصصة بالكامل للمنتج التالي:\n"
                    . "اسم المنتج: {$title}\n"
                    . "سعر المنافس في السوق / سعر البيع المقترح: {$competitorPrice} DH\n"
                    . "ثمن شراء الجملة (C_wholesale): {$cWholesale} DH\n"
                    . "تكلفة الشحن (C_shipping): {$cShipping} DH\n"
                    . "تكلفة التغليف والتأكيد (Confirmation & Packaging): {$cPackaging} DH\n"
                    . "كمية المخزون المتاحة للشراء/الافتتاحية: {$stockQuantity} قطعة\n"
                    . "الميزانية الإعلانية الإجمالية المخصصة: {$totalAdBudget} DH\n"
                    . "نسبة المرجوعات المتوقعة: " . ($returnRate * 100) . "%\n"
                    . ($targetDailyOrders > 0 ? "تفضيل مستهدف الطلبيات اليومية: {$targetDailyOrders} طلب/يوم\n" : "ملاحظة هامة: يجب أن تقوم بتحديد واقتراح مستهدف الطلبيات اليومية الأكثر واقعية وربحية (target_daily_orders) بناءً على معطيات المنتج والسعر والمخزون المتاح.\n")
                    . ($extraNotes ? "ملاحظات إضافية: {$extraNotes}\n" : "")
                    . "\nتعليمات حاسمة وخاصة:\n"
                    . "1. قم بكتابة نصوص إعلانية احترافية حقيقية بالدارجة المغربية مخصصة وموجهة تماماً لـ ({$title}) ومنافعها الخاصة، ويمنع تماماً كتابة نصوص عامة أو مكررة.\n"
                    . "2. حدد الجمهور المستهدف الدقيق (الفئة العمرية، الجنس، الاهتمامات الدقيقة لـ {$title}، أبرز المدن المغربية والمنصات الإعلانية الأنسب له).\n"
                    . "3. أرجع النتائج بصيغة JSON حصراً بالتنسيق الهيكلي التالي دون أي نصوص خارج JSON:\n"
                    . "{\n"
                    . "  \"financial_model\": {\n"
                    . "    \"selling_price\": {$competitorPrice},\n"
                    . "    \"c_wholesale\": {$cWholesale},\n"
                    . "    \"c_shipping\": {$cShipping},\n"
                    . "    \"c_packaging\": {$cPackaging},\n"
                    . "    \"stock_quantity\": {$stockQuantity},\n"
                    . "    \"total_ad_budget\": {$totalAdBudget},\n"
                    . "    \"target_daily_orders\": " . ($targetDailyOrders > 0 ? $targetDailyOrders : 15) . "\n"
                    . "  },\n"
                    . "  \"target_audience\": {\n"
                    . "    \"age_range\": \"الفئة العمرية المناسبة لـ {$title}\",\n"
                    . "    \"gender\": \"الجنس المستهدف لـ {$title}\",\n"
                    . "    \"top_cities\": [\"مدن مغربية مستهدفة لـ {$title}\"],\n"
                    . "    \"best_platforms\": [\"منصات إعلانية لـ {$title}\"],\n"
                    . "    \"interests\": [\"اهتمامات مخصصة لـ {$title}\"]\n"
                    . "  },\n"
                    . "  \"offers_strategy\": {\n"
                    . "    \"single_unit\": \"عرض قطعة واحدة لـ {$title}\",\n"
                    . "    \"bundle_2_units\": \"عرض قطعتين بخصم لـ {$title}\",\n"
                    . "    \"bundle_3_units\": \"عرض 3 قطع لـ {$title}\"\n"
                    . "  },\n"
                    . "  \"ad_creatives\": [\n"
                    . "    {\"angle\": \"زاوية حل المشكلة\", \"headline\": \"عنوان إعلاني جذاب بالدارجة لـ {$title}\", \"body\": \"نص إعلاني كامل ومخصص بالدارجة المغربية يصف حل مشكلة منتج {$title}\"},\n"
                    . "    {\"angle\": \"زاوية العرض المحدود والتوفير\", \"headline\": \"عنوان تخفيض وتوفير لـ {$title}\", \"body\": \"نص إعلاني تحفيزي بالدارجة يحث على الطلب السريع لـ {$title}\"},\n"
                    . "    {\"angle\": \"زاوية الثقة والضمان\", \"headline\": \"عنوان موثوقية وجودة لـ {$title}\", \"body\": \"نص إعلاني يركز على خدمة الدفع عند الاستلام وضمان معاينة {$title}\"}\n"
                    . "  ],\n"
                    . "  \"logistics_and_call_center\": {\n"
                    . "    \"confirmation_script_tip\": \"نصيحة مخصصة لمركز الاتصال عند التأكيد المباشر لـ {$title}\",\n"
                    . "    \"packaging_advice\": \"إرشادات التغليف المخصصة لحماية منتج {$title}\",\n"
                    . "    \"shipping_carrier_recommendation\": \"توصيات الشحن والتسليم لمنتج {$title}\"\n"
                    . "  },\n"
                    . "  \"executive_verdict\": \"التوصية والحكم التنفيذي المالي والتسويقي لمنتج {$title}\"\n"
                    . "}";

                $res = null;
                if ($provider === 'gemini') {
                    $res = $this->callGeminiRaw($prompt);
                } elseif ($provider === 'deepseek') {
                    $res = $this->callOpenAiCompatibleRaw('https://api.deepseek.com/chat/completions', $prompt);
                } elseif ($provider === 'apiyi') {
                    $res = $this->callOpenAiCompatibleRaw('https://api.apiyi.com/v1/chat/completions', $prompt);
                } elseif ($provider === 'openrouter') {
                    $res = $this->callOpenAiCompatibleRaw(
                        'https://openrouter.ai/api/v1/chat/completions',
                        $prompt,
                        [
                            'HTTP-Referer: ' . (env('app.baseURL', 'http://localhost:9090')),
                            'X-Title: Product Analytics Dashboard'
                        ]
                    );
                } else {
                    $res = $this->callOpenAiCompatibleRaw('https://api.openai.com/v1/chat/completions', $prompt);
                }

                if ($res && isset($res['financial_model'])) {
                    $res['financial_model'] = $this->calculateExactFinancialModel($params, $product, $res['financial_model']);
                    $res['ai_powered_by'] = strtoupper($provider) . ' (' . $model . ')';
                    return $res;
                } else {
                    $lastError = "تعذر الحصول على استجابة هيكلية صالحة من المزود الخارجي ({$provider} / {$model})";
                }
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                log_message('error', 'Phase 2 Deep Analyze LLM call failed: ' . $e->getMessage());
            }
        } else {
            $lastError = "مفتاح API الخاص بالمزود ({$provider}) غير متوفر أو غير صالح.";
        }

        if (!$allowFallback && $provider !== 'internal') {
            throw new \RuntimeException("فشل إجراء التحليل التفصيلي (Phase 2): فشل إجراء التحليل عبر المزود الخارجي (" . strtoupper($provider) . " / " . $model . "). (المحرك المحلي الاحتياطي Internal Market Engine معطل في إعدادات النظام). تفاصيل الخطأ: " . $lastError);
        }

        // Fallback to local heuristic engine for Phase 2
        return $this->runLocalPhase2Engine($product, $params);
    }

    /**
     * Ensures 100% mathematically exact COD financial calculations regardless of LLM output.
     */
    public function calculateExactFinancialModel(array $params, array $product, ?array $aiFinancials = []): array
    {
        $sellingPrice      = floatval($params['price_selling'] ?? $product['price'] ?? 250);
        $cWholesale        = floatval($params['c_wholesale'] ?? 70);
        $cShipping         = floatval($params['c_shipping'] ?? 35);
        $cPackaging        = floatval($params['c_packaging'] ?? 10);
        $stockQuantity     = intval($params['stock_quantity'] ?? 100);
        $totalAdBudget     = floatval($params['total_ad_budget'] ?? 1000);
        $returnRate        = floatval($params['return_rate'] ?? 0.20);

        if ($sellingPrice <= 0) $sellingPrice = 250;
        if ($cShipping <= 0) $cShipping = 35;
        if ($cPackaging <= 0) $cPackaging = 10;
        if ($stockQuantity <= 0) $stockQuantity = 100;
        if ($totalAdBudget <= 0) $totalAdBudget = 1000;

        // Real shipping cost including return rate (e.g. 35 / (1 - 0.2) = 43.75)
        $realShippingCost = round($cShipping / max(0.01, (1 - $returnRate)), 2);
        $totalCostBeforeAds = round($cWholesale + $realShippingCost + $cPackaging, 2);

        // Break-even CPA = Selling Price - Total Cost Before Ads
        $breakevenCpa = round(max(5, $sellingPrice - $totalCostBeforeAds), 2);

        // Check if AI suggested a valid target_cpa that is less than breakevenCpa
        $suggestedTargetCpa = floatval($aiFinancials['target_cpa'] ?? 0);
        if ($suggestedTargetCpa > 0 && $suggestedTargetCpa < $breakevenCpa) {
            $targetCpa = round($suggestedTargetCpa, 2);
        } else {
            // Target CPA = 60% of Break-even CPA for safe profitability
            $targetCpa = round($breakevenCpa * 0.6, 2);
        }

        // Check target daily orders
        if (!empty($params['target_daily_orders']) && intval($params['target_daily_orders']) > 0) {
            $targetDailyOrders = intval($params['target_daily_orders']);
        } elseif (!empty($aiFinancials['target_daily_orders']) && intval($aiFinancials['target_daily_orders']) > 0) {
            $targetDailyOrders = intval($aiFinancials['target_daily_orders']);
        } else {
            if ($sellingPrice <= 150) {
                $targetDailyOrders = 25;
            } elseif ($sellingPrice <= 300) {
                $targetDailyOrders = 15;
            } elseif ($sellingPrice <= 600) {
                $targetDailyOrders = 8;
            } else {
                $targetDailyOrders = 5;
            }
        }

        // Adjust target orders if stock quantity is small so it depletes smoothly over 3-7 days
        if ($stockQuantity < ($targetDailyOrders * 2)) {
            $targetDailyOrders = max(3, (int)ceil($stockQuantity / 4));
        }

        $netProfitPerOrder       = round($sellingPrice - $totalCostBeforeAds - $targetCpa, 2);
        $netMarginPct            = round(($netProfitPerOrder / $sellingPrice) * 100, 1);
        $roiPct                  = round(($netProfitPerOrder / max(1, ($totalCostBeforeAds + $targetCpa))) * 100, 1);
        $dailyAdBudget           = round($targetDailyOrders * $targetCpa, 2);
        $projectedDailyNetProfit = round($targetDailyOrders * $netProfitPerOrder, 2);
        $initialInventoryCapital = round($stockQuantity * $cWholesale, 2);
        $daysToSellOut           = round($stockQuantity / max(1, $targetDailyOrders), 1);
        $totalNetProfitForStock  = round($stockQuantity * $netProfitPerOrder, 2);

        return [
            'selling_price'              => $sellingPrice,
            'c_wholesale'                => $cWholesale,
            'c_shipping'                 => $cShipping,
            'c_packaging'                => $cPackaging,
            'stock_quantity'             => $stockQuantity,
            'total_ad_budget'            => $totalAdBudget,
            'target_daily_orders'        => $targetDailyOrders,
            'initial_inventory_capital'  => $initialInventoryCapital,
            'days_to_sell_out'           => $daysToSellOut,
            'real_shipping_with_returns' => $realShippingCost,
            'total_cost_before_ads'      => $totalCostBeforeAds,
            'breakeven_cpa'              => $breakevenCpa,
            'target_cpa'                 => $targetCpa,
            'net_profit_per_order'       => $netProfitPerOrder,
            'net_margin_pct'             => $netMarginPct,
            'roi_pct'                    => $roiPct,
            'daily_ad_budget_dh'         => $dailyAdBudget,
            'projected_daily_net_profit_dh' => $projectedDailyNetProfit,
            'total_net_profit_for_stock' => $totalNetProfitForStock
        ];
    }

    /**
     * Local Heuristic Engine for Phase 2 (Offline Fallback)
     */
    public function runLocalPhase2Engine(array $product, array $params): array
    {
        $title        = trim($product['title'] ?? $product['name'] ?? 'منتج بدون عنوان');
        $sellingPrice = floatval($params['price_selling'] ?? $product['price'] ?? 250);
        $bundle2Price = round(($sellingPrice * 2) * 0.85, 0); // 15% discount
        $bundle3Price = round(($sellingPrice * 3) * 0.78, 0); // 22% discount
        $financials   = $this->calculateExactFinancialModel($params, $product);

        $netProfit    = $financials['net_profit_per_order'] ?? 0;
        $roi          = $financials['roi_pct'] ?? 0;
        $dailyBudget  = $financials['daily_ad_budget_dh'] ?? 100;
        $targetOrders = $financials['target_daily_orders'] ?? 15;

        return [
            'ai_powered_by' => 'Internal Market Engine (Phase 2)',
            'financial_model' => $financials,
            'target_audience' => [
                'age_range' => '22 - 48 سنة',
                'gender' => 'نساء ورجال (مهتمون بالحلول العملية والتسوق المغربي)',
                'top_cities' => ['الدار البيضاء', 'الرباط / سلا', 'طنجة', 'مراكش', 'أكادير', 'فاس'],
                'best_platforms' => ['TikTok Ads (فيديوهات تجريبية)', 'Meta Ads (Facebook & Instagram)'],
                'interests' => ["منتجات الحلول الذكية ({$title})", 'التسوق الدفع عند الاستلام', 'عروض التخفيضات المميزة']
            ],
            'offers_strategy' => [
                'single_unit' => "قطعة واحدة من {$title} بـ {$sellingPrice} DH (توصيل مجاني والدفع عند الاستلام)",
                'bundle_2_units' => "قطعتين بـ {$bundle2Price} DH (توفير " . (($sellingPrice * 2) - $bundle2Price) . " DH + توصيل مجاني)",
                'bundle_3_units' => "3 قطع بـ {$bundle3Price} DH (عرض العائلة / أقصى توفير + هدية مجانية)"
            ],
            'ad_creatives' => [
                [
                    'angle' => 'زاوية حل المشكلة المباشرة',
                    'headline' => "الـحل النهائي والمجرب لـ {$title} وصل أخيراً للمغرب! 🇲🇦",
                    'body' => "هل تبحث عن أفضل جودة لـ {$title}؟ اطلب الآن واستفد من التوصيل السريع لجميع المدن المغربية والدفع بعد معاينة المنتج بنفسك!"
                ],
                [
                    'angle' => 'زاوية العرض الحصري والتوفير الضخم',
                    'headline' => "🔥 تخفيض خاص على {$title} + توصيل مجاني حتى باب المنزل اليوم فقط!",
                    'body' => "احصل على {$title} بسعر استثنائي قدره {$sellingPrice} درهم مع إمكانية توفير إضافي عند طلب قطعتين. سارع قبل نفاد المخزون!"
                ],
                [
                    'angle' => 'زاوية الضمان والثقة الكاملة',
                    'headline' => "⭐ أكثر من 1500 زبون مغربي اختاروا {$title} وأكدوا جودته الفائقة!",
                    'body' => "نضمن لك التجربة والرقي مع خدمة الدفع عند الاستلام والتأكيد الفوري عبر الهاتف. اطلب الآن بثقة وبدون مخاطرة."
                ]
            ],
            'logistics_and_call_center' => [
                'confirmation_script_tip' => "عند الاتصال بالزبون لتأكيد طلب {$title}، ركز على سرعة التوصيل وإمكانية فتح العلبة والمعاينة قبل الدفع لرفع نسبة التوصيل.",
                'packaging_advice' => "تأكد من تغليف وحدات {$title} بغلاف حماية فقاعي (Bubble Wrap) وعلبة متينة لتجنب أي أضرار أثناء الشحن والترانزيت.",
                'shipping_carrier_recommendation' => "اختر شركة توصيل مغربية تدعم التسليم في 24-48 ساعة وتتيح ميزة التتبع الإلكتروني والتأكيد السريع."
            ],
            'executive_verdict' => "منتج {$title} يحقق هامش ربح صافي قدره {$netProfit} DH لكل طلبية (ROI: {$roi}%). ينصح بالبدء بحملة تجريبية بميزانية {$dailyBudget} DH يومياً لاستهداف {$targetOrders} طلب/يوم."
        ];
    }

    protected function callOpenAiCompatibleRaw(string $endpoint, string $prompt, array $extraHeaders = []): ?array
    {
        @set_time_limit(180);
        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'أنت خبير واستشاري التجارة الإلكترونية بنظام الدفع عند الاستلام (COD) بالمغرب. قم بتحليل المنتج وإعادة الإجابة بصيغة JSON حصراً بدون أي إضافات خارجية.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.4,
            'max_tokens' => 3500
        ];

        $headers = array_merge([
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ], $extraHeaders);

        $isProd = defined('ENVIRONMENT') && ENVIRONMENT === 'production';

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => $isProd,
            CURLOPT_SSL_VERIFYHOST => $isProd ? 2 : 0,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            $content = $data['choices'][0]['message']['content'] ?? '';
            $content = preg_replace('/^```json\s*/i', '', trim($content));
            $content = preg_replace('/```$/', '', trim($content));
            return json_decode($content, true);
        } else {
            log_message('error', "AI Service Deep LLM [{$endpoint}] HTTP Code {$httpCode}: " . substr((string)$response, 0, 500));
        }

        return null;
    }

    protected function callGeminiRaw(string $prompt): ?array
    {
        $targetModel = $this->normalizeGeminiModel($this->model);
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$targetModel}:generateContent?key={$this->apiKey}";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => 0.4,
                'maxOutputTokens' => 3500
            ]
        ];

        $isProd = defined('ENVIRONMENT') && ENVIRONMENT === 'production';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => $isProd,
            CURLOPT_SSL_VERIFYHOST => $isProd ? 2 : 0,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $text = preg_replace('/^```json\s*/i', '', trim($text));
            $text = preg_replace('/```$/', '', trim($text));
            return json_decode($text, true);
        } else {
            log_message('error', "Gemini API Raw HTTP Error {$httpCode}: " . substr((string)$response, 0, 500));
        }

        return null;
    }
}

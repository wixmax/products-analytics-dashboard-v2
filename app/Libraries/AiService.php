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
     * Resolve provider, API Key, and model from request params or specific ENV variables
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

        // Get API Key based on provider
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

        // Normalize invalid/outdated model names to currently active ones
        $deprecatedGeminiModels = [
            'gemini-3.5-flash-lite', 'gemini-3.6-flash', 'gemini-1.5-flash',
            'gemini-1.5-flash-latest', 'gemini-1.5-pro', 'gemini-1.0-pro',
            'gemini-pro', 'gemini-2.0-flash-lite'
        ];
        if ($provider === 'gemini' && in_array($model, $deprecatedGeminiModels, true)) {
            $model = 'gemini-2.5-flash';
        }

        return [
            'provider' => $provider,
            'api_key'  => $apiKey,
            'model'    => $model,
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
     * Main entry point to evaluate products via LLM API or Fallback Heuristic Engine
     */
    public function evaluateProducts(array $products, array $params): array
    {
        @set_time_limit(180);
        $products = $this->sanitizeProductImageUrls($products);
        $config = $this->resolveConfig($params);
        $provider = $config['provider'];
        $apiKey   = $config['api_key'];
        $model    = $config['model'];
        $allowFallback = $config['allow_internal_fallback'] ?? true;
        $lastError = '';

        if ($this->isValidApiKey($apiKey) && $provider !== 'internal') {
            $this->provider = $provider;
            $this->apiKey   = $apiKey;
            $this->model    = $model;

            try {
                if ($this->provider === 'gemini') {
                    $result = $this->callGeminiApi($products, $params);
                } elseif ($this->provider === 'deepseek') {
                    $result = $this->callOpenAiCompatibleApi('https://api.deepseek.com/chat/completions', $products, $params);
                } elseif ($this->provider === 'apiyi') {
                    $result = $this->callOpenAiCompatibleApi('https://api.apiyi.com/v1/chat/completions', $products, $params);
                } elseif ($this->provider === 'openrouter') {
                    $result = $this->callOpenAiCompatibleApi(
                        'https://openrouter.ai/api/v1/chat/completions',
                        $products,
                        $params,
                        [
                            'HTTP-Referer: ' . (env('app.baseURL', 'http://localhost:9090')),
                            'X-Title: Product Analytics Dashboard'
                        ]
                    );
                } else {
                    $result = $this->callOpenAiCompatibleApi('https://api.openai.com/v1/chat/completions', $products, $params);
                }

                if ($result && isset($result['evaluations']) && is_array($result['evaluations'])) {
                    $result['ai_powered_by'] = strtoupper($this->provider) . ' (' . $this->model . ')';
                    $adBudget = floatval($params['ad_budget'] ?? $params['total_ad_budget'] ?? 1000);
                    $this->normalizeBudgetFitFlags($result, $adBudget);
                    return $result;
                } else {
                    $lastError = $this->lastCallError ?: "لم يرجع المزود نتائج صالحة للمنتجات (evaluations is missing or empty).";
                    log_message('error', "AI Service [{$this->provider}/{$this->model}]: " . $lastError);
                }
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                log_message('error', "AI Service LLM call failed [{$this->provider}/{$this->model}]: " . $lastError);
            }
        } else {
            $lastError = "مفتاح API الخاص بالمزود '{$provider}' غير مفعّل أو غير معروف.";
            log_message('info', "AI Service: " . $lastError);
        }

        // If internal fallback is disabled, throw Exception
        if (!$allowFallback && $provider !== 'internal') {
            throw new \RuntimeException("فشل إجراء التحليل عبر المزود الخارجي (" . strtoupper($provider) . " / " . $model . "). (المحرك المحلي الاحتياطي Internal Market Engine معطل في إعدادات النظام). تفاصيل الخطأ: " . ($lastError ?: "تعذر الوصول للمزود"));
        }

        // Fallback to local heuristic engine
        $fallback = $this->runLocalHeuristicEngine($products, $params);
        $fallback['ai_powered_by'] = 'Internal Market Engine (Offline Fallback)';
        $fallback['raw_input_payload'] = [
            'provider' => 'internal',
            'engine' => 'Internal Market Heuristic Engine (Rule-based)',
            'products_count' => count($products),
            'params' => $params
        ];
        $fallback['raw_output_response'] = [
            'status' => 'local_fallback_executed',
            'evaluations_count' => count($fallback['evaluations'] ?? [])
        ];
        $adBudget = floatval($params['ad_budget'] ?? $params['total_ad_budget'] ?? 1000);
        $this->normalizeBudgetFitFlags($fallback, $adBudget);
        return $fallback;
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
    protected function callOpenAiCompatibleApi(string $endpoint, array $products, array $params, array $extraHeaders = []): ?array
    {
        @set_time_limit(180);
        $prompt = $this->buildPrompt($products, $params);

        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'أنت خبير واستشاري متقدم في التجارة الإلكترونية بنظام الدفع عند الاستلام (COD) في المغرب والوطن العربي. مهمتك تحليل قائمة المنتجات وتحديد المنتجات الرابحة وإرجاع النتائج فقط بصيغة JSON نظيفة تلتزم بالهيكل المطلوب تماماً.'
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

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_FOLLOWLOCATION => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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
            $decoded = $this->cleanAndDecodeJson($content);
            $this->lastOutputResponse = [
                'http_code' => $httpCode,
                'content'   => $data ?? $response
            ];
            if ($decoded !== null) {
                $decoded['raw_input_payload'] = $inputPayloadLog;
                $decoded['raw_output_response'] = $this->lastOutputResponse;
                return $decoded;
            } else {
                $this->lastCallError = "الموديل ({$this->model}) أرجع استجابة لكن يتعذر استخراج هيكل JSON المطلوب منها. (جرّب استخدام موديل آخر أكثر استقراراً مثل gpt-4o-mini أو gemini-2.5-flash).";
            }
        } else {
            $errData = json_decode($response, true);
            $apiErrMsg = $errData['error']['message'] ?? $errData['message'] ?? (!empty($curlErr) ? "خطأ الاتصال (cURL): {$curlErr}" : (substr((string)$response, 0, 300) ?: "فشل الاتصال الخارجي (HTTP {$httpCode})"));
            $this->lastCallError = "خطأ من مزود الخدمة [{$this->provider}/{$this->model}] (رمز HTTP {$httpCode}): " . $apiErrMsg;
            $this->lastOutputResponse = [
                'http_code' => $httpCode,
                'error'     => $apiErrMsg,
                'raw_body'  => $errData ?? $response ?? $curlErr
            ];
            log_message('error', "AI Service LLM [{$endpoint}] HTTP Code {$httpCode}: " . ($curlErr ?: substr((string)$response, 0, 500)));
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
    protected function callGeminiApi(array $products, array $params): ?array
    {
        @set_time_limit(180);
        $targetModel = $this->normalizeGeminiModel($this->model);
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$targetModel}:generateContent?key={$this->apiKey}";
        $prompt = $this->buildPrompt($products, $params);

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => "أنت خبير واستشاري التجارة الإلكترونية في المغرب (COD). قم بتحليل المنتجات التالية وأرجع النتائج حصراً بصيغة JSON تلتزم بالهيكل المطلوب دون أي نص خارجي.\n\n" . $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
                'temperature' => 0.4,
                'maxOutputTokens' => 3500
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_FOLLOWLOCATION => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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
            $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $decoded = $this->cleanAndDecodeJson($content);
            $this->lastOutputResponse = [
                'http_code' => $httpCode,
                'content'   => $data ?? $response
            ];
            if ($decoded !== null) {
                $decoded['raw_input_payload'] = $inputPayloadLog;
                $decoded['raw_output_response'] = $this->lastOutputResponse;
                return $decoded;
            } else {
                $this->lastCallError = "موديل Gemini أرجع نصاً غير متوافق مع صيغة JSON المطلوبة.";
            }
        } else {
            $errData = json_decode($response, true);
            $apiErrMsg = $errData['error']['message'] ?? $errData['message'] ?? (!empty($curlErr) ? "خطأ الاتصال (cURL): {$curlErr}" : (substr((string)$response, 0, 300) ?: "فشل الاتصال بـ Gemini (HTTP {$httpCode})"));
            $this->lastCallError = "خطأ من Google Gemini (رمز HTTP {$httpCode}): " . $apiErrMsg;
            $this->lastOutputResponse = [
                'http_code' => $httpCode,
                'error'     => $apiErrMsg,
                'raw_body'  => $errData ?? $response ?? $curlErr
            ];
            log_message('error', "Gemini API HTTP Error {$httpCode}: " . ($curlErr ?: substr((string)$response, 0, 500)));
        }

        return null;
    }


    /**
     * Build Prompt for LLMs
     */
    protected function buildPrompt(array $products, array $params): string
    {
        $mode = $params['analysis_mode'] ?? 'comprehensive';
        $budget = $params['ad_budget_total'] ?? 5000;
        $season = $params['season'] ?? 'auto';
        $cShipping = $params['c_shipping_default'] ?? 35;

        $productsSummary = [];
        foreach ($products as $idx => $prod) {
            $title = $prod['title'] ?? $prod['name'] ?? ("منتج #" . ($idx + 1));
            $price = floatval($prod['price'] ?? $prod['selling_price'] ?? 250);
            $hasVideo = !empty($prod['video_path']) || !empty($prod['video']) || !empty($prod['video_url']);
            $adsCount = intval($prod['ads_count'] ?? $prod['active_ads'] ?? 12);
            $url = $prod['url'] ?? $prod['link'] ?? '';
            $img = $prod['image_url'] ?? $prod['image'] ?? '';

            $productsSummary[] = [
                'index' => $idx + 1,
                'title' => $title,
                'selling_price' => $price,
                'has_video_creative' => $hasVideo,
                'estimated_active_ads' => $adsCount,
                'url' => $url
            ];
        }

        $prompt = "قم بتحليل قائمة المنتجات المرشحة التالية لتشغيلها في السوق المغربي بنظام الدفع عند الاستلام (COD):\n";
        $prompt .= "المعايير المحددة:\n";
        $prompt .= "- نمط التحليل: {$mode}\n";
        $prompt .= "- الميزانية الإعلانية الإجمالية: {$budget} DH\n";
        $prompt .= "- الموسم المستهدف: {$season}\n";
        $prompt .= "- تكلفة التوصيل الأساسية: {$cShipping} DH\n\n";
        $prompt .= "قائمة المنتجات المرشحة:\n" . json_encode($productsSummary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
        $prompt .= "المطلوب إرجاع JSON بالهيكل التالي بدقة دون أي تغليف محيطي:\n";
        $prompt .= '{
  "title": "تقييم المنتجات بالذكاء الاصطناعي",
  "summary": {
    "total_analyzed": 10,
    "winners_count": 3,
    "promising_count": 5,
    "risk_count": 2,
    "avg_score": 78,
    "detected_season": "' . $season . '",
    "budget_recommended_count": 2,
    "budget_allocation_summary": "بناءً على الميزانية الإجمالية المحددة (' . $budget . ' DH) يوصي الذكاء الاصطناعي باختبار أفضل 2 منتجات فقط لعدم تشتيت رأس المال.",
    "top_winner": {
      "title": "اسم المنتج الأول الرابح",
      "score": 90,
      "target_price": 299,
      "net_margin_pct": 38.5,
      "image_url": "..."
    }
  },
  "evaluations": [
    {
      "title": "عنوان المنتج",
      "url": "رابط المنتج",
      "image_url": "رابط الصورة",
      "score": 85,
      "verdict": "winning",
      "verdict_label": "🟢 منتج رابح ممتاز",
      "is_budget_fit": true,
      "budget_allocation_note": "موصى باختباره مباشرة ضمن الميزانية الإجمالية المحددة",
      "breakdown": {
        "demand_score": 35,
        "season_score": 25,
        "logistics_score": 17,
        "budget_score": 8
      },
      "financials": {
        "c_wholesale": 60,
        "c_shipping": 35,
        "real_shipping_with_returns": 43.75,
        "estimated_cpa": 70,
        "target_price": 249,
        "net_profit": 75.25,
        "net_margin_pct": 30.2
      },
      "reasons": ["حجم إعلانات قوي", "متوافق مع الموسم الحالي"],
      "recommendation": "ينصح باختباره فوراً بميزانية 200 درهم يومياً",
      "narrative_analysis": {
        "summary": "نص التقييم والتشخيص التجاري للمنتج...",
        "market_fit": "نص ملاءمة الموسم والسوق المغربي...",
        "logistics_advice": "نص نصائح اللوجستيك والتوصيل بـ 20% مرجوعات...",
        "launch_strategy": "نص خطة الإطلاق والتسويق المقترحة..."
      }
    }
  ]
}';

        return $prompt;
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
                'breakdown' => [
                    'demand_score' => $demandScore,
                    'season_score' => $seasonScore,
                    'logistics_score' => $logisticsScore,
                    'budget_score' => $budgetScore,
                ],
                'financials' => [
                    'c_wholesale' => $cWholesale,
                    'c_shipping' => $cShipping,
                    'real_shipping_with_returns' => round($realShipping, 2),
                    'estimated_cpa' => $estimatedCpa,
                    'target_price' => $targetPrice,
                    'net_profit' => $netProfit,
                    'net_margin_pct' => $netMarginPct,
                ],
                'reasons' => [
                    $hasVideo ? "يتوفر على محتوى إعلاني فيديو جاهز" : "يحتاج إنشاء إبداعات إعلانية فيديو",
                    $seasonScore >= 20 ? "يتوافق مع موسم {$detectedSeason}" : "خارج الذروة الموسمية الحالية",
                    "معدل الربحية الصافي المتوقع: +{$netMarginPct}%"
                ],
                'recommendation' => $score >= 75 
                    ? "ينصح بالبدء في اختبار هذا المنتج فوراً بميزانية 200 DH يومياً عبر TikTok / Facebook Ads."
                    : ($score >= 50 ? "منتج واعد، يفضل تحسين زاوية التسويق وإنشاء فيديو إعلاني احترافي قبل إطلاقه." : "غير منصوح باختباره حالياً لارتفاع مخاطر التوصيل أو ضعف الطلب."),
                'narrative_analysis' => [
                    'summary' => "أظهر هذا المنتج مؤشرات تنافسية قوية بمعدل نقاط {$score}/100. " . ($hasVideo ? "وجود الفيديو الإعلاني يعزز من سرعة الإطلاق." : "ينصح بإنشاء إعلانات فيديو لتسريع المبيعات."),
                    'market_fit' => "يتماشى المنتج مع متطلبات السوق المغربي لموسم {$detectedSeason}. يوصى باستهداف الفئة العمرية بين 22 و 45 سنة.",
                    'logistics_advice' => "تكلفة التوصيل الأساسية احتسبت بـ {$cShipping} DH. مع احتساب 20% مرجوعات ملغاة تصبح تكلفة التوصيل الفعلية " . round($realShipping, 2) . " DH للطلب الناجح.",
                    'launch_strategy' => "خطة الإطلاق: البدء بميزانية إعلانية تجريبية بقيمة 150-200 DH/يوم لمدة 3 أيام، والتركيز على العرض الافتتاحي بالسعر المستهدف " . $targetPrice . " DH."
                ]
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
        $result['summary']['budget_allocation_summary'] = "بناءً على الميزانية الإجمالية المحنطة ({$adBudgetTotal} DH) يوصي الذكاء الاصطناعي باختبار أفضل {$recommendedCount} منتجات فقط لعدم تشتيت رأس المال.";
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
                }
            } catch (\Throwable $e) {
                log_message('error', 'Phase 2 Deep Analyze LLM call failed: ' . $e->getMessage());
            }
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

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_FOLLOWLOCATION => true
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
                'response_mime_type' => 'application/json',
                'temperature' => 0.4,
                'maxOutputTokens' => 3500
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_FOLLOWLOCATION => true
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

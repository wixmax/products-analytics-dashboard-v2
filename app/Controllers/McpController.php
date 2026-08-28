<?php

namespace App\Controllers;

use App\Models\ProductModel;
use App\Models\SnapshotModel;
use App\Libraries\FacebookAdsService;
use CodeIgniter\RESTful\ResourceController;

class McpController extends ResourceController
{
    protected $format = 'json';

    /**
     * Helper to parse raw_json string from data_snapshots table
     */
    private function parseSnapshotEntries($rawJsonStr): array
    {
        if (empty($rawJsonStr)) {
            return [];
        }
        try {
            $decoded = is_string($rawJsonStr) ? json_decode($rawJsonStr, true) : $rawJsonStr;
            if (!$decoded || !is_array($decoded)) {
                return [];
            }

            // If it's a tRPC batch structure: [0 => ['result' => ['data' => ['json' => ...]]]]
            $base = isset($decoded[0]) ? $decoded[0] : $decoded;

            if (isset($base['result']['data']['json']) && is_array($base['result']['data']['json'])) {
                $json = $base['result']['data']['json'];
                if (isset($json['productsEntries']) && is_array($json['productsEntries'])) {
                    return $json['productsEntries'];
                }
                if (isset($json['results']) && is_array($json['results'])) {
                    return $json['results'];
                }
                if (isset($json['products']) && is_array($json['products'])) {
                    return $json['products'];
                }
                if (isset($json['data']) && is_array($json['data'])) {
                    return $json['data'];
                }
            }

            // Direct array structures
            if (isset($decoded['productsEntries']) && is_array($decoded['productsEntries'])) {
                return $decoded['productsEntries'];
            }
            if (isset($decoded['results']) && is_array($decoded['results'])) {
                return $decoded['results'];
            }
            if (isset($decoded['products']) && is_array($decoded['products'])) {
                return $decoded['products'];
            }
            if (isset($decoded['data']) && is_array($decoded['data'])) {
                return $decoded['data'];
            }

            // If direct list of items
            if (isset($decoded[0]) && is_array($decoded[0])) {
                return $decoded;
            }

            return [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * GET/POST /api/mcp
     * Main MCP Protocol Endpoint (JSON-RPC 2.0)
     */
    public function handleMcp()
    {
        $response = $this->response;
        $response->setHeader('Access-Control-Allow-Origin', '*');
        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-API-Key, api-key, api_key, x-token');

        $method = strtolower($this->request->getMethod());

        if ($method === 'options') {
            return $response->setStatusCode(204);
        }

        $db = \Config\Database::connect();
        $globalEnabledRow = $db->table('settings')->where('key', 'mcp_global_enabled')->get()->getRowArray();
        $globalEnabled = $globalEnabledRow ? ($globalEnabledRow['value'] === '1') : true;

        if (!$globalEnabled) {
            return $this->respond([
                'jsonrpc' => '2.0',
                'id'      => null,
                'error'   => [
                    'code'    => -32603,
                    'message' => 'MCP server is currently disabled by administrator.'
                ]
            ], 200);
        }
        
        if ($method === 'get') {
            $acceptHeader = strtolower(trim($this->request->getHeaderLine('Accept')));
            if ($acceptHeader === 'text/event-stream' || $this->request->getGet('transport') === 'sse') {
                $token = $this->request->getGet('token');
                $postUrl = base_url('api/mcp') . ($token ? '?token=' . urlencode($token) : '');

                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');
                header('Connection: keep-alive');
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Headers: *');

                echo "event: endpoint\r\n";
                echo "data: " . $postUrl . "\r\n\r\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                exit;
            }

            // GET Discovery / Information Response
            return $this->respond([
                'mcp_server' => 'products-analytics-mcp-php',
                'version'    => '1.0.0',
                'status'     => 'running',
                'endpoint'   => base_url('api/mcp'),
                'capabilities' => [
                    'tools'   => true,
                    'prompts' => true,
                    'jsonrpc' => '2.0'
                ],
                'tools' => $this->getToolsManifest()
            ], 200);
        }

        $rawBody = $this->request->getBody();
        if (empty($rawBody)) {
            $rawBody = @file_get_contents('php://input');
        }
        if (!empty($rawBody)) {
            $rawBody = preg_replace('/^[\xEF\xBB\xBF\s]+/', '', $rawBody);
        }
        $input = !empty($rawBody) ? json_decode($rawBody, true) : null;

        if (!$input) {
            try {
                $input = $this->request->getJSON(true);
            } catch (\Throwable $e) {
                $input = null;
            }
        }

        if (!$input) {
            return $this->respond([
                'jsonrpc' => '2.0',
                'id'      => null,
                'error'   => [
                    'code'    => -32600,
                    'message' => 'Invalid Request. Empty or malformed JSON.'
                ]
            ], 200);
        }

        // Handle JSON-RPC Batch Request (array of requests)
        if (is_array($input) && isset($input[0]) && is_array($input[0])) {
            $batchResponses = [];
            foreach ($input as $singleRequest) {
                $res = $this->processSingleJsonRpcRequest($singleRequest);
                if ($res !== null) {
                    $batchResponses[] = $res;
                }
            }
            if (empty($batchResponses)) {
                return $this->response->setStatusCode(202);
            }
            return $this->respond($batchResponses, 200);
        }

        // Single Request
        $singleResponse = $this->processSingleJsonRpcRequest($input);
        if ($singleResponse === null) {
            return $this->response->setStatusCode(202);
        }

        return $this->respond($singleResponse, 200);
    }

    /**
     * Process a single JSON-RPC 2.0 request or notification
     */
    private function processSingleJsonRpcRequest($input)
    {
        if (!is_array($input) || !isset($input['method'])) {
            return [
                'jsonrpc' => '2.0',
                'id'      => $input['id'] ?? null,
                'error'   => [
                    'code'    => -32600,
                    'message' => 'Invalid Request. Missing method.'
                ]
            ];
        }

        $jsonrpcId = $input['id'] ?? null;
        $mcpMethod = $input['method'];
        $params    = $input['params'] ?? [];

        // Handle notifications (no JSON response body expected per JSON-RPC 2.0 spec)
        if (str_starts_with($mcpMethod, 'notifications/') || !array_key_exists('id', $input)) {
            return null;
        }

        switch ($mcpMethod) {
            case 'initialize':
                $clientProtocol = $params['protocolVersion'] ?? '2025-06-18';
                return [
                    'jsonrpc' => '2.0',
                    'id'      => $jsonrpcId,
                    'result'  => [
                        'protocolVersion' => $clientProtocol,
                        'capabilities'    => [
                            'tools'   => ['listChanged' => false],
                            'prompts' => ['listChanged' => false]
                        ],
                        'serverInfo'      => [
                            'name'    => 'products-analytics-mcp',
                            'version' => '1.0.0'
                        ],
                        'instructions'    => $this->getSystemPrompt()
                    ]
                ];

            case 'prompts/list':
                $skills = $this->getDynamicSkills();
                $prompts = [];
                foreach ($skills as $sId => $skill) {
                    if (empty($skill['enabled'])) continue;
                    $prompts[] = [
                        'name'        => str_replace('-', '_', $sId),
                        'description' => $skill['description'] ?? $skill['title'],
                        'arguments'   => [
                            [
                                'name'        => 'product_name',
                                'description' => 'Optional name or title of the product',
                                'required'    => false
                            ],
                            [
                                'name'        => 'product_image_url',
                                'description' => 'Optional product image reference URL',
                                'required'    => false
                            ],
                            [
                                'name'        => 'language',
                                'description' => 'Target language (e.g. Arabic, Moroccan Darija, French)',
                                'required'    => false
                            ]
                        ]
                    ];
                }

                // Default aliases
                $prompts[] = [
                    'name'        => 'ecommerce_analytics_assistant',
                    'description' => 'System prompt and skill instructions for e-commerce COD product analysis and winning ads discovery.',
                    'arguments'   => []
                ];

                return [
                    'jsonrpc' => '2.0',
                    'id'      => $jsonrpcId,
                    'result'  => [
                        'prompts' => $prompts
                    ]
                ];

            case 'prompts/get':
                $promptName = $params['name'] ?? 'ecommerce_analytics_assistant';
                $promptArgs = $params['arguments'] ?? [];

                $skills = $this->getDynamicSkills();
                foreach ($skills as $sId => $skill) {
                    $slug = str_replace('-', '_', $sId);
                    if ($promptName === $slug || $promptName === $sId) {
                        $instructions = $skill['instructions'] ?? '';
                        if ($sId === 'nano-banana-pro-consistent-ads') {
                            $instructions = $this->getNanoBananaSkillPrompt($promptArgs);
                        } else {
                            if (!empty($promptArgs['product_name'])) {
                                $instructions = "# Target Product: {$promptArgs['product_name']}\n\n" . $instructions;
                            }
                        }

                        return [
                            'jsonrpc' => '2.0',
                            'id'      => $jsonrpcId,
                            'result'  => [
                                'description' => $skill['title'] ?? 'Skill prompt',
                                'messages'    => [
                                    [
                                        'role'    => 'user',
                                        'content' => [
                                            'type' => 'text',
                                            'text' => $instructions
                                        ]
                                    ]
                                ]
                            ]
                        ];
                    }
                }

                return [
                    'jsonrpc' => '2.0',
                    'id'      => $jsonrpcId,
                    'result'  => [
                        'description' => 'System prompt and skill instructions for e-commerce product analysis.',
                        'messages'    => [
                            [
                                'role'    => 'user',
                                'content' => [
                                    'type' => 'text',
                                    'text' => $this->getSystemPrompt()
                                ]
                            ]
                        ]
                    ]
                ];

            case 'tools/list':
                return [
                    'jsonrpc' => '2.0',
                    'id'      => $jsonrpcId,
                    'result'  => [
                        'tools' => $this->getToolsManifest()
                    ]
                ];

            case 'tools/call':
                $toolName = $params['name'] ?? '';
                $toolArgs = $params['arguments'] ?? [];

                if (is_string($toolArgs)) {
                    $decodedArgs = json_decode($toolArgs, true);
                    if (is_array($decodedArgs)) {
                        $toolArgs = $decodedArgs;
                    }
                }
                if (!is_array($toolArgs)) {
                    $toolArgs = [];
                }
                
                try {
                    $resultData = $this->executeTool($toolName, $toolArgs);
                    $isError    = is_array($resultData) && (isset($resultData['error']) || ($resultData['status'] ?? '') === 'error');
                    
                    return [
                        'jsonrpc' => '2.0',
                        'id'      => $jsonrpcId,
                        'result'  => [
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => is_string($resultData) ? $resultData : json_encode($resultData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                                ]
                            ],
                            'isError' => $isError
                        ]
                    ];
                } catch (\Throwable $e) {
                    return [
                        'jsonrpc' => '2.0',
                        'id'      => $jsonrpcId,
                        'result'  => [
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => json_encode(['status' => 'error', 'error' => 'Internal error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                                ]
                            ],
                            'isError' => true
                        ]
                    ];
                }

            case 'resources/list':
                return [
                    'jsonrpc' => '2.0',
                    'id'      => $jsonrpcId,
                    'result'  => [
                        'resources' => []
                    ]
                ];

            case 'roots/list':
                return [
                    'jsonrpc' => '2.0',
                    'id'      => $jsonrpcId,
                    'result'  => [
                        'roots' => []
                    ]
                ];

            case 'ping':
                return [
                    'jsonrpc' => '2.0',
                    'id'      => $jsonrpcId,
                    'result'  => (object)[]
                ];

            default:
                return [
                    'jsonrpc' => '2.0',
                    'id'      => $jsonrpcId,
                    'error'   => [
                        'code'    => -32601,
                        'message' => 'Method not found: ' . $mcpMethod
                    ]
                ];
        }
    }

    /**
     * Retrieve all managed AI skills from database settings
     */
    private function getDynamicSkills(): array
    {
        $db = \Config\Database::connect();
        $row = $db->table('settings')->where('key', 'mcp_skills_list')->get()->getRowArray();
        if ($row && !empty($row['value'])) {
            $decoded = json_decode($row['value'], true);
            if (is_array($decoded) && !empty($decoded)) {
                return $decoded;
            }
        }

        // Fallback default skills
        return [
            'cod-assistant' => [
                'id'           => 'cod-assistant',
                'title'        => 'مهارة تحليل واستكشاف منتجات COD (COD Assistant)',
                'description'  => 'System prompt and skill instructions for e-commerce COD product analysis and winning ads discovery.',
                'badge'        => 'COD Strategy',
                'tool_name'    => 'get_ai_skill_instructions',
                'instructions' => $this->getSystemPrompt(),
                'enabled'      => true,
            ],
            'nano-banana-pro-consistent-ads' => [
                'id'           => 'nano-banana-pro-consistent-ads',
                'title'        => 'مهارة Nano Banana Pro (الهوية البصرية وتوليد الإعلانات)',
                'description'  => 'Nano Banana Pro Image-to-Image Ad Generator with Web Color System.',
                'badge'        => 'Creative Skill',
                'tool_name'    => 'get_nano_banana_pro_instructions',
                'instructions' => $this->getNanoBananaSkillPrompt(),
                'enabled'      => true,
            ],
        ];
    }

    /**
     * Retrieve global MCP system prompt / skill instructions
     */
    private function getNanoBananaSkillPrompt(array $args = []): string
    {
        $productName  = $args['product_name'] ?? '{{PRODUCT_NAME}}';
        $productImage = $args['product_image_url'] ?? '{{ask_user_product_image}}';
        $lang         = $args['language'] ?? 'Arabic';

        $skillFile = realpath(APPPATH . '/../.agents/skills/nano-banana-pro-consistent-ads/SKILL.md');
        if ($skillFile && file_exists($skillFile)) {
            $content = file_get_contents($skillFile);
            $content = str_replace('{{ask_user_product_image}}', $productImage, $content);
            $content = str_replace('{{LANGUAGE}}', $lang, $content);
            if ($productName !== '{{PRODUCT_NAME}}') {
                $content = "# Target Product: {$productName}\n\n" . $content;
            }
            return $content;
        }

        return "# Nano Banana Pro Ad & Web Color Pipeline\n\n"
             . "Product: {$productName}\n"
             . "Reference Asset: {$productImage}\n"
             . "Target Language: {$lang}\n";
    }

    private function getSystemPrompt(): string
    {
        $db = \Config\Database::connect();
        $row = $db->table('settings')->where('key', 'mcp_system_prompt')->get()->getRowArray();
        if ($row && !empty($row['value'])) {
            return $row['value'];
        }
        return 'تنبيه مهم: آلية العمل ومراحل التنفيذ
سيتم تنفيذ المهام على مراحل متتالية، ولا يتم الانتقال إلى أي مرحلة قبل إتمام المرحلة السابقة واعتماد نتائجها.
عند ارسال كلمة ابدا او start ضع الخيارات التالية
تحليل اخر اصدار snapshots
اضهار list_snapshots

المرحلة الأولى: اختيار واستكشاف المنتجات المرشحة والرابحة
- عند طلب الاستكشاف أو التحليل، استخدم أدوات MCP المتاحة مثل `filter_winning_products` (مع تحديد country=\'MA\' للسوق المغربي)، أو `list_snapshots` / `get_snapshot_by_date` للتواريخ المتاحة، أو `get_saved_products` للاستعلام عن المحفوظات الخاصة بالحساب.
- تحليل المنتجات المرشحة وتقييمها بناءً على معايير الجدوى والطلب المتاح بالبيانات.
- تطبيق نظام تقييم المنتجات (Score من 100): قوة الطلب في الإعلانات (40 نقطة)، ملاءمة السوق والموسم في المغرب (30 نقطة)، سهولة اللوجستيك (20 نقطة)، والتوافق مع قيود الميزانية والميول (10 نقاط).
- تصنيف المنتجات إلى: 🟢 رابحة (>= 75)، 🟡 واعدة (50-74)، 🔴 ضعيفة/عالية المخاطر (< 50).
- عرض قائمة التقييم والجداول بوضوح، وانتظار اختيار وموافقة المستخدم على المنتج الرابح قبل الانتقال للمرحلة التالية.
- عند دكر كمبتدأ و المبلغ المخصص للإعلانات بشكل شامل (1000 درهم افتراضي) في التجارة الالكترونية يجب اختيار منتجات مناسبة و أيضا بشكل افتراضي ستكون كمية المنتج بين 20 و 30 قطعة و أيضا يجب مراعات الميزانية و المدة التي يمكن ان تصرف فيها الكمية و نوع المنتج

المرحلة الثانية: إدخال بيانات المنتج والتحليل التفصيلي الشامل
بعد اعتماد المنتج الرابح، استخدم أداة `get_product_full_json` لجلب البيانات الخام الكاملة للمنتج (أو طلب البيانات النواقص مثل C_wholesale و C_shipping و سعر المنافس و الكمية من المستخدم)، ثم ابدأ تنفيذ جميع العمليات التالية:
1. التحليل المالي والتسعير ونموذج COD للسوق المغربي:
   - حساب التكلفة الفعلية للتوصيل مع الرجوع: C_shipping / (1 - R).
   - مقارنة سعر البيع المقترح مع سعر المنافس وتوضيح الفارق التنافسي بالنسبة المئوية.
   - تقديم جداول تسعير مفصلة تشمل هامش الربح الصافي النهائي (M_final).
2. خطة الإعلانات وتصريف المخزون:
   - ادا لم يدكر المستخدم الميزانية الاعلانية الشاملة اولا اقترح افضل ميزانية انطلاقا من الكمية و تمن المنتج المنافس و تمن الجملة 
   - تحديد مدة تصريف المخزون بناءً على حجم الوحدات (مثلاً Micro-Batch للمخزون < 20 قطعة).
   - تقسيم الميزانية الإعلانية عبر ثلاث مراحل: اختبار، توسيع، وحرق المخزون.
3. صناعة المحتوى الإعلاني والـ Creatives:
   - إنشاء سكربتات إعلانية مبنية على هيكل (Hook -> Problem -> Solution -> Offer -> CTA).
   - تقديم برومبتات AI دقيقة لتوليد فيديوهات UGC وعناوين ووصف إعلاني لكل منتج رابح.
   - توليد 4 برومبتات AI لصور إعلانية (احترافية، Lifestyle، زاوية تحويلية).
   - كتابة 3 نسخ إعلانية لفيسبوك (Primary Text) ومحتوى مخصص لتيك توك (Hooks, Hashtags).
4. الربط والتوجيه للمرحلة البصرية (Nano Banana Pro):
   - في نهاية المرحلة الثانية، اطرح على المستخدم السؤال التالي:
     "بعد اعتماد الخطة الإعلانية والمالية، هل ترغب في توليد الهوية البصرية، نظام ألوان الويب، وبرومبتات Nano Banana Pro لهذا المنتج؟"
   - عند موافقة المستخدم: يتم تفعيل مهارة `nano-banana-pro-consistent-ads` وتمرير صورة المنتج واسمه إليها مباشرة لتوليد الهوية البصرية ونظام الألوان وبرومبتات الإعلانات المتناسقة.

اللغة والأسلوب:
- استخدام اللغة العربية الفصحى أو الدارجة المغربية مع قبول المصطلحات التقنية (ROAS, CPA, COD).
- الاعتماد المكثف على الجداول والأرقام الواضحة والعملية.
- اضف اقتراحات و حلول
- يجب دكر عنوان الاعلان كماهو بين قوسين
- اضف رابط الاعلان على اسم المنتج 
- عند تقديم المنتجات والروابط للمستخدم، يجب توفير الروابط النصية المباشرة للمتاجر بدون تحويل أو استخدام روابط بحث Google التلقائية.
- Preserve URLs exactly.
- Never prepend google.com/search?q=.
- Never encode a URL as a Google search query.
- Return direct clickable URLs only.
- Before answering, verify that the URL hostname is the intended destination.';
    }

    /**
     * Resolve user & tenant_id from API Token or active session
     */
    private function resolveAuthenticatedUser()
    {
        $token = $this->request->getGet('token') 
              ?? $this->request->getGet('api_key') 
              ?? $this->request->getGet('api-key');

        if (empty($token)) {
            $authHeader = $this->request->getHeaderLine('Authorization');
            if (!empty($authHeader)) {
                if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                    $token = trim($matches[1]);
                } else {
                    $token = trim($authHeader);
                }
            }
        }

        if (empty($token)) {
            $token = $this->request->getHeaderLine('api-key');
        }

        if (empty($token)) {
            $token = $this->request->getHeaderLine('X-API-Key');
        }

        if (empty($token)) {
            $token = $this->request->getHeaderLine('api_key');
        }

        if (empty($token)) {
            $token = $this->request->getHeaderLine('x-token');
        }

        if (!empty($token)) {
            $db = \Config\Database::connect();
            $userRow = $db->table('users')->where('api_token', $token)->get()->getRowArray();
            if ($userRow) {
                return $userRow;
            }
        }

        // Fallback to active logged in session
        if (auth()->loggedIn()) {
            $user = auth()->user();
            return [
                'id'        => $user->id,
                'username'  => $user->username,
                'tenant_id' => $user->tenant_id ?? 1
            ];
        }

        return null;
    }

    /**
     * Get array of registered MCP tools and JSON schemas
     */
    private function getToolsManifest()
    {
        $db = \Config\Database::connect();
        $toolSettings = $db->table('settings')->like('key', 'mcp_tool_')->get()->getResultArray();
        $disabledTools = [];
        foreach ($toolSettings as $settingRow) {
            if ($settingRow['value'] === '0') {
                $disabledTools[] = str_replace('mcp_tool_', '', $settingRow['key']);
            }
        }

        $skills = $this->getDynamicSkills();
        $skillTools = [];
        foreach ($skills as $sId => $skill) {
            if (empty($skill['enabled'])) continue;
            $toolName = $skill['tool_name'] ?? ('get_' . str_replace('-', '_', $sId) . '_instructions');
            if (in_array($toolName, $disabledTools, true)) continue;

            $skillTools[] = [
                'name'        => $toolName,
                'description' => $skill['description'] ?? ('Retrieve skill instructions for ' . ($skill['title'] ?? $sId)),
                'inputSchema' => [
                    'type'                 => 'object',
                    'properties'           => [
                        'product_name'      => [
                            'type'        => 'string',
                            'description' => 'Optional product name to customize prompt templates.'
                        ],
                        'product_image_url' => [
                            'type'        => 'string',
                            'description' => 'Optional product image reference URL for image-to-image lock.'
                        ],
                        'language'          => [
                            'type'        => 'string',
                            'description' => 'Target language for copywriting and typography (e.g. Arabic, Moroccan Darija, French).'
                        ]
                    ],
                    'additionalProperties' => false
                ]
            ];
        }

        $allTools = array_merge($skillTools, [
            [
                'name'        => 'get_saved_products',
                'description' => 'Retrieve products saved specifically by the authenticated user/tenant, with options for collection, country, search, and sorting.',
                'inputSchema' => [
                    'type'                 => 'object',
                    'properties'           => [
                        'collection'   => ['type' => 'string', 'description' => 'Filter by collection name (e.g. عامة, ملابس, إلكترونيات)'],
                        'country'      => ['type' => 'string', 'description' => '2-letter country code (e.g. MA, SA)'],
                        'saved_status' => ['type' => 'string', 'description' => 'Status: active or inactive'],
                        'search_query' => ['type' => 'string', 'description' => 'Search term in title, body, or notes'],
                        'sort_by'      => ['type' => 'string', 'enum' => ['saved_at', 'rating', 'created_at', 'title']],
                        'sort_order'   => ['type' => 'string', 'enum' => ['ASC', 'DESC']],
                        'limit'        => ['type' => 'number', 'description' => 'Max products to return (default 50)'],
                        'offset'       => ['type' => 'number', 'description' => 'Offset for pagination (default 0)']
                    ],
                    'additionalProperties' => false
                ]
            ],
            [
                'name'        => 'save_product',
                'description' => 'Save or update a product in saved-ads (المحفوظات) for the authenticated user based on their MCP API token.',
                'inputSchema' => [
                    'type'                 => 'object',
                    'properties'           => [
                        'product_id'  => ['type' => 'number', 'description' => 'Database ID of the product if existing in the system'],
                        'product_url' => ['type' => 'string', 'description' => 'URL of the product to save (required if product_id is not provided)'],
                        'collection'  => ['type' => 'string', 'description' => 'Collection name (e.g. عامة, ملابس, إلكترونيات). Default is "عامة"'],
                        'notes'       => ['type' => 'string', 'description' => 'Optional user notes for the saved ad'],
                        'rating'      => ['type' => 'number', 'description' => 'Optional rating (0-5)'],
                        'title'       => ['type' => 'string', 'description' => 'Product title (optional)'],
                        'country'     => ['type' => 'string', 'description' => 'Country code (e.g. MA)']
                    ],
                    'additionalProperties' => false
                ]
            ],
            [
                'name'        => 'list_snapshots',
                'description' => 'List available data snapshots stored in the system, with optional origin filtering and pagination.',
                'inputSchema' => [
                    'type'                 => 'object',
                    'properties'           => [
                        'origin' => ['type' => 'string', 'description' => 'Filter by origin (Winning, China, Japan, Competitor, Local)'],
                        'limit'  => ['type' => 'number', 'description' => 'Limit results (default 20)'],
                        'offset' => ['type' => 'number', 'description' => 'Offset results (default 0)']
                    ],
                    'additionalProperties' => false
                ]
            ],
            [
                'name'        => 'get_snapshot_by_date',
                'description' => 'Request product data snapshot entries by date string, api_version, or snapshot_id.',
                'inputSchema' => [
                    'type'                 => 'object',
                    'properties'           => [
                        'date'        => ['type' => 'string', 'description' => 'Date or api_version substring (e.g. 2026-07-26)'],
                        'snapshot_id' => ['type' => 'number', 'description' => 'Exact snapshot ID'],
                        'origin'      => ['type' => 'string', 'description' => 'Origin category (default Winning)'],
                        'country'     => ['type' => 'string', 'description' => 'Country code (e.g. MA, SA)'],
                        'limit'       => ['type' => 'number', 'description' => 'Max items to return (default 100)']
                    ],
                    'additionalProperties' => false
                ]
            ],
            [
                'name'        => 'filter_winning_products',
                'description' => 'Filter snapshot and DB data specifically for Winning Products (origin = Winning) using tRPC API filters.',
                'inputSchema' => [
                    'type'                 => 'object',
                    'properties'           => [
                        'snapshot_id'     => ['type' => 'number'],
                        'date'            => ['type' => 'string'],
                        'country'         => ['type' => 'string', 'description' => '2-letter country code'],
                        'min_ads'         => ['type' => 'number'],
                        'max_ads'         => ['type' => 'number'],
                        'min_price'       => ['type' => 'number'],
                        'max_price'       => ['type' => 'number'],
                        'search_query'    => ['type' => 'string'],
                        'active_ads_only' => ['type' => 'boolean'],
                        'sort_by'         => ['type' => 'string', 'enum' => ['ads_count', 'title', 'price', 'date']],
                        'sort_order'      => ['type' => 'string', 'enum' => ['ASC', 'DESC']],
                        'limit'           => ['type' => 'number'],
                        'offset'          => ['type' => 'number']
                    ],
                    'additionalProperties' => false
                ]
            ],
            [
                'name'        => 'get_products',
                'description' => 'Fetch single or multiple products by IDs, or search products by name/title.',
                'inputSchema' => [
                    'type'                 => 'object',
                    'properties'           => [
                        'ids'     => ['type' => 'array', 'items' => ['type' => 'number']],
                        'name'    => ['type' => 'string'],
                        'origin'  => ['type' => 'string'],
                        'country' => ['type' => 'string'],
                        'limit'   => ['type' => 'number']
                    ],
                    'additionalProperties' => false
                ]
            ],
            [
                'name'        => 'get_product_full_json',
                'description' => 'Retrieve complete unredacted JSON object of a product by ID or title.',
                'inputSchema' => [
                    'type'                 => 'object',
                    'properties'           => [
                        'product_id' => ['type' => 'number'],
                        'title'      => ['type' => 'string']
                    ],
                    'additionalProperties' => false
                ]
            ],
            [
                'name'        => 'fetch_new_data',
                'description' => 'Fetch new product data entries filtered by date and country, with classification/origin defaulting to all (ككل / all).',
                'inputSchema' => [
                    'type'                 => 'object',
                    'properties'           => [
                        'date'           => ['type' => 'string', 'description' => 'Date string (e.g. YYYY-MM-DD), date range (today, yesterday, 7days, 30days), or api_version string'],
                        'country'        => ['type' => 'string', 'description' => '2-letter country code (e.g. MA, SA, DZ) or "all" (default all)'],
                        'classification' => ['type' => 'string', 'description' => 'Data classification/origin filter (e.g. Winning, Local, China, Japan, or "all" / "ككل"). Defaults to "all" (ككل).'],
                        'search_query'   => ['type' => 'string', 'description' => 'Search term for title or ad content'],
                        'sort_by'        => ['type' => 'string', 'enum' => ['date', 'ads_count', 'title', 'price']],
                        'sort_order'     => ['type' => 'string', 'enum' => ['ASC', 'DESC']],
                        'limit'          => ['type' => 'number', 'description' => 'Max products to return (default 50)'],
                        'offset'         => ['type' => 'number', 'description' => 'Offset for pagination (default 0)']
                    ],
                    'additionalProperties' => false
                ]
            ],
            [
                'name'        => 'facebook_search_ads',
                'description' => 'Search Facebook Ads Library with advanced filters (brand_name, country, ad_type, date_range, limit).',
                'inputSchema' => [
                    'type'                 => 'object',
                    'properties'           => [
                        'brand_name' => ['type' => 'string', 'description' => 'Brand or keyword name to search in Facebook Ads Library'],
                        'country'    => ['type' => 'string', 'description' => 'Target country code (e.g. US, MA, SA, GB). Default US'],
                        'ad_type'    => ['type' => 'string', 'enum' => ['ALL', 'POLITICAL_AND_ISSUE_ADS', 'HOUSING_ADS', 'NEWS_ADS', 'UNCATEGORIZED']],
                        'date_range' => ['type' => 'number', 'description' => 'Days to look back (default 30)'],
                        'limit'      => ['type' => 'number', 'description' => 'Maximum number of ads to return (1-100, default 50)'],
                        'token'      => ['type' => 'string', 'description' => 'Optional custom Facebook Graph API token']
                    ],
                    'required'             => ['brand_name'],
                    'additionalProperties' => false
                ]
            ],
            [
                'name'        => 'facebook_discover_competitors',
                'description' => 'Discover active competitor brands advertising in an industry / niche with ad volume rankings.',
                'inputSchema' => [
                    'type'                 => 'object',
                    'properties'           => [
                        'industry_keywords' => ['type' => 'string', 'description' => 'Industry or niche keywords (e.g. "fitness app", "skincare", "food delivery")'],
                        'region'            => ['type' => 'string', 'description' => 'Target country/region code (default US)'],
                        'min_ads'           => ['type' => 'number', 'description' => 'Minimum ads threshold to qualify brand (default 5)'],
                        'limit'             => ['type' => 'number', 'description' => 'Maximum brands to return (default 50)'],
                        'token'             => ['type' => 'string', 'description' => 'Optional custom Facebook Graph API token']
                    ],
                    'required'             => ['industry_keywords'],
                    'additionalProperties' => false
                ]
            ],
            [
                'name'        => 'facebook_analyze_creative',
                'description' => 'Deep analysis of ad creative elements (text copy, CTAs, sentiment, and urgency triggers).',
                'inputSchema' => [
                    'type'                 => 'object',
                    'properties'           => [
                        'ad_snapshot_url' => ['type' => 'string', 'description' => 'Facebook ad snapshot URL'],
                        'extract_text'    => ['type' => 'boolean', 'description' => 'Extract full text copy and sentiment keywords (default true)'],
                        'analyze_images'  => ['type' => 'boolean', 'description' => 'Analyze image elements (default true)'],
                        'detect_cta'      => ['type' => 'boolean', 'description' => 'Detect Call To Action (CTA) buttons and urgency words (default true)']
                    ],
                    'required'             => ['ad_snapshot_url'],
                    'additionalProperties' => false
                ]
            ],
            [
                'name'        => 'facebook_analyze_performance',
                'description' => 'Analyze advertising performance metrics for a brand (estimated impressions, spend range, platform distribution, demographics).',
                'inputSchema' => [
                    'type'                 => 'object',
                    'properties'           => [
                        'brand_name'  => ['type' => 'string', 'description' => 'Brand name to analyze'],
                        'time_period' => ['type' => 'number', 'description' => 'Analysis time window in days (default 30)'],
                        'token'       => ['type' => 'string', 'description' => 'Optional custom Facebook Graph API token']
                    ],
                    'required'             => ['brand_name'],
                    'additionalProperties' => false
                ]
            ],
            [
                'name'        => 'facebook_competitive_analysis',
                'description' => 'Compare ad strategies across multiple competitor brands (identifying market leaders, spend levels, platform trends, and common creative themes).',
                'inputSchema' => [
                    'type'                 => 'object',
                    'properties'           => [
                        'brands_list'    => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'List of brand names to compare'],
                        'analysis_depth' => ['type' => 'string', 'enum' => ['standard', 'deep']],
                        'token'          => ['type' => 'string', 'description' => 'Optional custom Facebook Graph API token']
                    ],
                    'required'             => ['brands_list'],
                    'additionalProperties' => false
                ]
            ],
            [
                'name'        => 'facebook_intelligence_report',
                'description' => 'Generate complete intelligence report for a brand with competitor benchmarks and actionable marketing recommendations.',
                'inputSchema' => [
                    'type'                 => 'object',
                    'properties'           => [
                        'brand_name'          => ['type' => 'string', 'description' => 'Primary brand name to generate report for'],
                        'include_competitors' => ['type' => 'boolean', 'description' => 'Include automated competitor discovery and benchmarking (default true)'],
                        'report_depth'        => ['type' => 'string', 'enum' => ['basic', 'standard', 'comprehensive']],
                        'token'               => ['type' => 'string', 'description' => 'Optional custom Facebook Graph API token']
                    ],
                    'required'             => ['brand_name'],
                    'additionalProperties' => false
                ]
            ],
            [
                'name'        => 'facebook_export_ads',
                'description' => 'Export Facebook ads data in various formats (json, csv, markdown) with optional creative analysis.',
                'inputSchema' => [
                    'type'                 => 'object',
                    'properties'           => [
                        'brand_name'        => ['type' => 'string', 'description' => 'Brand name to export ads for'],
                        'export_format'     => ['type' => 'string', 'enum' => ['json', 'csv', 'markdown'], 'description' => 'Export format (default json)'],
                        'include_creatives' => ['type' => 'boolean', 'description' => 'Include creative copy analysis (default false)'],
                        'limit'             => ['type' => 'number', 'description' => 'Maximum ads to export (default 100)'],
                        'token'             => ['type' => 'string', 'description' => 'Optional custom Facebook Graph API token']
                    ],
                    'required'             => ['brand_name'],
                    'additionalProperties' => false
                ]
            ]
        ]);

        return array_values(array_filter($allTools, function($t) use ($disabledTools) {
            return !in_array($t['name'], $disabledTools, true);
        }));
    }

    /**
     * Dispatch tool execution
     */
    private function executeTool($name, $args)
    {
        $db = \Config\Database::connect();
        $toolCheck = $db->table('settings')->where('key', 'mcp_tool_' . $name)->get()->getRowArray();
        if ($toolCheck && $toolCheck['value'] === '0') {
            return ['error' => "Tool '{$name}' is currently disabled by administrator."];
        }

        $snapshotModel = new SnapshotModel();
        $productModel  = new ProductModel();

        $skills = $this->getDynamicSkills();
        foreach ($skills as $sId => $skill) {
            $toolName = $skill['tool_name'] ?? ('get_' . str_replace('-', '_', $sId) . '_instructions');
            if ($name === $toolName || $name === ('get_' . str_replace('-', '_', $sId) . '_instructions')) {
                if ($sId === 'nano-banana-pro-consistent-ads') {
                    $instructions = $this->getNanoBananaSkillPrompt($args);
                } else {
                    $instructions = $skill['instructions'] ?? '';
                    if (!empty($args['product_name'])) {
                        $instructions = "# Target Product: {$args['product_name']}\n\n" . $instructions;
                    }
                }

                return [
                    'status'             => 'success',
                    'skill_id'           => $sId,
                    'skill_name'         => $skill['title'] ?? $sId,
                    'title'              => $skill['title'] ?? $sId,
                    'badge'              => $skill['badge'] ?? 'AI Skill',
                    'skill_instructions' => $instructions
                ];
            }
        }

        if ($name === 'get_saved_products') {
            $authUser = $this->resolveAuthenticatedUser();
            if (!$authUser) {
                return [
                    'status'   => 'error',
                    'total'    => 0,
                    'products' => [],
                    'error'    => 'Unauthorized: Invalid or missing API token. Please generate a token in your profile settings.'
                ];
            }

            $tenantId      = $authUser['tenant_id'] ?? 1;
            $collection    = $args['collection'] ?? null;
            $status        = $args['saved_status'] ?? null;
            $countryFilter = isset($args['country']) ? strtoupper($args['country']) : null;
            $searchQuery   = isset($args['search_query']) ? strtolower($args['search_query']) : null;
            $sortBy        = $args['sort_by'] ?? 'saved_at';
            $sortOrder     = strtoupper($args['sort_order'] ?? 'DESC');
            $limit         = intval($args['limit'] ?? 50);
            $offset        = intval($args['offset'] ?? 0);

            $builder = $productModel->where('tenant_id', $tenantId)
                                   ->where('is_saved', true);

            if (!empty($collection)) {
                $builder->where('collection', $collection);
            }
            if (!empty($status)) {
                $builder->where('saved_status', $status);
            }
            if (!empty($countryFilter)) {
                $builder->like('country', $countryFilter);
            }
            if (!empty($searchQuery)) {
                $builder->groupStart()
                        ->like('title', $searchQuery)
                        ->orLike('ad_title', $searchQuery)
                        ->orLike('ad_body', $searchQuery)
                        ->orLike('notes', $searchQuery)
                        ->groupEnd();
            }

            if (in_array($sortBy, ['saved_at', 'rating', 'created_at', 'title'])) {
                $builder->orderBy($sortBy, $sortOrder);
            } else {
                $builder->orderBy('saved_at', 'DESC');
            }

            $savedProducts = $builder->findAll($limit, $offset);

            return [
                'status'          => 'success',
                'total'           => count($savedProducts),
                'user'            => [
                    'username'  => $authUser['username'] ?? 'User',
                    'tenant_id' => $tenantId
                ],
                'total_returned'  => count($savedProducts),
                'filters_applied' => [
                    'collection'   => $collection,
                    'saved_status' => $status,
                    'country'      => $countryFilter,
                    'search_query' => $searchQuery,
                    'sort_by'      => $sortBy,
                    'sort_order'   => $sortOrder,
                ],
                'products' => $savedProducts
            ];
        }

        if ($name === 'save_product' || $name === 'save_ad') {
            $authUser = $this->resolveAuthenticatedUser();
            if (!$authUser) {
                return [
                    'status' => 'error',
                    'error'  => 'Unauthorized: Invalid or missing API token. Please pass a valid token in the URL or Authorization header.'
                ];
            }

            $tenantId   = $authUser['tenant_id'] ?? 1;
            $productId  = isset($args['product_id']) ? intval($args['product_id']) : null;
            $productUrl = $args['product_url'] ?? null;
            $collection = !empty($args['collection']) ? $args['collection'] : 'عامة';
            $notes      = $args['notes'] ?? '';
            $rating     = isset($args['rating']) ? intval($args['rating']) : 0;

            $targetProduct = null;

            if ($productId) {
                $targetProduct = $productModel->find($productId);
                if ($targetProduct && empty($productUrl)) {
                    $productUrl = $targetProduct['product_url'] ?? null;
                }
            }

            $existingTenantProduct = null;
            if (!empty($productUrl)) {
                $existingTenantProduct = $productModel->where('product_url', $productUrl)
                                                     ->where('tenant_id', $tenantId)
                                                     ->first();
            }

            if ($existingTenantProduct) {
                $updateData = [
                    'is_saved'     => true,
                    'saved_at'     => date('Y-m-d H:i:s'),
                    'saved_status' => 'active',
                    'collection'   => $collection ?: ($existingTenantProduct['collection'] ?: 'عامة'),
                ];
                if (!empty($notes)) {
                    $updateData['notes'] = $notes;
                }
                if ($rating > 0) {
                    $updateData['rating'] = $rating;
                }
                if (!empty($args['title'])) {
                    $updateData['title'] = $args['title'];
                }

                $productModel->update($existingTenantProduct['id'], $updateData);
                $savedRecord = $productModel->find($existingTenantProduct['id']);

                return [
                    'status'   => 'success',
                    'action'   => 'updated_saved',
                    'message'  => 'تم حفظ وتحديث المنتج بنجاح في المحفوظات! ⭐',
                    'user'     => [
                        'id'        => $authUser['id'] ?? null,
                        'username'  => $authUser['username'] ?? 'User',
                        'tenant_id' => $tenantId
                    ],
                    'product'  => $savedRecord
                ];
            }

            $globalProduct = null;
            if (!empty($productUrl)) {
                $globalProduct = $productModel->where('product_url', $productUrl)->first();
            } elseif ($targetProduct) {
                $globalProduct = $targetProduct;
                $productUrl    = $targetProduct['product_url'] ?? '';
            }

            $source = $globalProduct ?: ($targetProduct ?: []);

            if (empty($productUrl) && empty($source)) {
                return [
                    'status' => 'error',
                    'error'  => 'Product not found. Please provide a valid product_id or product_url.'
                ];
            }

            $origin = $args['origin'] ?? $source['origin'] ?? 'Winning';
            $dataToInsert = [
                'title'              => $args['title'] ?? $source['title'] ?? 'بدون عنوان',
                'product_url'        => $productUrl ?: ($source['product_url'] ?? ''),
                'country'            => $args['country'] ?? $source['country'] ?? '',
                'algo'               => $args['algo'] ?? $source['algo'] ?? 'winning',
                'ad_start_date'      => $args['ad_start_date'] ?? $source['ad_start_date'] ?? date('Y-m-d'),
                'ads_count'          => intval($args['ads_count'] ?? $source['ads_count'] ?? 0),
                'unique_image_count' => intval($args['unique_image_count'] ?? $source['unique_image_count'] ?? 0),
                'unique_video_count' => intval($args['unique_video_count'] ?? $source['unique_video_count'] ?? 0),
                'avg_creatives'      => floatval($args['avg_creatives'] ?? $source['avg_creatives'] ?? 1),
                'ads_per_unique_url' => floatval($args['ads_per_unique_url'] ?? $source['ads_per_unique_url'] ?? 1),
                'ad_title'           => $args['ad_title'] ?? $source['ad_title'] ?? '',
                'ad_body'            => $args['ad_body'] ?? $source['ad_body'] ?? '',
                'ad_image_urls'      => is_array($args['ad_image_urls'] ?? null) ? implode(';', $args['ad_image_urls']) : ($args['ad_image_urls'] ?? $source['ad_image_urls'] ?? ''),
                'ad_video_urls'      => is_array($args['ad_video_urls'] ?? null) ? implode(';', $args['ad_video_urls']) : ($args['ad_video_urls'] ?? $source['ad_video_urls'] ?? ''),
                'price_1'            => strval($args['price'] ?? $args['price_1'] ?? $source['price_1'] ?? '0'),
                'active_ads'         => true,
                'origin'             => $origin,
                'api_version'        => $args['api_version'] ?? $source['api_version'] ?? '',
                'is_saved'           => true,
                'saved_at'           => date('Y-m-d H:i:s'),
                'collection'         => $collection,
                'saved_status'       => 'active',
                'rating'             => $rating,
                'notes'              => $notes,
                'tenant_id'          => $tenantId
            ];

            $newId = $productModel->insert($dataToInsert);
            $savedRecord = $productModel->find($newId);

            return [
                'status'   => 'success',
                'action'   => 'saved',
                'message'  => 'تم حفظ المنتج بنجاح في المحفوظات! ⭐',
                'user'     => [
                    'id'        => $authUser['id'] ?? null,
                    'username'  => $authUser['username'] ?? 'User',
                    'tenant_id' => $tenantId
                ],
                'product'  => $savedRecord
            ];
        }

        if ($name === 'list_snapshots') {
            $origin = $args['origin'] ?? null;
            $limit  = intval($args['limit'] ?? 20);
            $offset = intval($args['offset'] ?? 0);

            $builder = $db->table('data_snapshots')
                          ->select('id, origin, api_version, product_count, created_at, updated_at');
            if (!empty($origin)) {
                $builder->where('origin', $origin);
            }
            $snapshots = $builder->orderBy('id', 'DESC')->limit($limit, $offset)->get()->getResultArray();

            return [
                'status'    => 'success',
                'total'     => count($snapshots),
                'count'     => count($snapshots),
                'snapshots' => $snapshots
            ];
        }

        if ($name === 'get_snapshot_by_date') {
            $dateStr       = $args['date'] ?? null;
            $snapshotId    = $args['snapshot_id'] ?? null;
            $origin        = $args['origin'] ?? 'Winning';
            $countryFilter = isset($args['country']) ? strtoupper($args['country']) : null;
            $limit         = intval($args['limit'] ?? 100);

            $snapshotRow = null;
            if ($snapshotId) {
                $snapshotRow = $db->table('data_snapshots')->where('id', $snapshotId)->get()->getRowArray();
            } elseif (!empty($dateStr)) {
                $escapedDate = $db->escapeLikeString($dateStr);
                $snapshotRow = $db->table('data_snapshots')
                                  ->groupStart()
                                      ->like('api_version', $dateStr)
                                      ->orWhere("CAST(created_at AS TEXT) LIKE '%{$escapedDate}%'")
                                  ->groupEnd()
                                  ->where('origin', $origin)
                                  ->orderBy('id', 'DESC')
                                  ->get()
                                  ->getRowArray();
            } else {
                $snapshotRow = $db->table('data_snapshots')->where('origin', $origin)->orderBy('id', 'DESC')->get()->getRowArray();
            }

            if (!$snapshotRow) {
                return [
                    'status' => 'error',
                    'total'  => 0,
                    'items'  => [],
                    'products' => [],
                    'error'  => 'No snapshot found matching criteria'
                ];
            }

            $entries = $this->parseSnapshotEntries($snapshotRow['raw_json'] ?? '');
            if ($countryFilter) {
                $entries = array_values(array_filter($entries, function($e) use ($countryFilter) {
                    $cList = array_map('trim', explode(';', strtoupper($e['country'] ?? '')));
                    return in_array($countryFilter, $cList, true);
                }));
            }

            $total = count($entries);
            $entries = array_slice($entries, 0, $limit);

            return [
                'status'            => 'success',
                'total'             => $total,
                'items'             => $entries,
                'products'          => $entries,
                'snapshot' => [
                    'id'            => $snapshotRow['id'],
                    'origin'        => $snapshotRow['origin'],
                    'api_version'   => $snapshotRow['api_version'],
                    'product_count' => $snapshotRow['product_count'],
                    'created_at'    => $snapshotRow['created_at']
                ],
                'returned_count'    => count($entries),
                'total_in_snapshot' => $total
            ];
        }

        if ($name === 'filter_winning_products') {
            $snapshotId    = $args['snapshot_id'] ?? null;
            $dateStr       = $args['date'] ?? null;
            $countryFilter = isset($args['country']) ? strtoupper($args['country']) : null;
            $minAds        = isset($args['min_ads']) ? floatval($args['min_ads']) : null;
            $maxAds        = isset($args['max_ads']) ? floatval($args['max_ads']) : null;
            $minPrice      = isset($args['min_price']) ? floatval($args['min_price']) : null;
            $maxPrice      = isset($args['max_price']) ? floatval($args['max_price']) : null;
            $searchQuery   = isset($args['search_query']) ? strtolower($args['search_query']) : null;
            $activeAdsOnly = !empty($args['active_ads_only']);
            $sortBy        = $args['sort_by'] ?? 'ads_count';
            $sortOrder     = strtoupper($args['sort_order'] ?? 'DESC');
            $limit         = intval($args['limit'] ?? 50);
            $offset        = intval($args['offset'] ?? 0);

            $snapshotRow = null;
            if ($snapshotId) {
                $snapshotRow = $db->table('data_snapshots')->where('id', $snapshotId)->where('origin', 'Winning')->get()->getRowArray();
            } elseif (!empty($dateStr)) {
                $escapedDate = $db->escapeLikeString($dateStr);
                $snapshotRow = $db->table('data_snapshots')
                                  ->where('origin', 'Winning')
                                  ->groupStart()
                                      ->like('api_version', $dateStr)
                                      ->orWhere("CAST(created_at AS TEXT) LIKE '%{$escapedDate}%'")
                                  ->groupEnd()
                                  ->orderBy('id', 'DESC')
                                  ->get()
                                  ->getRowArray();
            } else {
                $snapshotRow = $db->table('data_snapshots')->where('origin', 'Winning')->orderBy('id', 'DESC')->get()->getRowArray();
            }

            $entries = [];
            if ($snapshotRow) {
                $entries = $this->parseSnapshotEntries($snapshotRow['raw_json'] ?? '');
            } else {
                $entries = $db->table('products')->where('origin', 'Winning')->get()->getResultArray();
            }

            // Filter entries
            $filtered = array_filter($entries, function($item) use ($countryFilter, $minAds, $maxAds, $minPrice, $maxPrice, $searchQuery, $activeAdsOnly) {
                $cList = array_map('trim', explode(';', strtoupper($item['country'] ?? '')));
                if ($countryFilter && !in_array($countryFilter, $cList, true)) return false;

                $adsCount = intval($item['ads_count'] ?? $item['adsCount'] ?? 0);
                if ($minAds !== null && $adsCount < $minAds) return false;
                if ($maxAds !== null && $adsCount > $maxAds) return false;

                $price = floatval($item['price_1'] ?? $item['actualPrice'] ?? $item['price'] ?? 0);
                if ($minPrice !== null && $price < $minPrice) return false;
                if ($maxPrice !== null && $price > $maxPrice) return false;

                if ($activeAdsOnly) {
                    $active = isset($item['active_ads']) ? $item['active_ads'] : true;
                    if (!$active) return false;
                }

                if ($searchQuery) {
                    $title   = strtolower($item['product_title'] ?? $item['title'] ?? '');
                    $adTitle = strtolower($item['ad_title'] ?? '');
                    $adBody  = strtolower($item['ad_body'] ?? '');
                    if (strpos($title, $searchQuery) === false && strpos($adTitle, $searchQuery) === false && strpos($adBody, $searchQuery) === false) {
                        return false;
                    }
                }

                return true;
            });

            // Sort entries
            usort($filtered, function($a, $b) use ($sortBy, $sortOrder) {
                if ($sortBy === 'ads_count') {
                    $valA = intval($a['ads_count'] ?? $a['adsCount'] ?? 0);
                    $valB = intval($b['ads_count'] ?? $b['adsCount'] ?? 0);
                } elseif ($sortBy === 'price') {
                    $valA = floatval($a['price_1'] ?? $a['actualPrice'] ?? $a['price'] ?? 0);
                    $valB = floatval($b['price_1'] ?? $b['actualPrice'] ?? $b['price'] ?? 0);
                } elseif ($sortBy === 'title') {
                    $valA = strtolower($a['product_title'] ?? $a['title'] ?? '');
                    $valB = strtolower($b['product_title'] ?? $b['title'] ?? '');
                } else {
                    $valA = $a['ad_start_date'] ?? $a['created_at'] ?? '';
                    $valB = $b['ad_start_date'] ?? $b['created_at'] ?? '';
                }

                if ($valA == $valB) return 0;
                if ($sortOrder === 'ASC') {
                    return ($valA < $valB) ? -1 : 1;
                }
                return ($valA > $valB) ? -1 : 1;
            });

            $totalMatching = count($filtered);
            $paginated = array_slice($filtered, $offset, $limit);

            return [
                'status'         => 'success',
                'total'          => $totalMatching,
                'snapshot_info' => $snapshotRow ? [
                    'id'          => $snapshotRow['id'],
                    'api_version' => $snapshotRow['api_version'],
                    'date'        => $snapshotRow['created_at']
                ] : 'Database Products Fallback',
                'filters_applied' => [
                    'origin'          => 'Winning',
                    'country'         => $countryFilter,
                    'min_ads'         => $minAds,
                    'max_ads'         => $maxAds,
                    'min_price'       => $minPrice,
                    'max_price'       => $maxPrice,
                    'search_query'    => $searchQuery,
                    'active_ads_only' => $activeAdsOnly,
                    'sort_by'         => $sortBy,
                    'sort_order'      => $sortOrder
                ],
                'total_matching' => $totalMatching,
                'returned_count' => count($paginated),
                'products'       => $paginated
            ];
        }

        if ($name === 'get_products') {
            $ids       = $args['ids'] ?? [];
            $nameQuery = $args['name'] ?? null;
            $origin    = $args['origin'] ?? null;
            $country   = $args['country'] ?? null;
            $limit     = intval($args['limit'] ?? 20);

            $builder = $db->table('products');
            if (!empty($ids) && is_array($ids)) {
                $builder->whereIn('id', $ids);
            }
            if (!empty($nameQuery)) {
                $builder->groupStart()
                        ->like('title', $nameQuery)
                        ->orLike('ad_title', $nameQuery)
                        ->groupEnd();
            }
            if (!empty($origin)) {
                $builder->where('origin', $origin);
            }
            if (!empty($country)) {
                $builder->like('country', strtoupper($country));
            }

            $products = $builder->orderBy('id', 'DESC')->limit($limit)->get()->getResultArray();
            return [
                'status'         => 'success',
                'total'          => count($products),
                'returned_count' => count($products),
                'products'       => $products
            ];
        }

        if ($name === 'get_product_full_json') {
            $productId  = $args['product_id'] ?? null;
            $titleQuery = $args['title'] ?? null;

            if (!$productId && !$titleQuery) {
                return ['error' => 'Must provide either product_id or title'];
            }

            $productRow = null;
            if ($productId) {
                $productRow = $db->table('products')->where('id', $productId)->get()->getRowArray();
            } else {
                $productRow = $db->table('products')->like('title', $titleQuery)->orderBy('id', 'DESC')->get()->getRowArray();
            }

            // Raw tRPC JSON search from snapshot
            $rawTrpcObject = null;
            $recentSnapshots = $db->table('data_snapshots')->orderBy('id', 'DESC')->limit(10)->get()->getResultArray();
            foreach ($recentSnapshots as $snap) {
                $entries = $this->parseSnapshotEntries($snap['raw_json'] ?? '');
                foreach ($entries as $entry) {
                    if ($productId && (($entry['id'] ?? null) == $productId || ($entry['product_id'] ?? null) == $productId)) {
                        $rawTrpcObject = $entry;
                        break 2;
                    }
                    if ($titleQuery) {
                        $pTitle = strtolower($entry['product_title'] ?? $entry['title'] ?? '');
                        if (strpos($pTitle, strtolower($titleQuery)) !== false) {
                            $rawTrpcObject = $entry;
                            break 2;
                        }
                    }
                }
            }

            return [
                'product_id'              => $productRow['id'] ?? null,
                'product_database_record' => $productRow,
                'raw_trpc_json'           => $rawTrpcObject
            ];
        }

        if ($name === 'fetch_new_data') {
            $dateStr        = $args['date'] ?? null;
            $countryFilter  = isset($args['country']) ? strtoupper(trim($args['country'])) : 'ALL';
            $classification = isset($args['classification']) ? trim($args['classification']) : ($args['origin'] ?? 'all');
            $searchQuery    = isset($args['search_query']) ? strtolower(trim($args['search_query'])) : null;
            $sortBy         = $args['sort_by'] ?? 'date';
            $sortOrder      = strtoupper($args['sort_order'] ?? 'DESC');
            $limit          = intval($args['limit'] ?? 50);
            $offset         = intval($args['offset'] ?? 0);

            // Handle classification default: "all" / "ككل"
            $isAllClassifications = empty($classification) || strtolower($classification) === 'all' || $classification === 'ككل';

            $builder = $db->table('products');

            // Exclude tenant-saved copies to query master product catalog
            $builder->groupStart()
                        ->where('is_saved', false)
                        ->orWhere('is_saved IS NULL')
                    ->groupEnd();

            // Filter classification/origin if not "all" / "ككل"
            if (!$isAllClassifications) {
                $builder->where('origin', $classification);
            }

            // Filter country
            if ($countryFilter !== 'ALL' && !empty($countryFilter) && $countryFilter !== 'ككل') {
                $builder->like('country', $countryFilter);
            }

            // Filter date
            if (!empty($dateStr) && $dateStr !== 'all' && $dateStr !== 'ككل') {
                $today = date('Y-m-d');
                if ($dateStr === 'today') {
                    $escapedToday = $db->escapeLikeString($today);
                    $builder->groupStart()
                            ->where('ad_start_date', $today)
                            ->orWhere("api_version LIKE '%{$escapedToday}%'")
                            ->groupEnd();
                } elseif ($dateStr === 'yesterday') {
                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                    $escapedYesterday = $db->escapeLikeString($yesterday);
                    $builder->groupStart()
                            ->where('ad_start_date', $yesterday)
                            ->orWhere("api_version LIKE '%{$escapedYesterday}%'")
                            ->groupEnd();
                } elseif ($dateStr === '7days') {
                    $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
                    $builder->groupStart()
                            ->where('ad_start_date >=', $sevenDaysAgo)
                            ->groupEnd();
                } elseif ($dateStr === '30days') {
                    $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
                    $builder->groupStart()
                            ->where('ad_start_date >=', $thirtyDaysAgo)
                            ->groupEnd();
                } else {
                    $escapedDate = $db->escapeLikeString($dateStr);
                    $builder->groupStart()
                            ->where('ad_start_date', $dateStr)
                            ->orWhere("api_version LIKE '%{$escapedDate}%'")
                            ->groupEnd();
                }
            }

            // Search query
            if (!empty($searchQuery)) {
                $builder->groupStart()
                        ->like('title', $searchQuery)
                        ->orLike('ad_title', $searchQuery)
                        ->orLike('ad_body', $searchQuery)
                        ->groupEnd();
            }

            // Sorting
            if ($sortBy === 'ads_count') {
                $builder->orderBy('ads_count', $sortOrder);
            } elseif ($sortBy === 'title') {
                $builder->orderBy('title', $sortOrder);
            } elseif ($sortBy === 'price') {
                $builder->orderBy('CAST(price_1 AS NUMERIC)', $sortOrder);
            } else {
                $builder->orderBy('ad_start_date', $sortOrder)
                        ->orderBy('id', $sortOrder);
            }

            // Step 1: Execute local products table query
            $totalMatching = $builder->countAllResults(false);
            $products = $builder->limit($limit, $offset)->get()->getResultArray();

            $forceLiveSync = !empty($args['force_live_sync']) || !empty($args['live_fetch']);

            // Step 2: Check data_snapshots table FIRST if products table returned 0 items and not force_live_sync
            if ($totalMatching === 0 && !$forceLiveSync && !empty($dateStr) && $dateStr !== 'all' && $dateStr !== 'ككل') {
                $escapedDate = $db->escapeLikeString($dateStr);
                $snapBuilder = $db->table('data_snapshots')
                                  ->groupStart()
                                      ->like('api_version', $dateStr)
                                      ->orWhere("CAST(created_at AS TEXT) LIKE '%{$escapedDate}%'")
                                  ->groupEnd();
                if (!$isAllClassifications) {
                    $snapBuilder->where('origin', $classification);
                }
                $snapshotRows = $snapBuilder->orderBy('id', 'DESC')->limit(10)->get()->getResultArray();
                $allEntries = [];
                foreach ($snapshotRows as $snapRow) {
                    $entries = $this->parseSnapshotEntries($snapRow['raw_json'] ?? '');
                    foreach ($entries as $e) {
                        if ($countryFilter !== 'ALL' && !empty($countryFilter) && $countryFilter !== 'ككل') {
                            if (!empty($e['country'])) {
                                $cList = array_map('trim', explode(';', strtoupper($e['country'])));
                                if (!in_array($countryFilter, $cList, true)) continue;
                            }
                        }
                        if ($searchQuery) {
                            $title = strtolower($e['product_title'] ?? $e['title'] ?? '');
                            if (strpos($title, $searchQuery) === false) continue;
                        }
                        $allEntries[] = $e;
                    }
                }
                if (!empty($allEntries)) {
                    $totalMatching = count($allEntries);
                    $paginated = array_slice($allEntries, $offset, $limit);
                    return [
                        'status'          => 'success',
                        'total'           => $totalMatching,
                        'source'          => 'data_snapshots',
                        'filters_applied' => [
                            'date'           => $dateStr,
                            'country'        => $countryFilter,
                            'classification' => $isAllClassifications ? 'all (ككل)' : $classification,
                            'search_query'   => $searchQuery,
                        ],
                        'returned_count' => count($paginated),
                        'products'       => $paginated
                    ];
                }
            }

            // Step 3: If BOTH products table AND data_snapshots have 0 matching items (OR forceLiveSync is true), fetch live data via SyncService
            if ($totalMatching === 0 || $forceLiveSync) {
                try {
                    $syncService = new \App\Services\SyncService();
                    $countryParam = ($countryFilter !== 'ALL' && $countryFilter !== 'ككل' && !empty($countryFilter))
                        ? $countryFilter
                        : "DZ;TN;MA;LY;EG;SA;QA;EA;OM;BH;KW;GB;IE;FR;BE;LU;CH;DE;AT;ES;IT;NL;PT;NG;CI;SN;KE";

                    // Dynamically format target date for API version (e.g. 1.10-12026-08-13)
                    $targetDateStr = date('Y-m-d');
                    if (!empty($dateStr) && $dateStr !== 'all' && $dateStr !== 'ككل') {
                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
                            $targetDateStr = $dateStr;
                        } elseif ($dateStr === 'yesterday') {
                            $targetDateStr = date('Y-m-d', strtotime('-1 day'));
                        }
                    }
                    $winningVersion = "1.10-1" . $targetDateStr;

                    $normClass = strtolower($classification);
                    if ($normClass === 'winning' || $isAllClassifications) {
                        $inputObj = [
                            "0" => [
                                "json" => [
                                    "category" => "Popular;Electronics;Home & Garden;Health & Beauty;Apparel & Accessories;Tools;Baby & Toddler",
                                    "country"  => $countryParam,
                                    "v"        => $winningVersion
                                ]
                            ]
                        ];
                        $trpcUrl = 'https://www.overviewdata.io/api/trpc/data.winingProducts?batch=1&input=' . urlencode(json_encode($inputObj, JSON_FORCE_OBJECT));
                        $syncService->fetchAndSaveTrpcUrl($trpcUrl);

                        if ($isAllClassifications) {
                            $inputInsights = [
                                "0" => [
                                    "json" => [
                                        "title"          => "",
                                        "category"       => "Popular;Electronics;Home & Garden;Health & Beauty;Apparel & Accessories;Tools;Baby & Toddler",
                                        "priceFrom"      => -1,
                                        "priceTo"        => -1,
                                        "weeks"          => 12,
                                        "country"        => $countryParam,
                                        "transformation" => "none",
                                        "v"              => "1.3--5"
                                    ]
                                ]
                            ];
                            $trpcInsightsUrl = 'https://www.overviewdata.io/api/trpc/data.insights?batch=1&input=' . urlencode(json_encode($inputInsights, JSON_FORCE_OBJECT));
                            $syncService->fetchAndSaveTrpcUrl($trpcInsightsUrl);
                        }
                    } elseif ($normClass === 'china') {
                        $inputObj = [
                            "0" => [
                                "json" => null,
                                "meta" => ["values" => ["undefined"]]
                            ]
                        ];
                        $trpcUrl = 'https://www.overviewdata.io/api/trpc/data.chinaProducts?batch=1&input=' . urlencode(json_encode($inputObj, JSON_FORCE_OBJECT));
                        $syncService->fetchAndSaveTrpcUrl($trpcUrl);
                    } elseif ($normClass === 'japan') {
                        $inputObj = [
                            "0" => [
                                "json" => null,
                                "meta" => ["values" => ["undefined"]]
                            ]
                        ];
                        $trpcUrl = 'https://www.overviewdata.io/api/trpc/data.japanProducts?batch=1&input=' . urlencode(json_encode($inputObj, JSON_FORCE_OBJECT));
                        $syncService->fetchAndSaveTrpcUrl($trpcUrl);
                    } else {
                        // "local" or specific category
                        $inputObj = [
                            "0" => [
                                "json" => [
                                    "title"          => "",
                                    "category"       => "Popular;Electronics;Home & Garden;Health & Beauty;Apparel & Accessories;Tools;Baby & Toddler",
                                    "priceFrom"      => -1,
                                    "priceTo"        => -1,
                                    "weeks"          => 12,
                                    "country"        => $countryParam,
                                    "transformation" => "none",
                                    "v"              => "1.3--5"
                                ]
                            ]
                        ];
                        $trpcUrl = 'https://www.overviewdata.io/api/trpc/data.insights?batch=1&input=' . urlencode(json_encode($inputObj, JSON_FORCE_OBJECT));
                        $syncService->fetchAndSaveTrpcUrl($trpcUrl);
                    }

                    // Re-run DB Query to fetch freshly inserted/updated products
                    $builder = $db->table('products');
                    $builder->groupStart()
                                ->where('is_saved', false)
                                ->orWhere('is_saved IS NULL')
                            ->groupEnd();

                    if (!$isAllClassifications) {
                        $builder->where('origin', $classification);
                    }

                    if ($countryFilter !== 'ALL' && !empty($countryFilter) && $countryFilter !== 'ككل') {
                        $builder->like('country', $countryFilter);
                    }

                    if (!empty($dateStr) && $dateStr !== 'all' && $dateStr !== 'ككل') {
                        $today = date('Y-m-d');
                        if ($dateStr === 'today') {
                            $escapedToday = $db->escapeLikeString($today);
                            $builder->groupStart()
                                    ->where('ad_start_date', $today)
                                    ->orWhere("api_version LIKE '%{$escapedToday}%'")
                                    ->groupEnd();
                        } elseif ($dateStr === 'yesterday') {
                            $yesterday = date('Y-m-d', strtotime('-1 day'));
                            $escapedYesterday = $db->escapeLikeString($yesterday);
                            $builder->groupStart()
                                    ->where('ad_start_date', $yesterday)
                                    ->orWhere("api_version LIKE '%{$escapedYesterday}%'")
                                    ->groupEnd();
                        } elseif ($dateStr === '7days') {
                            $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
                            $builder->groupStart()
                                    ->where('ad_start_date >=', $sevenDaysAgo)
                                    ->groupEnd();
                        } elseif ($dateStr === '30days') {
                            $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
                            $builder->groupStart()
                                    ->where('ad_start_date >=', $thirtyDaysAgo)
                                    ->groupEnd();
                        } else {
                            $escapedDate = $db->escapeLikeString($dateStr);
                            $builder->groupStart()
                                    ->where('ad_start_date', $dateStr)
                                    ->orWhere("api_version LIKE '%{$escapedDate}%'")
                                    ->groupEnd();
                        }
                    }

                    if (!empty($searchQuery)) {
                        $builder->groupStart()
                                ->like('title', $searchQuery)
                                ->orLike('ad_title', $searchQuery)
                                ->orLike('ad_body', $searchQuery)
                                ->groupEnd();
                    }

                    if ($sortBy === 'ads_count') {
                        $builder->orderBy('ads_count', $sortOrder);
                    } elseif ($sortBy === 'title') {
                        $builder->orderBy('title', $sortOrder);
                    } elseif ($sortBy === 'price') {
                        $builder->orderBy('CAST(price_1 AS NUMERIC)', $sortOrder);
                    } else {
                        $builder->orderBy('ad_start_date', $sortOrder)
                                ->orderBy('id', $sortOrder);
                    }

                    $totalMatching = $builder->countAllResults(false);
                    $products = $builder->limit($limit, $offset)->get()->getResultArray();

                    // Fallback: If strict single-day ad_start_date filter yielded 0 results after live sync, return latest fetched products for this classification and country
                    if ($totalMatching === 0) {
                        $fallbackBuilder = $db->table('products');
                        $fallbackBuilder->groupStart()
                                            ->where('is_saved', false)
                                            ->orWhere('is_saved IS NULL')
                                        ->groupEnd();

                        if (!$isAllClassifications) {
                            $fallbackBuilder->where('origin', $classification);
                        }

                        if ($countryFilter !== 'ALL' && !empty($countryFilter) && $countryFilter !== 'ككل') {
                            $fallbackBuilder->like('country', $countryFilter);
                        }

                        if (!empty($searchQuery)) {
                            $fallbackBuilder->groupStart()
                                            ->like('title', $searchQuery)
                                            ->orLike('ad_title', $searchQuery)
                                            ->orLike('ad_body', $searchQuery)
                                            ->groupEnd();
                        }

                        if ($sortBy === 'ads_count') {
                            $fallbackBuilder->orderBy('ads_count', $sortOrder);
                        } elseif ($sortBy === 'title') {
                            $fallbackBuilder->orderBy('title', $sortOrder);
                        } elseif ($sortBy === 'price') {
                            $fallbackBuilder->orderBy('CAST(price_1 AS NUMERIC)', $sortOrder);
                        } else {
                            $fallbackBuilder->orderBy('ad_start_date', $sortOrder)
                                            ->orderBy('id', $sortOrder);
                        }

                        $totalMatching = $fallbackBuilder->countAllResults(false);
                        $products = $fallbackBuilder->limit($limit, $offset)->get()->getResultArray();
                    }
                } catch (\Throwable $e) {
                    log_message('error', 'MCP fetch_new_data SyncService error: ' . $e->getMessage());
                }
            }

            return [
                'status'          => 'success',
                'total'           => $totalMatching,
                'filters_applied' => [
                    'date'           => $dateStr ?? 'all',
                    'country'        => $countryFilter,
                    'classification' => $isAllClassifications ? 'all (ككل)' : $classification,
                    'search_query'   => $searchQuery,
                    'sort_by'        => $sortBy,
                    'sort_order'     => $sortOrder,
                ],
                'returned_count' => count($products),
                'products'       => $products
            ];
        }

        // ===== FACEBOOK ADS LIBRARY MCP TOOLS =====
        $fbService = new FacebookAdsService();

        if ($name === 'facebook_search_ads' || $name === 'search_facebook_ads' || $name === 'fb_search_ads') {
            $brandName = (string) ($args['brand_name'] ?? '');
            $country   = (string) ($args['country'] ?? 'US');
            $adType    = (string) ($args['ad_type'] ?? 'ALL');
            $dateRange = (int) ($args['date_range'] ?? 30);
            $limit     = (int) ($args['limit'] ?? 50);
            $token     = $args['token'] ?? null;
            return $fbService->searchAds($brandName, $country, $adType, $dateRange, $limit, $token);
        }

        if ($name === 'facebook_discover_competitors' || $name === 'discover_competitor_brands' || $name === 'fb_discover_competitors') {
            $industryKeywords = (string) ($args['industry_keywords'] ?? '');
            $region           = (string) ($args['region'] ?? 'US');
            $minAds           = (int) ($args['min_ads'] ?? 5);
            $limit            = (int) ($args['limit'] ?? 50);
            $token            = $args['token'] ?? null;
            return $fbService->discoverCompetitors($industryKeywords, $region, $minAds, $limit, $token);
        }

        if ($name === 'facebook_analyze_creative' || $name === 'analyze_ad_creative_elements' || $name === 'fb_analyze_creative') {
            $snapshotUrl = (string) ($args['ad_snapshot_url'] ?? '');
            $extractText = (bool) ($args['extract_text'] ?? true);
            $analyzeImg  = (bool) ($args['analyze_images'] ?? true);
            $detectCta   = (bool) ($args['detect_cta'] ?? true);
            return $fbService->analyzeCreativeElements($snapshotUrl, $extractText, $analyzeImg, $detectCta);
        }

        if ($name === 'facebook_analyze_performance' || $name === 'analyze_ad_performance_metrics' || $name === 'fb_analyze_performance') {
            $brandName  = (string) ($args['brand_name'] ?? '');
            $timePeriod = (int) ($args['time_period'] ?? 30);
            $token      = $args['token'] ?? null;
            return $fbService->analyzePerformanceMetrics($brandName, $timePeriod, null, $token);
        }

        if ($name === 'facebook_competitive_analysis' || $name === 'competitive_ad_analysis' || $name === 'fb_competitive_analysis') {
            $brandsList = (array) ($args['brands_list'] ?? []);
            $depth      = (string) ($args['analysis_depth'] ?? 'standard');
            $token      = $args['token'] ?? null;
            return $fbService->competitiveAnalysis($brandsList, null, $depth, $token);
        }

        if ($name === 'facebook_intelligence_report' || $name === 'generate_facebook_intelligence_report' || $name === 'fb_intelligence_report') {
            $brandName           = (string) ($args['brand_name'] ?? '');
            $includeCompetitors  = (bool) ($args['include_competitors'] ?? true);
            $reportDepth         = (string) ($args['report_depth'] ?? 'comprehensive');
            $token               = $args['token'] ?? null;
            return $fbService->generateIntelligenceReport($brandName, $includeCompetitors, $reportDepth, $token);
        }

        if ($name === 'facebook_export_ads' || $name === 'export_facebook_ads_data' || $name === 'fb_export_ads') {
            $brandName        = (string) ($args['brand_name'] ?? '');
            $exportFormat     = (string) ($args['export_format'] ?? 'json');
            $includeCreatives = (bool) ($args['include_creatives'] ?? false);
            $limit            = (int) ($args['limit'] ?? 100);
            $token            = $args['token'] ?? null;
            return $fbService->exportAdsData($brandName, $exportFormat, $includeCreatives, $limit, $token);
        }

        throw new \Exception("Unknown tool: {$name}");
    }
}

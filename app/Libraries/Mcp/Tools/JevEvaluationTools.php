<?php

namespace App\Libraries\Mcp\Tools;

use App\Libraries\Mcp\ToolInterface;
use App\Services\CloudflareJevService;
use App\Libraries\SaaS\QuotaManager;
use App\Models\ProductModel;

class JevEvaluationTools implements ToolInterface
{
    protected string $action;

    public function __construct(string $action = 'jev_evaluate_product')
    {
        $this->action = $action;
    }

    public function getName(): string
    {
        return $this->action;
    }

    public function getDescription(): string
    {
        switch ($this->action) {
            case 'jev_evaluate_product':
                return 'Evaluate e-commerce & COD product viability, problem-solving score, delivery risk, impulse buy potential, and target niche using Cloudflare typesafe/jev structured evaluation.';
            case 'jev_evaluate_ad':
                return 'Analyze Facebook ad copy, marketing angle, hook effectiveness, and ad policy compliance risk using Cloudflare typesafe/jev structured evaluation.';
            case 'jev_evaluate_custom':
                return 'Perform fast arbitrary structured evaluation of any text or JSON state against typed questions (noul, choice, score) using Cloudflare typesafe/jev with calibrated probabilities.';
            default:
                return 'Cloudflare typesafe/jev evaluation tool.';
        }
    }

    public function getInputSchema(): array
    {
        switch ($this->action) {
            case 'jev_evaluate_product':
                return [
                    'type' => 'object',
                    'properties' => [
                        'product_id'       => ['type' => 'number', 'description' => 'Optional database ID of an existing product to evaluate'],
                        'title'            => ['type' => 'string', 'description' => 'Product title/name if not passing product_id'],
                        'ad_title'         => ['type' => 'string', 'description' => 'Ad headline or marketing subtitle'],
                        'ad_body'          => ['type' => 'string', 'description' => 'Product description or ad body text'],
                        'price'            => ['type' => 'number', 'description' => 'Selling price in local currency'],
                        'country'          => ['type' => 'string', 'description' => 'Target market country code (e.g. MA, SA, DZ, AE)'],
                        'custom_questions' => ['type' => 'object', 'description' => 'Optional custom typed questions overriding default COD evaluation']
                    ],
                    'additionalProperties' => false
                ];

            case 'jev_evaluate_ad':
                return [
                    'type' => 'object',
                    'properties' => [
                        'ad_body'          => ['type' => 'string', 'description' => 'The ad copy or primary text to evaluate'],
                        'headline'         => ['type' => 'string', 'description' => 'Optional ad headline or hook'],
                        'brand_name'       => ['type' => 'string', 'description' => 'Optional brand name'],
                        'country'          => ['type' => 'string', 'description' => 'Target country (e.g. MA, SA, US)'],
                        'format'           => ['type' => 'string', 'enum' => ['video', 'image', 'carousel'], 'description' => 'Creative media format'],
                        'custom_questions' => ['type' => 'object', 'description' => 'Optional custom typed questions overriding default ad evaluation']
                    ],
                    'required' => ['ad_body'],
                    'additionalProperties' => false
                ];

            case 'jev_evaluate_custom':
                return [
                    'type' => 'object',
                    'properties' => [
                        'state'     => [
                            'description' => 'The context or object to evaluate. Can be a string or structured JSON object/dictionary.',
                            'type'        => ['string', 'object']
                        ],
                        'questions' => [
                            'type'        => 'object',
                            'description' => 'Dictionary of typed questions. Each question must specify type: "noul", "choice", or "score" with optional criteria.'
                        ]
                    ],
                    'required' => ['state', 'questions'],
                    'additionalProperties' => false
                ];

            default:
                return ['type' => 'object'];
        }
    }

    public function execute(array $args, ?array $context = null): array
    {
        $tenantId = $context['user']['tenant_id'] ?? 1;

        // Quota check
        $quotaManager = new QuotaManager();
        if (!$quotaManager->canExecute($tenantId, 'ai_analyses')) {
            return [
                'success' => false,
                'error'   => 'Quota exceeded for AI analyses today. Please upgrade your tier or wait until tomorrow.'
            ];
        }

        $service = new CloudflareJevService();
        if (!$service->isConfigured()) {
            return [
                'success' => false,
                'error'   => 'Cloudflare API credentials (account ID or API token) are not configured in the system.'
            ];
        }

        $result = [];

        switch ($this->action) {
            case 'jev_evaluate_product':
                $productData = [];
                $productId = intval($args['product_id'] ?? 0);

                if ($productId > 0) {
                    $productModel = new ProductModel();
                    $product = $productModel->find($productId);
                    if ($product) {
                        $productData = $product;
                    }
                }

                // Merge explicit arguments over database row
                if (!empty($args['title'])) {
                    $productData['title'] = $args['title'];
                }
                if (!empty($args['ad_title'])) {
                    $productData['ad_title'] = $args['ad_title'];
                }
                if (!empty($args['ad_body'])) {
                    $productData['ad_body'] = $args['ad_body'];
                }
                if (isset($args['price'])) {
                    $productData['price'] = $args['price'];
                }
                if (!empty($args['country'])) {
                    $productData['country'] = $args['country'];
                }

                if (empty($productData['title']) && empty($productData['ad_body'])) {
                    return [
                        'success' => false,
                        'error'   => 'Product title or ad body is required to perform evaluation.'
                    ];
                }

                $customQuestions = !empty($args['custom_questions']) && is_array($args['custom_questions'])
                    ? $args['custom_questions']
                    : null;

                $result = $service->evaluateProduct($productData, $customQuestions);
                if (!empty($result['success'])) {
                    $result['product_title'] = $productData['title'] ?? ($productData['ad_title'] ?? 'Product');
                    if ($productId > 0) {
                        $result['product_id'] = $productId;
                    }
                }
                break;

            case 'jev_evaluate_ad':
                $adData = [
                    'ad_body'    => $args['ad_body'] ?? '',
                    'headline'   => $args['headline'] ?? '',
                    'brand_name' => $args['brand_name'] ?? '',
                    'country'    => $args['country'] ?? '',
                    'format'     => $args['format'] ?? 'video'
                ];

                $customQuestions = !empty($args['custom_questions']) && is_array($args['custom_questions'])
                    ? $args['custom_questions']
                    : null;

                $result = $service->evaluateAdCreative($adData, $customQuestions);
                break;

            case 'jev_evaluate_custom':
                $state = $args['state'] ?? '';
                $questions = $args['questions'] ?? [];

                if (empty($state)) {
                    return ['success' => false, 'error' => 'State cannot be empty.'];
                }
                if (empty($questions) || !is_array($questions)) {
                    return ['success' => false, 'error' => 'Questions must be a non-empty object.'];
                }

                $result = $service->evaluate($state, $questions);
                break;

            default:
                return ['success' => false, 'error' => 'Unknown action: ' . $this->action];
        }

        // Record usage upon successful execution
        if (!empty($result['success'])) {
            $quotaManager->recordUsage($tenantId, 'ai_analyses');
        }

        return $result;
    }
}

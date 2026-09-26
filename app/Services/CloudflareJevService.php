<?php

namespace App\Services;

use Config\Cloudflare;
use App\Models\ProductModel;
use App\Libraries\Ai\PromptBuilder;

/**
 * Service for interacting with Cloudflare Workers AI 'typesafe/jev' structured evaluation model.
 */
class CloudflareJevService
{
    protected Cloudflare $config;
    protected $client;
    protected ProductModel $productModel;

    public function __construct(?Cloudflare $config = null)
    {
        $this->config = $config ?? new \Config\Cloudflare();
        $this->client = \Config\Services::curlrequest([
            'timeout' => 30,
            'http_errors' => false
        ]);
        $this->productModel = new ProductModel();
    }

    /**
     * Check if Cloudflare account ID and API token are configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->config->accountId) && !empty($this->config->apiToken);
    }

    /**
     * Low-level evaluation against typesafe/jev on Cloudflare Workers AI
     *
     * @param string|array $state Text string or structured JSON object/array
     * @param array $questions Associative array of typed questions (noul, choice, score)
     * @return array Standardized evaluation result or error array
     */
    public function evaluate(string|array $state, array $questions): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error'   => 'Cloudflare account ID or API Token is not configured in .env or Cloudflare config.'
            ];
        }

        if (empty($state)) {
            return [
                'success' => false,
                'error'   => 'Evaluation state cannot be empty.'
            ];
        }

        if (empty($questions)) {
            return [
                'success' => false,
                'error'   => 'At least one typed question must be provided.'
            ];
        }

        $endpoint = "https://api.cloudflare.com/client/v4/accounts/{$this->config->accountId}/ai/run";

        $payload = [
            'model' => 'typesafe/jev',
            'input' => [
                'state'     => $state,
                'questions' => $questions
            ]
        ];

        try {
            $response = $this->client->request('POST', $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->config->apiToken,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json'
                ],
                'json' => $payload
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody();
            $decoded = json_decode($body, true);

            if ($statusCode !== 200) {
                $errMsg = $decoded['errors'][0]['message'] ?? ('HTTP Error ' . $statusCode);
                return [
                    'success' => false,
                    'error'   => 'Cloudflare AI error: ' . $errMsg,
                    'raw'     => $decoded
                ];
            }

            // Cloudflare Workers AI formats responses inside result.result or directly in result
            $resData = $decoded['result'] ?? [];
            if (isset($resData['result']['answers'])) {
                $evalData = $resData['result'];
            } elseif (isset($resData['answers'])) {
                $evalData = $resData;
            } else {
                $evalData = $resData;
            }

            return [
                'success' => true,
                'model'   => $evalData['model'] ?? 'typesafe/jev',
                'answers' => $evalData['answers'] ?? [],
                'usage'   => $evalData['usage'] ?? ($resData['usage'] ?? [])
            ];
        } catch (\Throwable $e) {
            log_message('error', 'CloudflareJevService Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error'   => 'Cloudflare AI request failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Evaluate a product for COD eCommerce viability
     *
     * @param array $product Product row or structured product attributes
     * @param array|null $customQuestions Optional override for questions
     * @return array
     */
    public function evaluateProduct(array $product, ?array $customQuestions = null): array
    {
        $state = [
            'title'          => trim($product['title'] ?? ''),
            'ad_title'       => trim($product['ad_title'] ?? ''),
            'ad_body'        => mb_substr(strip_tags($product['ad_body'] ?? ''), 0, 800),
            'price'          => $product['price'] ?? null,
            'country'        => $product['country'] ?? null,
            'ads_count'      => intval($product['ads_count'] ?? 1),
            'origin'         => $product['origin'] ?? 'Local',
            'video_creative' => !empty($product['video_path']) || !empty($product['ad_video_urls']) || !empty($product['has_video_creative'])
        ];

        $questions = $customQuestions ?? PromptBuilder::getJevProductEvaluationQuestions();

        return $this->evaluate($state, $questions);
    }

    /**
     * Evaluate an ad copy / creative for marketing angle, compliance, and hook strength
     *
     * @param array $ad Ad creative data (headline, body, format, brand, country)
     * @param array|null $customQuestions Optional override for questions
     * @return array
     */
    public function evaluateAdCreative(array $ad, ?array $customQuestions = null): array
    {
        $state = [
            'headline'     => trim($ad['headline'] ?? $ad['ad_title'] ?? ''),
            'ad_body'      => trim($ad['ad_body'] ?? $ad['text'] ?? ''),
            'brand'        => trim($ad['brand_name'] ?? $ad['brand'] ?? ''),
            'country'      => trim($ad['country'] ?? ''),
            'media_format' => trim($ad['format'] ?? ($ad['has_video'] ?? false ? 'video' : 'image'))
        ];

        $questions = $customQuestions ?? PromptBuilder::getJevAdEvaluationQuestions();

        return $this->evaluate($state, $questions);
    }
}

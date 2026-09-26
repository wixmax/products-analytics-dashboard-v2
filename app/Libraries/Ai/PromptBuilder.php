<?php

namespace App\Libraries\Ai;

class PromptBuilder
{
    /**
     * Build Prompt for Screening Phase or Compact Retry
     */
    public static function buildScreeningPrompt(array $products, array $params, string $mode = 'screening'): string
    {
        $adBudget = floatval($params['ad_budget_total'] ?? $params['ad_budget'] ?? 5000);
        $season   = trim($params['season'] ?? 'auto');
        $cShipping = floatval($params['c_shipping_default'] ?? 35);

        $productsSummary = [];
        foreach ($products as $idx => $prod) {
            if (!is_array($prod)) continue;

            $index = isset($prod['index']) ? intval($prod['index']) : ($idx + 1);
            $id    = $prod['id'] ?? $prod['product_id'] ?? null;
            $rawTitle = trim(strval($prod['title'] ?? $prod['name'] ?? ("منتج #" . $index)));
            $title = mb_substr($rawTitle, 0, 180);

            $rawPrice = floatval($prod['price'] ?? $prod['selling_price'] ?? 0);
            $sellingPrice = $rawPrice > 0 ? $rawPrice : null;

            $hasVideo = !empty($prod['video_path']) || !empty($prod['video']) || !empty($prod['video_url']) || !empty($prod['has_video_creative']);
            $adsCount = intval($prod['ads_count'] ?? $prod['active_ads'] ?? $prod['estimated_active_ads'] ?? 10);
            $country  = $prod['country'] ?? $prod['country_code'] ?? null;

            $url = '';
            if (isset($prod['url']) && is_string($prod['url'])) {
                $rawUrl = trim($prod['url']);
                if (str_starts_with($rawUrl, 'http://') || str_starts_with($rawUrl, 'https://')) {
                    $url = $rawUrl;
                }
            } elseif (isset($prod['link']) && is_string($prod['link'])) {
                $rawUrl = trim($prod['link']);
                if (str_starts_with($rawUrl, 'http://') || str_starts_with($rawUrl, 'https://')) {
                    $url = $rawUrl;
                }
            }

            $imgUrl = '';
            if (isset($prod['image_url']) && is_string($prod['image_url'])) {
                $rawImg = trim($prod['image_url']);
                if (str_starts_with($rawImg, 'http://') || str_starts_with($rawImg, 'https://')) {
                    $imgUrl = $rawImg;
                }
            } elseif (isset($prod['image']) && is_string($prod['image'])) {
                $rawImg = trim($prod['image']);
                if (str_starts_with($rawImg, 'http://') || str_starts_with($rawImg, 'https://')) {
                    $imgUrl = $rawImg;
                }
            }

            $item = [
                'index'                => $index,
                'title'                => $title,
                'selling_price'        => $sellingPrice,
                'has_video_creative'   => (bool)$hasVideo,
                'estimated_active_ads' => $adsCount
            ];
            if ($id !== null && $id !== '') {
                $item['id'] = $id;
            }
            if ($country !== null && $country !== '') {
                $item['country'] = $country;
            }
            if (!empty($url)) {
                $item['url'] = $url;
            }
            if (!empty($imgUrl)) {
                $item['image_url'] = $imgUrl;
            }

            $productsSummary[] = $item;
        }

        $jsonProducts = json_encode($productsSummary, JSON_UNESCAPED_UNICODE);

        if ($mode === 'compact_retry') {
            $prompt = "قيم بسرعة هذه الدفعة من المنتجات للسوق المغربي COD:\n";
            $prompt .= "الميزانية: {$adBudget} DH | الموسم: {$season}\n";
            $prompt .= "المنتجات:\n{$jsonProducts}\n\n";
            $prompt .= "أرجع JSON فقط بصيغة {\"evaluations\":[...]} بدون markdown. لكل منتج ضع:\n";
            $prompt .= '{"index":1,"id":"إن وجد","title":"عنوان","url":"","image_url":"","score":80,"verdict":"winning|promising|risk","verdict_label":"🟢 منتج رابح ممتاز","is_budget_fit":false,"reason":"سبب مختصر جداً أقل من 100 حرف","recommendation":"توصية قصيرة جداً"}' . "\n";
            $prompt .= "قواعد: لا تضف narrative_analysis أو breakdown أو financials. لا تكرر المنتجات.";
            return $prompt;
        }

        $prompt = "قم بإجراء تقييم أولي خفيف (Screening) لقائمة المنتجات التالية المرشحة للتسويق بنظام الدفع عند الاستلام (COD) في المغرب:\n";
        $prompt .= "- الميزانية الإعلانية الإجمالية: {$adBudget} DH\n";
        $prompt .= "- الموسم المستهدف: {$season}\n";
        $prompt .= "- تكلفة التوصيل الافتراضية: {$cShipping} DH\n\n";
        $prompt .= "قائمة المنتجات (Batch Payload):\n" . $jsonProducts . "\n\n";
        $prompt .= "تعليمات حاسمة وقواعد الهيكل المطلوبة:\n";
        $prompt .= "1. أرجع النتيجة حصراً بصيغة JSON نظيفة وصالحة بالهيكل المحدد أدناه، بدون أي تغليف Markdown (لا تستخدم ```json) وبدون أي نص خارجي.\n";
        $prompt .= "2. يجب أن تحتوي استجابتك على مصفوفة \"evaluations\" فقط، مع عنصر evaluation واحد لكل منتج في الدفعة.\n";
        $prompt .= "3. حافظ على القيم المدخلة لـ (index) و (id) كما وردت لربط النتائج بالمنتج الأصلي.\n";
        $prompt .= "4. يمنع منعاً باتاً إضافة حقول narrative_analysis أو breakdown أو financials أو target_price أو net_profit أو estimated_cpa في هذه المرحلة.\n";
        $prompt .= "5. لا تخترع أسعاراً أو روابط أو صوراً أو تكاليف غير موجودة في البيانات المدخلة.\n";
        $prompt .= "6. النص في \"reason\" يجب ألا يتجاوز 120 حرفاً. النص في \"recommendation\" يجب ألا يتجاوز 140 حرفاً.\n\n";
        $prompt .= "الهيكل المطلوب لكل منتج داخل مصفوفة evaluations:\n";
        $prompt .= '{"evaluations": [
  {
    "index": 1,
    "id": "إن وجد في البيانات",
    "title": "عنوان المنتج",
    "url": "الرابط إن وجد وإلا string فارغة",
    "image_url": "رابط الصورة إن وجد وإلا string فارغة",
    "score": 75,
    "verdict": "winning|promising|risk",
    "verdict_label": "🟢 منتج رابح ممتاز|🟡 واعد|🔴 ضعيف",
    "is_budget_fit": true,
    "reason": "سبب التقييم في أقل من 120 حرفاً",
    "recommendation": "توصية سريعة في أقل من 140 حرفاً"
  }
]}';

        return $prompt;
    }

    /**
     * Standard typed evaluation questions for COD product viability using typesafe/jev
     */
    public static function getJevProductEvaluationQuestions(): array
    {
        return [
            'is_problem_solving' => [
                'type'         => 'noul',
                'instructions' => 'Does this product solve a clear, practical problem or alleviate specific pain points for consumers?'
            ],
            'niche' => [
                'type'         => 'choice',
                'instructions' => 'Which primary market category does this product belong to?',
                'criteria'     => [
                    'beauty_personal_care' => 'Skincare, haircare, cosmetics, grooming and personal hygiene',
                    'kitchen_home'         => 'Cooking utensils, cleaning, home organization, decor',
                    'health_wellness'      => 'Fitness, posture correctors, pain relief, orthopedic',
                    'gadgets_electronics'  => 'Phone accessories, smart tools, car gadgets, tech devices',
                    'automotive'           => 'Car accessories, repair kits, detailing, emergency tools',
                    'general_merchandise'  => 'Clothing, toys, jewelry, novelty items'
                ]
            ],
            'impulse_buy' => [
                'type'         => 'score',
                'instructions' => 'Rate the impulse buy potential and visual wow-factor for social media advertising (COD market)',
                'criteria'     => [
                    'Low: utility item or needs high consideration and research',
                    'Moderate: interesting with some visual appeal or novelty',
                    'High: strong instant wow-factor, high emotional impulse to order immediately'
                ]
            ],
            'shipping_delivery_risk' => [
                'type'         => 'score',
                'instructions' => 'Rate the logistical and delivery risk in a cash-on-delivery environment (breakage, fragile materials, heavy weight, or high return rates)',
                'criteria'     => [
                    'Low risk: compact, lightweight, durable, easy to pack and deliver safely',
                    'Moderate risk: delicate components or sizing dependency',
                    'High risk: fragile glass/ceramic, heavy/bulky, high probability of return or shipping damage'
                ]
            ],
            'winning_potential' => [
                'type'         => 'score',
                'instructions' => 'Overall rating of whether this product can be scaled profitably as a winning COD product',
                'criteria'     => [
                    'Low: saturated or difficult to market via COD',
                    'Moderate: viable for testing with targeted angles',
                    'High: excellent winning potential for aggressive scaling'
                ]
            ]
        ];
    }

    /**
     * Standard typed evaluation questions for ad creative and copy using typesafe/jev
     */
    public static function getJevAdEvaluationQuestions(): array
    {
        return [
            'marketing_angle' => [
                'type'         => 'choice',
                'instructions' => 'What is the primary psychological or marketing angle employed in this ad copy?',
                'criteria'     => [
                    'problem_solution' => 'Emphasizes pain points and demonstrates the solution',
                    'social_proof'     => 'Customer reviews, testimonials, influencer endorsement',
                    'urgency_scarcity' => 'Limited time discounts, ending sales, limited stock warnings',
                    'emotional_status' => 'Status, luxury, transformation, self-improvement',
                    'novelty_curiosity' => 'Unusual demonstration, curiosity gap, innovative feature'
                ]
            ],
            'is_hook_effective' => [
                'type'         => 'noul',
                'instructions' => 'Does the headline/copy provide a compelling scroll-stopping hook within the opening statement?'
            ],
            'policy_risk' => [
                'type'         => 'score',
                'instructions' => 'Rate the Facebook Advertising Policy compliance risk (deceptive claims, before/after imagery, medical cures, unrealistic promises)',
                'criteria'     => [
                    'Safe: complies with standard advertising policies',
                    'Borderline: aggressive claims or sensitive topic that might trigger review',
                    'High risk: severe policy violations, guaranteed medical cures, or deceptive promises'
                ]
            ],
            'call_to_action_strength' => [
                'type'         => 'score',
                'instructions' => 'How clear, urgent, and frictionless is the call-to-action (CTA)?',
                'criteria'     => [
                    'Weak: vague or missing CTA',
                    'Moderate: standard shop now or learn more direction',
                    'Strong: compelling COD offer, cash on delivery assurance, clear next step'
                ]
            ]
        ];
    }
}


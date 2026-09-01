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
}

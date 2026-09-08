<?php

namespace App\Libraries;

/**
 * ChoufliyaService
 * 
 * Service for interfacing with Choufliya Wholesale Marketplace (choufliya.ma).
 * Provides text-based search, reverse image search, supplier extraction,
 * profit margin estimation, and export capabilities.
 */
class ChoufliyaService
{
    protected string $searchEndpoint = 'https://choufliya.ma/api/v1/search';
    protected string $cdnBase = 'https://cdn.choufliya.ma';

    /**
     * Search wholesale products by text query
     */
    public function searchByText(string $query, int $limit = 20, array $options = []): array
    {
        $postFields = [
            'text'            => $query,
            'top_k'           => '200',
            'per_page'        => (string) $limit,
            'show_duplicates' => 'false',
        ];

        if (!empty($options['category'])) {
            $postFields['category'] = $options['category'];
        }

        $apiResponse = $this->sendMultipartRequest($postFields);
        return $this->formatResults($apiResponse, $options);
    }

    /**
     * Search wholesale products using an image (URL or local path)
     */
    public function searchByImage(string $imageSource, int $limit = 20, array $options = []): array
    {
        $tempFilePath = null;
        $fileToUpload = null;
        $mimeType = 'image/jpeg';
        $fileName = 'query.jpg';

        if (filter_var($imageSource, FILTER_VALIDATE_URL)) {
            $imageContent = $this->downloadImageFast($imageSource);
            if ($imageContent === null) {
                throw new \Exception("تعذر تحميل الصورة من الرابط أو استغرق وقتاً طويلاً.");
            }
            $tempFilePath = tempnam(sys_get_temp_dir(), 'chouf_img_');
            file_put_contents($tempFilePath, $imageContent);
            $fileToUpload = $tempFilePath;

            // Determine mime type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = finfo_file($finfo, $tempFilePath);
            finfo_close($finfo);
            if (!empty($detectedMime)) {
                $mimeType = $detectedMime;
            }
        } elseif (file_exists($imageSource)) {
            $fileToUpload = $imageSource;
            $fileName = basename($imageSource);
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = finfo_file($finfo, $imageSource);
            finfo_close($finfo);
            if (!empty($detectedMime)) {
                $mimeType = $detectedMime;
            }
        } else {
            throw new \Exception("مصدر الصورة غير صالح أو الملف غير موجود.");
        }

        $cfile = new \CURLFile($fileToUpload, $mimeType, $fileName);

        $postFields = [
            'image'           => $cfile,
            'top_k'           => '200',
            'per_page'        => (string) $limit,
            'show_duplicates' => 'false',
        ];

        if (!empty($options['category'])) {
            $postFields['category'] = $options['category'];
        }

        try {
            $apiResponse = $this->sendMultipartRequest($postFields);
            $formatted = $this->formatResults($apiResponse, $options);
        } finally {
            if ($tempFilePath && file_exists($tempFilePath)) {
                @unlink($tempFilePath);
            }
        }

        return $formatted;
    }

    /**
     * Download remote image fast using cURL with strict timeout
     */
    public function downloadImageFast(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ]);
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($code === 200 && !empty($data)) ? $data : null;
    }

    /**
     * Find wholesale suppliers & calculate estimated margins for a retail product
     */
    public function findSupplierAlternatives(string $productTitle, ?float $retailPrice = null, int $limit = 20, ?string $imageUrl = null, bool $preferImage = false): array
    {
        $wholesaleItems = [];

        // Try image search if explicitly preferred
        if ($preferImage && !empty($imageUrl)) {
            try {
                $wholesaleItems = $this->searchByImage($imageUrl, $limit);
            } catch (\Throwable $e) {
                // Fallback to text
            }
        }

        // Fast text search (primary, response in <1s)
        if (empty($wholesaleItems)) {
            $cleanTitle = $this->extractCoreKeywords($productTitle);
            $wholesaleItems = $this->searchByText($cleanTitle ?: $productTitle, $limit);
        }

        // Calculate margins if retail price is provided
        $suppliersSummary = [];
        $minWholesalePrice = null;
        $maxWholesalePrice = null;

        foreach ($wholesaleItems as &$item) {
            $wPrice = $item['numericPrice'] ?? null;
            if ($wPrice !== null && $wPrice > 0) {
                if ($minWholesalePrice === null || $wPrice < $minWholesalePrice) {
                    $minWholesalePrice = $wPrice;
                }
                if ($maxWholesalePrice === null || $wPrice > $maxWholesalePrice) {
                    $maxWholesalePrice = $wPrice;
                }

                if ($retailPrice !== null && $retailPrice > 0) {
                    $margin = $retailPrice - $wPrice;
                    $marginPercent = round(($margin / $retailPrice) * 100, 1);
                    $item['margin_mad'] = round($margin, 2);
                    $item['margin_percentage'] = $marginPercent;
                    $item['is_profitable'] = $margin > 0;
                }
            }

            if (!empty($item['supplier'])) {
                $suppliersSummary[$item['supplier']] = ($suppliersSummary[$item['supplier']] ?? 0) + 1;
            }
        }

        return [
            'searched_product'   => $productTitle,
            'retail_price'       => $retailPrice,
            'suppliers_count'    => count($suppliersSummary),
            'items_count'        => count($wholesaleItems),
            'min_wholesale_price'=> $minWholesalePrice,
            'max_wholesale_price'=> $maxWholesalePrice,
            'estimated_potential_margin' => ($retailPrice && $minWholesalePrice) ? round($retailPrice - $minWholesalePrice, 2) : null,
            'products'           => $wholesaleItems,
        ];
    }

    /**
     * Send multipart/form-data POST request using native curl
     */
    protected function sendMultipartRequest(array $postFields): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->searchEndpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Referer: https://choufliya.ma/',
            'Origin: https://choufliya.ma',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \Exception("فشل الاتصال بخادم شوف ليا: {$curlError}");
        }

        if ($httpCode !== 200) {
            throw new \Exception("خطأ في استجابة خادم شوف ليا: كود {$httpCode}");
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new \Exception("استجابة غير صالحة من خادم شوف ليا.");
        }

        return $decoded;
    }

    /**
     * Parse raw API results into structured format
     */
    protected function formatResults(array $apiData, array $options = []): array
    {
        $rawItems = [];
        if (isset($apiData['results']) && is_array($apiData['results'])) {
            $rawItems = $apiData['results'];
        } elseif (isset($apiData['items']) && is_array($apiData['items'])) {
            $rawItems = $apiData['items'];
        } elseif (array_is_list($apiData)) {
            $rawItems = $apiData;
        }

        $items = [];
        foreach ($rawItems as $item) {
            $m = $item['metadata'] ?? [];
            $title = $m['title_ar'] ?? $m['title_fr'] ?? $m['title_en'] ?? $m['subcategory'] ?? $item['display_name'] ?? $item['group_name'] ?? 'منتج جملة';
            $supplier = $item['display_name'] ?? $item['group_name'] ?? $m['group_name'] ?? $m['supplier'] ?? 'مورد شوف ليا';
            
            $priceData = $this->extractPrice($item);

            // WhatsApp link & phone
            $whatsappLink = $item['whatsapp_url'] ?? $m['whatsapp_url'] ?? '';
            $phone = $item['whatsapp'] ?? $m['whatsapp'] ?? $item['phone'] ?? null;
            if (empty($whatsappLink) && !empty($phone)) {
                $cleanPhone = preg_replace('/[^\d]/', '', (string)$phone);
                if (!empty($cleanPhone)) {
                    $whatsappLink = "https://wa.me/{$cleanPhone}";
                }
            }

            // Telegram
            $telegramWeb = $item['telegram_link'] ?? $m['telegram_link'] ?? '';
            if (empty($telegramWeb) && !empty($m['group_username']) && !empty($m['message_id'])) {
                $telegramWeb = "https://t.me/{$m['group_username']}/{$m['message_id']}";
            }
            $telegramApp = $this->convertToTelegramAppProtocol($telegramWeb);

            // Images with Local Proxy to bypass BunnyCDN 403 Hotlink Protection
            $rawThumb = '';
            $rawFull = '';
            if (!empty($item['image_url'])) {
                $full = str_starts_with($item['image_url'], 'http') ? $item['image_url'] : $this->cdnBase . (str_starts_with($item['image_url'], '/') ? '' : '/') . $item['image_url'];
                $rawThumb = str_ends_with($full, '/thumb') ? $full : "{$full}/thumb";
                $rawFull = preg_replace('/\/thumb$/', '', $full);
            } else {
                $id = $item['id'] ?? $item['image_id'] ?? ($m['image_id'] ?? null);
                if ($id) {
                    $rawThumb = "{$this->cdnBase}/api/v1/img/{$id}/thumb";
                    $rawFull = "{$this->cdnBase}/api/v1/img/{$id}";
                }
            }

            // Use our proxy route so images load 100% on localhost / any domain
            $proxyThumb = !empty($rawThumb) ? '/api/choufliya/proxy-image?url=' . urlencode($rawThumb) : '';
            $proxyFull  = !empty($rawFull)  ? '/api/choufliya/proxy-image?url=' . urlencode($rawFull)  : '';

            $scoreVal = floatval($item['similarity_score'] ?? $item['score'] ?? $item['similarity'] ?? 0);
            $matchPercentage = min(100, max(0, (int)round($scoreVal * 100)));

            $rawDate = $m['date'] ?? $item['date'] ?? $m['indexed_at'] ?? $item['created_at'] ?? null;
            $dateInfo = $this->formatPublishDate($rawDate);

            $formattedItem = [
                'title'               => $title,
                'supplier'            => $supplier,
                'price'               => $priceData['hasExactPrice'] ? "{$priceData['price']} {$priceData['currency']}" : 'عند التواصل (حسب الكمية)',
                'numericPrice'        => $priceData['price'],
                'currency'            => $priceData['currency'],
                'hasExactPrice'       => $priceData['hasExactPrice'],
                'phone'               => $phone,
                'whatsappLink'        => $whatsappLink ?: null,
                'telegramLink'        => $telegramWeb ?: null,
                'telegramAppProtocol' => $telegramApp ?: null,
                'matchPercentage'     => $matchPercentage > 0 ? $matchPercentage : null,
                'publishDateRelative' => $dateInfo['relativeText'] ?? null,
                'publishDateExact'    => $dateInfo['exactDate'] ?? null,
                'rawDate'             => $rawDate,
                'category'            => $m['category'] ?? $item['category'] ?? null,
                'description'         => $m['desc_ar'] ?? $m['desc_fr'] ?? $m['desc_en'] ?? $m['text'] ?? null,
                'imageUrl'            => $proxyFull ?: $rawFull,
                'thumbUrl'            => $proxyThumb ?: $rawThumb,
                'originalImageUrl'    => $rawFull,
                'originalThumbUrl'    => $rawThumb
            ];

            // Filters
            if (!empty($options['whatsappOnly']) && empty($formattedItem['whatsappLink'])) {
                continue;
            }
            if (!empty($options['hasPriceOnly']) && !$formattedItem['hasExactPrice']) {
                continue;
            }

            $items[] = $formattedItem;
        }

        // Sorting
        $sortBy = $options['sortBy'] ?? 'match';
        if ($sortBy === 'price_asc') {
            usort($items, function($a, $b) {
                if (!$a['hasExactPrice']) return 1;
                if (!$b['hasExactPrice']) return -1;
                return $a['numericPrice'] <=> $b['numericPrice'];
            });
        } elseif ($sortBy === 'price_desc') {
            usort($items, function($a, $b) {
                if (!$a['hasExactPrice']) return 1;
                if (!$b['hasExactPrice']) return -1;
                return $b['numericPrice'] <=> $a['numericPrice'];
            });
        } elseif ($sortBy === 'date') {
            usort($items, function($a, $b) {
                return strtotime($b['rawDate'] ?? '1970-01-01') <=> strtotime($a['rawDate'] ?? '1970-01-01');
            });
        }

        return $items;
    }

    /**
     * Helper to extract price from metadata or description
     */
    protected function extractPrice(array $item): array
    {
        $m = $item['metadata'] ?? [];
        $price = null;
        $currency = 'MAD';
        $hasExactPrice = false;

        if (isset($item['price_info']['price']) && $item['price_info']['price'] !== null) {
            $price = floatval($item['price_info']['price']);
            $currency = $item['price_info']['currency'] ?? 'MAD';
            $hasExactPrice = true;
        } elseif (isset($item['price']) && $item['price'] !== null) {
            $price = floatval($item['price']);
            $hasExactPrice = true;
        } elseif (isset($m['price']) && $m['price'] !== null) {
            $price = floatval($m['price']);
            $hasExactPrice = true;
        } else {
            $description = ($m['desc_ar'] ?? '') . ' ' . ($m['desc_fr'] ?? '') . ' ' . ($m['text'] ?? '');
            if (preg_match('/(?:ثمن|سعر|prix|price)\s*[:=]?\s*(\d+(?:[.,]\d+)?)\s*(?:dh|mad|درهم)?/iu', $description, $matches)) {
                $price = floatval(str_replace(',', '.', $matches[1]));
                $hasExactPrice = true;
            } elseif (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:dh|mad|درهم)/iu', $description, $matches)) {
                $price = floatval(str_replace(',', '.', $matches[1]));
                $hasExactPrice = true;
            }
        }

        return [
            'price'         => $price,
            'currency'      => $currency,
            'hasExactPrice' => $hasExactPrice,
        ];
    }

    /**
     * Relative Arabic date formatting
     */
    protected function formatPublishDate(?string $rawDate): ?array
    {
        if (empty($rawDate)) return null;
        $ts = strtotime($rawDate);
        if ($ts === false) return null;

        $diff = time() - $ts;
        if ($diff < 0) {
            return ['relativeText' => 'الآن', 'exactDate' => date('Y-m-d', $ts)];
        }

        $days = (int) floor($diff / 86400);
        if ($days <= 0) {
            $hours = (int) floor($diff / 3600);
            if ($hours <= 0) {
                $min = (int) floor($diff / 60);
                $rel = $min <= 2 ? 'الآن' : "منذ {$min} دقيقة";
            } elseif ($hours == 1) {
                $rel = 'منذ ساعة';
            } elseif ($hours == 2) {
                $rel = 'منذ ساعتين';
            } elseif ($hours <= 10) {
                $rel = "منذ {$hours} ساعات";
            } else {
                $rel = "منذ {$hours} ساعة";
            }
        } elseif ($days == 1) {
            $rel = 'أمس';
        } elseif ($days == 2) {
            $rel = 'منذ يومين';
        } elseif ($days <= 10) {
            $rel = "منذ {$days} أيام";
        } elseif ($days < 30) {
            $weeks = (int) floor($days / 7);
            $rel = $weeks <= 1 ? 'منذ أسبوع' : ($weeks == 2 ? 'منذ أسبوعين' : "منذ {$weeks} أسابيع");
        } else {
            $months = (int) floor($days / 30);
            $rel = $months <= 1 ? 'منذ شهر' : ($months == 2 ? 'منذ شهرين' : "منذ {$months} أشهر");
        }

        return [
            'relativeText' => $rel,
            'exactDate'    => date('Y-m-d', $ts)
        ];
    }

    /**
     * Telegram URI converter
     */
    protected function convertToTelegramAppProtocol(string $webUrl): string
    {
        if (empty($webUrl)) return '';
        if (preg_match('/t\.me\/c\/(\d+)\/(\d+)/i', $webUrl, $m)) {
            return "tg://privatepost?channel={$m[1]}&post={$m[2]}";
        }
        if (preg_match('/t\.me\/([a-zA-Z0-9_]+)\/(\d+)/i', $webUrl, $m)) {
            return "tg://resolve?domain={$m[1]}&post={$m[2]}";
        }
        return $webUrl;
    }

    /**
     * Clean product title to extract clean search keywords
     */
    protected function extractCoreKeywords(string $title): string
    {
        // Remove emojis and common marketing slogans
        $clean = preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $title);
        $clean = preg_replace('/\(.*?\)|\[.*?\]/', '', $clean);
        $clean = str_ireplace(['ودع الفوضى', 'توصيل مجاني', 'عرض خاص', 'أصلي', 'جديد', 'حصري', 'تخفيض'], '', $clean);
        return trim($clean);
    }
}

<?php

namespace App\Libraries;

use Config\Database;

/**
 * FacebookAdsService
 * 
 * Full PHP migration of Facebook Ads Library MCP with advanced intelligence,
 * competitor discovery, creative analysis, performance estimation, and reporting.
 */
class FacebookAdsService
{
    protected string $baseUrl = 'https://graph.facebook.com/v19.0/ads_archive';
    protected ?string $accessToken = null;

    public function __construct(?string $accessToken = null)
    {
        if (!empty($accessToken)) {
            $this->accessToken = trim($accessToken);
        } else {
            $this->accessToken = $this->resolveAccessToken();
        }
    }

    /**
     * Resolve Facebook Access Token from settings DB, .env, or system environment.
     */
    public function resolveAccessToken(): ?string
    {
        if (class_exists(Database::class)) {
            try {
                $db = Database::connect();
                $row = $db->table('settings')->where('key', 'facebook_access_token')->get()->getRowArray();
                if (!empty($row['value'])) {
                    return trim($row['value']);
                }
            } catch (\Throwable $e) {
                // DB fallback
            }
        }

        if (function_exists('env')) {
            $envToken = env('FACEBOOK_ACCESS_TOKEN');
            if (!empty($envToken)) {
                return trim($envToken);
            }
        }

        $getEnv = getenv('FACEBOOK_ACCESS_TOKEN');
        if (!empty($getEnv)) {
            return trim($getEnv);
        }

        if (!empty($_ENV['FACEBOOK_ACCESS_TOKEN'])) {
            return trim($_ENV['FACEBOOK_ACCESS_TOKEN']);
        }

        return null;
    }

    /**
     * Set access token manually.
     */
    public function setAccessToken(string $token): self
    {
        $this->accessToken = trim($token);
        return $this;
    }

    /**
     * Get current access token.
     */
    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    /**
     * Make an authenticated HTTP GET request to Facebook Graph API with robust error handling.
     */
    public function makeRequest(array $params, ?string $token = null): array
    {
        $activeToken = $token ?: $this->accessToken;

        if (empty($activeToken)) {
            return [
                'success' => false,
                'error'   => 'Facebook access token is missing. Please configure it in Settings or MCP Admin panel.'
            ];
        }

        $params['access_token'] = $activeToken;

        // Facebook Graph API expects ad_reached_countries as a JSON array string if passed as array
        if (isset($params['ad_reached_countries']) && is_array($params['ad_reached_countries'])) {
            $params['ad_reached_countries'] = json_encode(array_values($params['ad_reached_countries']));
        }

        $url = $this->baseUrl . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Accept-Encoding: gzip, deflate'
            ],
            CURLOPT_ENCODING       => ''
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'success' => false,
                'error'   => 'cURL Connection Error: ' . $curlError
            ];
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400 || (isset($decoded['error']) && !empty($decoded['error']))) {
            $errorMessage = is_array($decoded['error']) 
                ? ($decoded['error']['message'] ?? json_encode($decoded['error'])) 
                : ($decoded['error'] ?? "HTTP Error {$httpCode}");
            
            return [
                'success'   => false,
                'http_code' => $httpCode,
                'error'     => $errorMessage,
                'raw'       => $decoded
            ];
        }

        if ($decoded === null) {
            return [
                'success' => false,
                'error'   => 'Invalid JSON response from Facebook Graph API.',
                'raw_response' => substr($response, 0, 500)
            ];
        }

        $decoded['success'] = true;
        return $decoded;
    }

    /**
     * Extract Ad ID from snapshot URL.
     */
    public function extractAdIdFromUrl(string $snapshotUrl): ?string
    {
        if (preg_match('/id=(\d+)/i', $snapshotUrl, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Analyze ad creative using web scraping and HTML parsing.
     */
    public function analyzeAdCreative(string $snapshotUrl): array
    {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $snapshotUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                CURLOPT_HTTPHEADER     => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9,ar;q=0.8,fr;q=0.7',
                ]
            ]);

            $html = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError || empty($html)) {
                return [
                    'success' => false,
                    'error'   => 'Failed to fetch snapshot URL: ' . ($curlError ?: 'Empty response')
                ];
            }

            // Extract plain text and key parts using DOMDocument
            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
            libxml_clear_errors();

            // Remove script and style elements
            $scripts = $dom->getElementsByTagName('script');
            while ($scripts->length > 0) {
                $scripts->item(0)->parentNode->removeChild($scripts->item(0));
            }
            $styles = $dom->getElementsByTagName('style');
            while ($styles->length > 0) {
                $styles->item(0)->parentNode->removeChild($styles->item(0));
            }

            $extractedText = trim(preg_replace('/\s+/', ' ', $dom->textContent ?? ''));

            return [
                'success'        => true,
                'extracted_text' => $extractedText,
                'html_length'    => strlen($html)
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage()
            ];
        }
    }

    /**
     * Tool 1: Search Facebook Ads Library with advanced filters
     */
    public function searchAds(
        string $brandName,
        string $country = 'US',
        string $adType = 'ALL',
        int $dateRange = 30,
        int $limit = 50,
        ?string $token = null
    ): array {
        $params = [
            'search_terms'         => $brandName,
            'ad_reached_countries' => [$country],
            'fields'               => 'id,ad_creation_time,ad_creative_bodies,ad_creative_link_captions,ad_creative_link_descriptions,ad_creative_link_titles,ad_snapshot_url,currency,demographic_distribution,delivery_by_region,impressions,page_id,page_name,publisher_platforms,spend',
            'limit'                => min(max($limit, 1), 100),
            'ad_active_status'     => 'ALL'
        ];

        if ($adType !== 'ALL') {
            $params['ad_type'] = $adType;
        }

        $result = $this->makeRequest($params, $token);

        if (!isset($result['success']) || !$result['success']) {
            return $result;
        }

        $ads = $result['data'] ?? [];

        return [
            'success'       => true,
            'brand'         => $brandName,
            'country'       => $country,
            'total_ads'     => count($ads),
            'ads'           => $ads,
            'search_params' => $params
        ];
    }

    /**
     * Tool 2: Discover competitor brands in an industry / niche
     */
    public function discoverCompetitors(
        string $industryKeywords,
        string $region = 'US',
        int $minAds = 5,
        int $limit = 100,
        ?string $token = null
    ): array {
        $params = [
            'search_terms'         => $industryKeywords,
            'ad_reached_countries' => [$region],
            'fields'               => 'page_name,page_id,ad_creation_time',
            'limit'                => min(max($limit * 3, 10), 100),
            'ad_active_status'     => 'ACTIVE'
        ];

        $result = $this->makeRequest($params, $token);

        if (!isset($result['success']) || !$result['success']) {
            return $result;
        }

        $brandCounts = [];
        foreach ($result['data'] ?? [] as $ad) {
            $pageName = trim($ad['page_name'] ?? '');
            if (!empty($pageName)) {
                $brandCounts[$pageName] = ($brandCounts[$pageName] ?? 0) + 1;
            }
        }

        $qualifiedBrands = [];
        foreach ($brandCounts as $brand => $count) {
            if ($count >= $minAds) {
                $qualifiedBrands[$brand] = $count;
            }
        }

        arsort($qualifiedBrands);

        $discoveredBrands = [];
        $i = 0;
        foreach ($qualifiedBrands as $brand => $count) {
            if ($i >= $limit) break;
            $discoveredBrands[] = [
                'brand_name' => $brand,
                'ad_count'   => $count
            ];
            $i++;
        }

        return [
            'success'                => true,
            'industry'               => $industryKeywords,
            'region'                 => $region,
            'min_ads_threshold'      => $minAds,
            'total_qualified_brands' => count($qualifiedBrands),
            'discovered_brands'      => $discoveredBrands
        ];
    }

    /**
     * Tool 3: Deep analysis of ad creative elements (Text, CTAs, Sentiment, Urgency)
     */
    public function analyzeCreativeElements(
        string $adSnapshotUrl,
        bool $extractText = true,
        bool $analyzeImages = true,
        bool $detectCta = true
    ): array {
        $creativeAnalysis = $this->analyzeAdCreative($adSnapshotUrl);

        if (!($creativeAnalysis['success'] ?? false)) {
            return $creativeAnalysis;
        }

        $textContent = $creativeAnalysis['extracted_text'] ?? '';
        $adId = $this->extractAdIdFromUrl($adSnapshotUrl);

        $analysis = [];

        if ($extractText) {
            $words = preg_split('/\s+/u', trim($textContent), -1, PREG_SPLIT_NO_EMPTY);
            
            // Sentiment & power keywords (multilingual: EN, AR, FR)
            $sentimentKeywords = [];
            if (preg_match_all('/\b(?:amazing|best|free|save|new|limited|exclusive|now|مجاني|عرض|تخفيض|حصري|أفضل|جديد|gratuit|meilleur|nouveau|offre|remise)\b/iu', $textContent, $matches)) {
                $sentimentKeywords = array_values(array_unique($matches[0]));
            }

            $analysis['text_analysis'] = [
                'word_count'         => count($words),
                'character_count'    => mb_strlen($textContent),
                'sentiment_keywords' => $sentimentKeywords,
                'full_text'          => mb_substr($textContent, 0, 3000)
            ];
        }

        if ($detectCta) {
            $ctaPatterns = [
                '/\b(?:shop now|buy now|learn more|sign up|download|get started|try free|claim offer|order now|book now|click here|swipe up)\b/iu',
                '/\b(?:اشتري الآن|اطلب الآن|تسوق الآن|احجز الآن|المزيد|سجل الآن|اكتشف|اضغط هنا|احصل على العرض)\b/iu',
                '/\b(?:acheter|commander|en savoir plus|profiter|cliquez ici|découvrir)\b/iu'
            ];

            $detectedCtas = [];
            foreach ($ctaPatterns as $pattern) {
                if (preg_match_all($pattern, $textContent, $matches)) {
                    $detectedCtas = array_merge($detectedCtas, $matches[0]);
                }
            }
            $detectedCtas = array_values(array_unique($detectedCtas));

            $urgencyWords = [];
            if (preg_match_all('/\b(?:now|today|limited|hurry|urgent|expires|deadline|اليوم|الآن|فوري|كمية محدودة|ينتهي قريباً|aujourd\'hui|vite|urgent|limité)\b/iu', $textContent, $matches)) {
                $urgencyWords = array_values(array_unique($matches[0]));
            }

            $analysis['cta_analysis'] = [
                'detected_ctas' => $detectedCtas,
                'cta_count'     => count($detectedCtas),
                'urgency_words' => array_values(array_unique($urgencyWords))
            ];
        }

        return [
            'success'  => true,
            'ad_url'   => $adSnapshotUrl,
            'ad_id'    => $adId,
            'analysis' => $analysis
        ];
    }

    /**
     * Tool 4: Analyze ad performance metrics (Impressions, Spend, Platforms, Demographics)
     */
    public function analyzePerformanceMetrics(
        string $brandName,
        int $timePeriod = 30,
        ?array $performanceMetrics = null,
        ?string $token = null
    ): array {
        $params = [
            'search_terms'         => $brandName,
            'ad_reached_countries' => ['US'],
            'fields'               => 'id,ad_creation_time,impressions,spend,reach,demographic_distribution,delivery_by_region,publisher_platforms',
            'limit'                => 100,
            'ad_active_status'     => 'ALL'
        ];

        $result = $this->makeRequest($params, $token);

        if (!isset($result['success']) || !$result['success']) {
            return $result;
        }

        $ads = $result['data'] ?? [];
        $totalImpressions = 0;
        $totalSpend = 0.0;
        $platformDistribution = [];
        $demographicSummary = [];

        foreach ($ads as $ad) {
            // Impressions
            if (isset($ad['impressions']) && is_string($ad['impressions'])) {
                $impStr = str_replace([',', ' ', '≤', '<', '>'], '', $ad['impressions']);
                if (is_numeric($impStr)) {
                    $totalImpressions += (int) $impStr;
                }
            }

            // Spend
            if (isset($ad['spend']) && is_string($ad['spend'])) {
                $spendStr = $ad['spend'];
                if (preg_match_all('/\d+/', str_replace(',', '', $spendStr), $matches)) {
                    $numbers = array_map('intval', $matches[0]);
                    if (!empty($numbers)) {
                        $avgSpend = array_sum($numbers) / count($numbers);
                        $totalSpend += $avgSpend;
                    }
                }
            }

            // Platforms
            if (isset($ad['publisher_platforms']) && is_array($ad['publisher_platforms'])) {
                foreach ($ad['publisher_platforms'] as $platform) {
                    $platformDistribution[$platform] = ($platformDistribution[$platform] ?? 0) + 1;
                }
            }

            // Demographics
            if (isset($ad['demographic_distribution']) && is_array($ad['demographic_distribution'])) {
                foreach ($ad['demographic_distribution'] as $demo) {
                    $ageGender = ($demo['age'] ?? 'unknown') . '_' . ($demo['gender'] ?? 'unknown');
                    $demographicSummary[$ageGender] = ($demographicSummary[$ageGender] ?? 0) + 1;
                }
            }
        }

        $adsCount = count($ads);

        return [
            'success'             => true,
            'brand'               => $brandName,
            'analysis_period'     => "{$timePeriod} days",
            'total_ads_analyzed'  => $adsCount,
            'performance_summary' => [
                'total_impressions'      => $totalImpressions,
                'estimated_total_spend'  => round($totalSpend, 2),
                'platform_distribution'  => $platformDistribution,
                'demographic_summary'    => $demographicSummary,
                'avg_impressions_per_ad' => $adsCount > 0 ? round($totalImpressions / $adsCount, 2) : 0,
                'avg_spend_per_ad'       => $adsCount > 0 ? round($totalSpend / $adsCount, 2) : 0
            ]
        ];
    }

    /**
     * Tool 5: Comprehensive competitive ad analysis across multiple brands
     */
    public function competitiveAnalysis(
        array $brandsList,
        ?array $metricsComparison = null,
        string $analysisDepth = 'standard',
        ?string $token = null
    ): array {
        $comparisonResults = [];

        foreach ($brandsList as $brand) {
            $brand = trim($brand);
            if (empty($brand)) continue;

            $brandData = $this->searchAds($brand, 'US', 'ALL', 30, 50, $token);

            if (!($brandData['success'] ?? false)) {
                continue;
            }

            $ads = $brandData['ads'] ?? [];
            $platforms = [];
            $estimatedSpend = 0.0;
            $creativeThemes = [];
            $activeCount = 0;

            foreach ($ads as $ad) {
                if (!empty($ad['ad_creation_time'])) {
                    $activeCount++;
                }

                if (isset($ad['publisher_platforms']) && is_array($ad['publisher_platforms'])) {
                    foreach ($ad['publisher_platforms'] as $p) {
                        $platforms[$p] = true;
                    }
                }

                if (isset($ad['spend']) && is_string($ad['spend'])) {
                    $spendStr = $ad['spend'];
                    if ($spendStr !== '≤$100' && preg_match_all('/\d+/', str_replace(',', '', $spendStr), $matches)) {
                        $nums = array_map('intval', $matches[0]);
                        if (!empty($nums)) {
                            $estimatedSpend += (array_sum($nums) / count($nums));
                        }
                    }
                }

                if (isset($ad['ad_creative_bodies']) && is_array($ad['ad_creative_bodies'])) {
                    foreach ($ad['ad_creative_bodies'] as $body) {
                        $words = preg_split('/\s+/u', mb_strtolower($body), -1, PREG_SPLIT_NO_EMPTY);
                        foreach ($words as $w) {
                            if (mb_strlen($w) > 4 && preg_match('/^[a-zA-Z\x{0600}-\x{06FF}]+$/u', $w)) {
                                $creativeThemes[] = $w;
                            }
                        }
                    }
                }
            }

            $comparisonResults[$brand] = [
                'total_ads'       => count($ads),
                'active_ads'      => $activeCount,
                'platforms'       => array_keys($platforms),
                'estimated_spend' => round($estimatedSpend, 2),
                'creative_themes' => array_values(array_slice(array_unique($creativeThemes), 0, 10))
            ];
        }

        // Generate competitive insights
        $marketLeader = null;
        $highestSpender = null;
        $maxAds = -1;
        $maxSpend = -1.0;
        $allPlatforms = [];
        $allThemes = [];

        foreach ($comparisonResults as $b => $data) {
            if ($data['total_ads'] > $maxAds) {
                $maxAds = $data['total_ads'];
                $marketLeader = $b;
            }
            if ($data['estimated_spend'] > $maxSpend) {
                $maxSpend = $data['estimated_spend'];
                $highestSpender = $b;
            }
            foreach ($data['platforms'] as $p) {
                $allPlatforms[$p] = ($allPlatforms[$p] ?? 0) + 1;
            }
            foreach ($data['creative_themes'] as $th) {
                $allThemes[$th] = ($allThemes[$th] ?? 0) + 1;
            }
        }

        arsort($allPlatforms);

        $commonThemes = [];
        foreach ($allThemes as $th => $c) {
            if ($c > 1) {
                $commonThemes[] = $th;
            }
        }

        return [
            'success'              => true,
            'brands_analyzed'      => $brandsList,
            'comparison_results'   => $comparisonResults,
            'competitive_insights' => [
                'market_leader'   => $marketLeader,
                'highest_spender' => $highestSpender,
                'platform_trends' => $allPlatforms,
                'common_themes'   => array_slice($commonThemes, 0, 10)
            ],
            'analysis_timestamp'   => date('c')
        ];
    }

    /**
     * Tool 6: Generate comprehensive Facebook ads intelligence report
     */
    public function generateIntelligenceReport(
        string $brandName,
        bool $includeCompetitors = true,
        string $reportDepth = 'comprehensive',
        ?string $token = null
    ): array {
        $report = [
            'success'            => true,
            'brand'              => $brandName,
            'report_timestamp'   => date('c'),
            'analysis_summary'   => [],
            'detailed_findings'  => [],
            'recommendations'    => []
        ];

        try {
            // 1. Basic Ad Search
            $basicAds = $this->searchAds($brandName, 'US', 'ALL', 30, 100, $token);
            $totalAds = $basicAds['total_ads'] ?? 0;
            $report['analysis_summary']['total_ads'] = $totalAds;

            // 2. Performance Analysis
            $performance = $this->analyzePerformanceMetrics($brandName, 30, null, $token);
            $report['detailed_findings']['performance_metrics'] = $performance['performance_summary'] ?? [];

            // 3. Recent Activity Analysis
            $recentAds = [];
            foreach ($basicAds['ads'] ?? [] as $ad) {
                if (!empty($ad['ad_creation_time'])) {
                    $recentAds[] = $ad;
                }
            }
            $report['detailed_findings']['recent_activity'] = [
                'total_recent_ads' => count($recentAds),
                'avg_ads_per_week' => count($recentAds) > 0 ? round(count($recentAds) / 4, 1) : 0
            ];

            // 4. Platform Analysis
            $platforms = [];
            foreach ($basicAds['ads'] ?? [] as $ad) {
                if (isset($ad['publisher_platforms']) && is_array($ad['publisher_platforms'])) {
                    foreach ($ad['publisher_platforms'] as $platform) {
                        $platforms[$platform] = ($platforms[$platform] ?? 0) + 1;
                    }
                }
            }
            $report['detailed_findings']['platform_distribution'] = $platforms;

            // 5. Competitor Analysis
            if ($includeCompetitors) {
                $competitors = $this->discoverCompetitors($brandName, 'US', 3, 5, $token);
                $topCompetitorNames = [];
                foreach ($competitors['discovered_brands'] ?? [] as $disc) {
                    $topCompetitorNames[] = $disc['brand_name'];
                }

                if (!empty($topCompetitorNames)) {
                    $compList = array_unique(array_merge([$brandName], array_slice($topCompetitorNames, 0, 4)));
                    $compAnalysis = $this->competitiveAnalysis($compList, null, 'standard', $token);
                    $report['detailed_findings']['competitive_landscape'] = $compAnalysis['competitive_insights'] ?? [];
                }
            }

            // 6. Generate Actionable Recommendations
            $recommendations = [];
            if ($totalAds < 10) {
                $recommendations[] = 'Consider increasing ad creative volume and testing new hooks for better market share and presence.';
            }
            if (!isset($platforms['instagram']) && isset($platforms['facebook'])) {
                $recommendations[] = 'Expand ad distribution to Instagram Reels and Stories for broader audience reach.';
            }
            if (count($recentAds) < 5) {
                $recommendations[] = 'Increase creative refresh cycle to prevent ad fatigue and maintain optimal CTR.';
            }
            if (empty($recommendations)) {
                $recommendations[] = 'Maintain active creative testing and scale high-performing ad angles.';
            }

            $report['recommendations'] = $recommendations;

            // Executive summary
            $primaryPlatform = 'Unknown';
            if (!empty($platforms)) {
                arsort($platforms);
                $primaryPlatform = array_key_first($platforms);
            }

            $report['analysis_summary']['ad_activity_level'] = (count($recentAds) > 10) ? 'High' : ((count($recentAds) > 4) ? 'Medium' : 'Low');
            $report['analysis_summary']['platform_diversity'] = count($platforms);
            $report['analysis_summary']['primary_platform']   = $primaryPlatform;
            $report['analysis_summary']['competitive_position'] = $includeCompetitors ? 'Competitive benchmark included' : 'Not requested';

        } catch (\Throwable $e) {
            $report['success'] = false;
            $report['error']   = $e->getMessage();
        }

        return $report;
    }

    /**
     * Tool 7: Export Facebook ads data to various formats (json, csv, markdown)
     */
    public function exportAdsData(
        string $brandName,
        string $exportFormat = 'json',
        bool $includeCreatives = false,
        int $limit = 100,
        ?string $token = null
    ): array {
        $adsData = $this->searchAds($brandName, 'US', 'ALL', 30, $limit, $token);

        if (!($adsData['success'] ?? false)) {
            return $adsData;
        }

        $ads = $adsData['ads'] ?? [];
        $exportData = [];

        foreach ($ads as $ad) {
            $adRecord = [
                'ad_id'           => $ad['id'] ?? null,
                'page_name'       => $ad['page_name'] ?? null,
                'creation_time'   => $ad['ad_creation_time'] ?? null,
                'impressions'     => $ad['impressions'] ?? null,
                'spend'           => $ad['spend'] ?? null,
                'currency'        => $ad['currency'] ?? null,
                'creative_bodies' => $ad['ad_creative_bodies'] ?? [],
                'platforms'       => $ad['publisher_platforms'] ?? [],
                'snapshot_url'    => $ad['ad_snapshot_url'] ?? null
            ];

            if ($includeCreatives && !empty($ad['ad_snapshot_url'])) {
                $creativeAnalysis = $this->analyzeCreativeElements($ad['ad_snapshot_url']);
                $adRecord['creative_analysis'] = $creativeAnalysis['analysis'] ?? [];
            }

            $exportData[] = $adRecord;
        }

        $formattedData = null;

        if ($exportFormat === 'json') {
            $formattedData = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } elseif ($exportFormat === 'csv') {
            $lines = ["id,page_name,creation_time,impressions,spend,platforms"];
            foreach ($exportData as $r) {
                $plats = is_array($r['platforms']) ? implode(';', $r['platforms']) : '';
                $cleanPage = str_replace('"', '""', $r['page_name'] ?? '');
                $lines[] = "\"{$r['ad_id']}\",\"{$cleanPage}\",\"{$r['creation_time']}\",\"{$r['impressions']}\",\"{$r['spend']}\",\"{$plats}\"";
            }
            $formattedData = implode("\n", $lines);
        } elseif ($exportFormat === 'markdown') {
            $lines = [
                "# Facebook Ads Export",
                "## Brand: {$brandName}",
                "",
                "| Ad ID | Page Name | Creation Time | Impressions | Spend | Platforms |",
                "|---|---|---|---|---|---|"
            ];
            foreach ($exportData as $r) {
                $plats = is_array($r['platforms']) ? implode(', ', $r['platforms']) : '';
                $lines[] = "| {$r['ad_id']} | {$r['page_name']} | {$r['creation_time']} | {$r['impressions']} | {$r['spend']} | {$plats} |";
            }
            $formattedData = implode("\n", $lines);
        } else {
            $formattedData = $exportData;
        }

        return [
            'success'          => true,
            'brand'            => $brandName,
            'export_format'    => $exportFormat,
            'total_records'    => count($exportData),
            'export_data'      => $formattedData,
            'export_timestamp' => date('c')
        ];
    }
}

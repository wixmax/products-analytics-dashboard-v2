<?php

namespace App\Libraries\Mcp\Tools;

use App\Libraries\Mcp\ToolInterface;
use App\Libraries\FacebookAdsService;

class FacebookAdsTools implements ToolInterface
{
    protected string $action;

    public function __construct(string $action = 'facebook_search_ads')
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
            case 'facebook_search_ads':
                return 'Search Facebook Ads Library with advanced filters (brand_name, country, ad_type, date_range, limit).';
            case 'facebook_discover_competitors':
                return 'Discover active competitor brands advertising in an industry / niche with ad volume rankings.';
            case 'facebook_analyze_creative':
                return 'Deep analysis of ad creative elements (text copy, CTAs, sentiment, and urgency triggers).';
            case 'facebook_analyze_performance':
                return 'Analyze advertising performance metrics for a brand (estimated impressions, spend range, platform distribution, demographics).';
            case 'facebook_competitive_analysis':
                return 'Compare ad strategies across multiple competitor brands (identifying market leaders, spend levels, platform trends, and common creative themes).';
            case 'facebook_intelligence_report':
                return 'Generate complete intelligence report for a brand with competitor benchmarks and actionable marketing recommendations.';
            case 'facebook_export_ads':
                return 'Export Facebook ads data in various formats (json, csv, markdown) with optional creative analysis.';
            default:
                return 'Facebook Ads Library tool.';
        }
    }

    public function getInputSchema(): array
    {
        switch ($this->action) {
            case 'facebook_search_ads':
                return [
                    'type' => 'object',
                    'properties' => [
                        'brand_name' => ['type' => 'string', 'description' => 'Brand or keyword name to search in Facebook Ads Library'],
                        'country'    => ['type' => 'string', 'description' => 'Target country code (e.g. US, MA, SA, GB). Default US'],
                        'ad_type'    => ['type' => 'string', 'enum' => ['ALL', 'POLITICAL_AND_ISSUE_ADS', 'HOUSING_ADS', 'NEWS_ADS', 'UNCATEGORIZED']],
                        'date_range' => ['type' => 'number', 'description' => 'Days to look back (default 30)'],
                        'limit'      => ['type' => 'number', 'description' => 'Maximum number of ads to return (1-100, default 50)'],
                        'token'      => ['type' => 'string', 'description' => 'Optional custom Facebook Graph API token']
                    ],
                    'required' => ['brand_name'],
                    'additionalProperties' => false
                ];

            case 'facebook_discover_competitors':
                return [
                    'type' => 'object',
                    'properties' => [
                        'industry_keywords' => ['type' => 'string', 'description' => 'Industry or niche keywords (e.g. "fitness app", "skincare", "food delivery")'],
                        'region'            => ['type' => 'string', 'description' => 'Target country/region code (default US)'],
                        'min_ads'           => ['type' => 'number', 'description' => 'Minimum ads threshold to qualify brand (default 5)'],
                        'limit'             => ['type' => 'number', 'description' => 'Maximum brands to return (default 50)'],
                        'token'             => ['type' => 'string', 'description' => 'Optional custom Facebook Graph API token']
                    ],
                    'required' => ['industry_keywords'],
                    'additionalProperties' => false
                ];

            case 'facebook_analyze_creative':
                return [
                    'type' => 'object',
                    'properties' => [
                        'ad_snapshot_url' => ['type' => 'string', 'description' => 'Facebook ad snapshot URL'],
                        'extract_text'    => ['type' => 'boolean', 'description' => 'Extract full text copy and sentiment keywords (default true)'],
                        'analyze_images'  => ['type' => 'boolean', 'description' => 'Analyze image elements (default true)'],
                        'detect_cta'      => ['type' => 'boolean', 'description' => 'Detect Call To Action (CTA) buttons and urgency words (default true)']
                    ],
                    'required' => ['ad_snapshot_url'],
                    'additionalProperties' => false
                ];

            case 'facebook_analyze_performance':
                return [
                    'type' => 'object',
                    'properties' => [
                        'brand_name'  => ['type' => 'string', 'description' => 'Brand name to analyze'],
                        'time_period' => ['type' => 'number', 'description' => 'Analysis time window in days (default 30)'],
                        'token'       => ['type' => 'string', 'description' => 'Optional custom Facebook Graph API token']
                    ],
                    'required' => ['brand_name'],
                    'additionalProperties' => false
                ];

            case 'facebook_competitive_analysis':
                return [
                    'type' => 'object',
                    'properties' => [
                        'brands_list'    => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'List of brand names to compare'],
                        'analysis_depth' => ['type' => 'string', 'enum' => ['standard', 'deep']],
                        'token'          => ['type' => 'string', 'description' => 'Optional custom Facebook Graph API token']
                    ],
                    'required' => ['brands_list'],
                    'additionalProperties' => false
                ];

            case 'facebook_intelligence_report':
                return [
                    'type' => 'object',
                    'properties' => [
                        'brand_name'          => ['type' => 'string', 'description' => 'Primary brand name to generate report for'],
                        'include_competitors' => ['type' => 'boolean', 'description' => 'Include automated competitor discovery and benchmarking (default true)'],
                        'report_depth'        => ['type' => 'string', 'enum' => ['basic', 'standard', 'comprehensive']],
                        'token'               => ['type' => 'string', 'description' => 'Optional custom Facebook Graph API token']
                    ],
                    'required' => ['brand_name'],
                    'additionalProperties' => false
                ];

            case 'facebook_export_ads':
                return [
                    'type' => 'object',
                    'properties' => [
                        'brand_name'        => ['type' => 'string', 'description' => 'Brand name to export ads for'],
                        'export_format'     => ['type' => 'string', 'enum' => ['json', 'csv', 'markdown'], 'description' => 'Export format (default json)'],
                        'include_creatives' => ['type' => 'boolean', 'description' => 'Include creative copy analysis (default false)'],
                        'limit'             => ['type' => 'number', 'description' => 'Maximum ads to export (default 100)'],
                        'token'             => ['type' => 'string', 'description' => 'Optional custom Facebook Graph API token']
                    ],
                    'required' => ['brand_name'],
                    'additionalProperties' => false
                ];

            default:
                return ['type' => 'object', 'properties' => []];
        }
    }

    public function execute(array $args, ?array $context = null): array
    {
        $fbService = new FacebookAdsService();

        switch ($this->action) {
            case 'facebook_search_ads':
            case 'search_facebook_ads':
            case 'fb_search_ads':
                $brandName = (string) ($args['brand_name'] ?? '');
                $country   = (string) ($args['country'] ?? 'US');
                $adType    = (string) ($args['ad_type'] ?? 'ALL');
                $dateRange = (int) ($args['date_range'] ?? 30);
                $limit     = (int) ($args['limit'] ?? 50);
                $token     = $args['token'] ?? null;
                return $fbService->searchAds($brandName, $country, $adType, $dateRange, $limit, $token);

            case 'facebook_discover_competitors':
            case 'discover_competitor_brands':
            case 'fb_discover_competitors':
                $industryKeywords = (string) ($args['industry_keywords'] ?? '');
                $region           = (string) ($args['region'] ?? 'US');
                $minAds           = (int) ($args['min_ads'] ?? 5);
                $limit            = (int) ($args['limit'] ?? 50);
                $token            = $args['token'] ?? null;
                return $fbService->discoverCompetitors($industryKeywords, $region, $minAds, $limit, $token);

            case 'facebook_analyze_creative':
            case 'analyze_ad_creative_elements':
            case 'fb_analyze_creative':
                $snapshotUrl = (string) ($args['ad_snapshot_url'] ?? '');
                $extractText = (bool) ($args['extract_text'] ?? true);
                $analyzeImg  = (bool) ($args['analyze_images'] ?? true);
                $detectCta   = (bool) ($args['detect_cta'] ?? true);
                return $fbService->analyzeCreativeElements($snapshotUrl, $extractText, $analyzeImg, $detectCta);

            case 'facebook_analyze_performance':
            case 'analyze_ad_performance_metrics':
            case 'fb_analyze_performance':
                $brandName  = (string) ($args['brand_name'] ?? '');
                $timePeriod = (int) ($args['time_period'] ?? 30);
                $token      = $args['token'] ?? null;
                return $fbService->analyzePerformanceMetrics($brandName, $timePeriod, null, $token);

            case 'facebook_competitive_analysis':
            case 'competitive_ad_analysis':
            case 'fb_competitive_analysis':
                $brandsList = (array) ($args['brands_list'] ?? []);
                $depth      = (string) ($args['analysis_depth'] ?? 'standard');
                $token      = $args['token'] ?? null;
                return $fbService->competitiveAnalysis($brandsList, null, $depth, $token);

            case 'facebook_intelligence_report':
            case 'generate_facebook_intelligence_report':
            case 'fb_intelligence_report':
                $brandName          = (string) ($args['brand_name'] ?? '');
                $includeCompetitors = (bool) ($args['include_competitors'] ?? true);
                $reportDepth        = (string) ($args['report_depth'] ?? 'comprehensive');
                $token              = $args['token'] ?? null;
                return $fbService->generateIntelligenceReport($brandName, $includeCompetitors, $reportDepth, $token);

            case 'facebook_export_ads':
            case 'export_facebook_ads_data':
            case 'fb_export_ads':
                $brandName        = (string) ($args['brand_name'] ?? '');
                $exportFormat     = (string) ($args['export_format'] ?? 'json');
                $includeCreatives = (bool) ($args['include_creatives'] ?? false);
                $limit            = (int) ($args['limit'] ?? 100);
                $token            = $args['token'] ?? null;
                return $fbService->exportAdsData($brandName, $exportFormat, $includeCreatives, $limit, $token);

            default:
                throw new \Exception("Unknown Facebook Ads action: {$this->action}");
        }
    }
}

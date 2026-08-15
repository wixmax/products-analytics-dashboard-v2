<?php

namespace App\Controllers;

use App\Libraries\FacebookAdsService;
use CodeIgniter\RESTful\ResourceController;

class FacebookAdsController extends ResourceController
{
    protected $format = 'json';
    protected FacebookAdsService $fbService;

    public function __construct()
    {
        $this->fbService = new FacebookAdsService();
    }

    /**
     * Helper to get request input payload (JSON or POST)
     */
    private function getPayload(): array
    {
        $json = $this->request->getJSON(true);
        if (is_array($json)) {
            return $json;
        }
        return $this->request->getVar() ?: [];
    }

    /**
     * GET/POST /api/facebook-ads/search
     */
    public function search()
    {
        $payload = $this->getPayload();
        $brandName = trim($payload['brand_name'] ?? $this->request->getGet('brand_name') ?? '');
        $country   = trim($payload['country'] ?? $this->request->getGet('country') ?? 'US');
        $adType    = trim($payload['ad_type'] ?? $this->request->getGet('ad_type') ?? 'ALL');
        $limit     = (int) ($payload['limit'] ?? $this->request->getGet('limit') ?? 50);
        $token     = $payload['token'] ?? $this->request->getGet('token') ?? null;

        if (empty($brandName)) {
            return $this->fail('Brand name (brand_name) is required.', 400);
        }

        $res = $this->fbService->searchAds($brandName, $country, $adType, 30, $limit, $token);
        return $this->respond($res);
    }

    /**
     * GET/POST /api/facebook-ads/discover-competitors
     */
    public function discoverCompetitors()
    {
        $payload = $this->getPayload();
        $industryKeywords = trim($payload['industry_keywords'] ?? $this->request->getGet('industry_keywords') ?? '');
        $region           = trim($payload['region'] ?? $this->request->getGet('region') ?? 'US');
        $minAds           = (int) ($payload['min_ads'] ?? $this->request->getGet('min_ads') ?? 5);
        $limit            = (int) ($payload['limit'] ?? $this->request->getGet('limit') ?? 50);
        $token            = $payload['token'] ?? $this->request->getGet('token') ?? null;

        if (empty($industryKeywords)) {
            return $this->fail('Industry keywords (industry_keywords) are required.', 400);
        }

        $res = $this->fbService->discoverCompetitors($industryKeywords, $region, $minAds, $limit, $token);
        return $this->respond($res);
    }

    /**
     * POST /api/facebook-ads/analyze-creative
     */
    public function analyzeCreative()
    {
        $payload = $this->getPayload();
        $snapshotUrl = trim($payload['ad_snapshot_url'] ?? '');
        $extractText = (bool) ($payload['extract_text'] ?? true);
        $analyzeImg  = (bool) ($payload['analyze_images'] ?? true);
        $detectCta   = (bool) ($payload['detect_cta'] ?? true);

        if (empty($snapshotUrl)) {
            return $this->fail('Ad snapshot URL (ad_snapshot_url) is required.', 400);
        }

        $res = $this->fbService->analyzeCreativeElements($snapshotUrl, $extractText, $analyzeImg, $detectCta);
        return $this->respond($res);
    }

    /**
     * GET/POST /api/facebook-ads/analyze-performance
     */
    public function analyzePerformance()
    {
        $payload = $this->getPayload();
        $brandName  = trim($payload['brand_name'] ?? $this->request->getGet('brand_name') ?? '');
        $timePeriod = (int) ($payload['time_period'] ?? $this->request->getGet('time_period') ?? 30);
        $token      = $payload['token'] ?? $this->request->getGet('token') ?? null;

        if (empty($brandName)) {
            return $this->fail('Brand name (brand_name) is required.', 400);
        }

        $res = $this->fbService->analyzePerformanceMetrics($brandName, $timePeriod, null, $token);
        return $this->respond($res);
    }

    /**
     * POST /api/facebook-ads/competitive-analysis
     */
    public function competitiveAnalysis()
    {
        $payload = $this->getPayload();
        $brandsList = $payload['brands_list'] ?? [];
        if (is_string($brandsList)) {
            $brandsList = array_map('trim', explode(',', $brandsList));
        }
        $depth = trim($payload['analysis_depth'] ?? 'standard');
        $token = $payload['token'] ?? null;

        if (empty($brandsList) || !is_array($brandsList)) {
            return $this->fail('A list of brand names (brands_list) is required.', 400);
        }

        $res = $this->fbService->competitiveAnalysis($brandsList, null, $depth, $token);
        return $this->respond($res);
    }

    /**
     * GET/POST /api/facebook-ads/intelligence-report
     */
    public function intelligenceReport()
    {
        $payload = $this->getPayload();
        $brandName           = trim($payload['brand_name'] ?? $this->request->getGet('brand_name') ?? '');
        $includeCompetitors  = (bool) ($payload['include_competitors'] ?? $this->request->getGet('include_competitors') ?? true);
        $reportDepth         = trim($payload['report_depth'] ?? $this->request->getGet('report_depth') ?? 'comprehensive');
        $token               = $payload['token'] ?? $this->request->getGet('token') ?? null;

        if (empty($brandName)) {
            return $this->fail('Brand name (brand_name) is required.', 400);
        }

        $res = $this->fbService->generateIntelligenceReport($brandName, $includeCompetitors, $reportDepth, $token);
        return $this->respond($res);
    }

    /**
     * POST /api/facebook-ads/export
     */
    public function export()
    {
        $payload = $this->getPayload();
        $brandName        = trim($payload['brand_name'] ?? '');
        $exportFormat     = trim($payload['export_format'] ?? 'json');
        $includeCreatives = (bool) ($payload['include_creatives'] ?? false);
        $limit            = (int) ($payload['limit'] ?? 100);
        $token            = $payload['token'] ?? null;

        if (empty($brandName)) {
            return $this->fail('Brand name (brand_name) is required.', 400);
        }

        $res = $this->fbService->exportAdsData($brandName, $exportFormat, $includeCreatives, $limit, $token);
        return $this->respond($res);
    }
}

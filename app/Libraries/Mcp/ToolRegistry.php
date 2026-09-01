<?php

namespace App\Libraries\Mcp;

use App\Libraries\Mcp\Tools\DynamicSkillTool;
use App\Libraries\Mcp\Tools\SavedProductsTool;
use App\Libraries\Mcp\Tools\SnapshotTools;
use App\Libraries\Mcp\Tools\ProductFilterTool;
use App\Libraries\Mcp\Tools\VectorSearchTool;
use App\Libraries\Mcp\Tools\FacebookAdsTools;

class ToolRegistry
{
    /** @var array<string, ToolInterface> */
    protected array $tools = [];

    public function __construct()
    {
        $this->registerDefaultTools();
    }

    /**
     * Register core built-in tools
     */
    protected function registerDefaultTools(): void
    {
        // Saved Products
        $this->register(new SavedProductsTool('get_saved_products'));
        $this->register(new SavedProductsTool('save_product'));

        // Snapshots
        $this->register(new SnapshotTools('list_snapshots'));
        $this->register(new SnapshotTools('get_snapshot_by_date'));

        // Product Queries & Filters
        $this->register(new ProductFilterTool('filter_winning_products'));
        $this->register(new ProductFilterTool('fetch_new_data'));
        $this->register(new ProductFilterTool('get_products'));
        $this->register(new ProductFilterTool('get_product_full_json'));

        // Vector Search
        $this->register(new VectorSearchTool('semantic_search_products'));
        $this->register(new VectorSearchTool('find_similar_products'));

        // Facebook Ads Suite
        $this->register(new FacebookAdsTools('facebook_search_ads'));
        $this->register(new FacebookAdsTools('facebook_discover_competitors'));
        $this->register(new FacebookAdsTools('facebook_analyze_creative'));
        $this->register(new FacebookAdsTools('facebook_analyze_performance'));
        $this->register(new FacebookAdsTools('facebook_competitive_analysis'));
        $this->register(new FacebookAdsTools('facebook_intelligence_report'));
        $this->register(new FacebookAdsTools('facebook_export_ads'));
    }

    public function register(ToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    /**
     * Retrieve dynamic skills from database settings with fallback
     */
    public function getDynamicSkills(): array
    {
        try {
            $db = \Config\Database::connect();
            $row = $db->table('settings')->where('key', 'mcp_skills_list')->get()->getRowArray();
            if ($row && !empty($row['value'])) {
                $decoded = json_decode($row['value'], true);
                if (is_array($decoded) && !empty($decoded)) {
                    return $decoded;
                }
            }
        } catch (\Throwable $e) {
            // Fallback gracefully if database connection is unavailable
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
                'instructions' => '',
                'enabled'      => true,
            ],
        ];
    }

    public function getSystemPrompt(): string
    {
        try {
            $db = \Config\Database::connect();
            $row = $db->table('settings')->where('key', 'mcp_system_prompt')->get()->getRowArray();
            if ($row && !empty($row['value'])) {
                return $row['value'];
            }
        } catch (\Throwable $e) {
            // Fallback gracefully
        }

        return 'تنبيه مهم: آلية العمل ومراحل التنفيذ
سيتم تنفيذ المهام على 4 مراحل متتالية ومرتبة، ولا يتم الانتقال إلى أي مرحلة قبل إتمام المرحلة السابقة واعتماد نتائجها من المستخدم.';
    }

    /**
     * Get disabled tools list from settings with fallback
     */
    public function getDisabledTools(): array
    {
        $disabledTools = [];
        try {
            $db = \Config\Database::connect();
            $toolSettings = $db->table('settings')->like('key', 'mcp_tool_')->get()->getResultArray();
            foreach ($toolSettings as $settingRow) {
                if ($settingRow['value'] === '0') {
                    $disabledTools[] = str_replace('mcp_tool_', '', $settingRow['key']);
                }
            }
        } catch (\Throwable $e) {
            // Fallback gracefully
        }
        return $disabledTools;
    }

    /**
     * Get array of registered MCP tools and JSON schemas
     */
    public function getToolsManifest(): array
    {
        $disabledTools = $this->getDisabledTools();
        $skills = $this->getDynamicSkills();

        $skillTools = [];
        foreach ($skills as $sId => $skill) {
            if (empty($skill['enabled'])) continue;
            $tool = new DynamicSkillTool($skill, $this->getSystemPrompt());
            $toolName = $tool->getName();
            if (in_array($toolName, $disabledTools, true)) continue;

            $skillTools[] = [
                'name'        => $toolName,
                'description' => $tool->getDescription(),
                'inputSchema' => $tool->getInputSchema()
            ];
        }

        $manifest = $skillTools;

        foreach ($this->tools as $name => $tool) {
            if (in_array($name, $disabledTools, true)) continue;
            $manifest[] = [
                'name'        => $tool->getName(),
                'description' => $tool->getDescription(),
                'inputSchema' => $tool->getInputSchema()
            ];
        }

        return array_values($manifest);
    }

    /**
     * Execute a tool by name with aliases and dynamic skills support
     */
    public function execute(string $name, array $args, ?array $context = null): array
    {
        $disabledTools = $this->getDisabledTools();
        if (in_array($name, $disabledTools, true)) {
            return ['error' => "Tool '{$name}' is currently disabled by administrator."];
        }

        // Quota check per tenant if user context is provided
        $authUser = $context['user'] ?? null;
        $tenantId = isset($authUser['tenant_id']) ? intval($authUser['tenant_id']) : null;
        $quotaManager = new \App\Libraries\SaaS\QuotaManager();

        if ($tenantId !== null && !$quotaManager->canExecute($tenantId, 'mcp_calls')) {
            return [
                'status'  => 'error',
                'error'   => 'Quota exceeded: Daily MCP API call limit reached for your workspace. Please upgrade or contact support.'
            ];
        }

        // Check dynamic skills
        $skills = $this->getDynamicSkills();
        foreach ($skills as $sId => $skill) {
            $tool = new DynamicSkillTool($skill, $this->getSystemPrompt());
            $toolName = $tool->getName();
            $legacyName = 'get_' . str_replace('-', '_', $sId) . '_instructions';

            if ($name === $toolName || $name === $legacyName) {
                if ($tenantId !== null) {
                    $quotaManager->recordUsage($tenantId, 'mcp_calls');
                }
                return $tool->execute($args, $context);
            }
        }

        // Aliases mapping
        $aliases = [
            'save_ad'                               => 'save_product',
            'search_facebook_ads'                   => 'facebook_search_ads',
            'fb_search_ads'                         => 'facebook_search_ads',
            'discover_competitor_brands'            => 'facebook_discover_competitors',
            'fb_discover_competitors'               => 'facebook_discover_competitors',
            'analyze_ad_creative_elements'          => 'facebook_analyze_creative',
            'fb_analyze_creative'                   => 'facebook_analyze_creative',
            'analyze_ad_performance_metrics'        => 'facebook_analyze_performance',
            'fb_analyze_performance'                => 'facebook_analyze_performance',
            'competitive_ad_analysis'               => 'facebook_competitive_analysis',
            'fb_competitive_analysis'               => 'facebook_competitive_analysis',
            'generate_facebook_intelligence_report' => 'facebook_intelligence_report',
            'fb_intelligence_report'                => 'facebook_intelligence_report',
            'export_facebook_ads_data'              => 'facebook_export_ads',
            'fb_export_ads'                         => 'facebook_export_ads',
        ];

        $resolvedName = $aliases[$name] ?? $name;

        if (isset($this->tools[$resolvedName])) {
            if ($tenantId !== null) {
                $quotaManager->recordUsage($tenantId, 'mcp_calls');
                if ($resolvedName === 'semantic_search_products' || $resolvedName === 'find_similar_products') {
                    $quotaManager->recordUsage($tenantId, 'vector_searches');
                }
            }
            return $this->tools[$resolvedName]->execute($args, $context);
        }

        throw new \Exception("Unknown tool: {$name}");
    }
}

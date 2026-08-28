<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;

class McpAdminController extends BaseController
{
    /**
     * Helper to retrieve a setting from settings table.
     */
    private function getSetting(string $key, $default = null)
    {
        $db = \Config\Database::connect();
        $row = $db->table('settings')->where('key', $key)->get()->getRowArray();
        return $row ? $row['value'] : $default;
    }

    /**
     * Helper to set or update a setting in settings table.
     */
    private function setSetting(string $key, string $value): void
    {
        $db = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');
        $existing = $db->table('settings')->where('key', $key)->get()->getRowArray();
        if ($existing) {
            $db->table('settings')->where('key', $key)->update([
                'value'      => $value,
                'updated_at' => $now,
            ]);
        } else {
            $db->table('settings')->insert([
                'key'        => $key,
                'value'      => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function getDefaultSystemPrompt(): string
    {
        return "تنبيه مهم: آلية العمل ومراحل التنفيذ\n"
             . "سيتم تنفيذ المهام على مراحل متتالية، ولا يتم الانتقال إلى أي مرحلة قبل إتمام المرحلة السابقة واعتماد نتائجها.\n\n"
             . "المرحلة الأولى: اختيار واستكشاف المنتجات المرشحة والرابحة\n"
             . "- عند طلب الاستكشاف أو التحليل، استخدم أدوات MCP المتاحة مثل `fetch_new_data` (لجلب البيانات الجديدة حسب التاريخ والدولة والتصنيف افتراضياً ككل)، أو `filter_winning_products` (مع تحديد country='MA' للسوق المغربي)، أو `list_snapshots` / `get_snapshot_by_date` للتواريخ المتاحة، أو `get_saved_products` للاستعلام عن المحفوظات الخاصة بالحساب.\n"
             . "- تحليل المنتجات المرشحة وتقييمها بناءً على معايير الجدوى والطلب المتاح بالبيانات.\n"
             . "- تطبيق نظام تقييم المنتجات (Score من 100): قوة الطلب في الإعلانات (40 نقطة)، ملاءمة السوق والموسم في المغرب (30 نقطة)، سهولة اللوجستيك (20 نقطة)، والتوافق مع قيود الميزانية والميول (10 نقاط).\n"
             . "- تصنيف المنتجات إلى: 🟢 رابحة (>= 75)، 🟡 واعدة (50-74)، 🔴 ضعيفة/عالية المخاطر (< 50).\n"
             . "- عرض قائمة التقييم والجداول بوضوح، وانتظار اختيار وموافقة المستخدم على المنتج الرابح قبل الانتقال للمرحلة التالية.\n\n"
             . "المرحلة الثانية: إدخال بيانات المنتج والتحليل التفصيلي الشامل\n"
             . "بعد اعتماد المنتج الرابح، استخدم أداة `get_product_full_json` لجلب البيانات الخام الكاملة للمنتج (أو طلب البيانات النواقص مثل C_wholesale و C_shipping من المستخدم)، ثم ابدأ تنفيذ جميع العمليات التالية:\n"
             . "1. التحليل المالي والتسعير ونموذج COD للسوق المغربي:\n"
             . "   - حساب التكلفة الفعلية للتوصيل مع الرجوع: C_shipping / (1 - R).\n"
             . "   - مقارنة سعر البيع المقترح مع سعر المنافس وتوضيح الفارق التنافسي بالنسبة المئوية.\n"
             . "   - تقديم جداول تسعير مفصلة تشمل هامش الربح الصافي النهائي (M_final).\n"
             . "2. خطة الإعلانات وتصريف المخزون:\n"
             . "   - تحديد مدة تصريف المخزون بناءً على حجم الوحدات (مثلاً Micro-Batch للمخزون < 20 قطعة).\n"
             . "   - تقسيم الميزانية الإعلانية عبر ثلاث مراحل: اختبار، توسيع، وحرق المخزون.\n"
             . "3. صناعة المحتوى الإعلاني والـ Creatives:\n"
             . "   - إنشاء سكربتات إعلانية مبنية على هيكل (Hook -> Problem -> Solution -> Offer -> CTA).\n"
             . "   - تقديم برومبتات AI دقيقة لتوليد فيديوهات UGC وعناوين ووصف إعلاني لكل منتج رابح.\n"
             . "   - توليد 4 برومبتات AI لصور إعلانية (احترافية، Lifestyle، زاوية تحويلية).\n"
             . "   - كتابة 3 نسخ إعلانية لفيسبوك (Primary Text) ومحتوى مخصص لتيك توك (Hooks, Hashtags).\n\n"
             . "اللغة والأسلوب:\n"
             . "- استخدام اللغة العربية الفصحى أو الدارجة المغربية مع قبول المصطلحات التقنية (ROAS, CPA, COD).\n"
             . "- الاعتماد المكثف على الجداول والأرقام الواضحة والعملية.";
    }

    /**
     * Display the Admin MCP Control Panel.
     */
    public function index()
    {
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            return redirect()->to('/')->with('error', 'غير مسموح لك بالوصول لهذه الصفحة (صلاحية المسؤول مطلوبة).');
        }

        $db = \Config\Database::connect();

        // 1. Get MCP global status & tool settings
        $globalEnabled = $this->getSetting('mcp_global_enabled', '1') === '1';
        $systemPrompt  = $this->getSetting('mcp_system_prompt', $this->getDefaultSystemPrompt());

        $allTools = [
            'get_nano_banana_pro_instructions' => [
                'name'        => 'get_nano_banana_pro_instructions',
                'title'       => 'مهارة Nano Banana Pro (الهوية البصرية وتوليد الإعلانات)',
                'description' => 'توليد برومبتات إعلانية احترافية، تثبيت صورة المنتج (Image Lock)، واستخراج نظام ألوان الويب (HEX/CSS).',
                'badge'       => 'Creative Skill مهارة إبداعية',
            ],
            'get_ai_skill_instructions' => [
                'name'        => 'get_ai_skill_instructions',
                'title'       => 'جلب مهارة وتوجيهات التحليل (AI Skill Instructions)',
                'description' => 'أداة تُتيح للنماذج قراءة توجيهات ومهارات نظام التحليل بالسوق المغربي بنظام COD تلقائياً عند طلب الاستكشاف.',
                'badge'       => 'Skill أداة المهارة',
            ],
            'get_saved_products' => [
                'name'        => 'get_saved_products',
                'title'       => 'جلب المحفوظات الخاصة بالحساب',
                'description' => 'استرجاع جميع المنتجات المحفوظة الخاصة بالعضو الموثق حسب مساحة العمل (Tenant-isolated).',
                'badge'       => 'خبير المحفوظات',
            ],
            'list_snapshots' => [
                'name'        => 'list_snapshots',
                'title'       => 'عرض لقاطات البيانات',
                'description' => 'استعراض لقطات البيانات المخزنة بالنظام مع فلترة المصدر والتصفح.',
                'badge'       => 'بيانات عامة',
            ],
            'get_snapshot_by_date' => [
                'name'        => 'get_snapshot_by_date',
                'title'       => 'جلب البيانات حسب التاريخ أو الإصدار',
                'description' => 'البحث عن منتجات لقطة بيانات محددة بالتاريخ أو رقم الاصدار.',
                'badge'       => 'تاريخي',
            ],
            'filter_winning_products' => [
                'name'        => 'filter_winning_products',
                'title'       => 'فلترة المنتجات الرابحة (Winning)',
                'description' => 'فلترة متقدمة للمنتجات الرابحة حسب عدد الإعلانات، السعر، والدولة.',
                'badge'       => 'تحليل متقدم',
            ],
            'get_products' => [
                'name'        => 'get_products',
                'title'       => 'البحث عن المنتجات بالمعرف أو الاسم',
                'description' => 'جلب قائمة منتجات محددة بناءً على المعرفات أو البحث بالكلمات المفتاحية.',
                'badge'       => 'استعلام أساسي',
            ],
            'get_product_full_json' => [
                'name'        => 'get_product_full_json',
                'title'       => 'جلب تفاصيل المنتج الكاملة (JSON Unredacted)',
                'description' => 'جلب بيانات المنتج الخام الكاملة لتحليل التفاصيل التقنية والدقيقة.',
                'badge'       => 'بيانات كاملة',
            ],
            'fetch_new_data' => [
                'name'        => 'fetch_new_data',
                'title'       => 'جلب البيانات الجديدة حسب التاريخ والدولة (Fetch New Data)',
                'description' => 'أداة تُتيح جلب البيانات والمنتجات الجديدة بناءً على التاريخ والدولة مع جعل التصنيف (Classification) ككل افتراضياً.',
                'badge'       => 'جلب تلقائي',
            ],
            'save_product' => [
                'name'        => 'save_product',
                'title'       => 'حفظ المنتج في المحفوظات (Save Product to Saved Ads)',
                'description' => 'أداة تُتيح حفظ أو تحديث منتج في المحفوظات التابعة لحساب العضو الموثق مفتاحه.',
                'badge'       => 'إدارة المحفوظات',
            ],
            'facebook_search_ads' => [
                'name'        => 'facebook_search_ads',
                'title'       => 'البحث في مكتبة إعلانات فيسبوك (Facebook Ads Search)',
                'description' => 'أداة البحث المتقدم والمفلتر في مكتبة إعلانات فيسبوك الرسمية حسب العلامة التجارية والدولة.',
                'badge'       => 'إعلانات فيسبوك',
            ],
            'facebook_discover_competitors' => [
                'name'        => 'facebook_discover_competitors',
                'title'       => 'استكشاف المنافسين في القطاع (Discover Competitors)',
                'description' => 'اكتشاف المتاجر والعلامات التجارية المنافسة التي تطلق إعلانات نشطة في قطاع محدد وترتيبها.',
                'badge'       => 'تحليل المنافسين',
            ],
            'facebook_analyze_creative' => [
                'name'        => 'facebook_analyze_creative',
                'title'       => 'تحليل المحتوى الإعلاني والـ CTAs (Analyze Creative)',
                'description' => 'تفكيك لقطة الإعلان، استخراج النصوص، أزرار الدعوة للإجراء (CTA)، وكلمات الإلحاح والحوافز.',
                'badge'       => 'تحليل المحتوى',
            ],
            'facebook_analyze_performance' => [
                'name'        => 'facebook_analyze_performance',
                'title'       => 'تحليل أداء وإنفاق الإعلانات (Performance & Spend Metrics)',
                'description' => 'تقدير مرات الظهور، نطاقات الإنفاق، وتوزيع المنصات (Facebook, Instagram) والديموغرافيا.',
                'badge'       => 'أداء وإنفاق',
            ],
            'facebook_competitive_analysis' => [
                'name'        => 'facebook_competitive_analysis',
                'title'       => 'المقارنة التنافسية الذكية (Competitive Analysis)',
                'description' => 'مقارنة استراتيجيات الإعلانات بين عدة منافسين وتحديد قائد السوق والأنماط المشتركة.',
                'badge'       => 'مقارنة استخباراتية',
            ],
            'facebook_intelligence_report' => [
                'name'        => 'facebook_intelligence_report',
                'title'       => 'تقرير الاستخبارات الإعلانية الشامل (Facebook Intelligence Report)',
                'description' => 'توليد تقرير استخباراتي متكامل للعلامة التجارية مع مقارنة المنافسين وتوصيات تسويقية دقيقة.',
                'badge'       => 'تقرير استخباراتي',
            ],
            'facebook_export_ads' => [
                'name'        => 'facebook_export_ads',
                'title'       => 'تصدير بيانات الإعلانات (Export Facebook Ads)',
                'description' => 'تصدير بيانات الإعلانات والتحليلات بتنسيقات متعددة (JSON, CSV, Markdown).',
                'badge'       => 'تصدير البيانات',
            ],
        ];

        foreach ($allTools as $toolKey => &$toolMeta) {
            $settingVal = $this->getSetting("mcp_tool_{$toolKey}", '1');
            $toolMeta['enabled'] = ($settingVal === '1');
        }

        // 2. Fetch users with tokens and tenants
        $users = $db->table('users')
            ->select('users.id, users.username, users.api_token, users.created_at, tenants.name as tenant_name, auth_identities.secret as email')
            ->join('tenants', 'tenants.id = users.tenant_id', 'left')
            ->join('auth_identities', "auth_identities.user_id = users.id AND auth_identities.type = 'email_password'", 'left')
            ->orderBy('users.id', 'ASC')
            ->get()
            ->getResultArray();

        // Stats calculation
        $totalUsers = count($users);
        $usersWithTokenCount = 0;
        foreach ($users as $u) {
            if (!empty($u['api_token'])) {
                $usersWithTokenCount++;
            }
        }

        $enabledToolsCount = 0;
        foreach ($allTools as $t) {
            if ($t['enabled']) {
                $enabledToolsCount++;
            }
        }

        $facebookToken = $this->getSetting('facebook_access_token', env('FACEBOOK_ACCESS_TOKEN', ''));

        return view('admin/mcp', [
            'globalEnabled'       => $globalEnabled,
            'systemPrompt'        => $systemPrompt,
            'defaultSystemPrompt' => $this->getDefaultSystemPrompt(),
            'tools'               => $allTools,
            'users'               => $users,
            'totalUsers'          => $totalUsers,
            'usersWithTokenCount' => $usersWithTokenCount,
            'enabledToolsCount'   => $enabledToolsCount,
            'mcpEndpointUrl'      => site_url('api/mcp'),
            'facebookToken'       => $facebookToken,
        ]);
    }

    /**
     * Save Facebook Graph API Access Token
     */
    public function saveFacebookToken(): RedirectResponse
    {
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            return redirect()->to('/')->with('error', 'غير مسموح لك بالوصول.');
        }

        $token = trim((string) $this->request->getPost('facebook_access_token'));
        $this->setSetting('facebook_access_token', $token);

        return redirect()->back()->with('message', 'تم حفظ مفتاح Facebook Graph API Access Token بنجاح! 🔑✨');
    }

    /**
     * Toggle global MCP status (Enabled/Disabled).
     */
    public function toggleGlobalStatus(): RedirectResponse
    {
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            return redirect()->to('/')->with('error', 'غير مسموح لك بالوصول.');
        }

        $status = $this->request->getPost('status') === '1' ? '1' : '0';
        $this->setSetting('mcp_global_enabled', $status);

        $msg = ($status === '1') ? 'تم تفعيل سيرفر MCP بنجاح! 🟢' : 'تم إيقاف سيرفر MCP مؤقتاً 🔴';
        return redirect()->back()->with('message', $msg);
    }

    /**
     * Toggle individual tool active/disabled status.
     */
    public function toggleTool(): RedirectResponse
    {
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            return redirect()->to('/')->with('error', 'غير مسموح لك بالوصول.');
        }

        $toolName = $this->request->getPost('tool_name');
        $status   = $this->request->getPost('status') === '1' ? '1' : '0';

        if (!empty($toolName)) {
            $this->setSetting("mcp_tool_{$toolName}", $status);
            $msg = ($status === '1') ? "تم تفعيل أداة '{$toolName}' بنجاح! ✅" : "تم تعطيل أداة '{$toolName}' 🚫";
            return redirect()->back()->with('message', $msg);
        }

        return redirect()->back()->with('error', 'اسم الأداة غير صالح.');
    }

    /**
     * Generate or regenerate token for a specific user ID.
     */
    public function generateUserToken($userId): RedirectResponse
    {
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            return redirect()->to('/')->with('error', 'غير مسموح لك بالوصول.');
        }

        $db = \Config\Database::connect();
        $targetUser = $db->table('users')->where('id', $userId)->get()->getRowArray();

        if (!$targetUser) {
            return redirect()->back()->with('error', 'المستخدم غير موجود.');
        }

        $newToken = 'mcp_' . bin2hex(random_bytes(24));
        $db->table('users')->where('id', $userId)->update(['api_token' => $newToken]);

        return redirect()->back()->with('message', "تم توليد مفتاح API جديد للمستخدم '{$targetUser['username']}' بنجاح! 🔑");
    }

    /**
     * Revoke token for a specific user ID.
     */
    public function revokeUserToken($userId): RedirectResponse
    {
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            return redirect()->to('/')->with('error', 'غير مسموح لك بالوصول.');
        }

        $db = \Config\Database::connect();
        $targetUser = $db->table('users')->where('id', $userId)->get()->getRowArray();

        if (!$targetUser) {
            return redirect()->back()->with('error', 'المستخدم غير موجود.');
        }

        $db->table('users')->where('id', $userId)->update(['api_token' => null]);

        return redirect()->back()->with('message', "تم إلغاء مفتاح API للمستخدم '{$targetUser['username']}' بنجاح. 🚫");
    }

    /**
     * Update system prompt & AI skill instructions.
     */
    public function updateSystemPrompt(): RedirectResponse
    {
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            return redirect()->to('/')->with('error', 'غير مسموح لك بالوصول.');
        }

        $prompt = $this->request->getPost('system_prompt');
        $reset  = $this->request->getPost('reset');

        if ($reset === '1') {
            $prompt = $this->getDefaultSystemPrompt();
            $this->setSetting('mcp_system_prompt', $prompt);
            return redirect()->back()->with('message', 'تم استعادة توجيهات النظام والمهارات الافتراضية بنجاح! 🔄');
        }

        if (is_string($prompt)) {
            $this->setSetting('mcp_system_prompt', trim($prompt));
            return redirect()->back()->with('message', 'تم حفظ توجيهات النظام ومهارات الذكاء الاصطناعي (MCP Skill Prompt) بنجاح! 📜✨');
        }

        return redirect()->back()->with('error', 'التوجيه المدخل غير صالح.');
    }
}

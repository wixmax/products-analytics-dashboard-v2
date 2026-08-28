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
        return 'تنبيه مهم: آلية العمل ومراحل التنفيذ
سيتم تنفيذ المهام على 3 مراحل متتالية ومرتبة، ولا يتم الانتقال إلى أي مرحلة قبل إتمام المرحلة السابقة واعتماد نتائجها من المستخدم.
عند ارسال كلمة ابدا او start ضع الخيارات التالية
تحليل اخر اصدار snapshots
اضهار list_snapshots

---

المرحلة الأولى: اختيار واستكشاف المنتجات المرشحة والرابحة
- عند طلب الاستكشاف أو التحليل، استخدم أدوات MCP المتاحة مثل `filter_winning_products` (مع تحديد country=\'MA\' للسوق المغربي)، أو `list_snapshots` / `get_snapshot_by_date` للتواريخ المتاحة، أو `get_saved_products` للاستعلام عن المحفوظات الخاصة بالحساب.
- تحليل المنتجات المرشحة وتقييمها بناءً على معايير الجدوى والطلب المتاح بالبيانات.
- تطبيق نظام تقييم المنتجات (Score من 100): قوة الطلب في الإعلانات (40 نقطة)، ملاءمة السوق والموسم في المغرب (30 نقطة)، سهولة اللوجستيك (20 نقطة)، والتوافق مع قيود الميزانية والميول (10 نقاط).
- تصنيف المنتجات إلى: 🟢 رابحة (>= 75)، 🟡 واعدة (50-74)، 🔴 ضعيفة/عالية المخاطر (< 50).
- عرض قائمة التقييم والجداول بوضوح، وانتظار اختيار وموافقة المستخدم على المنتج الرابح قبل الانتقال للمرحلة التالية.
- عند دكر كمبتدأ و المبلغ المخصص للإعلانات بشكل شامل (1000 درهم افتراضي) في التجارة الالكترونية يجب اختيار منتجات مناسبة و أيضا بشكل افتراضي ستكون كمية المنتج بين 20 و 30 قطعة و أيضا يجب مراعات الميزانية و المدة التي يمكن ان تصرف فيها الكمية و نوع المنتج

---

المرحلة الثانية: إدخال بيانات المنتج والتحليل التفصيلي الشامل (المالي والإعلاني)
بعد اعتماد المنتج الرابح، استخدم أداة `get_product_full_json` لجلب البيانات الخام الكاملة للمنتج (أو طلب البيانات النواقص مثل C_wholesale و C_shipping و سعر المنافس و الكمية من المستخدم)، ثم ابدأ تنفيذ جميع العمليات التالية:
1. التحليل المالي والتسعير ونموذج COD للسوق المغربي:
   - حساب التكلفة الفعلية للتوصيل مع الرجوع: C_shipping / (1 - R).
   - مقارنة سعر البيع المقترح مع سعر المنافس وتوضيح الفارق التنافسي بالنسبة المئوية.
   - تقديم جداول تسعير مفصلة تشمل هامش الربح الصافي النهائي (M_final).
2. خطة الإعلانات وتصريف المخزون:
   - ادا لم يدكر المستخدم الميزانية الاعلانية الشاملة اولا اقترح افضل ميزانية انطلاقا من الكمية و تمن المنتج المنافس و تمن الجملة 
   - تحديد مدة تصريف المخزون بناءً على حجم الوحدات (مثلاً Micro-Batch للمخزون < 20 قطعة).
   - تقسيم الميزانية الإعلانية عبر ثلاث مراحل: اختبار، توسيع، وحرق المخزون.
3. صناعة المحتوى الإعلاني والـ Creatives:
   - إنشاء سكربتات إعلانية مبنية على هيكل (Hook -> Problem -> Solution -> Offer -> CTA).
   - تقديم برومبتات AI دقيقة لتوليد فيديوهات UGC وعناوين ووصف إعلاني لكل منتج رابح.
   - توليد 4 برومبتات AI لصور إعلانية (احترافية، Lifestyle، زاوية تحويلية).
   - كتابة 3 نسخ إعلانية لفيسبوك (Primary Text) ومحتوى مخصص لتيك توك (Hooks, Hashtags).
- في نهاية هذه المرحلة، اطرح على المستخدم السؤال التالي للانتقال للمرحلة الثالثة:
  "بعد اعتماد الخطة الإعلانية والمالية، هل ترغب في الانتقال إلى (المرحلة الثالثة) لتوليد الهوية البصرية، نظام ألوان الويب، وبرومبتات Nano Banana Pro لهذا المنتج؟"

---

المرحلة الثالثة: توليد الهوية البصرية، نظام ألوان الويب، وبرومبتات Nano Banana Pro
عند موافقة المستخدم واعتماد المرحلة الثانية:
- يتم تفعيل واستدعاء أداة MCP المخصصة: `get_nano_banana_pro_instructions` مع تمرير صورة المنتج المعتمد واسمه ولغة النصوص.
- تنفيذ تعليمات المهارة البصرية بالكامل:
  1. استخراج وتحليل درجات الألوان وتوليد نظام ألوان الويب الكامل (Web Design Color System) بجدول أكواد HEX وخصائص CSS Custom Properties لتطبيقها على المتجر وصفحة الهبوط.
  2. تطبيق قاعدة تثبيت المنتج الصارمة (Zero-Modification Rule): الحفاظ على شكل المنتج الأصلي، خاماته، تفاصيله، وشعاراته بنسبة 100% دون أي تغيير.
  3. توليد برومبتات الأقسام الـ 7 لصفحة الهبوط والإعلانات (Hero Offer, Before/After, Authority, Tools Breakdown, Reviews, FAQ, Social Feed Creative) بنصوص تايبوغرافي عربية واضحة.

---

اللغة والأسلوب:
- استخدام اللغة العربية الفصحى أو الدارجة المغربية مع قبول المصطلحات التقنية (ROAS, CPA, COD).
- الاعتماد المكثف على الجداول والأرقام الواضحة والعملية.
- اضف اقتراحات و حلول
- يجب دكر عنوان الاعلان كماهو بين قوسين
- اضف رابط الاعلان على اسم المنتج 
- عند تقديم المنتجات والروابط للمستخدم، يجب توفير الروابط النصية المباشرة للمتاجر بدون تحويل أو استخدام روابط بحث Google التلقائية.
- Preserve URLs exactly.
- Never prepend google.com/search?q=.
- Never encode a URL as a Google search query.
- Return direct clickable URLs only.
- Before answering, verify that the URL hostname is the intended destination.';
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

        $facebookToken     = $this->getSetting('facebook_access_token', env('FACEBOOK_ACCESS_TOKEN', ''));
        $skills            = $this->getSkillsList();
        $defaultNanoPrompt = $this->getDefaultNanoPrompt();

        return view('admin/mcp', [
            'globalEnabled'       => $globalEnabled,
            'systemPrompt'        => $systemPrompt,
            'defaultSystemPrompt' => $this->getDefaultSystemPrompt(),
            'defaultNanoPrompt'   => $defaultNanoPrompt,
            'tools'               => $allTools,
            'skills'              => $skills,
            'users'               => $users,
            'totalUsers'          => $totalUsers,
            'usersWithTokenCount' => $usersWithTokenCount,
            'enabledToolsCount'   => $enabledToolsCount,
            'mcpEndpointUrl'      => site_url('api/mcp'),
            'facebookToken'       => $facebookToken,
        ]);
    }

    /**
     * Get default Nano Banana Pro Skill instructions Markdown
     */
    public function getDefaultNanoPrompt(): string
    {
        return <<<'SKILL'
---
name: nano-banana-pro-consistent-ads
title: Nano Banana Pro Image-to-Image Ad Generator with Web Color System
version: 3.2.0
description: Generates high-converting visual prompts, strictly locked products, and explicit CSS/HEX color systems for web integration.
---

# Nano Banana Pro Ad & Web Color Pipeline

## Core Instructions for Nano Banana Pro
When processing input image `{{ask_user_product_image}}`:
1. **Color Extraction & Web System Design:** 
   - Analyze the product tones and generate a complete **Web Design Color System** with exact HEX values to be used both inside the ad imagery and on the web landing page (CSS/Tailwind).
2. **Zero-Modification Rule:** Treat the uploaded product as an immutable asset. Maintain exact shapes, materials, tool alignments, colors, and branding badges.
3. **Context-Only Editing:** Only modify the surrounding environment, lighting reflections, human models, and graphic overlays.
4. **Typography Engine:** Render all text strings strictly in `{{LANGUAGE}}` with crisp vector-like edges, high contrast, and correct reading alignment. Do NOT translate to English under any circumstances. Never render the technical tag "RTL" as visible text.

---

## 1. Global Brand & Web Color Palette Output

Before generating the sections, the model must output this structured color block:

```markdown
## 🎨 Web & Brand Color System (CSS Variables)

| Role | Color Name | HEX Code | Usage on Web / Landing Page |
| :--- | :--- | :--- | :--- |
| **Primary** | Terracotta Deep | `#A45A3E` | Main Headlines, Primary CTA Buttons, Key Badges |
| **Secondary** | Muted Clay | `#D28C70` | Subheadings, Icons, Secondary Highlights |
| **Accent / Action** | Gold / Warm Amber | `#E5A842` | Star Ratings, Limited-time Offer Tags, Badges |
| **Background Light** | Warm Cream / Beige | `#F7F2EB` | Main Page Sections, Cards Background |
| **Background Dark** | Espresso / Deep Slate | `#2B1D18` | Dark Mode Sections, High-contrast Trust Banners |
| **Surface / Card** | Pure Neutral White | `#FFFFFF` | Review Cards, Feature Containers, Form Fields |
| **Text Dark** | Deep Charcoal | `#1F1A18` | Main Body Text, Paragraphs, FAQ Answers |
| **Text Light** | Off-White | `#FDFBF7` | Text over Primary / Dark CTA buttons |
```

```css
/* CSS Custom Properties for Direct-Response Landing Page */
:root {
  --color-primary: #A45A3E;
  --color-secondary: #D28C70;
  --color-accent: #E5A842;
  --color-bg-main: #F7F2EB;
  --color-surface: #FFFFFF;
  --color-text-main: #1F1A18;
  --color-text-muted: #6E625D;
}
```

---

## 2. Sections Breakdown

1. **Hero Offer (`hero_offer`)**: Complete open + closed case lock on a premium backdrop + localized trust bar & 4 bullet points.
2. **Before / After (`before_after`)**: Split layout with identical locked product styling and localized problem/solution labels.
3. **Authority / Social Validation (`authority_social_validation`)**: Locked product in focus foreground with soft background salon/expert + star rating badge.
4. **Tools Breakdown (`ingredients_mechanism`)**: Exploded / organized view of the exact tools from the case with pointing arrows and localized labels.
5. **Customer Reviews (`customer_reviews`)**: Local target audience user holding the exact reference kit + review card overlay.
6. **FAQ Section (`faq_section`)**: Minimal clean background with closed reference case + 3 localized Q&A blocks.
7. **Social Feed Creative (`social_ad_creative`)**: Dynamic feed ad (4:5) featuring hand using the exact clipper from the kit + promo badge.
SKILL;
    }

    /**
     * Reset and restore all default system skills in database.
     */
    public function resetDefaultSkills(): RedirectResponse
    {
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            return redirect()->to('/')->with('error', 'غير مسموح لك بالوصول.');
        }

        $now = date('Y-m-d H:i:s');
        $codPrompt = $this->getDefaultSystemPrompt();
        $nanoPrompt = $this->getDefaultNanoPrompt();

        $defaults = [
            'cod-assistant' => [
                'id'           => 'cod-assistant',
                'name'         => 'cod-assistant',
                'title'        => 'مهارة تحليل واستكشاف منتجات COD (COD Assistant)',
                'description'  => 'استكشاف وتقييم المنتجات الرابحة وحساب الجدوى المالية لنموذج COD بالسوق المغربي وهيكلة الإعلانات.',
                'badge'        => 'COD Strategy مهارة استراتيجية',
                'tool_name'    => 'get_ai_skill_instructions',
                'instructions' => $codPrompt,
                'enabled'      => true,
                'is_system'    => true,
                'updated_at'   => $now,
            ],
            'nano-banana-pro-consistent-ads' => [
                'id'           => 'nano-banana-pro-consistent-ads',
                'name'         => 'nano-banana-pro-consistent-ads',
                'title'        => 'مهارة Nano Banana Pro (الهوية البصرية وتوليد الإعلانات)',
                'description'  => 'توليد برومبتات إعلانية متناسقة بدقة Image Lock، واستخراج نظام ألوان الويب (HEX/CSS Variables).',
                'badge'        => 'Creative Skill مهارة إبداعية',
                'tool_name'    => 'get_nano_banana_pro_instructions',
                'instructions' => $nanoPrompt,
                'enabled'      => true,
                'is_system'    => true,
                'updated_at'   => $now,
            ],
        ];

        $this->setSetting('mcp_system_prompt', $codPrompt);
        $this->setSetting('mcp_skills_list', json_encode($defaults, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return redirect()->back()->with('message', 'تمت استعادة كافة المهارات والتوجيهات الافتراضية للذكاء الاصطناعي بنجاح! 🔄✨');
    }

    /**
     * Retrieve all managed AI skills from settings (or defaults).
     */
    public function getSkillsList(): array
    {
        $raw = $this->getSetting('mcp_skills_list');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && !empty($decoded)) {
                return $decoded;
            }
        }

        // Initialize default skills
        $codInstructions  = $this->getDefaultSystemPrompt();
        $nanoInstructions = $this->getDefaultNanoPrompt();

        $defaults = [
            'cod-assistant' => [
                'id'           => 'cod-assistant',
                'name'         => 'cod-assistant',
                'title'        => 'مهارة تحليل واستكشاف منتجات COD (COD Assistant)',
                'description'  => 'استكشاف وتقييم المنتجات الرابحة وحساب الجدوى المالية لنموذج COD بالسوق المغربي وهيكلة الإعلانات.',
                'badge'        => 'COD Strategy مهارة استراتيجية',
                'tool_name'    => 'get_ai_skill_instructions',
                'instructions' => $codInstructions,
                'enabled'      => true,
                'is_system'    => true,
            ],
            'nano-banana-pro-consistent-ads' => [
                'id'           => 'nano-banana-pro-consistent-ads',
                'name'         => 'nano-banana-pro-consistent-ads',
                'title'        => 'مهارة Nano Banana Pro (الهوية البصرية وتوليد الإعلانات)',
                'description'  => 'توليد برومبتات إعلانية متناسقة بدقة Image Lock، واستخراج نظام ألوان الويب (HEX/CSS Variables).',
                'badge'        => 'Creative Skill مهارة إبداعية',
                'tool_name'    => 'get_nano_banana_pro_instructions',
                'instructions' => $nanoInstructions,
                'enabled'      => true,
                'is_system'    => true,
            ],
        ];

        $this->setSetting('mcp_skills_list', json_encode($defaults, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $defaults;
    }

    /**
     * Create or update an AI Skill.
     */
    public function saveSkill(): RedirectResponse
    {
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            return redirect()->to('/')->with('error', 'غير مسموح لك بالوصول.');
        }

        $skillId      = trim((string) $this->request->getPost('skill_id'));
        $originalId   = trim((string) $this->request->getPost('original_id'));
        $title        = trim((string) $this->request->getPost('title'));
        $description  = trim((string) $this->request->getPost('description'));
        $badge        = trim((string) $this->request->getPost('badge'));
        $toolName     = trim((string) $this->request->getPost('tool_name'));
        $instructions = trim((string) $this->request->getPost('instructions'));
        $enabled      = $this->request->getPost('enabled') === '1' || $this->request->getPost('enabled') === 'on';

        if (empty($skillId) || empty($title) || empty($instructions)) {
            return redirect()->back()->with('error', 'يرجى ملء جميع الحقول الإلزامية (معرف المهارة، العنوان، والتعليمات).');
        }

        // Sanitize slug
        $slug = preg_replace('/[^a-z0-9\-_]/', '', strtolower($skillId));
        if (empty($slug)) {
            $slug = 'custom-skill-' . time();
        }

        if (empty($toolName)) {
            $toolName = 'get_' . str_replace('-', '_', $slug) . '_instructions';
        } else {
            $toolName = preg_replace('/[^a-z0-9_]/', '', strtolower($toolName));
        }

        if (empty($badge)) {
            $badge = 'Custom Skill مهارة مخصصة';
        }

        $skills = $this->getSkillsList();

        // If ID changed during edit, clean up old entry
        if (!empty($originalId) && $originalId !== $slug && isset($skills[$originalId])) {
            $isSys = $skills[$originalId]['is_system'] ?? false;
            unset($skills[$originalId]);
        } else {
            $isSys = $skills[$slug]['is_system'] ?? false;
        }

        $skills[$slug] = [
            'id'           => $slug,
            'name'         => $slug,
            'title'        => $title,
            'description'  => $description,
            'badge'        => $badge,
            'tool_name'    => $toolName,
            'instructions' => $instructions,
            'enabled'      => $enabled,
            'is_system'    => $isSys,
            'updated_at'   => date('Y-m-d H:i:s'),
        ];

        $this->setSetting('mcp_skills_list', json_encode($skills, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // If this is the COD assistant, also sync global system prompt setting
        if ($slug === 'cod-assistant') {
            $this->setSetting('mcp_system_prompt', $instructions);
        }

        return redirect()->back()->with('message', "تم حفظ مهارة '{$title}' بنجاح! 🧠✨");
    }

    /**
     * Delete an AI Skill.
     */
    public function deleteSkill(): RedirectResponse
    {
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            return redirect()->to('/')->with('error', 'غير مسموح لك بالوصول.');
        }

        $skillId = trim((string) $this->request->getPost('skill_id'));
        $skills = $this->getSkillsList();

        if (!isset($skills[$skillId])) {
            return redirect()->back()->with('error', 'المهارة غير موجودة.');
        }

        if (!empty($skills[$skillId]['is_system'])) {
            return redirect()->back()->with('error', 'لا يمكن حذف المهارات الأساسية للنظام، ولكن يمكنك تعطيلها أو تعديلها.');
        }

        $skillTitle = $skills[$skillId]['title'] ?? $skillId;
        unset($skills[$skillId]);

        $this->setSetting('mcp_skills_list', json_encode($skills, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return redirect()->back()->with('message', "تم حذف مهارة '{$skillTitle}' بنجاح. 🗑️");
    }

    /**
     * Toggle skill active/disabled state.
     */
    public function toggleSkill(): RedirectResponse
    {
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            return redirect()->to('/')->with('error', 'غير مسموح لك بالوصول.');
        }

        $skillId = trim((string) $this->request->getPost('skill_id'));
        $status  = $this->request->getPost('status') === '1';

        $skills = $this->getSkillsList();
        if (isset($skills[$skillId])) {
            $skills[$skillId]['enabled'] = $status;
            $this->setSetting('mcp_skills_list', json_encode($skills, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $msg = $status ? "تم تفعيل مهارة '{$skills[$skillId]['title']}' بنجاح! ✅" : "تم تعطيل مهارة '{$skills[$skillId]['title']}' 🚫";
            return redirect()->back()->with('message', $msg);
        }

        return redirect()->back()->with('error', 'المهارة غير موجودة.');
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

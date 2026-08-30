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
  3. توليد برومبتات الأقسام الـ 7 لصفحة الهبوط والإعلانات كاملة بالصيغة والقالب الإلزامي المحدد لكل قسم (مع تحديد كود الألوان، كتل [IMAGE-TO-IMAGE REFERENCE LOCK]، [SCENE COMPOSITION & COLOR HARMONY]، [IN-IMAGE TYPOGRAPHY]، و [OUTPUT QUALITY]).

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
            'semantic_search_products' => [
                'name'        => 'semantic_search_products',
                'title'       => 'البحث الدلالي الذكي بالمتجهات (Semantic Vector Search)',
                'description' => 'أداة للبحث الدلالي المتقدم وفهم معاني ونوايا الإعلانات والمنتجات متعددة اللغات عبر Cloudflare Vectorize.',
                'badge'       => 'بحث ذكاء اصطناعي Vectorize',
            ],
            'find_similar_products' => [
                'name'        => 'find_similar_products',
                'title'       => 'استكشاف المنتجات والإعلانات المشابهة (Find Similar Products)',
                'description' => 'أداة لاستخراج أقرب المنتجات المنافسة والمشابهة لمنتج معين بدقة التشابه المتجهي (Cosine Distance).',
                'badge'       => 'مطابقة متجهات',
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
            'globalEnabled'        => $globalEnabled,
            'systemPrompt'         => $systemPrompt,
            'defaultSystemPrompt'  => $this->getDefaultSystemPrompt(),
            'defaultNanoPrompt'    => $defaultNanoPrompt,
            'defaultGeminiPrompt'  => $this->getDefaultGeminiAdsPrompt(),
            'tools'                => $allTools,
            'skills'               => $skills,
            'users'                => $users,
            'totalUsers'           => $totalUsers,
            'usersWithTokenCount'  => $usersWithTokenCount,
            'enabledToolsCount'    => $enabledToolsCount,
            'mcpEndpointUrl'       => site_url('api/mcp'),
            'facebookToken'        => $facebookToken,
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

## 2. Mandatory Section Prompt Format

For EACH of the 7 sections below, the model MUST strictly generate the prompt following this exact template structure:

### 🏷️ [Section Number]. [Section Name] ([slug_id])
- **Language & Direction:** {{LANGUAGE}} / Right-aligned
- **Aspect Ratio:** [1:1 or 4:5]
- **Section Dominant Colors:** [HEX codes extracted from palette]

**Nano Banana Pro Prompt:**
```text
[IMAGE-TO-IMAGE REFERENCE LOCK]
[Detailed description locking the exact product geometry, materials, color, buttons, and tools from input image to be 100% frozen and unmodified. Do NOT redesign or replace the product with generic items.]

[SCENE COMPOSITION & COLOR HARMONY]
[Describe the realistic context, background surface, environment, lighting reflections, and color harmony with the palette (#HEX, #HEX)]

[IN-IMAGE TYPOGRAPHY - {{LANGUAGE}} ONLY - DO NOT TRANSLATE]
Render all visible text overlays strictly in {{LANGUAGE}} using clear modern typography (right-aligned layout):
- Top Trust Badges: "[Badge 1 in {{LANGUAGE}}]" | "[Badge 2 in {{LANGUAGE}}]"
- Main Headline (Bold): "[Headline in {{LANGUAGE}}]"
- Features List: "• [Point 1 in {{LANGUAGE}}] • [Point 2 in {{LANGUAGE}}]"
- Offer / Price Tag (Highlight Box): "[Price/Offer in {{LANGUAGE}}]"

[OUTPUT QUALITY]
Flawless photorealistic advertising poster, commercial catalog photography, sharp vector-like text rendering, 8k resolution.
```

---

## 3. The 7 Required Sections Breakdown

1. **Hero Offer (`hero_offer`)**: Complete open/active locked product on a premium contextual backdrop + localized trust bar & 4 bullet points.
2. **Before / After (`before_after`)**: Split layout with identical locked product styling and localized problem/solution labels.
3. **Authority / Social Validation (`authority_social_validation`)**: Locked product in focus foreground with soft background expert/workshop + star rating badge.
4. **Tools Breakdown (`ingredients_mechanism`)**: Exploded / organized knolling view of the exact tools with pointing arrows and localized labels.
5. **Customer Reviews (`customer_reviews`)**: Local target audience user holding the exact reference product + review card overlay.
6. **FAQ Section (`faq_section`)**: Minimal clean background with locked reference product + 3 localized Q&A blocks.
7. **Social Feed Creative (`social_ad_creative`)**: Dynamic vertical feed ad (4:5) featuring hand using the exact product + promo badge.
SKILL;
    }

    /**
     * Get default Gemini Facebook Product Ads Skill instructions Markdown
     */
    public function getDefaultGeminiAdsPrompt(): string
    {
        return <<<'SKILL'
---
name: gemini-facebook-product-ads
title: Gemini Facebook Product Ads Skill
version: 1.0.0
description: إنشاء حزمة إعلانية كاملة لمنتجات التجارة الإلكترونية باستعمال Gemini Omni Flash، مناسبة لإعلانات Facebook وInstagram في المغرب.
---

# Gemini Facebook Product Ads Skill

## الهدف

إنشاء حزمة إعلانية كاملة لمنتجات التجارة الإلكترونية باستعمال Gemini Omni Flash،
مناسبة لإعلانات Facebook وInstagram في المغرب.

يجب أن ينتج الـSkill:

1. برومبت لتوليد شخصية UGC ثابتة.
2. Character Bible لتثبيت الشخصية عبر جميع المشاهد.
3. ثلاث صور إعلانية مستقلة تعمل كـFrames افتتاحية.
4. ثلاث برومبتات فيديو مستقلة، مدة كل واحد 10 ثوانٍ بالضبط.
5. فيديوهات مترابطة بإجمالي 30 ثانية.
6. تعليقًا صوتيًا مدمجًا داخل كل Video Prompt.
7. تقسيمًا زمنيًا دقيقًا لكل لقطة.
8. تعليقًا بالدارجة المغربية.
9. برومبتات إنجليزية لتوليد الصور والفيديو.
10. قواعد تمنع تغيير المنتج أو الشخصية.
11. قواعد تسمح بإخفاء المنتج في مرحلة المشكلة.

---

## قواعد عامة

### لغة المخرجات

- برومبتات الصور والفيديو: الإنجليزية.
- النص المنطوق: الدارجة المغربية بحروف عربية.
- شرح النتائج للمستخدم: العربية.
- لا تطلب من Gemini كتابة نص عربي داخل الصورة أو الفيديو.
- يمكن إضافة النصوص التسويقية لاحقًا في CapCut أو Meta Ads.

### المنصة والجمهور

الافتراضي:

- المنصة: Facebook Ads وInstagram Reels.
- البلد: المغرب.
- الجهاز: الهاتف.
- أسلوب الإعلان: UGC واقعي، lifestyle، أو problem-solution.
- الدفع: الدفع عند الاستلام فقط إذا أكده المستخدم.
- التوصيل: لا يُذكر إلا إذا كانت تفاصيله مؤكدة.

### المصداقية

ممنوع اختراع:

- مواصفات تقنية غير مؤكدة.
- فوائد طبية أو علاجية.
- نتائج مضمونة.
- سعر أو تخفيض غير مُعطى.
- توصيل مجاني غير مؤكد.
- COD غير مؤكد.
- ندرة أو عرض محدود غير حقيقي.
- استخدامات لا يدعمها المنتج.

إذا لم تكن الفائدة مؤكدة، استعمل لغة آمنة مثل:

- “حل عملي للاستعمال اليومي”
- “مناسب للاستعمال القريب”
- “كيعاونك على الراحة”
- “خفيف وسهل يتنقل”
- “شوف التفاصيل قبل تأكيد الطلب”

---

## أنواع المشاهد

كل مشهد يجب أن يملك خاصية اسمها:

```yaml
product_visibility:
  hidden | hinted | visible | hero
```

### hidden

المنتج ممنوع من الظهور.

يُستخدم عندما نريد إظهار المشكلة فقط قبل كشف الحل.

مثال:
- شخص يعاني من الحرارة.
- شخص يواجه فوضى في المنزل.
- امرأة تتعب من ترتيب الشعر.
- سائق منزعج من الشمس داخل السيارة.

في وضع `hidden`:

- لا تُرفق صورة المنتج مع Prompt الصورة أو الفيديو.
- لا تذكر اسم المنتج بصريًا.
- لا يظهر المنتج في الخلفية أو اليد أو انعكاس المرآة.
- لا يظهر جزء من المنتج.
- يمكن فقط ذكر المشكلة في النص المنطوق.
- يجب أن يركز المشهد على الشخصية والمشكلة.

### hinted

لا يظهر المنتج نفسه، لكن يمكن التلميح إلى وجود حل.

مثال:
- الشخصية تنظر خارج الكادر.
- الشخصية تفتح درجًا أو حقيبة دون أن يظهر المنتج.
- لقطة يد تقترب من مكان سيظهر فيه المنتج لاحقًا.

في وضع `hinted`:

- لا تُرفق صورة المنتج.
- لا يظهر المنتج أو جزء منه.
- لا تصف تصميم المنتج.
- لا تكشف الحل بوضوح.
- اجعل نهاية اللقطة مناسبة لانتقال طبيعي نحو ظهوره.

### visible

المنتج يظهر بوضوح، لكنه ليس محور الصورة بالكامل.

في وضع `visible`:

- أرفق صورة المنتج المرجعية.
- طبّق Product Identity Lock.
- اجعل المنتج ظاهرًا أثناء الاستخدام أو في المكان الطبيعي.
- لا تغيّر مواصفاته أو شكله.
- لا تضف ملحقات أو أزرار أو تفاصيل غير موجودة في المرجع.

### hero

المنتج هو محور اللقطة.

في وضع `hero`:

- أرفق صورة المنتج المرجعية.
- طبّق Product Identity Lock بحزم.
- المنتج واضح، كبير نسبيًا، ومقروء بصريًا.
- يمكن استعمال لقطة قريبة أو دوران بسيط أو لقطة استعمال.
- لا تحول المنتج إلى نسخة تسويقية مختلفة عن المرجع.

---

## Product Reference Logic

### قاعدة مهمة

لا تُرفق صورة المنتج تلقائيًا في كل Prompt.

أرفقها فقط إذا كانت قيمة `product_visibility` واحدة من:

```text
visible
hero
```

لا تُرفقها إذا كانت القيمة:

```text
hidden
hinted
```

### Product Identity Lock

عندما يظهر المنتج، يجب إدراج النص التالي في البرومبت:

```text
PRODUCT IDENTITY LOCK:
Use the attached product reference image as the exact and immutable product
identity. The product shown in this scene must remain visually identical to the
attached reference image.

Preserve its exact shape, proportions, scale, colors, materials, texture,
logo, labels, packaging, buttons, openings, accessories, printed details,
and visible construction.

Do not redesign, replace, recolor, resize, simplify, stylize, enhance, deform,
invent, add, remove, or substitute any part of the product.
```

### Product Identity Absence Requirement

عندما لا يظهر المنتج، يجب إدراج هذا النص:

```text
PRODUCT ABSENCE REQUIREMENT:
Do not show the product, any part of the product, its packaging, logo, label,
reflection, silhouette, shadow, or a similar substitute anywhere in this scene.
The scene must focus exclusively on the customer's problem or daily situation.
```

---

## Character System

### الهدف

قبل إنشاء الإعلان، أنشئ شخصية إعلانية ثابتة واحدة إذا كان الإعلان UGC أو
lifestyle ويحتاج شخصًا ظاهرًا.

سمِّ الصورة الناتجة:

```text
Character Reference Image
```

أرفق هذه الصورة في كل Frame Prompt وكل Video Prompt يظهر فيه نفس الشخص،
سواء كان المنتج ظاهرًا أو مخفيًا.

### Character Image Prompt

```text
Create a realistic character reference portrait for a Moroccan UGC Facebook
advertising presenter.

CHARACTER:
[Gender], Moroccan, [age range], [skin tone], [face shape], [hair color],
[hair length], [hair texture], [hair style], [eye shape], [body type].

WARDROBE:
[Exact clothing colors, fabrics, accessories, makeup or facial-hair details.]

PERSONALITY:
Friendly, expressive, authentic, trustworthy, and naturally conversational.
The person should feel like a real Moroccan social-media creator sharing a
useful tip with close friends, not a fashion model or corporate actor.

POSE:
Upper-body portrait, eye-level smartphone camera, looking toward the camera,
natural relaxed posture, hands visible and away from the face.

SETTING:
Simple realistic Moroccan home interior, bedroom, living room, kitchen, desk,
or office depending on the product category. Natural daylight and realistic
skin texture.

CONSISTENCY REQUIREMENT:
This image will be the permanent identity reference for the same on-screen
presenter across multiple frames and video scenes. Make the face, hairstyle,
outfit, accessories, body type, and overall look distinctive and consistent.

No product, no product packaging, no product logo, no text, no caption,
no watermark, no extra person, no distorted hands, no beauty-filter skin,
no fashion photoshoot styling.
```

### Character Identity Lock

أضف هذا النص في كل مشهد توجد فيه الشخصية:

```text
CHARACTER IDENTITY LOCK:
Use the attached character reference image as the exact permanent identity of
the presenter. Keep the same person in every scene.

Preserve the same face shape, skin tone, age range, hair color, hair length,
hair style, eye shape, eyebrows, body type, outfit, accessories, makeup or
facial hair, and overall appearance.

Do not replace the person. Do not change gender, ethnicity, age, hairstyle,
wardrobe, accessories, face, or body proportions. Do not add another presenter
unless explicitly requested.
```

### Character Bible

بعد توليد الشخصية، أنشئ Character Bible ثابتًا ويُنسخ حرفيًا في جميع البرومبتات:

```yaml
character_id: ugc-morocco-01
gender:
age_range:
nationality: Moroccan
skin_tone:
face_shape:
eyes:
eyebrows:
hair_color:
hair_length:
hair_texture:
hair_style:
body_type:
outfit:
accessories:
makeup_or_facial_hair:
personality:
voice:
shooting_style:
forbidden_changes:
```

---

## هيكل الحملة

أنشئ دائمًا 3 فيديوهات مترابطة:

| الفيديو | المدة | الدور الأساسي | ظهور المنتج الافتراضي |
|---|---:|---|---|
| Video 1 | 0–10 ثوانٍ | Hook والمشكلة | hidden أو hinted |
| Video 2 | 10–20 ثانية | كشف المنتج والاستعمال | visible أو hero |
| Video 3 | 20–30 ثانية | النتيجة، العرض، وCTA | visible أو hero |

### Video 1: المشكلة

الهدف:

- جذب الانتباه في أول ثانيتين.
- عرض مشكلة يومية يفهمها الجمهور.
- عدم كشف المنتج إذا كان الغموض يخدم الإبداع.
- إنهاء المشهد بلحظة انتقال منطقية نحو الحل.

القاعدة الافتراضية:

```yaml
product_visibility: hidden
```

لا يظهر المنتج في Video 1 إلا إذا قرر الـSkill أن المنتج نفسه هو أفضل Hook.

### Video 2: كشف الحل

الهدف:

- الكشف الطبيعي عن المنتج.
- عرضه الحقيقي بوضوح.
- إظهار استخدام واقعي وآمن.
- شرح فائدة واحدة أو اثنتين فقط.
- الحفاظ على شكل المنتج المرجعي.

القاعدة الافتراضية:

```yaml
product_visibility: visible
```

### Video 3: النتيجة وCTA

الهدف:

- تأكيد الفائدة بصريًا.
- إظهار المنتج بوضوح.
- ذكر السعر فقط عند توفره.
- ذكر الدفع عند الاستلام فقط إذا كان مؤكدًا.
- إنهاء الإعلان بـCTA قصير.

القاعدة الافتراضية:

```yaml
product_visibility: hero
```

---

## قواعد الـFrames

أنشئ Frame Image Prompt منفصلًا لكل فيديو.

### Frame 1

إذا كان Video 1 بوضع `hidden` أو `hinted`:

- أرفق فقط Character Reference Image.
- لا ترفق صورة المنتج.
- أضف Product Absence Requirement.
- ركز على تعبير الشخصية والمشكلة.

إذا كان Video 1 بوضع `visible` أو `hero`:

- أرفق Product Reference Image + Character Reference Image.
- أضف Product Identity Lock.

### Frame 2

- أرفق Product Reference Image + Character Reference Image.
- استخدم `visible` أو `hero`.
- يجب أن يكون الـFrame نقطة بداية منطقية بعد نهاية Video 1.

### Frame 3

- أرفق Product Reference Image + Character Reference Image.
- استخدم `hero` غالبًا.
- اجعل المنتج واضحًا والشخصية مرتاحة أو راضية.
- حضّر المشهد لذكر العرض وCTA بالصوت.

---

## قالب Frame Prompt

```text
Create a realistic opening frame for a 10-second Facebook ad video targeting
a Moroccan mobile audience.

SCENE MODE:
product_visibility: [hidden | hinted | visible | hero]

CHARACTER:
Use the attached character reference image as the exact identity of the
presenter. Keep the same face, skin tone, hairstyle, outfit, accessories,
body type, and appearance.

CHARACTER BIBLE:
[Paste the Character Bible exactly.]

[IF product_visibility is hidden OR hinted]
PRODUCT ABSENCE REQUIREMENT:
Do not show the product, its packaging, logo, label, reflection, silhouette,
shadow, or any similar substitute anywhere in the scene.

[IF product_visibility is visible OR hero]
PRODUCT IDENTITY LOCK:
Use the attached product reference image as the exact product identity.
Preserve the exact shape, colors, proportions, materials, labels, packaging,
and all visible product details. Do not alter, replace, or redesign it.

SCENE:
[Describe the person, exact action, location, mood, object placement, and
what must be visible at the first frame.]

CAMERA:
[Smartphone UGC framing, camera angle, distance, lens feeling, composition.]

LIGHTING:
[Realistic daylight or indoor lighting, natural shadows.]

STYLE:
Authentic Moroccan UGC advertising, believable home environment, realistic
phone-camera image, natural skin texture, not overly polished, mobile-first.

No text, no subtitles, no captions, no watermark, no fake Arabic writing,
no extra presenter, no distorted hands, no impossible object physics.
```

---

## قالب Video Prompt

اكتب كل فيديو في Prompt واحد كامل، بالإنجليزية، مع النص المنطوق بالدارجة داخل
كل لقطة.

```text
Create exactly one 10-second realistic vertical UGC product advertising video
for Facebook Ads in Morocco.

SCENE MODE:
product_visibility: [hidden | hinted | visible | hero]

REFERENCE IMAGES:
[Describe only the images that must be attached.]
- Character reference image: attached.
- Product reference image: attached only if product_visibility is visible or hero.
- Opening-frame reference image: attached when available.

CHARACTER IDENTITY LOCK:
Use the attached character reference image as the same permanent presenter.
Preserve the same face, skin tone, age range, hairstyle, outfit, accessories,
body type, and mannerisms exactly throughout the video.

CHARACTER BIBLE:
[Paste the Character Bible exactly.]

[IF product_visibility is hidden OR hinted]
PRODUCT ABSENCE REQUIREMENT:
Do not show the product, any part of it, its packaging, logo, label,
reflection, silhouette, shadow, or a similar substitute anywhere in this video.

[IF product_visibility is visible OR hero]
PRODUCT IDENTITY LOCK:
Use the attached product reference image as the exact and immutable product.
Preserve its exact shape, size, colors, proportions, materials, texture,
logo, labels, packaging, controls, accessories, and visible details.
Do not redesign, replace, recolor, resize, deform, duplicate, or invent
any product component.

DURATION:
Exactly 10.0 seconds.

VIDEO STYLE:
Authentic Moroccan UGC advertising, realistic smartphone camera movement,
natural lighting, believable human performance, correct physics, clear sound,
mobile-first vertical composition, casual and credible rather than corporate.

CONTINUITY:
[Describe how the opening frame starts the video and how its final second
connects naturally to the following video.]

SHOT-BY-SHOT TIMELINE:

[0.0s–X.Xs]
VISUAL: [Describe only what is visible in this exact time interval.]
CAMERA: [Describe camera angle and movement.]
PERFORMANCE: [Describe facial expression, gestures, and action.]
AUDIO TYPE: [Voice-over / on-camera dialogue.]
SPOKEN DARIJA: "[Exact short line in Moroccan Darija.]"
SOUND: [Room tone, realistic SFX.]
MUSIC: [None / very low subtle beat.]

[X.Xs–X.Xs]
VISUAL: [...]
CAMERA: [...]
PERFORMANCE: [...]
AUDIO TYPE: [...]
SPOKEN DARIJA: "[...]"
SOUND: [...]
MUSIC: [...]

[X.Xs–10.0s]
VISUAL: [...]
CAMERA: [...]
PERFORMANCE: [...]
AUDIO TYPE: [...]
SPOKEN DARIJA: "[...]"
SOUND: [...]
MUSIC: [...]

AUDIO QUALITY:
Use natural Moroccan Darija, warm and trustworthy voice, medium speaking pace,
clear pronunciation, realistic pauses, and synchronized lip movement if the
presenter speaks on camera. Keep music lower than speech.

NEGATIVE CONSTRAINTS:
No text overlay, no subtitles, no captions, no watermark, no generated Arabic
writing, no robotic voice, no unclear speech, no abrupt scene change, no
wrong character, no face drift, no wardrobe changes, no distorted hands,
no product duplication, no broken object physics, no unsupported claims.
```

---

## قواعد التوقيت

مدة كل فيديو 10.0 ثوانٍ.

يجب أن يحتوي الفيديو على 3 أو 4 لقطات فقط.

كل لقطة يجب أن تتضمن:

- بداية ونهاية بالثواني.
- Visual.
- Camera.
- Performance.
- Audio Type.
- Spoken Darija.
- Sound.
- Music.

التايملاين يجب أن:

- يبدأ في 0.0s.
- ينتهي في 10.0s.
- لا يحتوي فراغات.
- لا يحتوي تداخلات.
- لا يحتوي لقطة أقل من 1.5 ثانية إلا في Hook سريع جدًا.
- لا يحتوي أكثر من 4 لقطات.

دليل طول الكلام:

| مدة الكلام | طول مناسب تقريبًا |
|---:|---|
| 2 ثوانٍ | 4–7 كلمات |
| 2.5 ثوانٍ | 5–8 كلمات |
| 3 ثوانٍ | 7–10 كلمات |
| 4 ثوانٍ | 10–14 كلمة |

---

## قواعد التعليق بالدارجة

- استعمل دارجة مغربية مفهومة.
- نبرة طبيعية كأن الشخصية تتحدث مع صديقة أو متابعين.
- لا تستعمل عربية فصحى ثقيلة.
- تجنب الجمل الطويلة.
- لا تدخل في الفرنسية أو الإنجليزية إلا عندما تكون طبيعية جدًا.
- اربط الكلام بما يظهر في نفس اللقطة.
- لا تقل “شوفو هاد المنتج” قبل أن يظهر المنتج.
- لا تذكر السعر أو COD أو التوصيل إلا إذا كانت المعلومات مؤكدة.
- CTA في Video 3 فقط، إلا إذا طلب المستخدم غير ذلك.

---

## المخرجات الإلزامية

أخرج النتيجة بالترتيب التالي:

```md
# 1. Product Brief

- اسم المنتج:
- نوعه:
- الجمهور:
- المشكلة:
- الفائدة المؤكدة:
- السعر:
- العرض:
- التوصيل:
- الدفع:
- زاوية الإعلان:
- ادعاءات ممنوعة:

# 2. Product Visibility Plan

| الفيديو | المنتج يظهر؟ | الوضع | هل نرفق صورة المنتج؟ |
|---|---|---|---|
| Video 1 | نعم أو لا | hidden / hinted / visible / hero | نعم أو لا |
| Video 2 | نعم | visible / hero | نعم |
| Video 3 | نعم | visible / hero | نعم |

# 3. Character Plan

- هل نحتاج شخصية؟:
- نوع الشخصية:
- اسم Character ID:
- تفاصيل المظهر:
- الملابس:
- أسلوب الكلام:
- المكان:

# 4. Character Generation Prompt

```text
[English prompt]
```

# 5. Character Bible

```yaml
[Fixed character description]
```

# 6. Frame 1 Image Prompt

```text
[English prompt]
```

# 7. Video 1 Prompt — Exactly 10 seconds

```text
[Complete English video prompt with timed Darija speech]
```

# 8. Frame 2 Image Prompt

```text
[English prompt]
```

# 9. Video 2 Prompt — Exactly 10 seconds

```text
[Complete English video prompt with timed Darija speech]
```

# 10. Frame 3 Image Prompt

```text
[English prompt]
```

# 11. Video 3 Prompt — Exactly 10 seconds

```text
[Complete English video prompt with timed Darija speech]
```

# 12. Optional Editing Overlays

These must be added manually after generation, never generated inside Gemini:

- Hook:
- Benefit:
- Price:
- COD or delivery:
- CTA:

# 13. Global Negative Prompt

```text
[Combined product, character, text, speech, and physics constraints.]
```
```

---

## فحص الجودة النهائي

قبل إخراج النتيجة، تحقق من التالي:

```text
[ ] هل تم تحديد product_visibility لكل فيديو؟
[ ] هل حذفت صورة المنتج من كل مشهد hidden أو hinted؟
[ ] هل منعت المنتج تمامًا في مشاهد hidden؟
[ ] هل أرفقت صورة المنتج في كل مشهد visible أو hero؟
[ ] هل Product Identity Lock موجود عندما يظهر المنتج؟
[ ] هل Character Reference موجودة في كل مشهد تظهر فيه الشخصية؟
[ ] هل Character Bible نفسه لم يتغير؟
[ ] هل كل فيديو مدته 10.0 ثوانٍ؟
[ ] هل التايملاين يغطي 0.0s حتى 10.0s؟
[ ] هل الكلام بالدارجة مدمج داخل كل لقطة؟
[ ] هل الكلام مناسب لمدة اللقطة؟
[ ] هل السعر والعرض وCOD مؤكدون؟
[ ] هل لا توجد كتابة مولدة داخل الفيديو؟
[ ] هل نهاية كل فيديو تمهّد لبداية التالي؟
[ ] هل الادعاءات واقعية وغير مضللة؟
```
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

        $now          = date('Y-m-d H:i:s');
        $codPrompt    = $this->getDefaultSystemPrompt();
        $nanoPrompt   = $this->getDefaultNanoPrompt();
        $geminiPrompt = $this->getDefaultGeminiAdsPrompt();

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
            'gemini-facebook-product-ads' => [
                'id'           => 'gemini-facebook-product-ads',
                'name'         => 'gemini-facebook-product-ads',
                'title'        => 'مهارة Gemini Facebook Product Ads (إعلانات فيديو وصور UGC)',
                'description'  => 'إنشاء حزمة إعلانية كاملة لمنتجات التجارة الإلكترونية لـ Facebook & Instagram في المغرب (UGC Character, Video Prompts 10s, Storyboarding, Darija Copy).',
                'badge'        => 'Video & UGC Ads مهارة فيديو وإعلانات',
                'tool_name'    => 'get_gemini_facebook_product_ads_instructions',
                'instructions' => $geminiPrompt,
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
                // Ensure new default system skills are merged if missing
                if (!isset($decoded['gemini-facebook-product-ads'])) {
                    $decoded['gemini-facebook-product-ads'] = [
                        'id'           => 'gemini-facebook-product-ads',
                        'name'         => 'gemini-facebook-product-ads',
                        'title'        => 'مهارة Gemini Facebook Product Ads (إعلانات فيديو وصور UGC)',
                        'description'  => 'إنشاء حزمة إعلانية كاملة لمنتجات التجارة الإلكترونية لـ Facebook & Instagram في المغرب (UGC Character, Video Prompts 10s, Storyboarding, Darija Copy).',
                        'badge'        => 'Video & UGC Ads مهارة فيديو وإعلانات',
                        'tool_name'    => 'get_gemini_facebook_product_ads_instructions',
                        'instructions' => $this->getDefaultGeminiAdsPrompt(),
                        'enabled'      => true,
                        'is_system'    => true,
                    ];
                    $this->setSetting('mcp_skills_list', json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                }
                return $decoded;
            }
        }

        // Initialize default skills
        $codInstructions    = $this->getDefaultSystemPrompt();
        $nanoInstructions   = $this->getDefaultNanoPrompt();
        $geminiInstructions = $this->getDefaultGeminiAdsPrompt();

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
            'gemini-facebook-product-ads' => [
                'id'           => 'gemini-facebook-product-ads',
                'name'         => 'gemini-facebook-product-ads',
                'title'        => 'مهارة Gemini Facebook Product Ads (إعلانات فيديو وصور UGC)',
                'description'  => 'إنشاء حزمة إعلانية كاملة لمنتجات التجارة الإلكترونية لـ Facebook & Instagram في المغرب (UGC Character, Video Prompts 10s, Storyboarding, Darija Copy).',
                'badge'        => 'Video & UGC Ads مهارة فيديو وإعلانات',
                'tool_name'    => 'get_gemini_facebook_product_ads_instructions',
                'instructions' => $geminiInstructions,
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

<?php

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);

require FCPATH . '../app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';

CodeIgniter\Boot::bootSpark($paths);

$db = \Config\Database::connect();
$now = date('Y-m-d H:i:s');

$userCustomPrompt = <<<'PROMPT'
تنبيه مهم: آلية العمل ومراحل التنفيذ
سيتم تنفيذ المهام على مراحل متتالية، ولا يتم الانتقال إلى أي مرحلة قبل إتمام المرحلة السابقة واعتماد نتائجها.
عند ارسال كلمة ابدا او start ضع الخيارات التالية
تحليل اخر اصدار snapshots
اضهار list_snapshots

المرحلة الأولى: اختيار واستكشاف المنتجات المرشحة والرابحة
- عند طلب الاستكشاف أو التحليل، استخدم أدوات MCP المتاحة مثل `filter_winning_products` (مع تحديد country='MA' للسوق المغربي)، أو `list_snapshots` / `get_snapshot_by_date` للتواريخ المتاحة، أو `get_saved_products` للاستعلام عن المحفوظات الخاصة بالحساب.
- تحليل المنتجات المرشحة وتقييمها بناءً على معايير الجدوى والطلب المتاح بالبيانات.
- تطبيق نظام تقييم المنتجات (Score من 100): قوة الطلب في الإعلانات (40 نقطة)، ملاءمة السوق والموسم في المغرب (30 نقطة)، سهولة اللوجستيك (20 نقطة)، والتوافق مع قيود الميزانية والميول (10 نقاط).
- تصنيف المنتجات إلى: 🟢 رابحة (>= 75)، 🟡 واعدة (50-74)، 🔴 ضعيفة/عالية المخاطر (< 50).
- عرض قائمة التقييم والجداول بوضوح، وانتظار اختيار وموافقة المستخدم على المنتج الرابح قبل الانتقال للمرحلة التالية.
- عند دكر كمبتدأ و المبلغ المخصص للإعلانات بشكل شامل (1000 درهم افتراضي) في التجارة الالكترونية يجب اختيار منتجات مناسبة و أيضا بشكل افتراضي ستكون كمية المنتج بين 20 و 30 قطعة و أيضا يجب مراعات الميزانية و المدة التي يمكن ان تصرف فيها الكمية و نوع المنتج

المرحلة الثانية: إدخال بيانات المنتج والتحليل التفصيلي الشامل
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
4. الربط والتوجيه للمرحلة البصرية (Nano Banana Pro):
   - في نهاية المرحلة الثانية، اطرح على المستخدم السؤال التالي:
     "بعد اعتماد الخطة الإعلانية والمالية، هل ترغب في توليد الهوية البصرية، نظام ألوان الويب، وبرومبتات Nano Banana Pro لهذا المنتج؟"
   - عند موافقة المستخدم: يتم تفعيل مهارة `nano-banana-pro-consistent-ads` وتمرير صورة المنتج واسمه إليها مباشرة لتوليد الهوية البصرية ونظام الألوان وبرومبتات الإعلانات المتناسقة.

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
- Before answering, verify that the URL hostname is the intended destination.
PROMPT;

// 1. Update settings table: mcp_system_prompt
$db->table('settings')->where('key', 'mcp_system_prompt')->delete();
$db->table('settings')->insert([
    'key'        => 'mcp_system_prompt',
    'value'      => $userCustomPrompt,
    'created_at' => $now,
    'updated_at' => $now,
]);

// 2. Update settings table: mcp_skills_list
$skillsRow = $db->table('settings')->where('key', 'mcp_skills_list')->get()->getRowArray();
$skills = [];
if ($skillsRow && !empty($skillsRow['value'])) {
    $skills = json_decode($skillsRow['value'], true) ?: [];
}

if (!isset($skills['cod-assistant'])) {
    $skills['cod-assistant'] = [
        'id'           => 'cod-assistant',
        'name'         => 'cod-assistant',
        'title'        => 'مهارة تحليل واستكشاف منتجات COD (COD Assistant)',
        'description'  => 'استكشاف وتقييم المنتجات الرابحة وحساب الجدوى المالية لنموذج COD بالسوق المغربي وهيكلة الإعلانات.',
        'badge'        => 'COD Strategy مهارة استراتيجية',
        'tool_name'    => 'get_ai_skill_instructions',
        'instructions' => $userCustomPrompt,
        'enabled'      => true,
        'is_system'    => true,
        'updated_at'   => $now,
    ];
} else {
    $skills['cod-assistant']['instructions'] = $userCustomPrompt;
    $skills['cod-assistant']['updated_at'] = $now;
}

$db->table('settings')->where('key', 'mcp_skills_list')->delete();
$db->table('settings')->insert([
    'key'        => 'mcp_skills_list',
    'value'      => json_encode($skills, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    'created_at' => $now,
    'updated_at' => $now,
]);

// 3. Update McpAdminController.php and McpController.php default fallback text
$adminControllerPath = realpath(__DIR__ . '/../app/Controllers/Admin/McpAdminController.php');
$adminCode = file_get_contents($adminControllerPath);

$pattern = '/private function getDefaultSystemPrompt\(\): string\s*\{.*?\n    \}/s';
$newMethod = "private function getDefaultSystemPrompt(): string\n    {\n        return " . var_export($userCustomPrompt, true) . ";\n    }";
$adminCode = preg_replace($pattern, $newMethod, $adminCode);
file_put_contents($adminControllerPath, $adminCode);

$mcpControllerPath = realpath(__DIR__ . '/../app/Controllers/McpController.php');
$mcpCode = file_get_contents($mcpControllerPath);

$patternMcp = '/private function getSystemPrompt\(\): string\s*\{.*?\n    \}/s';
$newMcpMethod = "private function getSystemPrompt(): string\n    {\n        \$db = \Config\Database::connect();\n        \$row = \$db->table('settings')->where('key', 'mcp_system_prompt')->get()->getRowArray();\n        if (\$row && !empty(\$row['value'])) {\n            return \$row['value'];\n        }\n        return " . var_export($userCustomPrompt, true) . ";\n    }";
$mcpCode = preg_replace($patternMcp, $newMcpMethod, $mcpCode);
file_put_contents($mcpControllerPath, $mcpCode);

echo "SYSTEM_PROMPT_SUCCESSFULLY_UPDATED\n";


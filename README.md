# 📊 Products Analytics Dashboard & MCP AI Platform

[![CodeIgniter 4](https://img.shields.io/badge/Framework-CodeIgniter%204.5+-red.svg)](https://codeigniter.com)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%20%7C%208.3-blue.svg)](https://php.net)
[![MCP Protocol](https://img.shields.io/badge/MCP-JSON--RPC%202.0%20%2F%20SSE-purple.svg)](https://modelcontextprotocol.io)
[![Vector Search](https://img.shields.io/badge/AI-Cloudflare%20Vectorize-orange.svg)](https://developers.cloudflare.com/vectorize/)
[![Test Suite](https://img.shields.io/badge/Unit%20Tests-23%20Passed%20(100%25)-brightgreen.svg)]()

منصة استخباراتية متقدمة لتحليل واكتشاف المنتجات الرابحة في التجارة الإلكترونية والدفع عند الاستلام (COD)، مع أدوات التجسس على إعلانات فيسبوك، والبحث الدلالي الفيكتوري بالذكاء الاصطناعي، وخادم بروتوكول **Model Context Protocol (MCP)** لربط نماذج الذكاء الاصطناعي الخارجية (مثل Claude وGemini وCursor) ببيانات المنصة اللحظية.

---

## 🌟 الميزات الرئيسية (Key Features)

### 1. 🛍️ استكشاف وتحليل المنتجات الرابحة (Product Intelligence)
- تصفية المنتجات حسب الدولة، عدد الإعلانات النشطة، متوسط عدد الكرياتيف، والسعر.
- خوارزميات رصد المنتجات الصاعدة والزوايا الإعلانية المربحة.
- محاكاة دقيقة لحساب اقتصاديات الوحدة لمنتجات الـ COD (تكاليف الشحن، التوصيل، ونسب الاسترجاع).

### 2. 🎯 التجسس الإعلاني (Facebook Ads Intelligence)
- بحث مباشر في مكتبة إعلانات فيسبوك (Facebook Ads Library) بحسب العلامة التجارية أو الكلمات المفتاحية.
- تحليل العناصر الإبداعية (الفيديوهات والصور والنصوص والخطافات الإعلانية Hooks).
- اكتشاف المنافسين النشطين وتوليد تقارير تسويقية استخباراتية شاملة.

### 3. 🔍 البحث الدلالي الفيكتوري بالذكاء الاصطناعي (Semantic Vector Search)
- مدعوم بواسطة **Cloudflare Vectorize** ونماذج التضمين اللغوي.
- فهم استفسارات البحث بمختلف اللغات واللهجات (العربية، الفرنسية، والإنجليزية) واستخراج المنتجات والنيتشات المتشابهة دلالياً.

### 4. 🤖 خادم بروتوكول الذكاء الاصطناعي (MCP Server)
- متوافق مع معايير **JSON-RPC 2.0** و **Server-Sent Events (SSE)**.
- يتيح لـ Claude Desktop، Cursor IDE، ووكلاء الذكاء الاصطناعي المخصصين استدعاء أدوات المنصة مباشرة.
- سجل أدوات معياري (`ToolRegistry` & `ToolInterface`) يدعم تفعيل وتعطيل الأدوات ديناميكياً من لوحة التحكم.

### 5. 🏢 دعم التعددية السحابية وعزل المستأجرين (Multi-Tenancy SaaS)
- عزل كامل لبيانات مساحات العمل والمحفوظات والملاحظات لكل مستأجر عبر `TenantContext` و `TenantModel`.
- نظام متطور لإدارة الحصص وحدود الاستخدام اليومية لكل مساحة عمل عبر `QuotaManager`.

### 6. ⚡ إدارة الأداء وضغط البيانات والمهام في الخلفية
- مشغل مهام غير متزامنة (`BackgroundTaskRunner` وأمر `php spark task:async`) لتشغيل المزامنة والفهرسة في الخلفية.
- نظام ضغط تلقائي للقطات البيانات (`SnapshotStorageHelper`) يوفر أكثر من **80%** من مساحة التخزين مع الحفاظ على التوافق التام.
- محرك تخزين مؤقت خفيف وفائق السرعة (`ResponseCache`).

---

## 🏗️ المعمارية البرمجية النظيفة (Clean Architecture)

تم بناء المشروع وفق معايير برمجية صارمة لضمان سهولة الصيانة وقابلية التوسع:

```
app/
 ├── Controllers/
 │    ├── Products.php                    # متحكم المنتجات الأساسي النظيف (< 400 سطر)
 │    ├── McpController.php               # خادم الـ MCP ونقطة نهاية JSON-RPC
 │    └── Traits/                         # تقسيم الوظائف إلى وحدات معيارية
 │         ├── SavedAdsTrait.php          # المحفوظات، المجموعات، المراقبة
 │         ├── SnapshotTrait.php          # إدارة واستعادة وضغط اللقطات
 │         ├── AiAnalysisTrait.php        # تحليلات واستراتيجيات الذكاء الاصطناعي
 │         ├── VectorizeTrait.php         # البحث الدلالي والفيكتوري
 │         ├── SyncTrait.php              # المزامنة الخارجية واستيراد tRPC
 │         └── SettingsTrait.php          # الإعدادات وتنظيف البيانات
 ├── Libraries/
 │    ├── Mcp/                            # منظومة بروتوكول الـ MCP
 │    │    ├── ToolInterface.php          # الواجهة المعيارية للأدوات
 │    │    ├── ToolRegistry.php           # سجل الأدوات المركزي وإدارة الصلاحيات
 │    │    └── Tools/                     # فئات الأدوات المتخصصة
 │    ├── Ai/PromptBuilder.php            # بناء قوالب البرومبتات الذكية
 │    ├── SaaS/QuotaManager.php           # إدارة ومراقبة حصص المستأجرين
 │    ├── Storage/SnapshotStorageHelper.php # ضغط وفك ضغط اللقطات (Gzip/Base64)
 │    ├── Cache/ResponseCache.php         # محرك الـ Cache للاستجابات السريعة
 │    └── Queue/BackgroundTaskRunner.php  # مشغل المهام غير المتزامنة
```

---

## 🚀 متطلبات التشغيل والتثبيت (Installation & Setup)

### 1. المتطلبات الأساسية
- **PHP:** 8.2 أو 8.3
- **PHP Extensions:** `intl`, `mbstring`, `curl`, `json`, `sqlite3` أو `pdo_pgsql`
- **Composer**

### 2. خطوات التثبيت

```bash
# 1. تثبيت الحزم والمكتبات
composer install

# 2. إنشاء ملف الإعدادات
cp env .env

# 3. تعديل إعدادات قاعدة البيانات ومفاتيح الـ API في ملف .env:
# database.default.hostname = localhost
# database.default.database = products_dashboard
# CLOUDFLARE_API_KEY = your_key_here

# 4. تنفيذ الهجرات (Migrations)
php spark migrate

# 5. تشغيل السيرفر المحلي
php spark serve
```

سيكون التطبيق متاحاً على: `http://localhost:8080`

---

## 💻 أوامر Spark CLI المتاحة (CLI Commands)

| الأمر | الوصف |
| :--- | :--- |
| `php spark task:async sync:data` | تشغيل مزامنة البيانات الخارجية في الخلفية كعملية غير متزامنة. |
| `php spark task:async vectorize:index` | تشغيل فهرسة المتجهات الفيكتورية على Cloudflare في الخلفية. |
| `php spark task:async --list` | استعراض قائمة المهام الجارية والمنفذة في الخلفية وحالاتها. |
| `php spark task:async --status=<task_id>` | فحص حالة وسجلات مهمة محددة. |
| `php spark sync:data` | تشغيل المزامنة المباشرة. |
| `php spark vectorize:index --limit=100` | فهرسة متجهات المنتجات بالـ CLI مباشرة. |

---

## 🧪 حزمة الاختبارات الآلية (Testing Suite)

يحتوي المشروع على حزمة اختبارات شاملة مبنية بـ **PHPUnit**:

```bash
# تشغيل كافة اختبارات الوحدة (Unit Tests)
.\vendor\bin\phpunit tests/unit/
```

### نتائج الاختبارات:
```text
PHPUnit 10.5.63 Runtime: PHP 8.3.6
.......................                                           23 / 23 (100%)
Tests: 23, Assertions: 160 -> OK (100% Passed)
```

---

## 🤖 ربط خادم الـ MCP مع عملاء الذكاء الاصطناعي (MCP Integration)

لربط الخادم مع **Claude Desktop**، أضف الإعدادات التالية إلى `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "products-analytics": {
      "command": "npx",
      "args": [
        "-y",
        "mcp-remote-client",
        "https://your-domain.com/api/mcp?token=YOUR_API_TOKEN"
      ]
    }
  }
}
```

> 📖 للاطلاع على الدليل الشامل لربط Cursor و Python و REST API، راجع ملف [`MCP_DEVELOPER_GUIDE.md`](file:///C:/Users/Faical/.gemini/antigravity/worktrees/products-analytics-dashboard/support_multi_version_mcp/MCP_DEVELOPER_GUIDE.md).

---

## 🔒 الأمان وحماية البيانات (Security & Safe Policies)

- **حماية جلسات الإدارة والمحاكاة:** منع تسريب الجلسات الإدارية أثناء استخدام خاصية الـ Impersonation.
- **عزل المستأجرين:** التحقق الصارم من `tenant_id` لمنع تداخل البيانات بين المستخدمين والشركات.
- **سياسة Git الآمنة:** جميع التعديلات والاختبارات تتم محلياً ولا يتم رفع الكود تلقائياً.

---

## 📄 الترخيص (License)

تم تطوير هذا المشروع للاستخدام الخاص والتجاري وفق أعلى معايير الجودة والأداء.

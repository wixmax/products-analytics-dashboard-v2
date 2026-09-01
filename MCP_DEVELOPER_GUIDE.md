# دليل المطورين لربط خادم بروتوكول MCP (Developer Integration Guide)

يوفر خادم **Model Context Protocol (MCP)** في نظام **Products Analytics Dashboard** نقطة نهاية موحدة ومتوافقة مع معايير **JSON-RPC 2.0** و **Server-Sent Events (SSE)**، مما يتيح لنماذج الذكاء الاصطناعي (مثل Claude، Gemini، Cursor، ووكلاء AI المخصصين) استدعاء أدوات التجسس الإعلاني، البحث الفيكتوري، والتحليلات التسويقية مباشرة.

---

## 1. معلومات نقطة النهاية (Endpoint & Authentication)

| الخاصية | القيمة |
| :--- | :--- |
| **Endpoint URL** | `https://your-domain.com/api/mcp` |
| **Methods** | `GET` (Discovery / SSE Stream) , `POST` (JSON-RPC 2.0 Calls) |
| **طرق المصادقة المدعومة** | 1. Header: `Authorization: Bearer <API_TOKEN>`<br>2. Header: `X-API-Key: <API_TOKEN>`<br>3. Query Param: `?token=<API_TOKEN>` |

---

## 2. التهيئة السريعة لـ Claude Desktop (`claude_desktop_config.json`)

أضف الإعدادات التالية إلى ملف `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "products-analytics": {
      "command": "npx",
      "args": [
        "-y",
        "mcp-remote-client",
        "https://your-domain.com/api/mcp?token=YOUR_API_TOKEN_HERE"
      ]
    }
  }
}
```

---

## 3. التهيئة لـ Cursor IDE / Antigravity / Cline

في إعدادات MCP في محرر الكود (Cursor Settings > Features > MCP):

- **Type:** `SSE` أو `HTTP`
- **Name:** `ProductsAnalyticsMCP`
- **Server URL:** `https://your-domain.com/api/mcp?token=YOUR_API_TOKEN`

---

## 4. أمثلة استدعاءات JSON-RPC 2.0 المباشرة

### أ. استعراض قائمة الأدوات (`tools/list`):

```bash
curl -X POST https://your-domain.com/api/mcp \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/list"
  }'
```

### ب. البحث عن المنتجات الرابحة (`filter_winning_products`):

```bash
curl -X POST https://your-domain.com/api/mcp \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -d '{
    "jsonrpc": "2.0",
    "id": 2,
    "method": "tools/call",
    "params": {
      "name": "filter_winning_products",
      "arguments": {
        "country": "MA",
        "min_ads": 5,
        "limit": 10
      }
    }
  }'
```

### ج. البحث الدلالي والفيكتوري بالذكاء الاصطناعي (`semantic_search_products`):

```bash
curl -X POST https://your-domain.com/api/mcp \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -d '{
    "jsonrpc": "2.0",
    "id": 3,
    "method": "tools/call",
    "params": {
      "name": "semantic_search_products",
      "arguments": {
        "query": "منتجات حل مشاكل تساقط الشعر",
        "country": "MA",
        "limit": 5
      }
    }
  }'
```

---

## 5. قائمة الأدوات المتاحة (Available MCP Tools)

| اسم الأداة | الوصف |
| :--- | :--- |
| `get_saved_products` | جلب المنتجات المحفوظة الخاصة بمساحة عمل المستخدم مع التصفية بالدولة والتقييم. |
| `save_product` | حفظ أو تحديث منتج في المحفوظات مع الملاحظات والتقييمات. |
| `list_snapshots` | استعراض لقطات البيانات التاريخية المتوفرة في النظام. |
| `get_snapshot_by_date` | جلب المنتجات من لقطة بيانات محددة بالتاريخ أو رقم الإصدار. |
| `filter_winning_products` | تصفية متقدمة للمنتجات الرابحة بحسب عدد الإعلانات والسعر والدولة. |
| `semantic_search_products` | بحث دلالي عبر Cloudflare Vectorize يفهم مختلف اللغات (عربية، فرنسية، إنجليزية). |
| `find_similar_products` | العثور على منتجات ومنافسين مشابهين لمنتج معين بناءً على المسافة الفيكتورية. |
| `facebook_search_ads` | بحث مباشر في مكتبة إعلانات فيسبوك عن العلامات التجارية والكلمات المفتاحية. |
| `facebook_discover_competitors` | اكتشاف المنافسين النشطين في نيتش معين وترتيبهم حسب حجم الإعلانات. |
| `facebook_intelligence_report` | توليد تقرير استخباراتي تسويقي شامل للعلامة التجارية مع المقارنات التنافسية. |
| `get_nano_banana_pro_instructions` | استدعاء مهارة توليد الهوية البصرية ونظام ألوان الويب وبرومبتات الإعلانات. |

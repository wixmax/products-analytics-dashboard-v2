<?php

namespace App\Libraries\Mcp\Tools;

use App\Libraries\Mcp\ToolInterface;
use App\Libraries\ChoufliyaService;

class ChoufliyaTools implements ToolInterface
{
    protected string $action;

    public function __construct(string $action = 'choufliya_search_products')
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
            case 'choufliya_search_products':
                return 'البحث في سوق الجملة المغربي (شوف بالجملة Choufliya) عن منتجات وموردين بالكلمات المفتاحية والاسم مع أسعار الجملة وأرقام الواتساب وتاريخ النشر.';
            case 'choufliya_search_by_image':
                return 'البحث العكسي بالصور في سوق الجملة المغربي (Choufliya) باستخدام رابط صورة المنتج للعثور على الموردين الذين يوفرون نفس السلعة.';
            case 'choufliya_find_suppliers':
                return 'استخراج الموردين وأسعار الجملة لمنتج معين مع حساب هامش الربح المتوقع تلقائياً بناءً على سعر البيع للمستهلك.';
            case 'choufliya_export_suppliers':
                return 'تصدير بيانات الموردين وأسعار الجملة لمنتج معين بصيغة CSV أو JSON.';
            default:
                return 'أداة سوق الجملة المغربي Choufliya.';
        }
    }

    public function getInputSchema(): array
    {
        switch ($this->action) {
            case 'choufliya_search_products':
                return [
                    'type' => 'object',
                    'properties' => [
                        'query'          => ['type' => 'string', 'description' => 'الكلمة المفتاحية أو اسم المنتج للبحث عنه في سوق الجملة (مثال: محفظة مدرسية، منظم سيارة)'],
                        'per_page'       => ['type' => 'number', 'description' => 'عدد المنتجات المطلوب جلبها (الافتراضي: 20)'],
                        'whatsapp_only'  => ['type' => 'boolean', 'description' => 'إظهار الموردين الذين يتوفر لديهم رابط واتساب مباشر فقط'],
                        'has_price_only' => ['type' => 'boolean', 'description' => 'إظهار المنتجات ذات السعر المحدد فقط'],
                        'sort_by'        => [
                            'type' => 'string',
                            'enum' => ['match', 'date', 'price_asc', 'price_desc'],
                            'description' => 'طريقة الترتيب: match (دقة المطابقة)، date (الأحدث نشراً)، price_asc (السعر من الأقل للأعلى)، price_desc (السعر من الأعلى للأقل)'
                        ],
                        'category'       => ['type' => 'string', 'description' => 'تصنيف المنتج اختياري']
                    ],
                    'required' => ['query'],
                    'additionalProperties' => false
                ];

            case 'choufliya_search_by_image':
                return [
                    'type' => 'object',
                    'properties' => [
                        'image_url'      => ['type' => 'string', 'description' => 'رابط صورة المنتج على الإنترنت للبحث العكسي عنها في سوق الجملة'],
                        'per_page'       => ['type' => 'number', 'description' => 'عدد المنتجات المطلوب جلبها (الافتراضي: 20)'],
                        'whatsapp_only'  => ['type' => 'boolean', 'description' => 'إظهار الموردين الذين يتوفر لديهم واتساب فقط'],
                        'has_price_only' => ['type' => 'boolean', 'description' => 'إظهار المنتجات ذات السعر المحدد فقط'],
                        'sort_by'        => ['type' => 'string', 'enum' => ['match', 'date', 'price_asc', 'price_desc']]
                    ],
                    'required' => ['image_url'],
                    'additionalProperties' => false
                ];

            case 'choufliya_find_suppliers':
                return [
                    'type' => 'object',
                    'properties' => [
                        'product_name' => ['type' => 'string', 'description' => 'اسم المنتج أو الفكرة المطلوبة للبحث عن مورديها بالجملة'],
                        'retail_price' => ['type' => 'number', 'description' => 'سعر البيع في المتجر/الإعلان بالدرهم المغربي لحساب هامش الربح المتوقع'],
                        'image_url'    => ['type' => 'string', 'description' => 'رابط صورة المنتج اختياري لتحسين دقة البحث العكسي'],
                        'limit'        => ['type' => 'number', 'description' => 'أقصى عدد موردين (الافتراضي: 10)']
                    ],
                    'required' => ['product_name'],
                    'additionalProperties' => false
                ];

            case 'choufliya_export_suppliers':
                return [
                    'type' => 'object',
                    'properties' => [
                        'query'  => ['type' => 'string', 'description' => 'الكلمة المفتاحية أو اسم المنتج المراد تصدير مورديه'],
                        'limit'  => ['type' => 'number', 'description' => 'عدد النتائج المراد تصديرها (الافتراضي: 50)'],
                        'format' => ['type' => 'string', 'enum' => ['csv', 'json'], 'description' => 'صيغة التصدير (csv أو json)']
                    ],
                    'required' => ['query'],
                    'additionalProperties' => false
                ];

            default:
                return ['type' => 'object', 'properties' => []];
        }
    }

    public function execute(array $args, ?array $context = null): array
    {
        $service = new ChoufliyaService();

        try {
            switch ($this->action) {
                case 'choufliya_search_products':
                    $query = trim($args['query'] ?? '');
                    if (empty($query)) {
                        return ['status' => 'error', 'error' => 'Query is required'];
                    }

                    $limit = intval($args['per_page'] ?? 20);
                    $options = [
                        'whatsappOnly' => !empty($args['whatsapp_only']),
                        'hasPriceOnly' => !empty($args['has_price_only']),
                        'sortBy'       => $args['sort_by'] ?? 'match',
                        'category'     => $args['category'] ?? null
                    ];

                    $results = $service->searchByText($query, $limit, $options);
                    return [
                        'status'         => 'success',
                        'query'          => $query,
                        'total_returned' => count($results),
                        'results'        => $results
                    ];

                case 'choufliya_search_by_image':
                    $imageUrl = trim($args['image_url'] ?? '');
                    if (empty($imageUrl)) {
                        return ['status' => 'error', 'error' => 'image_url is required'];
                    }

                    $limit = intval($args['per_page'] ?? 20);
                    $options = [
                        'whatsappOnly' => !empty($args['whatsapp_only']),
                        'hasPriceOnly' => !empty($args['has_price_only']),
                        'sortBy'       => $args['sort_by'] ?? 'match',
                    ];

                    $results = $service->searchByImage($imageUrl, $limit, $options);
                    return [
                        'status'         => 'success',
                        'image_url'      => $imageUrl,
                        'total_returned' => count($results),
                        'results'        => $results
                    ];

                case 'choufliya_find_suppliers':
                    $productName = trim($args['product_name'] ?? '');
                    if (empty($productName)) {
                        return ['status' => 'error', 'error' => 'product_name is required'];
                    }

                    $retailPrice = isset($args['retail_price']) ? floatval($args['retail_price']) : null;
                    $imageUrl = !empty($args['image_url']) ? trim($args['image_url']) : null;
                    $limit = intval($args['limit'] ?? 10);

                    $analysis = $service->findSupplierAlternatives($productName, $retailPrice, $limit, $imageUrl);
                    return array_merge(['status' => 'success'], $analysis);

                case 'choufliya_export_suppliers':
                    $query = trim($args['query'] ?? '');
                    if (empty($query)) {
                        return ['status' => 'error', 'error' => 'query is required'];
                    }

                    $limit = intval($args['limit'] ?? 50);
                    $format = strtolower($args['format'] ?? 'csv');

                    $results = $service->searchByText($query, $limit);

                    if ($format === 'json') {
                        return [
                            'status' => 'success',
                            'format' => 'json',
                            'count'  => count($results),
                            'data'   => $results
                        ];
                    }

                    // CSV export
                    $headers = ['Title', 'Supplier', 'Price', 'Phone', 'WhatsApp', 'Publish_Date', 'Image_URL'];
                    $rows = [];
                    foreach ($results as $item) {
                        $rows[] = [
                            $item['title'] ?? '',
                            $item['supplier'] ?? '',
                            $item['price'] ?? '',
                            $item['phone'] ?? '',
                            $item['whatsappLink'] ?? '',
                            $item['publishDateExact'] ?? '',
                            $item['imageUrl'] ?? ''
                        ];
                    }

                    $fp = fopen('php://temp', 'r+');
                    fputs($fp, "\xEF\xBB\xBF"); // UTF-8 BOM
                    fputcsv($fp, $headers);
                    foreach ($rows as $row) {
                        fputcsv($fp, $row);
                    }
                    rewind($fp);
                    $csvContent = stream_get_contents($fp);
                    fclose($fp);

                    return [
                        'status'   => 'success',
                        'format'   => 'csv',
                        'count'    => count($rows),
                        'filename' => 'choufliya_suppliers_' . date('Ymd_His') . '.csv',
                        'csv_data' => $csvContent
                    ];

                default:
                    throw new \Exception("Unsupported action: {$this->action}");
            }
        } catch (\Throwable $e) {
            return [
                'status'  => 'error',
                'error'   => $e->getMessage(),
                'action'  => $this->action
            ];
        }
    }
}

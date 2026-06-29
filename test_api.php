<?php
$input = (object)[
    "0" => [
        "json" => [
            "category" => "Popular;Electronics;Home & Garden;Health & Beauty;Apparel & Accessories;Tools;Baby & Toddler",
            "country" => "DZ;TN;MA;LY;EG;SA;QA;EA;OM;BH;KW;GB;IE;FR;BE;LU;CH;DE;AT;ES;IT;NL;PT;NG;CI;SN;KE",
            "v" => "1.10-12026-05-15"
        ]
    ]
];

$url = 'https://www.overviewdata.io/api/trpc/data.winingProducts?batch=1&input=' . urlencode(json_encode($input));

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Accept: application/json'
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$json = $data[0]['result']['data']['json'] ?? [];

echo "Winning products top keys: " . implode(', ', array_keys($json)) . "\n";
if (isset($json['results'])) {
    echo "results count: " . count($json['results']) . "\n";
    echo "First product keys: " . implode(', ', array_keys($json['results'][0] ?? [])) . "\n";
    echo "First product title: " . ($json['results'][0]['product_title'] ?? $json['results'][0]['title'] ?? 'N/A') . "\n";
} else {
    // If results is nested elsewhere
    if (is_array($json) && count($json) > 0) {
        echo "First item keys: " . implode(', ', array_keys($json[0] ?? [])) . "\n";
    }
}

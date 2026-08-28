<?php

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);

require FCPATH . '../app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';

CodeIgniter\Boot::bootSpark($paths);

$controller = new \App\Controllers\McpController();
$reflection = new \ReflectionClass($controller);

// 1. Check tools/list
$manifestMethod = $reflection->getMethod('getToolsManifest');
$manifestMethod->setAccessible(true);
$tools = $manifestMethod->invoke($controller);
$toolNames = array_column($tools, 'name');
echo "AVAILABLE MCP TOOLS: " . implode(', ', $toolNames) . "\n";

// 2. Check get_ai_skill_instructions
$callToolMethod = $reflection->getMethod('executeTool');
$callToolMethod->setAccessible(true);

$res1 = $callToolMethod->invoke($controller, 'get_ai_skill_instructions', []);
echo "TOOL 1 (get_ai_skill_instructions) CONTAINS HANDOVER LINE: " 
    . (strpos($res1['skill_instructions'] ?? '', 'nano-banana-pro-consistent-ads') !== false ? 'YES' : 'NO') 
    . "\n";

// 3. Check get_nano_banana_pro_instructions
$res2 = $callToolMethod->invoke($controller, 'get_nano_banana_pro_instructions', [
    'product_name' => 'Portable Manicure Kit',
    'product_image_url' => 'https://example.com/item.jpg',
    'language' => 'Arabic'
]);
echo "TOOL 2 (get_nano_banana_pro_instructions) STATUS: " . ($res2['status'] ?? 'FAIL') . "\n";
echo "TOOL 2 SKILL NAME: " . ($res2['skill_name'] ?? 'FAIL') . "\n";
echo "TOOL 2 CONTAINS COLOR SYSTEM: " 
    . (strpos($res2['skill_instructions'] ?? '', 'Web & Brand Color System') !== false ? 'YES' : 'NO') 
    . "\n";





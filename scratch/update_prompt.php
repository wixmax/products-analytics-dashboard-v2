<?php

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);

require FCPATH . '../app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';

CodeIgniter\Boot::bootSpark($paths);

$adminController = new \App\Controllers\Admin\McpAdminController();
$mcpController   = new \App\Controllers\McpController();

echo "--- 1. Testing McpAdminController::getSkillsList ---\n";
$skills = $adminController->getSkillsList();
echo "Found " . count($skills) . " skills: " . implode(', ', array_keys($skills)) . "\n";
assert(isset($skills['cod-assistant']), 'cod-assistant missing');
assert(isset($skills['nano-banana-pro-consistent-ads']), 'nano-banana-pro-consistent-ads missing');

echo "--- 2. Testing MCP Tools Manifest Reflection ---\n";
$reflection = new \ReflectionClass($mcpController);
$manifestMethod = $reflection->getMethod('getToolsManifest');
$manifestMethod->setAccessible(true);
$tools = $manifestMethod->invoke($mcpController);
$toolNames = array_column($tools, 'name');
echo "Registered MCP Tools: " . implode(', ', $toolNames) . "\n";
assert(in_array('get_nano_banana_pro_instructions', $toolNames, true), 'get_nano_banana_pro_instructions missing');
assert(in_array('get_ai_skill_instructions', $toolNames, true), 'get_ai_skill_instructions missing');

echo "--- 3. Testing Dynamic Tool Call Execution ---\n";
$execMethod = $reflection->getMethod('executeTool');
$execMethod->setAccessible(true);

$resNano = $execMethod->invoke($mcpController, 'get_nano_banana_pro_instructions', ['product_name' => 'Smart Watch Pro']);
echo "Nano Banana Call Status: " . ($resNano['status'] ?? 'ERROR') . " | Title: " . ($resNano['title'] ?? 'ERROR') . "\n";
assert($resNano['status'] === 'success');

$resCod = $execMethod->invoke($mcpController, 'get_ai_skill_instructions', []);
echo "COD Assistant Call Status: " . ($resCod['status'] ?? 'ERROR') . " | Contains Handover: " . (strpos($resCod['skill_instructions'], 'nano-banana-pro-consistent-ads') !== false ? 'YES' : 'NO') . "\n";
assert($resCod['status'] === 'success');

echo "\n ALL SKILL MANAGEMENT TESTS PASSED SUCCESSFULLY! \n";

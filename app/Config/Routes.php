<?php

use CodeIgniter\Router\RouteCollection;

service('auth')->routes($routes, ['except' => ['login', 'register']]);

// Custom Auth Controllers
$routes->get('login', '\App\Controllers\Auth\LoginController::loginView');
$routes->post('login', '\App\Controllers\Auth\LoginController::loginAction');
$routes->get('register', '\App\Controllers\Auth\RegisterController::registerView');
$routes->post('register', '\App\Controllers\Auth\RegisterController::registerAction');

// Google OAuth Controllers
$routes->get('auth/google', '\App\Controllers\Auth\OAuthController::googleLogin');
$routes->get('auth/google/callback', '\App\Controllers\Auth\OAuthController::googleCallback');

// Profile Routes
$routes->get('profile', '\App\Controllers\Auth\ProfileController::index');
$routes->post('profile/update', '\App\Controllers\Auth\ProfileController::update');
$routes->post('profile/change-password', '\App\Controllers\Auth\ProfileController::changePassword');
$routes->post('profile/generate-api-token', '\App\Controllers\Auth\ProfileController::generateApiToken');
$routes->post('profile/revoke-api-token', '\App\Controllers\Auth\ProfileController::revokeApiToken');

// Tenant/Workspace Settings
$routes->get('workspace', '\App\Controllers\Auth\WorkspaceController::index');
$routes->post('workspace/update', '\App\Controllers\Auth\WorkspaceController::update');
$routes->post('workspace/invite', '\App\Controllers\Auth\WorkspaceController::inviteMember');
$routes->post('workspace/remove-member/(:num)', '\App\Controllers\Auth\WorkspaceController::removeMember/$1');
$routes->post('workspace/switch', '\App\Controllers\Auth\WorkspaceController::switchWorkspace');

// Admin Panel Management
$routes->group('admin', ['filter' => 'group:superadmin,admin'], function($routes) {
    $routes->get('users', '\App\Controllers\Admin\UsersController::index');
    $routes->post('users/update-role', '\App\Controllers\Admin\UsersController::updateRole');
    $routes->post('users/toggle-status', '\App\Controllers\Admin\UsersController::toggleStatus');
    $routes->post('users/impersonate', '\App\Controllers\Admin\UsersController::impersonate');
    $routes->post('users/change-password', '\App\Controllers\Admin\UsersController::changePassword');

    // MCP Admin Control Panel Routes
    $routes->get('mcp', '\App\Controllers\Admin\McpAdminController::index');
    $routes->post('mcp/toggle-global', '\App\Controllers\Admin\McpAdminController::toggleGlobalStatus');
    $routes->post('mcp/toggle-tool', '\App\Controllers\Admin\McpAdminController::toggleTool');
    $routes->post('mcp/generate-token/(:num)', '\App\Controllers\Admin\McpAdminController::generateUserToken/$1');
    $routes->post('mcp/revoke-token/(:num)', '\App\Controllers\Admin\McpAdminController::revokeUserToken/$1');
    $routes->post('mcp/update-prompt', '\App\Controllers\Admin\McpAdminController::updateSystemPrompt');
    $routes->post('mcp/save-fb-token', '\App\Controllers\Admin\McpAdminController::saveFacebookToken');
});
$routes->get('admin/users/stop-impersonating', '\App\Controllers\Admin\UsersController::stopImpersonating');

$routes->get('/', 'Home::index');
$routes->get('/all-products', 'Home::allProducts');
$routes->get('/saved-ads', 'Home::savedAds');
$routes->get('/international-products', 'Home::internationalProducts');
$routes->get('/url-encoder', 'Home::urlEncoder');
$routes->get('/settings', 'Home::settings');
$routes->get('/snapshots', 'Home::snapshots');

// API Routes
$routes->get('/api/products', 'Products::index');
$routes->get('/api/products/stats', 'Products::stats');
$routes->get('/api/products/insights-charts', 'Products::insightsCharts');
$routes->get('/api/products/countries', 'Products::countries');
$routes->get('/api/products/available-countries', 'Products::availableCountries');
$routes->match(['GET', 'POST'], '/api/products/sync', 'Products::sync');
$routes->get('/api/products/available-dates', 'Products::getAvailableDates');
$routes->post('/api/products/sync-trpc', 'Products::syncTrpc');
$routes->post('/api/products/import', 'Products::importJson');

// Saved Ads & Bookmark Endpoints
$routes->get('/api/products/saved', 'Products::saved');
$routes->post('/api/products/saved/toggle', 'Products::toggleSave');
$routes->post('/api/products/save-thumbnail', 'Products::saveThumbnail');
$routes->post('/api/products/saved/rating', 'Products::updateRating');
$routes->post('/api/products/saved/notes', 'Products::updateNotes');
$routes->post('/api/products/saved/price', 'Products::updatePrice');
$routes->post('/api/products/saved/status', 'Products::updateStatus');
$routes->post('/api/products/saved/collection', 'Products::updateCollection');
$routes->post('/api/products/saved/clear', 'Products::clearSaved');

// Collections Endpoints
$routes->get('/api/products/collections', 'Products::collections');
$routes->post('/api/products/collections', 'Products::addCollection');
$routes->post('/api/products/collections/delete', 'Products::deleteCollection');

// Watchlist Endpoints
$routes->get('/api/products/watchlist', 'Products::watchlist');
$routes->post('/api/products/watchlist/toggle', 'Products::toggleWatchlist');

// Snapshots & Versions Endpoints
$routes->get('/api/products/versions', 'Products::versions');
$routes->get('/api/products/snapshots', 'Products::snapshots');
$routes->get('/api/products/snapshots/(:num)', 'Products::getSnapshot/$1');
$routes->post('/api/products/snapshots/import', 'Products::importSnapshot');
$routes->post('/api/products/saved/import', 'Products::importSavedAds');
$routes->post('/api/products/snapshots/(:num)/restore', 'Products::restoreSnapshot/$1');
$routes->post('/api/products/snapshots/(:num)/delete', 'Products::deleteSnapshot/$1');

// Activity Data Endpoints
$routes->get('/api/products/activity', 'Products::activity');
$routes->post('/api/products/activity', 'Products::activity');

// Settings Endpoints
$routes->get('/api/settings/(:segment)', 'Products::getSetting/$1');
$routes->post('/api/settings', 'Products::saveSetting');
$routes->post('/api/products/clear-database-data', 'Products::clearDatabaseData');
$routes->post('/api/products/delete-by-date', 'Products::deleteByDate');

// AI Winning Product Analysis Endpoints
$routes->post('/api/ai/analyze', 'Products::aiAnalyze');
$routes->post('/api/ai/analyze-deep', 'Products::aiDeepAnalyze');
$routes->post('/api/products/ai_deep_analyze', 'Products::aiDeepAnalyze');
$routes->get('/api/ai/history', 'Products::aiHistory');
$routes->get('/api/ai/history/(:num)', 'Products::aiHistoryDetail/$1');
$routes->post('/api/ai/history/(:num)/delete', 'Products::aiDeleteHistory/$1');
// Model Context Protocol (MCP) Endpoints
$routes->match(['GET', 'POST', 'OPTIONS'], '/api/mcp', 'McpController::handleMcp');
$routes->match(['GET', 'POST', 'OPTIONS'], '/api/mcp/(:segment)', 'McpController::handleMcp/$1');

// Facebook Ads Library Intelligence REST API Endpoints
$routes->group('api/facebook-ads', function($routes) {
    $routes->match(['GET', 'POST'], 'search', 'FacebookAdsController::search');
    $routes->match(['GET', 'POST'], 'discover-competitors', 'FacebookAdsController::discoverCompetitors');
    $routes->post('analyze-creative', 'FacebookAdsController::analyzeCreative');
    $routes->match(['GET', 'POST'], 'analyze-performance', 'FacebookAdsController::analyzePerformance');
    $routes->post('competitive-analysis', 'FacebookAdsController::competitiveAnalysis');
    $routes->match(['GET', 'POST'], 'intelligence-report', 'FacebookAdsController::intelligenceReport');
    $routes->post('export', 'FacebookAdsController::export');
});

// Update Database Schema Route (non-destructive migrations update)
$routes->get('update-db', '\App\Controllers\InstallController::updateDatabaseSchema');

// Installer Routes
$routes->group('install', function($routes) {
    $routes->get('/', '\App\Controllers\InstallController::index');
    $routes->get('database', '\App\Controllers\InstallController::database');
    $routes->post('database', '\App\Controllers\InstallController::saveDatabase');
    $routes->get('migrate', '\App\Controllers\InstallController::migrate');
    $routes->get('admin', '\App\Controllers\InstallController::admin');
    $routes->post('admin', '\App\Controllers\InstallController::saveAdmin');
    $routes->get('complete', '\App\Controllers\InstallController::complete');
});



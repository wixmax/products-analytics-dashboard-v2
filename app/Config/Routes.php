<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
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
$routes->match(['get', 'post'], '/api/products/sync', 'Products::sync');
$routes->post('/api/products/sync-trpc', 'Products::syncTrpc');
$routes->post('/api/products/import', 'Products::importJson');

// Saved Ads & Bookmark Endpoints
$routes->get('/api/products/saved', 'Products::saved');
$routes->post('/api/products/saved/toggle', 'Products::toggleSave');
$routes->post('/api/products/saved/rating', 'Products::updateRating');
$routes->post('/api/products/saved/notes', 'Products::updateNotes');
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
$routes->post('/api/products/snapshots/(:num)/restore', 'Products::restoreSnapshot/$1');

// Activity Data Endpoints
$routes->get('/api/products/activity', 'Products::activity');
$routes->post('/api/products/activity', 'Products::activity');

// Settings Endpoints
$routes->get('/api/settings/(:segment)', 'Products::getSetting/$1');
$routes->post('/api/settings', 'Products::saveSetting');
$routes->post('/api/products/clear-database-data', 'Products::clearDatabaseData');


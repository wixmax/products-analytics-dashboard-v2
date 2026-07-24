<?php
define('FCPATH', __DIR__ . '/../public/');
chdir(__DIR__ . '/../');

require 'app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/bootstrap.php';

$app = \Config\Services::codeigniter();
$app->initialize();

$migrate = \Config\Services::migrations();
$history = $migrate->getHistory();

echo "=== MIGRATIONS HISTORY IN DB ===\n";
foreach ($history as $h) {
    echo "ID: {$h->id} | Version: {$h->version} | Class: {$h->class} | Namespace: {$h->namespace}\n";
}

echo "\n=== FIND NAMESPACE MIGRATIONS ('CodeIgniter\\Shield') ===\n";
$shieldMigrations = $migrate->findNamespaceMigrations('CodeIgniter\Shield');
foreach ($shieldMigrations as $m) {
    print_r($m);
}

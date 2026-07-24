<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        $snapshotModel = new \App\Models\SnapshotModel();
        $latestSnapshot = $snapshotModel->where('origin', 'Winning')
                                        ->orderBy('created_at', 'DESC')
                                        ->first();
        
        $initialData = null;
        if ($latestSnapshot && !empty($latestSnapshot['raw_json'])) {
            $decoded = json_decode($latestSnapshot['raw_json'], true);
            if (is_array($decoded) && isset($decoded[0])) {
                $initialData = $decoded[0];
            } else {
                $initialData = $decoded;
            }
            
            // Add metadata so the frontend knows the snapshot's origin and version
            if (is_array($initialData)) {
                $initialData['origin'] = $latestSnapshot['origin'];
                $initialData['api_version'] = $latestSnapshot['api_version'];
                $initialData['snapshot_id'] = $latestSnapshot['id'];
                $initialData['created_at'] = $latestSnapshot['created_at'];
            }
        }

        return view('index', [
            'initialData' => $initialData
        ]);
    }

    public function savedAds(): string
    {
        return view('saved-ads');
    }

    public function internationalProducts(): string
    {
        return view('international-products');
    }

    public function urlEncoder(): string
    {
        return view('url-encoder');
    }

    public function settings(): string
    {
        $pendingMigrations = [];
        try {
            $db = \Config\Database::connect();
            $history = [];
            if ($db->tableExists('migrations')) {
                $history = $db->table('migrations')->get()->getResultArray();
            }

            $historyVersions = [];
            $historyClasses  = [];
            foreach ($history as $h) {
                if (!empty($h['version'])) {
                    $historyVersions[] = (string)$h['version'];
                }
                if (!empty($h['class'])) {
                    $historyClasses[] = strtolower(ltrim((string)$h['class'], '\\'));
                }
            }

            $migrate = \Config\Services::migrations();
            $namespaces = ['App', 'CodeIgniter\Shield'];
            foreach ($namespaces as $ns) {
                try {
                    $all = $migrate->findNamespaceMigrations($ns);
                    foreach ($all as $m) {
                        $mVer   = isset($m->version) ? (string)$m->version : null;
                        $mClass = isset($m->class) ? strtolower(ltrim((string)$m->class, '\\')) : null;

                        $isAlreadyRan = false;
                        if ($mVer && in_array($mVer, $historyVersions, true)) {
                            $isAlreadyRan = true;
                        } elseif ($mClass && in_array($mClass, $historyClasses, true)) {
                            $isAlreadyRan = true;
                        }

                        if (!$isAlreadyRan) {
                            $pendingMigrations[] = [
                                'name'      => $m->name ?? $m->version,
                                'version'   => $m->version,
                                'namespace' => $m->namespace,
                                'filename'  => basename($m->path),
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    // Ignore namespace if no migrations exist
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'Failed to check pending migrations: ' . $e->getMessage());
        }

        return view('settings', [
            'pendingMigrations' => $pendingMigrations
        ]);
    }

    public function snapshots()
    {
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            return redirect()->to('/');
        }
        return view('snapshots');
    }
}

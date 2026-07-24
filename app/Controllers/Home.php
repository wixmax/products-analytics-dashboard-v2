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
            $migrate = \Config\Services::migrations();
            $history = $migrate->getHistory();
            
            $historyVersions = [];
            $historyClasses = [];
            foreach ($history as $h) {
                $hObj = (object)$h;
                if (!empty($hObj->version)) {
                    $historyVersions[] = $hObj->version;
                }
                if (!empty($hObj->class)) {
                    $historyClasses[] = $hObj->class;
                }
            }

            $namespaces = ['App', 'CodeIgniter\Shield'];
            foreach ($namespaces as $ns) {
                try {
                    $all = $migrate->findNamespaceMigrations($ns);
                    foreach ($all as $m) {
                        $mVer = $m->version ?? null;
                        $mClass = $m->class ?? null;

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

<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        $snapshotModel = new \App\Models\SnapshotModel();
        $latestSnapshot = $snapshotModel->whereIn('origin', ['Winning', 'Local'])
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
        return view('settings');
    }

    public function snapshots(): string
    {
        return view('snapshots');
    }
}

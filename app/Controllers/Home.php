<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        return view('index', [
            'initialData' => null
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

<?php

namespace App\Controllers\Traits;

use App\Libraries\ChoufliyaService;

trait ChoufliyaTrait
{
    /**
     * Search Choufliya wholesale marketplace by text
     */
    public function choufliyaSearch()
    {
        $query = $this->request->getVar('query') ?? $this->request->getVar('q');
        if (empty($query)) {
            return $this->response->setJSON(['status' => 'error', 'error' => 'Query is required'])->setStatusCode(400);
        }

        $limit = intval($this->request->getVar('limit') ?? 20);
        $options = [
            'whatsappOnly' => filter_var($this->request->getVar('whatsapp_only'), FILTER_VALIDATE_BOOLEAN),
            'hasPriceOnly' => filter_var($this->request->getVar('has_price_only'), FILTER_VALIDATE_BOOLEAN),
            'sortBy'       => $this->request->getVar('sort_by') ?? 'match',
            'category'     => $this->request->getVar('category')
        ];

        try {
            $service = new ChoufliyaService();
            $results = $service->searchByText((string)$query, $limit, $options);
            return $this->response->setJSON([
                'status'  => 'success',
                'query'   => $query,
                'count'   => count($results),
                'results' => $results
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['status' => 'error', 'error' => $e->getMessage()])->setStatusCode(500);
        }
    }

    /**
     * Search Choufliya by image URL or uploaded file
     */
    public function choufliyaSearchImage()
    {
        $imageUrl = $this->request->getVar('image_url') ?? $this->request->getVar('imageUrl');
        $uploadedFile = $this->request->getFile('image');

        if (empty($imageUrl) && (!$uploadedFile || !$uploadedFile->isValid())) {
            return $this->response->setJSON(['status' => 'error', 'error' => 'image_url or uploaded image file is required'])->setStatusCode(400);
        }

        $limit = intval($this->request->getVar('limit') ?? 20);
        $options = [
            'whatsappOnly' => filter_var($this->request->getVar('whatsapp_only'), FILTER_VALIDATE_BOOLEAN),
            'hasPriceOnly' => filter_var($this->request->getVar('has_price_only'), FILTER_VALIDATE_BOOLEAN),
            'sortBy'       => $this->request->getVar('sort_by') ?? 'match'
        ];

        try {
            $service = new ChoufliyaService();
            $imageSource = !empty($imageUrl) ? (string)$imageUrl : $uploadedFile->getTempName();
            $results = $service->searchByImage($imageSource, $limit, $options);

            return $this->response->setJSON([
                'status'  => 'success',
                'count'   => count($results),
                'results' => $results
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['status' => 'error', 'error' => $e->getMessage()])->setStatusCode(500);
        }
    }

    /**
     * Get wholesale suppliers & margin analysis for a specific product
     */
    public function choufliyaSuppliers()
    {
        $productTitle = $this->request->getVar('title') ?? $this->request->getVar('product_name') ?? '';
        $retailPrice = $this->request->getVar('price');
        $imageUrl = $this->request->getVar('image_url');
        $uploadedFile = $this->request->getFile('image');
        $limit = intval($this->request->getVar('limit') ?? 20);
        $preferImage = filter_var($this->request->getVar('prefer_image'), FILTER_VALIDATE_BOOLEAN);

        $imageSource = null;
        if ($uploadedFile && $uploadedFile->isValid() && !$uploadedFile->hasMoved()) {
            $imageSource = $uploadedFile->getTempName();
            $preferImage = true;
        } elseif (!empty($imageUrl)) {
            $imageSource = (string)$imageUrl;
        }

        if (empty($productTitle) && empty($imageSource)) {
            return $this->response->setJSON(['status' => 'error', 'error' => 'title or image is required'])->setStatusCode(400);
        }

        try {
            $service = new ChoufliyaService();
            $data = $service->findSupplierAlternatives(
                (string)$productTitle,
                $retailPrice !== null && is_numeric($retailPrice) ? floatval($retailPrice) : null,
                $limit,
                $imageSource,
                $preferImage
            );

            return $this->response->setJSON(array_merge(['status' => 'success'], $data));
        } catch (\Throwable $e) {
            return $this->response->setJSON(['status' => 'error', 'error' => $e->getMessage()])->setStatusCode(500);
        }
    }

    /**
     * Proxy CDN images and video media to bypass BunnyCDN hotlink protection (HTTP 403) and CORS
     */
    public function choufliyaProxyImage()
    {
        if ($this->request->getMethod() === 'options') {
            return $this->response
                ->setHeader('Access-Control-Allow-Origin', '*')
                ->setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->setHeader('Access-Control-Allow-Headers', '*')
                ->setStatusCode(200);
        }

        $url = $this->request->getVar('url');
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->response->setStatusCode(400)->setBody('Invalid media URL');
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Referer: https://choufliya.ma/',
            'Origin: https://choufliya.ma',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'image/jpeg';
        curl_close($ch);

        if ($httpCode !== 200 || empty($data)) {
            return $this->response->setStatusCode(404)->setBody('Media not found');
        }

        return $this->response
            ->setHeader('Content-Type', $contentType)
            ->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', '*')
            ->setHeader('Cache-Control', 'public, max-age=86400')
            ->setBody($data);
    }
}

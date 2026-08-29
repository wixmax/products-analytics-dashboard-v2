<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Cloudflare extends BaseConfig
{
    /**
     * Enable or disable Cloudflare Vectorize features
     */
    public bool $enabled = false;

    /**
     * Cloudflare Account ID
     */
    public string $accountId = '';

    /**
     * Cloudflare API Token (Requires Workers AI & Vectorize permissions)
     */
    public string $apiToken = '';

    /**
     * Vectorize Index Name
     */
    public string $vectorizeIndex = 'products-index';

    /**
     * Workers AI Embedding Model
     * Recommended:
     * - '@cf/baai/bge-m3' (Multilingual - recommended for Arabic, English, French)
     * - '@cf/baai/bge-base-en-v1.5' (English optimized, 768 dimensions)
     * - '@cf/baai/bge-large-en-v1.5' (1024 dimensions)
     */
    public string $embeddingModel = '@cf/baai/bge-m3';

    /**
     * Optional custom Worker Endpoint URL (if using a Cloudflare Worker proxy)
     */
    public string $workerEndpoint = '';

    /**
     * Initialize config with environment variables if present
     */
    public function __construct()
    {
        parent::__construct();

        $this->enabled = filter_var(env('CLOUDFLARE_VECTORIZE_ENABLED', $this->enabled), FILTER_VALIDATE_BOOLEAN);
        $this->accountId = env('CLOUDFLARE_ACCOUNT_ID', $this->accountId);
        $this->apiToken = env('CLOUDFLARE_API_TOKEN', $this->apiToken);
        $this->vectorizeIndex = env('CLOUDFLARE_VECTORIZE_INDEX', $this->vectorizeIndex);
        $this->embeddingModel = env('CLOUDFLARE_EMBEDDING_MODEL', $this->embeddingModel);
        $this->workerEndpoint = env('CLOUDFLARE_WORKER_ENDPOINT', $this->workerEndpoint);
    }
}

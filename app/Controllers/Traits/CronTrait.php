<?php

namespace App\Controllers\Traits;

use App\Services\SyncService;
use App\Models\SettingModel;
use App\Libraries\Queue\BackgroundTaskRunner;

trait CronTrait
{
    /**
     * Webhook/API endpoint to execute daily cron sync
     * GET/POST /api/cron/daily?secret=...&date=...&async=0|1
     */
    public function dailyCron()
    {
        $settingModel = new SettingModel();
        $storedSecretRow = $settingModel->where('key', 'cron_secret_token')->first();
        $expectedSecret = $storedSecretRow['value'] ?? env('CRON_SECRET_TOKEN', '');

        // Generate and persist default secret if none exists
        if (empty($expectedSecret)) {
            $expectedSecret = bin2hex(random_bytes(16));
            if ($storedSecretRow) {
                $settingModel->update($storedSecretRow['id'], ['value' => $expectedSecret]);
            } else {
                $settingModel->insert(['key' => 'cron_secret_token', 'value' => $expectedSecret]);
            }
        }

        // Validate authorization: either valid secret query/header OR admin user session
        $providedSecret = $this->request->getVar('secret') 
            ?? $this->request->getHeaderLine('X-Cron-Secret') 
            ?? $this->request->getHeaderLine('Authorization');

        if (str_starts_with((string)$providedSecret, 'Bearer ')) {
            $providedSecret = substr((string)$providedSecret, 7);
        }

        $isAdmin = false;
        try {
            if (function_exists('auth') && auth()->loggedIn() && auth()->user()) {
                $isAdmin = auth()->user()->inGroup('superadmin', 'admin');
            }
        } catch (\Throwable $e) {
            $isAdmin = false;
        }
        $isAuthorized = ($isAdmin || (!empty($providedSecret) && hash_equals($expectedSecret, (string)$providedSecret)));

        if (!$isAuthorized) {
            return $this->failUnauthorized('Unauthorized: Invalid or missing cron secret token.');
        }

        $targetDate = $this->request->getVar('date') ?: date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            return $this->fail('Invalid date format. Expected YYYY-MM-DD.');
        }

        // Parse optional countries parameter
        $countriesParam = $this->request->getVar('countries');
        if (empty($countriesParam)) {
            $json = $this->request->getJSON(true);
            $countriesParam = $json['countries'] ?? null;
        }

        $countries = null;
        if (!empty($countriesParam)) {
            if (is_array($countriesParam)) {
                $countries = $countriesParam;
            } else {
                $parts = explode(',', str_replace(';', ',', strval($countriesParam)));
                $countries = array_filter(array_map('trim', $parts));
            }
            // Sanitize
            $countries = array_values(array_filter(array_map(fn($c) => strtoupper(trim($c)), $countries), fn($c) => preg_match('/^[A-Z]{2}$/', $c)));
        }

        $isAsync = filter_var($this->request->getVar('async'), FILTER_VALIDATE_BOOLEAN);

        // If async execution requested, dispatch background task to avoid HTTP timeout
        if ($isAsync) {
            $runner = new BackgroundTaskRunner();
            $args = ['--date' => $targetDate];
            if (!empty($countries)) {
                $args['--countries'] = implode(',', $countries);
            }
            $task = $runner->dispatchSparkCommand('cron:daily', $args);
            return $this->respond([
                'success'   => true,
                'message'   => 'Daily cron job dispatched asynchronously',
                'mode'      => 'async',
                'task_id'   => $task['task_id'],
                'date'      => $targetDate,
                'countries' => $countries,
            ]);
        }

        // Synchronous execution
        $syncService = new SyncService();
        $trigger = $isAdmin ? 'manual_admin' : 'webhook';
        $stats = $syncService->run($targetDate, $trigger, ['Winning'], $countries);

        return $this->respond([
            'success'   => true,
            'message'   => 'Daily data sync executed successfully',
            'mode'      => 'sync',
            'date'      => $targetDate,
            'countries' => $stats['Winning']['target_countries'] ?? $countries,
            'stats'     => $stats,
            'summary'   => SyncService::getLastSyncRun(),
        ]);
    }

    /**
     * Get the latest daily cron status and execution history
     * GET /api/cron/status
     */
    public function getCronStatus()
    {
        $lastRun = SyncService::getLastSyncRun();

        $settingModel = new SettingModel();
        $storedSecretRow = $settingModel->where('key', 'cron_secret_token')->first();
        $secret = $storedSecretRow['value'] ?? env('CRON_SECRET_TOKEN', '');

        if (empty($secret)) {
            $secret = bin2hex(random_bytes(16));
            if ($storedSecretRow) {
                $settingModel->update($storedSecretRow['id'], ['value' => $secret]);
            } else {
                $settingModel->insert(['key' => 'cron_secret_token', 'value' => $secret]);
            }
        }

        // Load configured winning countries
        $countriesRow = $settingModel->where('key', 'cron_winning_countries')->first();
        $configuredCountries = ['DZ', 'TN', 'MA', 'LY', 'EG', 'SA', 'QA', 'AE', 'OM', 'BH', 'KW'];
        if ($countriesRow && !empty($countriesRow['value'])) {
            $val = $countriesRow['value'];
            if (is_string($val)) {
                $decoded = json_decode($val, true);
                if (is_array($decoded) && !empty($decoded)) {
                    $configuredCountries = $decoded;
                } else {
                    $configuredCountries = array_filter(array_map('trim', explode(',', $val)));
                }
            } elseif (is_array($val) && !empty($val)) {
                $configuredCountries = $val;
            }
        }
        $configuredCountries = array_values(array_unique(array_map('strtoupper', $configuredCountries)));

        // Only reveal secret if admin
        $isAdmin = false;
        try {
            if (function_exists('auth') && auth()->loggedIn() && auth()->user()) {
                $isAdmin = auth()->user()->inGroup('superadmin', 'admin');
            }
        } catch (\Throwable $e) {
            $isAdmin = false;
        }

        $cronLogDir = WRITEPATH . 'logs' . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR;
        $cronLogFile = $cronLogDir . 'daily_sync_' . date('Y-m') . '.log';
        $recentLogTail = '';
        if (is_file($cronLogFile)) {
            $logContent = file_get_contents($cronLogFile);
            $recentLogTail = mb_substr($logContent, -1500);
        }

        return $this->respond([
            'success'              => true,
            'last_run'             => $lastRun,
            'configured_countries' => $configuredCountries,
            'cron_secret'          => $isAdmin ? $secret : null,
            'cron_url'             => $isAdmin ? site_url('api/cron/daily?secret=' . $secret) : null,
            'recent_logs'          => $recentLogTail,
        ]);
    }

    /**
     * Regenerate cron secret token (Admin only)
     * POST /api/cron/regenerate-secret
     */
    public function regenerateCronSecret()
    {
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            return $this->failForbidden('Restricted to administrators.');
        }

        $newSecret = bin2hex(random_bytes(16));
        $settingModel = new SettingModel();
        $storedSecretRow = $settingModel->where('key', 'cron_secret_token')->first();

        if ($storedSecretRow) {
            $settingModel->update($storedSecretRow['id'], [
                'value'      => $newSecret,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } else {
            $settingModel->insert([
                'key'        => 'cron_secret_token',
                'value'      => $newSecret,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        return $this->respond([
            'success'     => true,
            'message'     => 'تم إنشاء رمز أمان جديد للـ Cron بنجاح',
            'cron_secret' => $newSecret,
            'cron_url'    => site_url('api/cron/daily?secret=' . $newSecret),
        ]);
    }
}

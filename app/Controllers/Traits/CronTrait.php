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

        $isAdmin = auth()->loggedIn() && auth()->user()->inGroup('superadmin', 'admin');
        $isAuthorized = ($isAdmin || (!empty($providedSecret) && hash_equals($expectedSecret, (string)$providedSecret)));

        if (!$isAuthorized) {
            return $this->failUnauthorized('Unauthorized: Invalid or missing cron secret token.');
        }

        $targetDate = $this->request->getVar('date') ?: date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            return $this->fail('Invalid date format. Expected YYYY-MM-DD.');
        }

        $isAsync = filter_var($this->request->getVar('async'), FILTER_VALIDATE_BOOLEAN);

        // If async execution requested, dispatch background task to avoid HTTP timeout
        if ($isAsync) {
            $runner = new BackgroundTaskRunner();
            $task = $runner->dispatchSparkCommand('cron:daily', ['--date' => $targetDate]);
            return $this->respond([
                'success' => true,
                'message' => 'Daily cron job dispatched asynchronously',
                'mode'    => 'async',
                'task_id' => $task['task_id'],
                'date'    => $targetDate,
            ]);
        }

        // Synchronous execution
        $syncService = new SyncService();
        $trigger = $isAdmin ? 'manual_admin' : 'webhook';
        $stats = $syncService->run($targetDate, $trigger);

        return $this->respond([
            'success' => true,
            'message' => 'Daily data sync executed successfully',
            'mode'    => 'sync',
            'date'    => $targetDate,
            'stats'   => $stats,
            'summary' => SyncService::getLastSyncRun(),
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

        // Only reveal secret if admin
        $isAdmin = auth()->loggedIn() && auth()->user()->inGroup('superadmin', 'admin');

        $cronLogDir = WRITEPATH . 'logs' . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR;
        $cronLogFile = $cronLogDir . 'daily_sync_' . date('Y-m') . '.log';
        $recentLogTail = '';
        if (is_file($cronLogFile)) {
            $logContent = file_get_contents($cronLogFile);
            $recentLogTail = mb_substr($logContent, -1500);
        }

        return $this->respond([
            'success'       => true,
            'last_run'      => $lastRun,
            'cron_secret'   => $isAdmin ? $secret : null,
            'cron_url'      => $isAdmin ? site_url('api/cron/daily?secret=' . $secret) : null,
            'recent_logs'   => $recentLogTail,
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

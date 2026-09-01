<?php

namespace App\Libraries\Queue;

class BackgroundTaskRunner
{
    protected string $logDir;

    public function __construct()
    {
        $this->logDir = WRITEPATH . 'logs' . DIRECTORY_SEPARATOR . 'tasks' . DIRECTORY_SEPARATOR;
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0777, true);
        }
    }

    /**
     * Dispatch a Spark CLI command to run in the background
     *
     * @param string $sparkCommand e.g. "sync:data", "vectorize:index"
     * @param array $args CLI parameters e.g. ["--batch" => 50]
     * @return array Task metadata including taskId, status, and log path
     */
    public function dispatchSparkCommand(string $sparkCommand, array $args = []): array
    {
        $taskId = 'task_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $logFile = $this->logDir . $taskId . '.log';
        $metaFile = $this->logDir . $taskId . '.meta.json';

        $cmdParts = [];
        $cmdParts[] = PHP_BINARY;
        $cmdParts[] = ROOTPATH . 'spark';
        $cmdParts[] = escapeshellarg($sparkCommand);

        foreach ($args as $key => $val) {
            if (is_numeric($key)) {
                $cmdParts[] = escapeshellarg($val);
            } elseif (is_bool($val)) {
                if ($val) $cmdParts[] = escapeshellarg($key);
            } else {
                $cmdParts[] = escapeshellarg($key) . '=' . escapeshellarg($val);
            }
        }

        $fullCmd = implode(' ', $cmdParts);

        $meta = [
            'task_id'    => $taskId,
            'command'    => $sparkCommand,
            'full_cmd'   => $fullCmd,
            'created_at' => date('Y-m-d H:i:s'),
            'status'     => 'running',
            'log_file'   => $logFile,
        ];
        @file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT));

        // Launch in background depending on OS
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            pclose(popen("start /B cmd /C \"{$fullCmd} > " . escapeshellarg($logFile) . " 2>&1\"", "r"));
        } else {
            exec("{$fullCmd} > " . escapeshellarg($logFile) . " 2>&1 &");
        }

        return $meta;
    }

    /**
     * Get execution status and last lines of log for a task
     */
    public function getTaskStatus(string $taskId): array
    {
        $metaFile = $this->logDir . $taskId . '.meta.json';
        $logFile  = $this->logDir . $taskId . '.log';

        if (!is_file($metaFile)) {
            return [
                'task_id' => $taskId,
                'status'  => 'not_found',
                'error'   => 'Task metadata not found'
            ];
        }

        $meta = json_decode(file_get_contents($metaFile), true) ?: [];
        $logContent = is_file($logFile) ? file_get_contents($logFile) : '';

        // Check if finished by inspecting log content
        if (!empty($logContent)) {
            if (str_contains($logContent, 'completed successfully') || str_contains($logContent, 'Indexing complete!')) {
                $meta['status'] = 'completed';
            } elseif (str_contains($logContent, 'failed!') || str_contains($logContent, 'Fatal error:')) {
                $meta['status'] = 'failed';
            }
        }

        $meta['log_tail'] = mb_substr($logContent, -1000);
        return $meta;
    }

    /**
     * List all recent background tasks
     */
    public function listRecentTasks(int $limit = 20): array
    {
        $metaFiles = glob($this->logDir . '*.meta.json');
        if (empty($metaFiles)) {
            return [];
        }

        usort($metaFiles, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        $metaFiles = array_slice($metaFiles, 0, $limit);
        $tasks = [];

        foreach ($metaFiles as $mf) {
            $data = json_decode(file_get_contents($mf), true);
            if ($data) {
                $taskId = $data['task_id'] ?? basename($mf, '.meta.json');
                $tasks[] = $this->getTaskStatus($taskId);
            }
        }

        return $tasks;
    }
}

<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\Queue\BackgroundTaskRunner;

class QueueRunner extends BaseCommand
{
    protected $group       = 'Queue';
    protected $name        = 'task:async';
    protected $description = 'Dispatches or monitors background CLI tasks asynchronously';
    protected $usage       = 'task:async <command> [options] or task:async --list or task:async --status=<taskId>';
    protected $arguments   = [
        'cmd' => 'The target spark command to run asynchronously (e.g. sync:data, vectorize:index)'
    ];
    protected $options     = [
        '--list'   => 'List recent background tasks',
        '--status' => 'Check status of a specific task ID',
    ];

    public function run(array $params)
    {
        $runner = new BackgroundTaskRunner();

        if (CLI::getOption('list')) {
            $tasks = $runner->listRecentTasks();
            if (empty($tasks)) {
                CLI::write('No background tasks found.', 'yellow');
                return;
            }
            CLI::table($tasks, ['task_id', 'command', 'status', 'created_at']);
            return;
        }

        $statusTaskId = CLI::getOption('status');
        if (!empty($statusTaskId)) {
            $status = $runner->getTaskStatus($statusTaskId);
            CLI::write("Task ID: {$status['task_id']}", 'cyan');
            CLI::write("Status:  {$status['status']}", $status['status'] === 'completed' ? 'green' : 'yellow');
            CLI::write("Command: " . ($status['command'] ?? 'N/A'));
            if (!empty($status['log_tail'])) {
                CLI::newLine();
                CLI::write("--- Last Log Output ---", 'blue');
                CLI::write($status['log_tail']);
            }
            return;
        }

        $cmd = $params[0] ?? null;
        if (empty($cmd)) {
            CLI::error('Please provide a command to run (e.g. php spark task:async sync:data).');
            return;
        }

        CLI::write("Dispatching background task for '{$cmd}'...", 'cyan');
        $task = $runner->dispatchSparkCommand($cmd);

        CLI::write("Task dispatched successfully!", 'green');
        CLI::write("Task ID:  {$task['task_id']}", 'yellow');
        CLI::write("Log File: {$task['log_file']}");
        CLI::write("Check status anytime with: php spark task:async --status={$task['task_id']}", 'blue');
    }
}

<?php

namespace Config;

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;
use CodeIgniter\HotReloader\HotReloader;

/*
 * --------------------------------------------------------------------
 * Application Events
 * --------------------------------------------------------------------
 * Events allow you to tap into the execution of the program without
 * modifying or extending core files. This file provides a central
 * location to define your events, though they can always be added
 * at run-time, also, if needed.
 *
 * You create code that can execute by subscribing to events with
 * the 'on()' method. This accepts any form of callable, including
 * Closures, that will be executed when the event is triggered.
 *
 * Example:
 *      Events::on('create', [$myInstance, 'myMethod']);
 */

Events::on('pre_system', static function (): void {
    if (ENVIRONMENT !== 'testing') {
        $value = ini_get('zlib.output_compression');

        if (filter_var($value, FILTER_VALIDATE_BOOLEAN) || (int) $value > 0) {
            throw FrameworkException::forEnabledZlibOutputCompression();
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(static fn ($buffer) => $buffer);
    }

    /*
     * --------------------------------------------------------------------
     * Debug Toolbar Listeners.
     * --------------------------------------------------------------------
     * If you delete, they will no longer be collected.
     */
    if (CI_DEBUG && ! is_cli()) {
        Events::on('DBQuery', 'CodeIgniter\Debug\Toolbar\Collectors\Database::collect');
        service('toolbar')->respond();
        // Hot Reload route - for framework use on the hot reloader.
        if (ENVIRONMENT === 'development') {
            service('routes')->get('__hot-reload', static function (): void {
                (new HotReloader())->run();
            });
        }
    }
});

// Auto-create tenant upon registration
Events::on('register', static function (\CodeIgniter\Shield\Entities\User $user): void {
    if (!empty($user->tenant_id)) {
        return;
    }

    $db = \Config\Database::connect();
    
    // Generate a unique slug based on username or email
    $slugBase = $user->username ?: explode('@', $user->email)[0];
    $slug = url_title($slugBase, '-', true);
    
    // Ensure slug is unique
    $existing = $db->table('tenants')->where('slug', $slug)->get()->getRow();
    if ($existing) {
        $slug .= '-' . time();
    }

    $db->table('tenants')->insert([
        'name'       => $slugBase . "'s Workspace",
        'slug'       => $slug,
        'status'     => 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    $tenantId = $db->insertID('tenants_id_seq');

    // Update user's tenant_id
    $userModel = new \App\Models\UserModel();
    $userModel->bypassTenant()->update($user->id, ['tenant_id' => $tenantId]);

    // Insert user into tenant_users as owner
    $db->table('tenant_users')->insert([
        'tenant_id'  => $tenantId,
        'user_id'    => $user->id,
        'role'       => 'owner',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
});

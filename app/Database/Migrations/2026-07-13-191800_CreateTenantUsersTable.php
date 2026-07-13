<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTenantUsersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'tenant_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'role' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'default'    => 'member',
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['tenant_id', 'user_id']);
        $this->forge->createTable('tenant_users');

        // Migrate existing users to have a record in tenant_users table
        $db = \Config\Database::connect();
        $users = $db->table('users')->get()->getResultArray();
        
        $batch = [];
        foreach ($users as $user) {
            if (!empty($user['tenant_id'])) {
                $batch[] = [
                    'tenant_id'  => $user['tenant_id'],
                    'user_id'    => $user['id'],
                    'role'       => 'owner', // Default existing to owner
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            }
        }
        
        if (!empty($batch)) {
            $db->table('tenant_users')->insertBatch($batch);
        }
    }

    public function down()
    {
        $this->forge->dropTable('tenant_users', true);
    }
}

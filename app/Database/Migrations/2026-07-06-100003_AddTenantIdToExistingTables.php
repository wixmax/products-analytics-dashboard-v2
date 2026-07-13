<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTenantIdToExistingTables extends Migration
{
    private array $tables = ['products', 'collections', 'watched_stores', 'settings', 'data_snapshots'];

    public function up()
    {
        foreach ($this->tables as $table) {
            $this->forge->addColumn($table, [
                'tenant_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
            ]);
            $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'SET NULL', 'CASCADE');
        }
    }

    public function down()
    {
        foreach ($this->tables as $table) {
            $this->forge->dropForeignKey($table, "{$table}_tenant_id_foreign");
            $this->forge->dropColumn($table, 'tenant_id');
        }
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddApiVersionAndSnapshots extends Migration
{
    public function up()
    {
        // 1. Create data_snapshots table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'origin' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
            ],
            'api_version' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
            ],
            'raw_json' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'product_count' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
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
        $this->forge->createTable('data_snapshots');

        // 2. Add columns to products table
        $this->forge->addColumn('products', [
            'api_version' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
                'after'      => 'origin',
            ],
            'snapshot_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
                'after'      => 'api_version',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('products', ['api_version', 'snapshot_id']);
        $this->forge->dropTable('data_snapshots', true);
    }
}

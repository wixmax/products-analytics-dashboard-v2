<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSavedAdsColumnsAndTables extends Migration
{
    public function up()
    {
        // 1. Add saved columns to products table
        $this->forge->addColumn('products', [
            'is_saved' => [
                'type'    => 'BOOLEAN',
                'default' => false,
            ],
            'saved_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'rating' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'saved_status' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'default'    => 'active',
            ],
            'collection' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'default'    => 'عامة',
            ],
        ]);

        // 2. Create collections table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
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
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('collections');

        // Insert default collections
        $db = \Config\Database::connect();
        $db->table('collections')->insertBatch([
            ['name' => 'عامة', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['name' => 'ملابس', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['name' => 'إلكترونيات', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['name' => 'أدوات منزلية', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
        ]);

        // 3. Create watched_stores table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'domain' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
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
        $this->forge->addUniqueKey('domain');
        $this->forge->createTable('watched_stores');
    }

    public function down()
    {
        // Drop watched_stores table
        $this->forge->dropTable('watched_stores', true);

        // Drop collections table
        $this->forge->dropTable('collections', true);

        // Drop columns from products table
        $this->forge->dropColumn('products', [
            'is_saved', 'saved_at', 'rating', 'notes', 'saved_status', 'collection'
        ]);
    }
}

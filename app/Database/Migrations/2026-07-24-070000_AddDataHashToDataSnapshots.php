<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDataHashToDataSnapshots extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('data_snapshots')) {
            if (!$this->db->fieldExists('data_hash', 'data_snapshots')) {
                $this->forge->addColumn('data_snapshots', [
                    'data_hash' => [
                        'type'       => 'VARCHAR',
                        'constraint' => '32',
                        'null'       => true,
                        'default'    => null,
                        'after'      => 'product_count',
                    ],
                ]);
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('data_snapshots')) {
            if ($this->db->fieldExists('data_hash', 'data_snapshots')) {
                $this->forge->dropColumn('data_snapshots', 'data_hash');
            }
        }
    }
}

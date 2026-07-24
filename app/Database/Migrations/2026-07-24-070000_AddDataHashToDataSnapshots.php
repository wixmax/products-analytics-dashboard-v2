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

            // Backfill data_hash for legacy snapshots
            $legacySnapshots = $this->db->table('data_snapshots')
                ->select('id, raw_json')
                ->where('data_hash IS NULL OR data_hash = \'\'')
                ->get()
                ->getResultArray();

            foreach ($legacySnapshots as $snap) {
                if (!empty($snap['raw_json'])) {
                    $hash = md5($snap['raw_json']);
                    $this->db->table('data_snapshots')
                        ->where('id', $snap['id'])
                        ->update(['data_hash' => $hash]);
                }
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

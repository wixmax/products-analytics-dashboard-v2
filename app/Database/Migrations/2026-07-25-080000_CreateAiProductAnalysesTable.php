<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAiProductAnalysesTable extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('ai_product_analyses')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'user_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => false,
                    'default'    => 0,
                ],
                'tenant_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'default'    => null,
                ],
                'title' => [
                    'type'       => 'VARCHAR',
                    'constraint' => '255',
                    'null'       => false,
                    'default'    => 'تحليل المنتجات بالذكاء الاصطناعي',
                ],
                'analysis_mode' => [
                    'type'       => 'VARCHAR',
                    'constraint' => '50',
                    'null'       => false,
                    'default'    => 'comprehensive',
                ],
                'parameters_json' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'summary_stats_json' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'results_json' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('user_id');
            $this->forge->createTable('ai_product_analyses', true);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('ai_product_analyses')) {
            $this->forge->dropTable('ai_product_analyses', true);
        }
    }
}

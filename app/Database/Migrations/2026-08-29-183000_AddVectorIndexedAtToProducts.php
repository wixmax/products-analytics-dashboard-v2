<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddVectorIndexedAtToProducts extends Migration
{
    public function up()
    {
        $fields = [
            'vector_indexed_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
                'default' => null,
            ],
        ];

        $this->forge->addColumn('products', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('products', 'vector_indexed_at');
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddActivityDataToProducts extends Migration
{
    public function up()
    {
        $this->forge->addColumn('products', [
            'activity_data' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('products', ['activity_data']);
    }
}

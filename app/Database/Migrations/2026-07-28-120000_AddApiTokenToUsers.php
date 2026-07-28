<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddApiTokenToUsers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'api_token' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
                'unique'     => true,
                'after'      => 'username',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'api_token');
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterSettingsTablePrimaryKey extends Migration
{
    public function up()
    {
        // 1. Drop the primary key constraint on settings
        $this->db->query('ALTER TABLE settings DROP CONSTRAINT IF EXISTS pk_settings');

        // 2. Add an auto-incrementing id column as the new Primary Key
        $this->db->query('ALTER TABLE settings ADD COLUMN id SERIAL PRIMARY KEY');

        // 3. Add a unique constraint on (tenant_id, key)
        $this->db->query('ALTER TABLE settings ADD CONSTRAINT settings_tenant_key_unique UNIQUE (tenant_id, key)');
    }

    public function down()
    {
        // 1. Drop the unique constraint
        $this->db->query('ALTER TABLE settings DROP CONSTRAINT IF EXISTS settings_tenant_key_unique');

        // 2. Drop the id column
        $this->db->query('ALTER TABLE settings DROP COLUMN IF EXISTS id');

        // 3. Re-add the primary key on 'key'
        $this->db->query('ALTER TABLE settings ADD CONSTRAINT pk_settings PRIMARY KEY (key)');
    }
}

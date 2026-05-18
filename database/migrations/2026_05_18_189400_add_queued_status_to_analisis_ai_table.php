<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE analisis_ai
            MODIFY COLUMN status ENUM('queued', 'pending', 'running', 'completed', 'failed')
            NOT NULL DEFAULT 'queued'
        ");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE analisis_ai
            MODIFY COLUMN status ENUM('pending', 'running', 'completed', 'failed')
            NOT NULL DEFAULT 'pending'
        ");
    }
};

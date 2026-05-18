<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analisis_ai', function (Blueprint $table) {
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])
                ->default('pending')
                ->after('jenis');
        });
    }

    public function down(): void
    {
        Schema::table('analisis_ai', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};

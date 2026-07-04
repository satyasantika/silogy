<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mk', function (Blueprint $table) {
            $table->foreignUuid('koordinator_mk_id')->nullable()
                ->after('academic_unit_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mk', function (Blueprint $table) {
            $table->dropConstrainedForeignId('koordinator_mk_id');
        });
    }
};

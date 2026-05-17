<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_unit_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_unit_id')
                ->constrained('academic_units')
                ->cascadeOnDelete();
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->boolean('status_pimpinan')->default(false);
            $table->boolean('status_tim_kurikulum')->default(false);
            $table->string('jabatan', 100)->nullable();
            $table->timestamps();

            $table->unique(['academic_unit_id', 'user_id'], 'uq_auu_unit_user');
            $table->index('user_id', 'idx_auu_user');
            $table->index('status_pimpinan', 'idx_auu_pimpinan');
            $table->index('status_tim_kurikulum', 'idx_auu_timkur');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_unit_users');
    }
};

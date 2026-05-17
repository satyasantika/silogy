<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpl_profil_lulusan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cpl_id')
                ->constrained('cpl')
                ->cascadeOnDelete();
            $table->foreignUuid('profil_lulusan_id')
                ->constrained('profil_lulusan')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['cpl_id', 'profil_lulusan_id'], 'uq_cpl_profil');
        });

        Schema::create('cpl_bok', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cpl_id')
                ->constrained('cpl')
                ->cascadeOnDelete();
            $table->foreignUuid('bok_id')
                ->constrained('bok')
                ->cascadeOnDelete();
            $table->decimal('bobot', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['cpl_id', 'bok_id'], 'uq_cpl_bok');
        });

        Schema::create('cpl_mk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cpl_bok_id')
                ->constrained('cpl_bok')
                ->cascadeOnDelete();
            $table->foreignUuid('mk_id')
                ->constrained('mk')
                ->cascadeOnDelete();
            $table->decimal('bobot', 5, 2);
            $table->timestamps();

            $table->unique(['cpl_bok_id', 'mk_id'], 'uq_cpl_mk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpl_mk');
        Schema::dropIfExists('cpl_bok');
        Schema::dropIfExists('cpl_profil_lulusan');
    }
};

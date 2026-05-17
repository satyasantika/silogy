<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('parent_id')->nullable()
                ->constrained('academic_units')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->enum('type', ['university', 'faculty', 'department', 'study_program']);
            $table->string('code', 30)->nullable();
            $table->string('kode_pddikti', 30)->nullable();
            $table->string('nama', 150);
            $table->string('singkatan', 30)->nullable();
            $table->string('jenjang', 10)->nullable();
            $table->string('gelar_lulusan', 50)->nullable();
            $table->string('mapel', 100)->nullable();
            $table->text('visi')->nullable();
            $table->text('misi')->nullable();
            $table->text('tujuan')->nullable();
            $table->text('sasaran_strategis')->nullable();
            $table->text('alamat')->nullable();
            $table->string('no_telepon', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('website', 100)->nullable();
            $table->string('logo_path', 200)->nullable();
            $table->string('tahun_pendirian', 4)->nullable();
            $table->string('sk_pendirian', 100)->nullable();
            $table->string('tahun_akreditasi', 4)->nullable();
            $table->string('sk_akreditasi', 100)->nullable();
            $table->string('peringkat_akreditasi', 20)->nullable();
            $table->string('tahun_internasional', 4)->nullable();
            $table->string('sk_internasional', 100)->nullable();
            $table->enum('status', ['draft', 'aktif', 'nonaktif'])->default('draft');
            $table->timestamps();

            $table->index('type', 'idx_au_type');
            $table->index('parent_id', 'idx_au_parent');
            $table->index('code', 'idx_au_code');
            $table->unique('kode_pddikti', 'uq_au_pddikti');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_units');
    }
};

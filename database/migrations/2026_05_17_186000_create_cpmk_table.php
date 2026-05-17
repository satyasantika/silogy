<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpmk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mk_id')
                ->constrained('mk')
                ->cascadeOnDelete();
            $table->string('kode', 15);
            $table->text('deskripsi');
            $table->timestamps();

            $table->index('mk_id', 'idx_cpmk_mk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpmk');
    }
};

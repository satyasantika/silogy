<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Urutan tampil CPL/BoK MILIK kurikulum ini sendiri. Untuk CPL/BoK asing
 * yang tersingkap lewat adaptasi, urutan disimpan terpisah per unit pada
 * cpl_kode_overrides/bok_kode_overrides (lihat migrasi berikutnya) —
 * TIDAK di kolom ini, supaya preferensi urutan satu prodi tidak
 * mempengaruhi tampilan unit pemilik asli atau prodi lain yang sama-sama
 * mengadaptasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cpl', function (Blueprint $table) {
            $table->unsignedInteger('urutan')->nullable()->after('kode');
        });

        Schema::table('bok', function (Blueprint $table) {
            $table->unsignedInteger('urutan')->nullable()->after('kode');
        });

        $this->backfill('cpl');
        $this->backfill('bok');
    }

    protected function backfill(string $table): void
    {
        $kurikulumIds = DB::table($table)->select('kurikulum_id')->distinct()->pluck('kurikulum_id');

        foreach ($kurikulumIds as $kurikulumId) {
            $ids = DB::table($table)
                ->where('kurikulum_id', $kurikulumId)
                ->orderBy('created_at')
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids as $index => $id) {
                DB::table($table)->where('id', $id)->update(['urutan' => $index + 1]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('bok', function (Blueprint $table) {
            $table->dropColumn('urutan');
        });

        Schema::table('cpl', function (Blueprint $table) {
            $table->dropColumn('urutan');
        });
    }
};

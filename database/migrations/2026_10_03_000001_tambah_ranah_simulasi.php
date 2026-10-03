<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ranah simulasi: setiap sandbox latihan hidup di "ranah"-nya sendiri.
 *
 * Penanda `sandbox_id` hanya dipasang di tiga tabel akar (unit, pengguna,
 * mahasiswa). Tabel turunan (kurikulum, CPL, MK, kelas, …) mengikuti lewat
 * subquery unit pada global scope, sehingga tidak perlu menambah kolom di
 * puluhan tabel. NULL berarti data inti (nyata).
 *
 * Kolom tambahan di `simulasi_jalan` mencatat pemilik sandbox (hash cookie
 * pengunjung) dan aktivitas terakhirnya untuk pembersihan otomatis.
 * `simulasi_pengaturan` menampung sakelar global mode latihan: sakelar
 * lama menempel pada satu jalan, padahal sandbox kini dibuat sesuai
 * permintaan sehingga sakelarnya harus ada lebih dulu daripada jalannya.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tabelAkar = ['academic_units', 'users', 'mahasiswas'];

    public function up(): void
    {
        foreach ($this->tabelAkar as $tabel) {
            Schema::table($tabel, function (Blueprint $table): void {
                $table->char('sandbox_id', 36)->nullable()->index();
            });
        }

        Schema::table('simulasi_jalan', function (Blueprint $table): void {
            $table->string('pengunjung', 64)->nullable()->index();
            $table->timestamp('terakhir_aktif_pada')->nullable();
        });

        Schema::create('simulasi_pengaturan', function (Blueprint $table): void {
            $table->string('kunci', 50)->primary();
            $table->string('nilai', 255)->nullable();
            $table->timestamps();
        });

        $this->pindahkanSimulasiLama();

        // Sakelar mode latihan lama menempel pada jalan; bawa nilainya ke sakelar global.
        $nyala = DB::table('simulasi_jalan')->where('status', '!=', 'dibongkar')->where('coba_peran', true)->exists();

        DB::table('simulasi_pengaturan')->insert([
            'kunci' => 'coba_peran',
            'nilai' => $nyala ? '1' : '0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('simulasi_pengaturan');

        Schema::table('simulasi_jalan', function (Blueprint $table): void {
            $table->dropIndex(['pengunjung']);
            $table->dropColumn(['pengunjung', 'terakhir_aktif_pada']);
        });

        foreach ($this->tabelAkar as $tabel) {
            Schema::table($tabel, function (Blueprint $table): void {
                $table->dropIndex(['sandbox_id']);
                $table->dropColumn('sandbox_id');
            });
        }
    }

    /**
     * Simulasi tunggal buatan versi lama dijadikan satu sandbox: seluruh akar
     * yang tercatat di buku besarnya diberi penanda, sehingga data lama itu
     * ikut tersembunyi dari akun inti. Tanpa ini, sisa simulasi lama tetap
     * tampak sebagai data nyata.
     */
    private function pindahkanSimulasiLama(): void
    {
        $peta = [
            'App\\Modules\\Institusi\\Models\\AcademicUnit' => 'academic_units',
            'App\\Models\\User' => 'users',
            'App\\Modules\\Mahasiswa\\Models\\Mahasiswa' => 'mahasiswas',
        ];

        $jalanHidup = DB::table('simulasi_jalan')
            ->where('status', '!=', 'dibongkar')
            ->get(['id', 'mulai_pada']);

        foreach ($jalanHidup as $jalan) {
            foreach ($peta as $kelas => $tabel) {
                DB::table('simulasi_artefak')
                    ->where('simulasi_jalan_id', $jalan->id)
                    ->where('model_type', $kelas)
                    ->orderBy('id')
                    ->select('model_uuid')
                    ->chunk(500, function ($baris) use ($tabel, $jalan): void {
                        DB::table($tabel)
                            ->whereIn('id', $baris->pluck('model_uuid')->all())
                            ->update(['sandbox_id' => $jalan->id]);
                    });
            }

            DB::table('simulasi_jalan')
                ->where('id', $jalan->id)
                ->update(['terakhir_aktif_pada' => $jalan->mulai_pada]);
        }
    }
};

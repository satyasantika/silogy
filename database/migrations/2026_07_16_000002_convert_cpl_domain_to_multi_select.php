<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Domain CPL berubah dari pilihan tunggal (termasuk 'gabungan') menjadi
 * multiselect kognitif/afektif/psikomotorik. Baris lama berdomain
 * 'gabungan' diterjemahkan sebagai ketiga domain sekaligus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cpl', function (Blueprint $table) {
            $table->json('domain_baru')->nullable()->after('domain');
        });

        DB::table('cpl')->whereNotNull('domain')->orderBy('id')->select('id', 'domain')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $domains = $row->domain === 'gabungan'
                        ? ['kognitif', 'afektif', 'psikomotorik']
                        : [$row->domain];

                    DB::table('cpl')->where('id', $row->id)->update([
                        'domain_baru' => json_encode($domains),
                    ]);
                }
            });

        Schema::table('cpl', function (Blueprint $table) {
            $table->dropColumn('domain');
        });

        Schema::table('cpl', function (Blueprint $table) {
            $table->renameColumn('domain_baru', 'domain');
        });
    }

    public function down(): void
    {
        Schema::table('cpl', function (Blueprint $table) {
            $table->enum('domain_lama', ['kognitif', 'afektif', 'psikomotorik', 'gabungan'])->nullable()->after('domain');
        });

        DB::table('cpl')->whereNotNull('domain')->orderBy('id')->select('id', 'domain')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $domains = json_decode((string) $row->domain, true) ?? [];

                    DB::table('cpl')->where('id', $row->id)->update([
                        'domain_lama' => count($domains) > 1 ? 'gabungan' : ($domains[0] ?? null),
                    ]);
                }
            });

        Schema::table('cpl', function (Blueprint $table) {
            $table->dropColumn('domain');
        });

        Schema::table('cpl', function (Blueprint $table) {
            $table->renameColumn('domain_lama', 'domain');
        });
    }
};

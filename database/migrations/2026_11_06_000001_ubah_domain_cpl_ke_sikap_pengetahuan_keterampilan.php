<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Domain CPL mengikuti label Permendiktisaintek 39/2025: sikap,
 * pengetahuan, keterampilan. Peta disalin di sini (bukan dari
 * CplResource) supaya migrasi tidak ikut berubah bila kode aplikasi
 * berubah. Nilai yang tidak dikenal dibiarkan apa adanya.
 */
return new class extends Migration
{
    private const PETA = [
        'afektif' => 'sikap',
        'kognitif' => 'pengetahuan',
        'psikomotorik' => 'keterampilan',
    ];

    public function up(): void
    {
        $this->petakan(self::PETA);
    }

    public function down(): void
    {
        $this->petakan(array_flip(self::PETA));
    }

    /**
     * @param  array<string, string>  $peta
     */
    private function petakan(array $peta): void
    {
        DB::table('cpl')->whereNotNull('domain')->orderBy('id')->select('id', 'domain')
            ->chunkById(500, function ($rows) use ($peta): void {
                foreach ($rows as $row) {
                    $domains = json_decode((string) $row->domain, true);

                    if (! is_array($domains) || $domains === []) {
                        continue;
                    }

                    $baru = array_values(array_unique(array_map(
                        fn (mixed $domain): mixed => is_string($domain) ? ($peta[$domain] ?? $domain) : $domain,
                        $domains,
                    )));

                    DB::table('cpl')->where('id', $row->id)->update(['domain' => json_encode($baru)]);
                }
            });
    }
};

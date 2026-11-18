<?php

namespace App\Console\Commands;

use App\Modules\Kalkulasi\Jobs\RecalkulasiCplJob;
use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use Illuminate\Console\Command;

/**
 * Hitung ulang rantai kalkulasi CPL (Sub-CPMK → CPMK → CPL×MK → CPL×Unit →
 * cache dashboard) untuk setiap kelas yang sudah punya hasil_cpl_mk.
 *
 * Dipakai satu kali setelah rumus nilai_berbobot berubah (bobot CPL×BoK tidak
 * lagi ikut dikalikan), sebab kalkulasi hanya berjalan ulang saat nilai
 * berubah. Idempoten: menjalankannya berulang menghasilkan angka yang sama.
 */
class HitungUlangCpl extends Command
{
    protected $signature = 'kalkulasi:hitung-ulang-cpl
                            {--sync : Hitung langsung di proses ini, bukan lewat antrean}
                            {--unit= : Batasi ke satu unit akademik (UUID)}
                            {--semester= : Batasi ke satu semester (UUID)}';

    protected $description = 'Hitung ulang kalkulasi CPL seluruh kelas yang sudah punya hasil CPL×MK';

    public function handle(): int
    {
        $unitId = $this->opsi('unit');
        $semesterId = $this->opsi('semester');
        $sync = (bool) $this->option('sync');

        $kelasList = KelasMk::query()
            ->with('mkUnit')
            ->whereHas('kelasMkMahasiswas', function ($query) {
                $query->whereExists(
                    HasilCplMk::query()
                        ->selectRaw('1')
                        ->whereColumn('hasil_cpl_mk.kelas_mk_mahasiswa_id', (new KelasMkMahasiswa)->getTable().'.id'),
                );
            })
            ->when($semesterId !== null, fn ($query) => $query->where('semester_id', $semesterId))
            ->when($unitId !== null, fn ($query) => $query->whereHas(
                'mkUnit',
                fn ($q) => $q->where('academic_unit_id', $unitId),
            ))
            ->get();

        $terkirim = 0;

        foreach ($kelasList as $kelas) {
            $academicUnitId = $kelas->mkUnit?->academic_unit_id;

            if ($academicUnitId === null || $kelas->semester_id === null) {
                continue;
            }

            if ($sync) {
                RecalkulasiCplJob::dispatchSync($kelas->id, $academicUnitId, $kelas->semester_id);
            } else {
                RecalkulasiCplJob::dispatch($kelas->id, $academicUnitId, $kelas->semester_id)
                    ->onQueue('cpl-calculation');
            }

            $terkirim++;
        }

        $this->info(sprintf(
            'Hitung ulang CPL: %d kelas %s.',
            $terkirim,
            $sync ? 'dihitung langsung' : 'diantrekan ke cpl-calculation',
        ));

        return self::SUCCESS;
    }

    private function opsi(string $nama): ?string
    {
        $nilai = $this->option($nama);

        return is_string($nilai) && $nilai !== '' ? $nilai : null;
    }
}

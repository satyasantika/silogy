<?php

namespace App\Console\Commands;

use App\Modules\MK\Services\KonsolidasiLintasSemesterService;
use App\Modules\MK\Support\Konsolidasi\GrupKonsolidasi;
use App\Modules\MK\Support\Konsolidasi\NilaiTerbuang;
use App\Modules\MK\Support\Konsolidasi\RencanaKonsolidasi;
use Illuminate\Console\Command;

/**
 * Backfill satu-kali: lebur baris CPMK/Sub-CPMK/Asesmen kembar lintas
 * semester menjadi satu baris kanonik, lalu lampirkan baris itu ke seluruh
 * semester tempat anggotanya dulu berada.
 *
 * Tanpa --terapkan perintah ini hanya MEMBACA dan mencetak laporan, jadi
 * aman dijalankan lebih dulu pada salinan data produksi untuk memeriksa apa
 * yang akan digabung sebelum ada yang berubah.
 */
class KonsolidasiCpmkLintasSemester extends Command
{
    protected $signature = 'mk:konsolidasi-lintas-semester
                            {--mk= : Batasi ke satu mata kuliah (UUID)}
                            {--terapkan : Jalankan peleburan; tanpa flag ini hanya laporan}';

    protected $description = 'Laporkan (atau lebur) baris CPMK/Sub-CPMK/Asesmen kembar antar semester menjadi satu baris kanonik';

    public function handle(KonsolidasiLintasSemesterService $service): int
    {
        if (! $service->skemaLamaMasihAda()) {
            $this->info(
                'Basis data ini sudah memakai pivot semester — konsolidasi sudah dijalankan '
                .'dan baris kembar tidak bisa lahir lagi (dicegah UQ kode per MK).',
            );

            return self::SUCCESS;
        }

        $mkId = $this->option('mk');
        $mkId = is_string($mkId) && $mkId !== '' ? $mkId : null;

        $rencana = $service->rencana($mkId);

        if ($rencana->kosong()) {
            $this->info('Tidak ada baris kembar lintas semester — tidak ada yang perlu dilebur.');

            return self::SUCCESS;
        }

        $this->laporkan($rencana);

        if (! $this->option('terapkan')) {
            $this->newLine();
            $this->comment('Ini baru laporan. Jalankan ulang dengan --terapkan untuk mengeksekusinya.');

            return self::SUCCESS;
        }

        $hasil = $service->terapkan($mkId);

        $this->newLine();
        $this->info(sprintf(
            'Selesai. %d baris dilebur, %d nilai mahasiswa dibuang, %d kelas MK perlu dihitung ulang.',
            $hasil->barisDilebur(),
            count($hasil->nilaiTerbuang),
            count($hasil->kelasMkTerdampak),
        ));
        $this->comment('Jalankan ulang kalkulasi CPL agar hasil_* terisi kembali.');

        return self::SUCCESS;
    }

    private function laporkan(RencanaKonsolidasi $rencana): void
    {
        foreach ([
            GrupKonsolidasi::ENTITAS_CPMK => 'CPMK',
            GrupKonsolidasi::ENTITAS_SUBCPMK => 'Sub-CPMK',
            GrupKonsolidasi::ENTITAS_KOMPONEN => 'Asesmen',
        ] as $entitas => $label) {
            $this->laporkanGrup($label, $rencana->grupUntuk($entitas));
        }

        $this->laporkanKodeKosong($rencana);
        $this->laporkanNilaiTerbuang($rencana);

        $this->newLine();
        $this->line(sprintf(
            'Total: %d baris akan dilebur; %d kelas MK perlu dihitung ulang setelahnya.',
            $rencana->barisDilebur(),
            count($rencana->kelasMkTerdampak),
        ));
    }

    /**
     * @param  list<GrupKonsolidasi>  $grup
     */
    private function laporkanGrup(string $label, array $grup): void
    {
        if ($grup === []) {
            return;
        }

        $this->newLine();
        $this->line("<options=bold>{$label} — ".count($grup).' kelompok kembar</>');

        $this->table(
            ['Mata Kuliah', 'Kode', 'Baris', 'ID kanonik', 'Semester', 'Catatan'],
            array_map(fn (GrupKonsolidasi $item): array => [
                $item->mkNama,
                $item->kode,
                $item->jumlahBaris(),
                $item->idKanonik,
                count($item->semesterIds),
                $this->catatanGrup($item),
            ], $grup),
        );
    }

    private function catatanGrup(GrupKonsolidasi $item): string
    {
        $catatan = [];

        if ($item->bedaKolom !== []) {
            $catatan[] = 'isi berbeda: '.implode(', ', $item->bedaKolom);
        }

        if ($item->kembarDalamSemesterSama) {
            $catatan[] = 'kembar dalam semester yang sama';
        }

        return $catatan === [] ? '—' : implode('; ', $catatan);
    }

    private function laporkanKodeKosong(RencanaKonsolidasi $rencana): void
    {
        if ($rencana->komponenTanpaKode === []) {
            return;
        }

        $this->newLine();
        $this->warn(
            'Asesmen tanpa kode ('.count($rencana->komponenTanpaKode).' baris) — '
            .'kode akan diturunkan dari namanya, karena UQ baru (mk_id, kode) tidak mengizinkan kode kosong berulang:',
        );

        $this->table(
            ['Mata Kuliah', 'Nama asesmen', 'ID'],
            array_map(fn (array $baris): array => [
                $baris['mk'],
                $baris['nama'],
                $baris['id'],
            ], $rencana->komponenTanpaKode),
        );
    }

    private function laporkanNilaiTerbuang(RencanaKonsolidasi $rencana): void
    {
        if ($rencana->nilaiTerbuang === []) {
            return;
        }

        $this->newLine();
        $this->error(
            'PERHATIAN — '.count($rencana->nilaiTerbuang).' nilai mahasiswa akan dibuang. '
            .'Ini terjadi bila mahasiswa yang sama sudah punya nilai pada pemetaan tujuan:',
        );

        $this->table(
            ['NIM', 'Mahasiswa', 'Sub-CPMK', 'Asesmen', 'Nilai dibuang', 'Nilai dipertahankan'],
            array_map(fn (NilaiTerbuang $item): array => [
                $item->nim,
                $item->mahasiswa,
                $item->subcpmkKode,
                $item->asesmenKode,
                $item->nilai ?? '—',
                $item->nilaiDipertahankan ?? '—',
            ], $rencana->nilaiTerbuang),
        );
    }
}

<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\Kalkulasi\Models\HasilSubcpmk;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;

/**
 * Ketercapaian tiap Sub-CPMK pada satu kelas, dibaca dari hasil_subcpmk yang
 * sudah dihitung. Sub-CPMK yang hanya diukur lewat asesmen bersama yang tidak
 * dirinci angkanya sama dengan Sub-CPMK lain pada asesmen itu, sehingga tidak
 * boleh dibandingkan: ia ditandai "tak_dibedakan" dan tidak pernah "terlemah".
 */
class KetercapaianSubcpmkService
{
    public function __construct(private readonly PenilaianMatrixService $matrix) {}

    /**
     * @param  array<string, list<array{pivot_id: string, subcpmk_kode: string, bobot: float}>>  $rincian  asesmen dirinci (kunci: id komponen)
     * @return list<array{kode: string, nama: string|null, rata_rata: float|null, mencapai: int, dinilai: int, status: 'tercapai'|'lemah'|null, dasar: 'terpisah'|'tak_dibedakan', terlemah: bool}>
     */
    public function untukKelas(KelasMk $kelasMk, array $rincian, int $target): array
    {
        /** @var array<string, array{kode: string, nama: string|null, terpisah: bool}> $subcpmks */
        $subcpmks = [];

        foreach ($this->matrix->komponenUntukKelas($kelasMk) as $komponen) {
            $jumlahPivot = $komponen->subcpmkKomponens->count();
            $terpisah = isset($rincian[$komponen->id]) || $jumlahPivot === 1;

            foreach ($komponen->subcpmkKomponens as $pivot) {
                $subcpmk = $pivot->subcpmk;

                if ($subcpmk === null) {
                    continue;
                }

                $id = (string) $subcpmk->id;
                $subcpmks[$id] ??= [
                    'kode' => (string) $subcpmk->kode,
                    'nama' => $subcpmk->deskripsi !== null ? (string) $subcpmk->deskripsi : null,
                    'terpisah' => false,
                ];
                $subcpmks[$id]['terpisah'] = $subcpmks[$id]['terpisah'] || $terpisah;
            }
        }

        if ($subcpmks === []) {
            return [];
        }

        $kmmIds = KelasMkMahasiswa::query()->where('kelas_mk_id', $kelasMk->id)->pluck('id');

        $nilaiPerSubcpmk = HasilSubcpmk::query()
            ->whereIn('subcpmk_id', array_keys($subcpmks))
            ->whereIn('kelas_mk_mahasiswa_id', $kmmIds)
            ->whereNotNull('nilai_akhir')
            ->get(['subcpmk_id', 'nilai_akhir'])
            ->groupBy('subcpmk_id');

        $baris = [];

        foreach ($subcpmks as $id => $info) {
            $nilai = collect($nilaiPerSubcpmk->get($id, []))
                ->map(fn (HasilSubcpmk $h): float => (float) $h->nilai_akhir);

            $dinilai = $nilai->count();
            $rataRata = $dinilai > 0 ? round($nilai->avg(), 2) : null;

            $baris[] = [
                'kode' => $info['kode'],
                'nama' => $info['nama'],
                'rata_rata' => $rataRata,
                'mencapai' => $nilai->filter(fn (float $n): bool => $n >= $target)->count(),
                'dinilai' => $dinilai,
                'status' => $rataRata === null ? null : ($rataRata >= $target ? 'tercapai' : 'lemah'),
                'dasar' => $info['terpisah'] ? 'terpisah' : 'tak_dibedakan',
                'terlemah' => false,
            ];
        }

        usort($baris, fn (array $a, array $b): int => $a['kode'] <=> $b['kode']);

        return $this->tandaiTerlemah($baris);
    }

    /**
     * Tanda "terlemah" hanya di antara baris terukur terpisah yang punya
     * rata-rata, dan hanya bila ada dua baris atau lebih; seri semua ditandai.
     *
     * @param  list<array<string, mixed>>  $baris
     * @return list<array<string, mixed>>
     */
    private function tandaiTerlemah(array $baris): array
    {
        $pembanding = array_filter(
            $baris,
            fn (array $b): bool => $b['dasar'] === 'terpisah' && $b['rata_rata'] !== null,
        );

        if (count($pembanding) < 2) {
            return $baris;
        }

        $terendah = min(array_column($pembanding, 'rata_rata'));

        foreach ($pembanding as $i => $b) {
            if ($b['rata_rata'] === $terendah) {
                $baris[$i]['terlemah'] = true;
            }
        }

        return $baris;
    }
}

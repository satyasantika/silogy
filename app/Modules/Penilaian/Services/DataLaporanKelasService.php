<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\Kelas\Models\KelasMk;

/**
 * Sumber tunggal data laporan satu KelasMk: dipakai halaman laporan
 * (HasLaporanKelasMk) dan pembangun contoh terisi simulasi, sehingga sidik
 * validasi yang tersimpan sama persis dengan yang dihitung halaman.
 */
class DataLaporanKelasService
{
    /**
     * @return array{
     *     columns: list<array<string, mixed>>, kolomEvaluasi: list<array<string, mixed>>,
     *     rows: list<array<string, mixed>>, portofolioRows: list<array<string, mixed>>,
     *     nilai: array<string, array<string, string|null>>,
     *     rincian: array<string, list<array{pivot_id: string, subcpmk_kode: string, bobot: float}>>,
     *     nilaiRinci: array<string, array<string, string|null>>, targetCapaianLulusan: int,
     *     ketercapaianCpl: list<array<string, mixed>>, ketercapaianSubcpmk: list<array<string, mixed>>, distribusiNilaiHuruf: list<array<string, mixed>>,
     *     detailCplCpmkSubcpmk: list<array<string, mixed>>, rencanaEvaluasi: array<string, mixed>|null,
     *     blokRtl: array<string, mixed>, identitas: array{mk_nama: string|null, mk_kode: string|null, sks: int, dosen: string|null, semester_id: string}
     * }
     */
    public function muat(KelasMk $kelasMk): array
    {
        $matrix = app(PenilaianMatrixService::class);
        $komponens = $matrix->komponenUntukKelas($kelasMk);
        $semesterId = (string) $kelasMk->semester_id;

        $rows = $matrix->barisUntukKelas($kelasMk);

        $evaluasiCpl = app(EvaluasiCplService::class);
        $rincian = app(RincianNilaiService::class)->rincianUntukKelas($kelasMk);
        $nilaiRinci = $matrix->nilaiRinciUntukMatrix($rows, $rincian);
        $nilai = $matrix->terapkanRincian(
            $matrix->nilaiUntukMatrix($rows, $matrix->pivotIdsByKomponen($komponens)),
            $nilaiRinci,
            $rincian,
        );
        $evaluasiCpl->jalankanKalkulasiSinkron($kelasMk);
        $ketercapaianCpl = $evaluasiCpl->ketercapaianCplPerKelas($kelasMk);
        $target = $evaluasiCpl->targetCapaianLulusan($kelasMk);

        return [
            'columns' => $matrix->kolomDariKomponens($komponens, $semesterId),
            'kolomEvaluasi' => $matrix->kolomEvaluasiDariKomponens($komponens, $semesterId),
            'rows' => $rows,
            'portofolioRows' => $matrix->barisUntukKelas($kelasMk, 'mahasiswas.nim'),
            'nilai' => $nilai,
            'rincian' => $rincian,
            'nilaiRinci' => $nilaiRinci,
            'targetCapaianLulusan' => $target,
            'ketercapaianCpl' => $ketercapaianCpl,
            // Turunan dari data yang sudah masuk sidik; sengaja tidak ikut untukSidik().
            'ketercapaianSubcpmk' => app(KetercapaianSubcpmkService::class)->untukKelas($kelasMk, $rincian, $target),
            'distribusiNilaiHuruf' => $evaluasiCpl->distribusiNilaiHuruf($kelasMk),
            'detailCplCpmkSubcpmk' => $evaluasiCpl->detailCplCpmkSubcpmk($kelasMk),
            'rencanaEvaluasi' => app(RencanaEvaluasiService::class)->build($kelasMk->mkUnit?->mk_id, $kelasMk->semester_id),
            'blokRtl' => app(CatatanRtlService::class)->blok($kelasMk, $ketercapaianCpl),
            'identitas' => $this->identitas($kelasMk),
        ];
    }

    /**
     * Identitas kelas (Blok A laporan) dibaca dari KelasMk itu sendiri — bukan
     * dari pilihan halaman — agar halaman dan pembangun simulasi menghasilkan
     * sidik yang sama.
     *
     * @return array{mk_nama: string|null, mk_kode: string|null, sks: int, dosen: string|null, semester_id: string}
     */
    public function identitas(KelasMk $kelasMk): array
    {
        $kelasMk->loadMissing(['mkUnit.mk', 'dosenPengampu']);

        return [
            'mk_nama' => $kelasMk->mkUnit?->mk->nama,
            'mk_kode' => $kelasMk->mkUnit?->kode,
            'sks' => (int) ($kelasMk->mkUnit?->mk->sks ?? 0),
            'dosen' => $kelasMk->dosenPengampu?->namaDenganGelar(),
            'semester_id' => (string) $kelasMk->semester_id,
        ];
    }

    /**
     * Isi laporan yang menentukan sidik validasi — tanpa data volatil
     * (waktu, metadata "terakhir diubah").
     *
     * @param  array<string, mixed>  $data  keluaran muat() atau state halaman dengan kunci yang sama
     * @return array<string, mixed>
     */
    public function untukSidik(array $data): array
    {
        $sidik = [
            'rows' => $data['rows'],
            'columns' => $data['columns'],
            'kolomEvaluasi' => $data['kolomEvaluasi'],
            'nilai' => $data['nilai'],
            'ketercapaianCpl' => $data['ketercapaianCpl'],
            'distribusiNilaiHuruf' => $data['distribusiNilaiHuruf'],
            'detailCplCpmkSubcpmk' => $data['detailCplCpmkSubcpmk'],
            'rencanaEvaluasi' => $data['rencanaEvaluasi'],
            'targetCapaianLulusan' => $data['targetCapaianLulusan'],
            'rtl' => ['baris' => $data['blokRtl']['baris'], 'umum' => $data['blokRtl']['umum']],
            'identitas' => $data['identitas'],
        ];

        // Kunci baru hanya masuk bila ada asesmen dirinci, supaya sidik
        // kelas tanpa rincian (dan validasinya) tidak berubah.
        if (($data['rincian'] ?? []) !== []) {
            $sidik['rincian'] = $data['rincian'];
            $sidik['nilaiRinci'] = $data['nilaiRinci'] ?? [];
        }

        return $sidik;
    }

    /** @param  array<string, mixed>  $data keluaran muat() */
    public function sidik(array $data): string
    {
        return app(SidikLaporanKelas::class)->dariData($this->untukSidik($data));
    }
}

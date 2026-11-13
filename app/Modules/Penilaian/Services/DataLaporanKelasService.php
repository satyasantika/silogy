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
     *     nilai: array<string, array<string, string|null>>, targetCapaianLulusan: int,
     *     ketercapaianCpl: list<array<string, mixed>>, distribusiNilaiHuruf: list<array<string, mixed>>,
     *     detailCplCpmkSubcpmk: list<array<string, mixed>>, rencanaEvaluasi: array<string, mixed>|null,
     *     blokRtl: array<string, mixed>
     * }
     */
    public function muat(KelasMk $kelasMk): array
    {
        $matrix = app(PenilaianMatrixService::class);
        $komponens = $matrix->komponenUntukKelas($kelasMk);
        $semesterId = (string) $kelasMk->semester_id;

        $rows = $matrix->barisUntukKelas($kelasMk);

        $evaluasiCpl = app(EvaluasiCplService::class);
        $nilai = $matrix->nilaiUntukMatrix($rows, $matrix->pivotIdsByKomponen($komponens));
        $evaluasiCpl->jalankanKalkulasiSinkron($kelasMk);
        $ketercapaianCpl = $evaluasiCpl->ketercapaianCplPerKelas($kelasMk);

        return [
            'columns' => $matrix->kolomDariKomponens($komponens, $semesterId),
            'kolomEvaluasi' => $matrix->kolomEvaluasiDariKomponens($komponens, $semesterId),
            'rows' => $rows,
            'portofolioRows' => $matrix->barisUntukKelas($kelasMk, 'mahasiswas.nim'),
            'nilai' => $nilai,
            'targetCapaianLulusan' => $evaluasiCpl->targetCapaianLulusan($kelasMk),
            'ketercapaianCpl' => $ketercapaianCpl,
            'distribusiNilaiHuruf' => $evaluasiCpl->distribusiNilaiHuruf($kelasMk),
            'detailCplCpmkSubcpmk' => $evaluasiCpl->detailCplCpmkSubcpmk($kelasMk),
            'rencanaEvaluasi' => app(RencanaEvaluasiService::class)->build($kelasMk->mkUnit?->mk_id, $kelasMk->semester_id),
            'blokRtl' => app(CatatanRtlService::class)->blok($kelasMk, $ketercapaianCpl),
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
        return [
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
        ];
    }

    /** @param  array<string, mixed>  $data keluaran muat() */
    public function sidik(array $data): string
    {
        return app(SidikLaporanKelas::class)->dariData($this->untukSidik($data));
    }
}

<?php

namespace App\Modules\MK\Services;

use App\Modules\Kalkulasi\Jobs\RecalkulasiCplJob;
use App\Modules\MK\Support\Konsolidasi\AnalisisKonsolidasi;
use App\Modules\MK\Support\Konsolidasi\GrupKonsolidasi;
use App\Modules\MK\Support\Konsolidasi\NilaiTerbuang;
use App\Modules\MK\Support\Konsolidasi\RencanaKonsolidasi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Melebur baris CPMK/Sub-CPMK/Asesmen kembar lintas semester menjadi satu
 * baris kanonik, lalu melampirkan baris itu ke seluruh semester tempat
 * anggotanya dulu berada.
 *
 * Latar: sebelum pivot semester ada, "naik semester" dilakukan dengan
 * MENYALIN baris (SubcpmkSalinSemesterService / KomponenPenilaianSalin-
 * SemesterService), sehingga satu Sub-CPMK yang secara kurikuler sama
 * tersebar sebagai banyak ID. Konsolidasi mengembalikannya ke satu identitas
 * supaya pemakaian ulang berbasis ID punya titik tambat.
 *
 * rencana() murni membaca — aman dijalankan kapan saja, termasuk sebelum
 * migration pivot dipasang. terapkan() butuh tabel pivot sudah ada.
 */
class KonsolidasiLintasSemesterService
{
    /**
     * Kolom non-kunci yang perbedaannya dilaporkan (bukan penghalang merge).
     *
     * @var array<string, list<string>>
     */
    private const KOLOM_DIBANDINGKAN = [
        GrupKonsolidasi::ENTITAS_CPMK => ['deskripsi'],
        GrupKonsolidasi::ENTITAS_SUBCPMK => [
            'deskripsi', 'indikator', 'evaluasi',
            'bloom_kognitif', 'bloom_afektif', 'bloom_psikomotorik',
        ],
        GrupKonsolidasi::ENTITAS_KOMPONEN => ['nama', 'evaluasi_id', 'bobot'],
    ];

    /**
     * Konsolidasi hanya bermakna selama kolom semester lama masih ada.
     * Setelah migration memindahkannya ke pivot, UQ baru (mk_id, kode) dan
     * (mk_cpmk_id, kode) membuat baris kembar mustahil lahir lagi — jadi
     * perintahnya aman dijalankan ulang dan sekadar tidak menemukan apa pun.
     */
    public function skemaLamaMasihAda(): bool
    {
        return Schema::hasColumn('subcpmk', 'semester_id')
            && Schema::hasColumn('komponen_penilaian', 'semester_id');
    }

    public function rencana(?string $mkId = null): RencanaKonsolidasi
    {
        return $this->analisis($mkId)->rencana;
    }

    public function analisis(?string $mkId = null): AnalisisKonsolidasi
    {
        if (! $this->skemaLamaMasihAda()) {
            return new AnalisisKonsolidasi(
                rencana: new RencanaKonsolidasi([], [], [], []),
                cpmkKanonik: [],
                mkCpmkKanonik: [],
                subcpmkKanonik: [],
                komponenKanonik: [],
                kodeEfektif: [],
            );
        }

        $namaMk = $this->namaMk($mkId);

        [$grupCpmk, $cpmkKanonik] = $this->grupkanCpmk($mkId, $namaMk);
        $mkCpmkKanonik = $this->petaMkCpmkKanonik($cpmkKanonik);

        [$grupSubcpmk, $subcpmkKanonik] = $this->grupkanSubcpmk($mkId, $namaMk, $mkCpmkKanonik);
        [$grupKomponen, $komponenKanonik, $tanpaKode, $kodeEfektif] = $this->grupkanKomponen($mkId, $namaMk);

        $nilaiTerbuang = $this->simulasiTabrakanPemetaan($subcpmkKanonik, $komponenKanonik);

        $grup = [...$grupCpmk, ...$grupSubcpmk, ...$grupKomponen];

        return new AnalisisKonsolidasi(
            rencana: new RencanaKonsolidasi(
                grup: $grup,
                komponenTanpaKode: $tanpaKode,
                nilaiTerbuang: $nilaiTerbuang,
                kelasMkTerdampak: $this->kelasMkTerdampak($grup),
            ),
            cpmkKanonik: $cpmkKanonik,
            mkCpmkKanonik: $mkCpmkKanonik,
            subcpmkKanonik: $subcpmkKanonik,
            komponenKanonik: $komponenKanonik,
            kodeEfektif: $kodeEfektif,
        );
    }

    /**
     * Jalankan konsolidasi sekaligus isi ketiga tabel pivot semester dari
     * kolom lama. Dipanggil dari migration 2026_09_15_000002 dan dari
     * perintah mk:konsolidasi-lintas-semester --terapkan.
     *
     * Analisis dihitung ulang di dalam transaksi yang sama, bukan diterima
     * sebagai argumen, supaya tidak ada celah antara laporan dan eksekusi.
     */
    public function terapkan(?string $mkId = null): RencanaKonsolidasi
    {
        if (! $this->skemaLamaMasihAda()) {
            return new RencanaKonsolidasi([], [], [], []);
        }

        return DB::transaction(function () use ($mkId): RencanaKonsolidasi {
            $this->lengkapiSemesterPemetaan();

            $analisis = $this->analisis($mkId);

            $this->tulisKodeAsesmenTurunan($analisis);
            $this->alihkanMkCpmk($analisis);
            $this->alihkanPemetaan($analisis);
            $this->selesaikanTabrakanPemetaan();
            $this->hapusHasilKalkulasi($analisis->rencana);
            $this->isiPivotSemester($analisis);
            $this->hapusBarisPecundang($analisis);

            return $analisis->rencana;
        });
    }

    /**
     * subcpmk_komponenpenilaian.semester_id bisa NULL untuk baris lama yang
     * dibuat lewat relation manager Filament (yang tidak mengisinya). Harus
     * diisi dari komponen induk SEBELUM komponen_penilaian.semester_id
     * dibuang, karena kolom itulah satu-satunya sumbernya.
     */
    private function lengkapiSemesterPemetaan(): void
    {
        // Subquery berkorelasi, bukan UPDATE ... JOIN: sintaks join pada
        // UPDATE tidak didukung SQLite yang dipakai test suite.
        DB::table('subcpmk_komponenpenilaian')
            ->whereNull('semester_id')
            ->update([
                'semester_id' => DB::raw(
                    '(select semester_id from komponen_penilaian'
                    .' where komponen_penilaian.id = subcpmk_komponenpenilaian.komponen_penilaian_id)',
                ),
            ]);
    }

    private function tulisKodeAsesmenTurunan(AnalisisKonsolidasi $analisis): void
    {
        foreach ($analisis->rencana->komponenTanpaKode as $baris) {
            $kode = $analisis->kodeEfektif[$baris['id']] ?? null;

            if ($kode === null) {
                continue;
            }

            DB::table('komponen_penilaian')->where('id', $baris['id'])->update(['kode' => $kode]);
        }
    }

    /**
     * Alihkan mk_cpmk ke CPMK kanonik, lalu buang baris mk_cpmk yang jadi
     * kembar akibat peleburan itu — Sub-CPMK-nya dipindah ke baris kanonik.
     */
    private function alihkanMkCpmk(AnalisisKonsolidasi $analisis): void
    {
        foreach ($analisis->cpmkKanonik as $pecundang => $kanonik) {
            DB::table('mk_cpmk')->where('cpmk_id', $pecundang)->update(['cpmk_id' => $kanonik]);
        }

        foreach ($analisis->mkCpmkKanonik as $pecundang => $kanonik) {
            DB::table('subcpmk')->where('mk_cpmk_id', $pecundang)->update(['mk_cpmk_id' => $kanonik]);
            DB::table('mk_cpmk')->where('id', $pecundang)->delete();
        }
    }

    private function alihkanPemetaan(AnalisisKonsolidasi $analisis): void
    {
        foreach ($analisis->subcpmkKanonik as $pecundang => $kanonik) {
            DB::table('subcpmk_komponenpenilaian')
                ->where('subcpmk_id', $pecundang)
                ->update(['subcpmk_id' => $kanonik]);
        }

        foreach ($analisis->komponenKanonik as $pecundang => $kanonik) {
            DB::table('subcpmk_komponenpenilaian')
                ->where('komponen_penilaian_id', $pecundang)
                ->update(['komponen_penilaian_id' => $kanonik]);
        }
    }

    /**
     * Sisakan satu baris pemetaan per (subcpmk, asesmen, semester). Nilai
     * mahasiswa dari baris lain dipindahkan bila belum ada tandingannya,
     * dan dibuang bila sudah ada — persis seperti yang dilaporkan dry-run.
     */
    private function selesaikanTabrakanPemetaan(): void
    {
        // Peta kanonik sengaja kosong: pengalihan FK sudah tertulis ke basis
        // data pada langkah sebelumnya, jadi tabrakan kini terbaca apa adanya.
        $tabrakan = $this->kelompokPemetaanBertabrakan([], []);

        foreach ($tabrakan as $anggota) {
            $penyintas = $anggota[0];

            foreach (array_slice($anggota, 1) as $pivot) {
                $kmmSudahAda = DB::table('nilai_mahasiswas')
                    ->where('subcpmk_komponenpenilaian_id', $penyintas->id)
                    ->pluck('kelas_mk_mahasiswa_id');

                DB::table('nilai_mahasiswas')
                    ->where('subcpmk_komponenpenilaian_id', $pivot->id)
                    ->whereIn('kelas_mk_mahasiswa_id', $kmmSudahAda)
                    ->delete();

                DB::table('nilai_mahasiswas')
                    ->where('subcpmk_komponenpenilaian_id', $pivot->id)
                    ->update(['subcpmk_komponenpenilaian_id' => $penyintas->id]);

                DB::table('subcpmk_komponenpenilaian')->where('id', $pivot->id)->delete();
            }
        }
    }

    /**
     * hasil_subcpmk & hasil_cpmk murni turunan — dihitung ulang otomatis
     * oleh SubcpmkCalculator/CpmkCalculator setiap nilai disimpan. Dihapus
     * saja, jauh lebih sederhana dan lebih aman daripada mengalihkan FK-nya
     * sambil menghindari tabrakan UQ.
     *
     * @see RecalkulasiCplJob
     */
    private function hapusHasilKalkulasi(RencanaKonsolidasi $rencana): void
    {
        if ($rencana->kelasMkTerdampak === []) {
            return;
        }

        foreach (array_chunk($rencana->kelasMkTerdampak, 500) as $bagian) {
            DB::table('hasil_subcpmk')->whereIn('kelas_mk_id', $bagian)->delete();
            DB::table('hasil_cpmk')->whereIn('kelas_mk_id', $bagian)->delete();
        }
    }

    /**
     * Isi ketiga pivot dari kolom semester lama — untuk SELURUH baris, bukan
     * hanya yang dilebur. Baris pecundang dilampirkan atas nama baris
     * kanoniknya, sehingga semester yang dulu dipegang salinan kini menjadi
     * semester tambahan milik satu baris kanonik.
     */
    private function isiPivotSemester(AnalisisKonsolidasi $analisis): void
    {
        $this->isiSubcpmkSemester($analisis->subcpmkKanonik);
        $this->isiKomponenSemester($analisis->komponenKanonik);
        $this->isiCpmkSemester($analisis->cpmkKanonik);
    }

    /**
     * @param  array<string, string>  $kanonik
     */
    private function isiSubcpmkSemester(array $kanonik): void
    {
        $baris = DB::table('subcpmk')
            ->whereNotNull('semester_id')
            ->get(['id', 'semester_id', 'bobot']);

        $rows = [];

        foreach ($baris as $row) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'subcpmk_id' => $kanonik[(string) $row->id] ?? (string) $row->id,
                'semester_id' => (string) $row->semester_id,
                'bobot' => $row->bobot,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->sisipkanPivot('subcpmk_semester', $rows);
    }

    /**
     * @param  array<string, string>  $kanonik
     */
    private function isiKomponenSemester(array $kanonik): void
    {
        $baris = DB::table('komponen_penilaian')->get(['id', 'semester_id', 'bobot']);

        $rows = [];

        foreach ($baris as $row) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'komponen_penilaian_id' => $kanonik[(string) $row->id] ?? (string) $row->id,
                'semester_id' => (string) $row->semester_id,
                'bobot' => $row->bobot,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->sisipkanPivot('komponen_penilaian_semester', $rows);
    }

    /**
     * CPMK belum pernah punya kolom semester, jadi semesternya disimpulkan:
     * semester Sub-CPMK-nya; bila tidak ada, semester kelas MK-nya; bila
     * masih kosong, semester aktif. Tanpa ini seluruh CPMK lama akan
     * menghilang dari daftar begitu daftarnya disaring per semester.
     *
     * @param  array<string, string>  $kanonik
     */
    private function isiCpmkSemester(array $kanonik): void
    {
        $dariSubcpmk = DB::table('cpmk')
            ->join('mk_cpmk', 'mk_cpmk.cpmk_id', '=', 'cpmk.id')
            ->join('subcpmk', 'subcpmk.mk_cpmk_id', '=', 'mk_cpmk.id')
            ->whereNotNull('subcpmk.semester_id')
            ->distinct()
            ->get(['cpmk.id as cpmk_id', 'subcpmk.semester_id']);

        $dariKelas = DB::table('cpmk')
            ->join('mk_units', 'mk_units.mk_id', '=', 'cpmk.mk_id')
            ->join('kelas_mk', 'kelas_mk.mk_unit_id', '=', 'mk_units.id')
            ->distinct()
            ->get(['cpmk.id as cpmk_id', 'kelas_mk.semester_id']);

        $semesterAktif = DB::table('semesters')->where('status_aktif', true)->value('id')
            ?? DB::table('semesters')->orderByDesc('kode')->value('id');

        /** @var array<string, array<string, true>> $peta */
        $peta = [];

        foreach ([$dariSubcpmk, $dariKelas] as $sumber) {
            foreach ($sumber as $row) {
                $cpmkId = $kanonik[(string) $row->cpmk_id] ?? (string) $row->cpmk_id;
                $peta[$cpmkId][(string) $row->semester_id] = true;
            }
        }

        if ($semesterAktif !== null) {
            foreach (DB::table('cpmk')->pluck('id') as $id) {
                $cpmkId = $kanonik[(string) $id] ?? (string) $id;
                $peta[$cpmkId] ??= [(string) $semesterAktif => true];
            }
        }

        $rows = [];

        foreach ($peta as $cpmkId => $semesterIds) {
            foreach (array_keys($semesterIds) as $semesterId) {
                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'cpmk_id' => $cpmkId,
                    'semester_id' => $semesterId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        $this->sisipkanPivot('cpmk_semester', $rows);
    }

    /**
     * insertOrIgnore: beberapa baris pecundang bisa membawa semester yang
     * sama ke baris kanonik yang sama (mis. dua Sub-CPMK kembar dalam satu
     * semester), dan UQ pivot memang harus menolak yang kedua.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function sisipkanPivot(string $tabel, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $bagian) {
            DB::table($tabel)->insertOrIgnore($bagian);
        }
    }

    private function hapusBarisPecundang(AnalisisKonsolidasi $analisis): void
    {
        foreach (array_chunk(array_keys($analisis->subcpmkKanonik), 500) as $bagian) {
            DB::table('subcpmk')->whereIn('id', $bagian)->delete();
        }

        foreach (array_chunk(array_keys($analisis->komponenKanonik), 500) as $bagian) {
            DB::table('komponen_penilaian')->whereIn('id', $bagian)->delete();
        }

        foreach (array_chunk(array_keys($analisis->cpmkKanonik), 500) as $bagian) {
            DB::table('cpmk')->whereIn('id', $bagian)->delete();
        }
    }

    /**
     * Nama MK untuk pelaporan, di-index per id.
     *
     * @return array<string, string>
     */
    private function namaMk(?string $mkId): array
    {
        /** @var array<string, string> $nama */
        $nama = DB::table('mk')
            ->when(filled($mkId), fn ($query) => $query->where('id', $mkId))
            ->pluck('nama', 'id')
            ->all();

        return $nama;
    }

    /**
     * CPMK kembar dalam satu MK — kunci (mk_id, kode).
     *
     * @param  array<string, string>  $namaMk
     * @return array{0: list<GrupKonsolidasi>, 1: array<string, string>}
     */
    private function grupkanCpmk(?string $mkId, array $namaMk): array
    {
        $baris = DB::table('cpmk')
            ->when(filled($mkId), fn ($query) => $query->where('mk_id', $mkId))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return $this->bentukGrup(
            $baris,
            GrupKonsolidasi::ENTITAS_CPMK,
            fn (\stdClass $row): string => $row->mk_id.'|'.$row->kode,
            fn (\stdClass $row): string => (string) $row->mk_id,
            fn (\stdClass $row): string => (string) $row->kode,
            fn (\stdClass $row): ?string => null, // CPMK belum pernah punya kolom semester
            $namaMk,
        );
    }

    /**
     * Setelah CPMK dilebur, baris mk_cpmk bisa kembar pada (cpl_mk_id,
     * cpmk_id). Pemenangnya dipakai sebagai induk Sub-CPMK, jadi peta ini
     * harus dihitung SEBELUM Sub-CPMK dikelompokkan.
     *
     * @param  array<string, string>  $cpmkKanonik
     * @return array<string, string>
     */
    private function petaMkCpmkKanonik(array $cpmkKanonik): array
    {
        $baris = DB::table('mk_cpmk')
            ->orderByDesc('bobot')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $peta = [];
        $pemenangPerKunci = [];

        foreach ($baris as $row) {
            $cpmkId = $cpmkKanonik[$row->cpmk_id] ?? $row->cpmk_id;
            $kunci = $row->cpl_mk_id.'|'.$cpmkId;

            if (! array_key_exists($kunci, $pemenangPerKunci)) {
                $pemenangPerKunci[$kunci] = (string) $row->id;

                continue;
            }

            $peta[(string) $row->id] = $pemenangPerKunci[$kunci];
        }

        return $peta;
    }

    /**
     * Sub-CPMK kembar — kunci (mk_cpmk_id kanonik, kode). mk_cpmk_id selalu
     * ikut tersalin apa adanya oleh SubcpmkSalinSemesterService, jadi kunci
     * ini persis menandai hasil salinan antar semester.
     *
     * @param  array<string, string>  $namaMk
     * @param  array<string, string>  $mkCpmkKanonik
     * @return array{0: list<GrupKonsolidasi>, 1: array<string, string>}
     */
    private function grupkanSubcpmk(?string $mkId, array $namaMk, array $mkCpmkKanonik): array
    {
        $baris = DB::table('subcpmk')
            ->join('mk_cpmk', 'mk_cpmk.id', '=', 'subcpmk.mk_cpmk_id')
            ->join('cpmk', 'cpmk.id', '=', 'mk_cpmk.cpmk_id')
            ->when(filled($mkId), fn ($query) => $query->where('cpmk.mk_id', $mkId))
            ->orderBy('subcpmk.created_at')
            ->orderBy('subcpmk.id')
            ->select(['subcpmk.*', 'cpmk.mk_id as _mk_id'])
            ->get();

        return $this->bentukGrup(
            $baris,
            GrupKonsolidasi::ENTITAS_SUBCPMK,
            fn (\stdClass $row): string => ($mkCpmkKanonik[$row->mk_cpmk_id] ?? $row->mk_cpmk_id).'|'.$row->kode,
            fn (\stdClass $row): string => (string) $row->_mk_id,
            fn (\stdClass $row): string => (string) $row->kode,
            fn (\stdClass $row): ?string => $row->semester_id === null ? null : (string) $row->semester_id,
            $namaMk,
        );
    }

    /**
     * Asesmen kembar — kunci (mk_id, kode efektif). Baris tanpa kode diberi
     * kode turunan dari namanya supaya tetap bisa dicocokkan; UQ baru
     * (mk_id, kode) tidak mengizinkan NULL berulang.
     *
     * @param  array<string, string>  $namaMk
     * @return array{0: list<GrupKonsolidasi>, 1: array<string, string>, 2: list<array{id: string, mk: string, nama: string}>, 3: array<string, string>}
     */
    private function grupkanKomponen(?string $mkId, array $namaMk): array
    {
        $baris = DB::table('komponen_penilaian')
            ->when(filled($mkId), fn ($query) => $query->where('mk_id', $mkId))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $kodeEksplisit = [];

        foreach ($baris as $row) {
            if (filled($row->kode)) {
                $kodeEksplisit[$row->mk_id][(string) $row->kode] = true;
            }
        }

        $tanpaKode = [];
        $kodeEfektif = [];

        foreach ($baris as $row) {
            if (filled($row->kode)) {
                $kodeEfektif[(string) $row->id] = (string) $row->kode;

                continue;
            }

            $kode = $this->kodeTurunan((string) $row->nama, $kodeEksplisit[$row->mk_id] ?? []);
            $kodeEfektif[(string) $row->id] = $kode;

            $tanpaKode[] = [
                'id' => (string) $row->id,
                'mk' => $namaMk[$row->mk_id] ?? '—',
                'nama' => (string) $row->nama,
            ];
        }

        [$grup, $kanonik] = $this->bentukGrup(
            $baris,
            GrupKonsolidasi::ENTITAS_KOMPONEN,
            fn (\stdClass $row): string => $row->mk_id.'|'.$kodeEfektif[(string) $row->id],
            fn (\stdClass $row): string => (string) $row->mk_id,
            fn (\stdClass $row): string => $kodeEfektif[(string) $row->id],
            // komponen_penilaian.semester_id NOT NULL pada skema lama.
            fn (\stdClass $row): string => (string) $row->semester_id,
            $namaMk,
        );

        return [$grup, $kanonik, $tanpaKode, $kodeEfektif];
    }

    /**
     * @param  array<string, true>  $kodeTerpakai
     */
    private function kodeTurunan(string $nama, array $kodeTerpakai): string
    {
        $kode = Str::upper(Str::slug($nama, '_'));
        $kode = $kode === '' ? 'ASESMEN' : Str::substr($kode, 0, 30);

        if (! array_key_exists($kode, $kodeTerpakai)) {
            return $kode;
        }

        return Str::substr('AUTO_'.$kode, 0, 30);
    }

    /**
     * Inti pengelompokan: baris sudah terurut (created_at, id), jadi anggota
     * pertama tiap kunci otomatis jadi pemenang — deterministik dan bisa
     * diulang.
     *
     * @param  Collection<int, \stdClass>  $baris
     * @param  callable(\stdClass): string  $kunci
     * @param  callable(\stdClass): string  $mkIdDari
     * @param  callable(\stdClass): string  $kodeDari
     * @param  callable(\stdClass): ?string  $semesterDari
     * @param  array<string, string>  $namaMk
     * @return array{0: list<GrupKonsolidasi>, 1: array<string, string>}
     */
    private function bentukGrup(
        Collection $baris,
        string $entitas,
        callable $kunci,
        callable $mkIdDari,
        callable $kodeDari,
        callable $semesterDari,
        array $namaMk,
    ): array {
        /** @var array<string, list<\stdClass>> $perKunci */
        $perKunci = [];

        foreach ($baris as $row) {
            $perKunci[$kunci($row)][] = $row;
        }

        $grup = [];
        $kanonik = [];

        foreach ($perKunci as $anggota) {
            if (count($anggota) < 2) {
                continue;
            }

            $pemenang = $anggota[0];
            $pecundang = array_slice($anggota, 1);

            foreach ($pecundang as $row) {
                $kanonik[(string) $row->id] = (string) $pemenang->id;
            }

            $semester = [];

            foreach ($anggota as $row) {
                $semesterId = $semesterDari($row);

                if ($semesterId !== null) {
                    $semester[] = $semesterId;
                }
            }

            $grup[] = new GrupKonsolidasi(
                entitas: $entitas,
                mkId: $mkIdDari($pemenang),
                mkNama: $namaMk[$mkIdDari($pemenang)] ?? '—',
                kode: $kodeDari($pemenang),
                idKanonik: (string) $pemenang->id,
                idPecundang: array_map(fn (\stdClass $row): string => (string) $row->id, $pecundang),
                semesterIds: array_values(array_unique($semester)),
                bedaKolom: $this->bedaKolom($entitas, $anggota),
                kembarDalamSemesterSama: count($semester) !== count(array_unique($semester)),
            );
        }

        return [$grup, $kanonik];
    }

    /**
     * @param  list<\stdClass>  $anggota
     * @return list<string>
     */
    private function bedaKolom(string $entitas, array $anggota): array
    {
        $beda = [];

        foreach (self::KOLOM_DIBANDINGKAN[$entitas] ?? [] as $kolom) {
            $nilai = array_map(
                fn (\stdClass $row): string => (string) ($row->{$kolom} ?? ''),
                $anggota,
            );

            if (count(array_unique($nilai)) > 1) {
                $beda[] = $kolom;
            }
        }

        return $beda;
    }

    /**
     * Setelah Sub-CPMK dan Asesmen dilebur, beberapa baris
     * subcpmk_komponenpenilaian bisa jatuh ke kunci yang sama
     * (subcpmk_id, komponen_penilaian_id, semester_id). Satu baris bertahan;
     * nilai mahasiswa dari baris lain dipindahkan, kecuali bila mahasiswa
     * yang sama sudah punya nilai pada baris penyintas — yang seperti itu
     * dilaporkan sebagai kehilangan.
     *
     * @param  array<string, string>  $subcpmkKanonik
     * @param  array<string, string>  $komponenKanonik
     * @return list<NilaiTerbuang>
     */
    private function simulasiTabrakanPemetaan(array $subcpmkKanonik, array $komponenKanonik): array
    {
        // Tanpa peleburan tidak mungkin ada tabrakan baru: UQ lama
        // (subcpmk_id, komponen_penilaian_id) sudah menjamin keunikannya.
        if ($subcpmkKanonik === [] && $komponenKanonik === []) {
            return [];
        }

        $tabrakan = $this->kelompokPemetaanBertabrakan($subcpmkKanonik, $komponenKanonik);

        if ($tabrakan === []) {
            return [];
        }

        $terbuang = [];

        foreach ($tabrakan as $anggota) {
            $penyintas = $anggota[0];
            $nilaiPenyintas = DB::table('nilai_mahasiswas')
                ->where('subcpmk_komponenpenilaian_id', $penyintas->id)
                ->pluck('nilai', 'kelas_mk_mahasiswa_id');

            foreach (array_slice($anggota, 1) as $pivot) {
                $bentrok = DB::table('nilai_mahasiswas')
                    ->where('subcpmk_komponenpenilaian_id', $pivot->id)
                    ->whereIn('kelas_mk_mahasiswa_id', $nilaiPenyintas->keys())
                    ->get();

                foreach ($bentrok as $nilai) {
                    $terbuang[] = $this->laporkanNilaiTerbuang(
                        $nilai,
                        $penyintas,
                        $nilaiPenyintas->get($nilai->kelas_mk_mahasiswa_id),
                    );
                }
            }
        }

        return $terbuang;
    }

    /**
     * @param  array<string, string>  $subcpmkKanonik
     * @param  array<string, string>  $komponenKanonik
     * @return list<list<\stdClass>>
     */
    private function kelompokPemetaanBertabrakan(array $subcpmkKanonik, array $komponenKanonik): array
    {
        $pivots = DB::table('subcpmk_komponenpenilaian as skp')
            ->join('komponen_penilaian as kp', 'kp.id', '=', 'skp.komponen_penilaian_id')
            ->orderBy('skp.created_at')
            ->orderBy('skp.id')
            ->select([
                'skp.id',
                'skp.subcpmk_id',
                'skp.komponen_penilaian_id',
                DB::raw('COALESCE(skp.semester_id, kp.semester_id) as semester_id'),
            ])
            ->get();

        /** @var array<string, list<\stdClass>> $perKunci */
        $perKunci = [];

        foreach ($pivots as $pivot) {
            $subcpmkId = $subcpmkKanonik[$pivot->subcpmk_id] ?? $pivot->subcpmk_id;
            $komponenId = $komponenKanonik[$pivot->komponen_penilaian_id] ?? $pivot->komponen_penilaian_id;
            $perKunci[$subcpmkId.'|'.$komponenId.'|'.$pivot->semester_id][] = $pivot;
        }

        return array_values(array_filter(
            $perKunci,
            fn (array $anggota): bool => count($anggota) > 1,
        ));
    }

    private function laporkanNilaiTerbuang(\stdClass $nilai, \stdClass $penyintas, mixed $nilaiDipertahankan): NilaiTerbuang
    {
        $mahasiswa = DB::table('kelas_mk_mahasiswa as kmm')
            ->join('mahasiswas as m', 'm.id', '=', 'kmm.mahasiswa_id')
            ->where('kmm.id', $nilai->kelas_mk_mahasiswa_id)
            ->select(['m.nim', 'm.nama'])
            ->first();

        $konteks = DB::table('subcpmk_komponenpenilaian as skp')
            ->join('subcpmk as s', 's.id', '=', 'skp.subcpmk_id')
            ->join('komponen_penilaian as kp', 'kp.id', '=', 'skp.komponen_penilaian_id')
            ->where('skp.id', $penyintas->id)
            ->select(['s.kode as subcpmk_kode', 'kp.kode as asesmen_kode', 'kp.nama as asesmen_nama'])
            ->first();

        return new NilaiTerbuang(
            nim: (string) ($mahasiswa->nim ?? '—'),
            mahasiswa: (string) ($mahasiswa->nama ?? '—'),
            subcpmkKode: (string) ($konteks->subcpmk_kode ?? '—'),
            asesmenKode: (string) ($konteks->asesmen_kode ?? $konteks->asesmen_nama ?? '—'),
            nilai: $nilai->nilai === null ? null : (float) $nilai->nilai,
            nilaiDipertahankan: $nilaiDipertahankan === null ? null : (float) $nilaiDipertahankan,
        );
    }

    /**
     * Kelas MK yang hasil kalkulasinya perlu dihitung ulang setelah peleburan.
     *
     * @param  list<GrupKonsolidasi>  $grup
     * @return list<string>
     */
    private function kelasMkTerdampak(array $grup): array
    {
        $mkIds = array_values(array_unique(array_map(
            fn (GrupKonsolidasi $item): string => $item->mkId,
            $grup,
        )));

        if ($mkIds === []) {
            return [];
        }

        /** @var list<string> $ids */
        $ids = DB::table('kelas_mk')
            ->join('mk_units', 'mk_units.id', '=', 'kelas_mk.mk_unit_id')
            ->whereIn('mk_units.mk_id', $mkIds)
            ->pluck('kelas_mk.id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        return array_values(array_unique($ids));
    }
}

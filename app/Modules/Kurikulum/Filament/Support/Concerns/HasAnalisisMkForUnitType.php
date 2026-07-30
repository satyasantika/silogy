<?php

namespace App\Modules\Kurikulum\Filament\Support\Concerns;

use App\Models\User;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Services\AnalisisMkProdiService;
use App\Modules\Kurikulum\Services\IpkKumulatifService;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * Perilaku bersama halaman "Analisis MK" per level unit (prodi/fakultas/
 * universitas) — dipakai oleh AnalisisMkProdi, AnalisisMkFakultas,
 * AnalisisMkUniversitas. Kelas pemakai wajib mengisi analisisUnitType()
 * dan mendeklarasikan `implements HasTable, HasActions`.
 *
 * Untuk kurikulum prodi, data langsung dari mk_units prodi itu; untuk
 * fakultas/universitas, data adalah rollup lintas semua prodi yang
 * mengadaptasi MK kurikulum itu (lihat
 * AnalisisMkProdiService::mkUnitIdsUntukKurikulum()).
 */
trait HasAnalisisMkForUnitType
{
    use InteractsWithActions;
    use InteractsWithTable;

    /**
     * @var array{
     *     angkatan_list: list<string>,
     *     pemetaan: list<array{
     *         cpl_id: string, cpl_kode: string, cpl_deskripsi: string,
     *         ketercapaian: array{rata_rata: float|null, jumlah_mahasiswa: int, persentase_tercapai: float|null, tercapai: bool}|null,
     *         mk_rows: list<array{mk_id: string, nama: string, kode: string, sks: int, kontribusi: float, per_angkatan: array<string, array{rata_rata: float|null, n: int}>, rata_rata_keseluruhan: float|null}>,
     *     }>,
     * }
     */
    public array $hasilAnalisis = ['angkatan_list' => [], 'pemetaan' => []];

    /** Tipe academic_unit yang dilayani halaman ini: study_program|faculty|university. */
    abstract public static function analisisUnitType(): string;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if (! $user->hasRole(['Pimpinan', 'Tim Kurikulum'])) {
            return false;
        }

        $kurikulum = KurikulumTerpilih::current();

        return $kurikulum instanceof Kurikulum
            && $kurikulum->academicUnit?->type === static::analisisUnitType()
            && KurikulumTerpilih::scopedUnitIds($user)->contains($kurikulum->academic_unit_id);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->muatHasilAnalisis();
    }

    public function getTitle(): string
    {
        $kurikulum = $this->getKurikulumProperty();

        if ($kurikulum === null) {
            return static::$title ?? 'Analisis MK';
        }

        return sprintf(
            '%s — %s',
            static::$title ?? 'Analisis MK',
            KurikulumTerpilih::unitHierarchyLabel($kurikulum->academicUnit),
        );
    }

    /**
     * Memicu sinkronisasi kalkulasi CPL (queue mati, lihat
     * AnalisisMkProdiService::sinkronkanKalkulasiProdi()) lalu memuat data
     * tab 2 — dipanggil sekali di mount, BUKAN lewat computed property,
     * supaya operasi berat ini tidak berjalan berulang dalam satu request.
     */
    protected function muatHasilAnalisis(): void
    {
        $kurikulum = $this->getKurikulumProperty();

        if ($kurikulum === null) {
            $this->hasilAnalisis = ['angkatan_list' => [], 'pemetaan' => []];

            return;
        }

        $service = app(AnalisisMkProdiService::class);
        $mkUnitIds = $service->mkUnitIdsUntukKurikulum($kurikulum);
        $service->sinkronkanKalkulasiProdi($kurikulum, $mkUnitIds);
        $this->hasilAnalisis = $service->hasilAnalisisPerAngkatan($kurikulum, $mkUnitIds);
    }

    public function getKurikulumProperty(): ?Kurikulum
    {
        $terpilih = KurikulumTerpilih::current();

        if (! $terpilih instanceof Kurikulum) {
            return null;
        }

        $terpilih->loadMissing('academicUnit');

        if ($terpilih->academicUnit?->type !== static::analisisUnitType()) {
            return null;
        }

        $user = auth()->user();

        if (! $user instanceof User || ! KurikulumTerpilih::scopedUnitIds($user)->contains($terpilih->academic_unit_id)) {
            return null;
        }

        return $terpilih;
    }

    /**
     * @return list<array{
     *     cpl_kode: string,
     *     cpl_deskripsi: string,
     *     mk_rows: list<array{mk_id: string, nama: string, kode: string, sks: int, kontribusi: float}>,
     * }>
     */
    public function getPemetaanCplMkProperty(): array
    {
        $kurikulum = $this->getKurikulumProperty();

        if ($kurikulum === null) {
            return [];
        }

        return app(AnalisisMkProdiService::class)->pemetaanCplMk($kurikulum);
    }

    /**
     * Tab 3 — "Grafik CPL": satu radar chart per CPL, rerata per MK
     * penyumbang CPL itu. Dibangun dari $this->hasilAnalisis (sudah
     * disinkronkan sekali di muatHasilAnalisis()) — tidak query ulang.
     *
     * @return list<array{
     *     cpl_id: string, cpl_kode: string, cpl_deskripsi: string,
     *     ada_data: bool, labels: list<string>, data: list<float>,
     * }>
     */
    public function getRadarPerCplProperty(): array
    {
        return collect($this->hasilAnalisis['pemetaan'])
            ->map(function (array $cplGroup): array {
                $mkRows = collect($cplGroup['mk_rows']);

                return [
                    'cpl_id' => $cplGroup['cpl_id'],
                    'cpl_kode' => $cplGroup['cpl_kode'],
                    'cpl_deskripsi' => $cplGroup['cpl_deskripsi'],
                    'ada_data' => $mkRows->contains(fn (array $mkRow): bool => $mkRow['rata_rata_keseluruhan'] !== null),
                    'labels' => $mkRows->pluck('nama')->values()->all(),
                    'data' => $mkRows->map(fn (array $mkRow): float => (float) ($mkRow['rata_rata_keseluruhan'] ?? 0))->values()->all(),
                ];
            })
            ->all();
    }

    /**
     * Modal trigger pada tab "Pemetaan Rencana Asesmen CPL" (khusus
     * fakultas/universitas — lihat tabel-pemetaan-cpl-mk.blade.php):
     * tabel prodi mana memakai kode apa untuk satu MK, dibatasi ke
     * mk_unit_ids kurikulum yang dikerjakan saat ini.
     */
    public function kodePerProdiAction(): Action
    {
        return Action::make('kodePerProdi')
            ->label('Lihat kode per prodi')
            ->modalHeading(fn (array $arguments): string => 'Kode Mata Kuliah per Prodi — '.($arguments['nama'] ?? ''))
            ->modalContent(function (array $arguments): View {
                $kurikulum = $this->getKurikulumProperty();
                $mkUnitIds = $kurikulum !== null
                    ? app(AnalisisMkProdiService::class)->mkUnitIdsUntukKurikulum($kurikulum)
                    : collect();

                $baris = app(AnalisisMkProdiService::class)->kodePerProdiUntukMk(
                    (string) ($arguments['mkId'] ?? ''),
                    $mkUnitIds,
                );

                return view('filament.modules.kurikulum.partials.tabel-kode-per-prodi', ['baris' => $baris]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup');
    }

    /**
     * Tab 4 — "Hasil Analisis Asesmen CPL per Mahasiswa": roster IPK
     * kumulatif kurikulum yang dikerjakan lewat komponen Table Filament
     * native (bukan HTML mentah seperti tab 1-3) supaya dapat
     * search/pagination bawaan untuk ~100+ mahasiswa. Ringkasan IPK
     * dihitung SEKALI per render tabel (bukan per baris) lewat
     * IpkKumulatifService::rosterKurikulum(), disimpan ke variabel lokal
     * yang di-closure-kan ke tiap kolom — menghindari N+1. Hanya mahasiswa
     * yang mengontrak penawaran MK kurikulum ini yang tampil; SKS, nilai,
     * IPK, dan grafik CPL modal hanya menghitung penawaran MK kurikulum ini.
     */
    public function table(Table $table): Table
    {
        $kurikulum = $this->getKurikulumProperty();
        $mkUnitIds = $kurikulum !== null
            ? app(AnalisisMkProdiService::class)->mkUnitIdsUntukKurikulum($kurikulum)
            : null;

        $roster = $kurikulum !== null
            ? collect(app(IpkKumulatifService::class)->rosterKurikulum($kurikulum, $mkUnitIds))->keyBy('mahasiswa_id')
            : new Collection;

        // Tabel hanya menampilkan mahasiswa yang mengontrak MK pada kurikulum
        // terpilih (bukan seluruh mahasiswa prodi).
        $mahasiswaIds = $roster->keys()->all();

        // Kurikulum fakultas/universitas adalah rollup lintas beberapa prodi
        // (lihat AnalisisMkProdiService::mkUnitIdsUntukKurikulum()) — kolom
        // Prodi hanya relevan/ditampilkan pada kasus itu.
        $tampilkanKolomProdi = static::analisisUnitType() !== 'study_program';

        return $table
            ->query(
                $mahasiswaIds !== []
                    ? Mahasiswa::query()->whereIn('id', $mahasiswaIds)
                    : Mahasiswa::query()->whereRaw('1 = 0'),
            )
            ->columns([
                TextColumn::make('nim')->label('NPM')->searchable()->sortable(),
                TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
                TextColumn::make('academicUnit.nama')
                    ->label('Prodi')
                    ->visible($tampilkanKolomProdi),
                TextColumn::make('sks_dikontrak')
                    ->label('SKS Dikontrak')
                    ->getStateUsing(fn (Mahasiswa $record): int => $roster->get($record->id)['sks_dikontrak'] ?? 0),
                TextColumn::make('nilai_angka')
                    ->label('Nilai Angka')
                    ->getStateUsing(fn (Mahasiswa $record): ?float => $roster->get($record->id)['nilai_angka'] ?? null)
                    ->formatStateUsing(fn (?float $state): string => $state !== null ? number_format($state, 2) : '-'),
                TextColumn::make('nilai_huruf')
                    ->label('Nilai Huruf')
                    ->badge()
                    ->getStateUsing(fn (Mahasiswa $record): string => $roster->get($record->id)['nilai_huruf'] ?? '-')
                    ->color(fn (string $state): string => match (true) {
                        $state === '-' => 'gray',
                        str_starts_with($state, 'A') => 'success',
                        str_starts_with($state, 'B') => 'info',
                        str_starts_with($state, 'C') => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('bobot_huruf')
                    ->label('Bobot Huruf')
                    ->getStateUsing(fn (Mahasiswa $record): float => $roster->get($record->id)['bobot_huruf'] ?? 0.0)
                    ->formatStateUsing(fn (float $state): string => number_format($state, 2)),
                TextColumn::make('ipk')
                    ->label('IPK')
                    ->weight('bold')
                    ->getStateUsing(fn (Mahasiswa $record): float => $roster->get($record->id)['ipk'] ?? 0.0)
                    ->formatStateUsing(fn (float $state): string => number_format($state, 2)),
            ])
            ->recordActions([
                Action::make('grafikMahasiswa')
                    ->label('Grafik')
                    ->icon(Heroicon::OutlinedChartBar)
                    ->color('gray')
                    ->size('sm')
                    ->modalHeading(fn (Mahasiswa $record): string => "Capaian CPL — {$record->nim} - {$record->nama}")
                    ->modalContent(function (Mahasiswa $record) use ($kurikulum, $mkUnitIds) {
                        $capaian = $kurikulum !== null
                            ? app(IpkKumulatifService::class)->capaianCplMahasiswa($record->id, $kurikulum, $mkUnitIds)
                            : [];

                        return view('filament.modules.kurikulum.partials.capaian-cpl-mahasiswa', [
                            'mahasiswaId' => $record->id,
                            'capaian' => $capaian,
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ])
            ->paginated([10, 25, 50]);
    }
}

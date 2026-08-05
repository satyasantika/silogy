<?php

namespace App\Modules\Kurikulum\Filament\Support\Concerns;

use App\Models\User;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Services\AnalisisMkProdiService;
use App\Modules\Kurikulum\Services\IpkKumulatifService;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Support\Filament\DelegasiMenu;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

/**
 * Perilaku bersama tiga menu laporan Pimpinan (Hasil Analisis CPL, Grafik
 * CPL, Analisis per Mahasiswa) — konten sama dengan tab Analisis MK, tanpa
 * tab pemetaan. Kurikulum mengikuti KurikulumTerpilih (prodi/fakultas/
 * universitas) selama unitnya masuk scope pimpinan.
 *
 * Kelas pemakai wajib `implements HasActions` (dan `HasTable` bila
 * menampilkan tabel mahasiswa).
 */
trait HasLaporanAnalisisCplPimpinan
{
    use InteractsWithActions;
    use InteractsWithTable;

    /**
     * @var array{
     *     angkatan_list: list<string>,
     *     pemetaan: list<array{
     *         cpl_id: string, cpl_kode: string, cpl_deskripsi: string,
     *         ketercapaian: array{rata_rata: float|null, jumlah_mahasiswa: int, persentase_tercapai: float|null, tercapai: bool}|null,
     *         mk_rows: list<array{mk_id: string, nama: string, kode: string, sks: int, kontribusi: float, bobot_mentah: float, per_angkatan: array<string, array{rata_rata: float|null, n: int}>, rata_rata_keseluruhan: float|null}>,
     *     }>,
     * }
     */
    public array $hasilAnalisis = ['angkatan_list' => [], 'pemetaan' => []];

    public static function canAccess(): bool
    {
        if (! DelegasiMenu::peranAktifPimpinan()) {
            return false;
        }

        $user = auth()->user();

        return $user instanceof User && $user->can('lihat_laporan');
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
            return static::$title ?? 'Laporan CPL';
        }

        return sprintf(
            '%s — %s',
            static::$title ?? 'Laporan CPL',
            KurikulumTerpilih::unitHierarchyLabel($kurikulum->academicUnit),
        );
    }

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

        $user = auth()->user();

        if (! $user instanceof User || ! KurikulumTerpilih::scopedUnitIds($user)->contains($terpilih->academic_unit_id)) {
            return null;
        }

        return $terpilih;
    }

    /**
     * Kontrak yang sama dengan HasAnalisisMkForUnitType — dipakai partial
     * tabel-hasil-analisis-cpl (dan pemetaan bila dipakai ulang) untuk
     * membedakan tampilan kode MK prodi vs rollup fakultas/universitas.
     */
    public function analisisUnitType(): string
    {
        return $this->getKurikulumProperty()?->academicUnit?->type ?? 'study_program';
    }

    /**
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

    public function table(Table $table): Table
    {
        $kurikulum = $this->getKurikulumProperty();
        $mkUnitIds = $kurikulum !== null
            ? app(AnalisisMkProdiService::class)->mkUnitIdsUntukKurikulum($kurikulum)
            : null;

        $roster = $kurikulum !== null
            ? collect(app(IpkKumulatifService::class)->rosterKurikulum($kurikulum, $mkUnitIds))->keyBy('mahasiswa_id')
            : new Collection;

        $mahasiswaIds = $roster->keys()->all();
        $tampilkanKolomProdi = $kurikulum?->academicUnit?->type !== 'study_program';

        return $table
            ->query(
                $mahasiswaIds !== []
                    ? Mahasiswa::query()->whereIn('id', $mahasiswaIds)
                    : Mahasiswa::query()->whereRaw('1 = 0'),
            )
            ->columns([
                TextColumn::make('nim')->label('NPM')->searchable()->sortable(),
                TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
                TextColumn::make('academicUnit.nama_lengkap')
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

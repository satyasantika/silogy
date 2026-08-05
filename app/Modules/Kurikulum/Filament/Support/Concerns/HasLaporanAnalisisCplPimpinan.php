<?php

namespace App\Modules\Kurikulum\Filament\Support\Concerns;

use App\Models\User;
use App\Modules\Institusi\Services\DashboardPimpinanService;
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
use Illuminate\Support\HtmlString;

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
     * Data view KPI donat untuk kurikulum terpilih.
     *
     * @param  'both'|'mk'|'mahasiswa'  $fokus
     * @return array{
     *     mk_total: int, mk_dinilai: int, mk_progress_persen: int,
     *     mahasiswa_total: int, mahasiswa_dinilai: int, mahasiswa_progress_persen: int,
     *     tampil_mk: bool, tampil_mahasiswa: bool, compact: bool, page: bool, nested: bool
     * }
     */
    public function dataKpiProgressPenilaian(string $fokus = 'both', bool $nested = false): array
    {
        $kurikulum = $this->getKurikulumProperty();

        $rekap = $kurikulum instanceof Kurikulum
            ? app(DashboardPimpinanService::class)->rekapProgressPenilaianUntukKurikulum($kurikulum)
            : [
                'mk_total' => 0,
                'mk_dinilai' => 0,
                'mk_progress_persen' => 0,
                'mahasiswa_total' => 0,
                'mahasiswa_dinilai' => 0,
                'mahasiswa_progress_persen' => 0,
            ];

        return [
            ...$rekap,
            'tampil_mk' => $fokus === 'both' || $fokus === 'mk',
            'tampil_mahasiswa' => $fokus === 'both' || $fokus === 'mahasiswa',
            'compact' => false,
            'page' => true,
            'nested' => $nested,
        ];
    }

    public function htmlKpiProgressPenilaian(string $fokus = 'both', bool $nested = false): HtmlString
    {
        return new HtmlString(view(
            'filament.modules.kurikulum.partials.laporan-kurikulum-kpi',
            $this->dataKpiProgressPenilaian($fokus, $nested),
        )->render());
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
            ->description(fn (): HtmlString => KurikulumTerpilih::bannerHtml(
                bodyHtml: $this->htmlKpiProgressPenilaian('mahasiswa', nested: true)->toHtml(),
            ))
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

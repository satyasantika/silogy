<?php

namespace App\Modules\Kurikulum\Filament\Pages;

use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Kurikulum\Filament\Pages\Concerns\InteraksiMatrixPage;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Services\NormalisasiBobotCplMkService;
use App\Modules\Kurikulum\Support\CplBokAdaptasiScope;
use App\Modules\MK\Models\Mk;
use App\Modules\Penilaian\Support\NormalisasiBobotDesimal;
use App\Support\Filament\NavigationGroupPeran;
use App\Support\Filament\NavigationSortPeran;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Matriks interaksi CPL ↔ MK: kolom CPL (via BoK), baris mata kuliah,
 * irisan berupa bobot kontribusi (pivot cpl_mk); total bobot dihitung
 * PER BARIS (per MK, dijumlah lintas CplBok) — target invariannya tepat
 * 100%. Turut menampilkan MK/CplBok milik unit lain yang tersingkap lewat
 * adaptasi MK — baris hanya bisa diedit bila MK-nya benar-benar milik
 * unit ini (lihat CplBokAdaptasiScope::canEditCplMkCell()); MK
 * fakultas/universitas yang teradaptasi selalu read-only di sini, walau
 * kolom CPL-nya sendiri milik unit ini.
 */
class CplMkMatrix extends Page
{
    use InteraksiMatrixPage;

    protected string $view = 'filament.modules.kurikulum.pages.cpl-mk-matrix';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroupPeran::resolve('Interaksi');
    }

    public static function getNavigationSort(): ?int
    {
        return NavigationSortPeran::resolve('cpl-mk', 3);
    }

    protected static ?string $navigationLabel = 'CPL ↔ MK';

    protected static ?string $title = 'Interaksi CPL ↔ MK (bobot)';

    protected static ?string $slug = 'interaksi/cpl-mk';

    public function updateBobot(string $mkId, string $cplBokId, ?string $bobot): void
    {
        $kurikulum = $this->getKurikulum();
        $kurikulumId = $kurikulum?->id;
        $unitId = $kurikulum?->academic_unit_id;

        if ($kurikulumId === null || $unitId === null || ! CplBokAdaptasiScope::isVisibleMkCplBokCell($mkId, $cplBokId, $kurikulumId)) {
            Notification::make()
                ->title('Sel interaksi tidak valid untuk kurikulum ini')
                ->warning()
                ->send();

            return;
        }

        $mk = Mk::query()->findOrFail($mkId);

        if (! CplBokAdaptasiScope::canEditCplMkCell($mk, $unitId)) {
            Notification::make()
                ->title('Sel ini murni milik unit lain')
                ->body('MK dan pasangan CPL/BoK ini sama-sama bukan milik unit Anda, sehingga tidak dapat diubah dari sini.')
                ->warning()
                ->send();

            return;
        }

        $bobot = trim((string) $bobot);

        if ($bobot === '' || ! is_numeric($bobot) || (float) $bobot <= 0) {
            CplMk::query()
                ->where('mk_id', $mkId)
                ->where('cpl_bok_id', $cplBokId)
                ->delete();

            return;
        }

        $batas = CplMk::sisaBobotTersedia($mkId, $cplBokId);
        $nilai = round(min(round((float) $bobot, CplMk::DESIMAL_BOBOT), $batas), CplMk::DESIMAL_BOBOT);

        if ($nilai <= 0) {
            CplMk::query()
                ->where('mk_id', $mkId)
                ->where('cpl_bok_id', $cplBokId)
                ->delete();

            return;
        }

        CplMk::query()->updateOrCreate(
            ['mk_id' => $mkId, 'cpl_bok_id' => $cplBokId],
            ['bobot' => $nilai],
        );
    }

    /**
     * MK yang tampil sebagai baris matriks: MK kurikulum ini + MK unit lain
     * yang diadaptasi ke kurikulum ini.
     *
     * @return Collection<int, Mk>
     */
    protected function mkTampil(Kurikulum $kurikulum): Collection
    {
        $adaptedMkIds = CplBokAdaptasiScope::adaptedMkIds($kurikulum->id);

        return Mk::query()
            ->where(fn ($query) => $query
                ->where('kurikulum_id', $kurikulum->id)
                ->when(
                    $adaptedMkIds->isNotEmpty(),
                    fn ($q) => $q->orWhereIn('id', $adaptedMkIds),
                ))
            ->orderBy('nama')
            ->get();
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [$this->normalisasiSemuaCplMkAction()];
    }

    /**
     * Tombol "Normalisasi semua" — menjalankan normalisasi per baris untuk
     * seluruh MK yang tampil di matriks sekaligus. MK yang sudah 100%, MK
     * tanpa CPL, dan MK milik unit lain dilewati.
     */
    public function normalisasiSemuaCplMkAction(): Action
    {
        return Action::make('normalisasiSemuaCplMk')
            ->label('Normalisasi semua')
            ->icon(Heroicon::OutlinedScale)
            ->color('warning')
            ->visible(fn (): bool => $this->getKurikulum() !== null)
            ->requiresConfirmation()
            ->modalHeading('Normalisasi bobot semua MK')
            ->modalDescription(
                'Bobot setiap MK terhadap CPL (via BoK) akan disesuaikan secara proporsional sehingga '
                .'total tiap baris tepat 100%. MK yang sudah 100%, MK yang belum punya CPL, dan MK milik '
                .'unit lain tidak akan disentuh.',
            )
            ->schema([
                NormalisasiBobotDesimal::field(),
            ])
            ->modalSubmitActionLabel('Normalisasi semua')
            ->action(function (array $data): void {
                $kurikulum = $this->getKurikulum();

                if ($kurikulum === null) {
                    return;
                }

                $ringkasan = app(NormalisasiBobotCplMkService::class)->normalisasiSemua(
                    $this->mkTampil($kurikulum),
                    $kurikulum->academic_unit_id,
                    NormalisasiBobotDesimal::dariData($data),
                );

                $rincian = sprintf(
                    'Sudah 100%%: %d MK · belum ada CPL: %d MK · milik unit lain (dilewati): %d MK.',
                    $ringkasan['sudah_pas'],
                    $ringkasan['kosong'],
                    $ringkasan['terkunci'],
                );

                if ($ringkasan['dinormalisasi'] === 0) {
                    Notification::make()
                        ->title('Tidak ada MK yang perlu dinormalisasi')
                        ->body($rincian)
                        ->info()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(sprintf('%d MK berhasil dinormalisasi', $ringkasan['dinormalisasi']))
                    ->body($rincian)
                    ->success()
                    ->send();
            });
    }

    /**
     * Tombol "Normalisasi" per baris MK — muncul bila total bobot CPL (via
     * BoK) yang berinteraksi dengan MK tsb belum tepat 100%. Hanya sel
     * yang benar-benar bisa diedit unit ini yang diredistribusi; sel
     * terkunci (MK/CplBok murni milik unit lain) tidak disentuh.
     */
    public function normalisasiBobotCplMkAction(): Action
    {
        return Action::make('normalisasiBobotCplMk')
            ->label('Normalisasi')
            ->icon(Heroicon::OutlinedScale)
            ->color('warning')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading('Normalisasi bobot CPL ↔ MK')
            ->modalDescription(
                'Bobot MK ini terhadap tiap CPL (via BoK) yang berinteraksi akan disesuaikan secara '
                .'proporsional lalu dibulatkan sesuai pilihan di bawah, sehingga totalnya tepat 100%. '
                .'CPL (via BoK) yang bukan milik unit Anda tidak akan disentuh.',
            )
            ->schema([
                NormalisasiBobotDesimal::field(),
            ])
            ->modalSubmitActionLabel('Normalisasi')
            ->action(function (array $data, array $arguments): void {
                $unitId = $this->getKurikulum()?->academic_unit_id;
                $mk = Mk::query()->find($arguments['mkId'] ?? null);

                if ($unitId === null || ! $mk instanceof Mk) {
                    return;
                }

                $desimal = NormalisasiBobotDesimal::dariData($data);
                $hasil = app(NormalisasiBobotCplMkService::class)->normalisasi($mk, $unitId, $desimal);

                match ($hasil['status']) {
                    'dinormalisasi' => Notification::make()
                        ->title('Bobot MK berhasil dinormalisasi')
                        ->body(sprintf(
                            'Total sebelumnya %.2f%% untuk %d CPL, kini totalnya tepat 100%%.',
                            $hasil['total_sebelum'],
                            $hasil['jumlah'],
                        ))
                        ->success()
                        ->send(),
                    'sudah_pas' => Notification::make()
                        ->title('Total bobot sudah 100%')
                        ->body('Tidak ada perubahan yang diperlukan.')
                        ->info()
                        ->send(),
                    'terkunci' => Notification::make()
                        ->title('Tidak ada ruang untuk menormalisasi')
                        ->body('Bobot dari CPL (via BoK) unit lain sudah mencapai/melebihi 100% — tidak ada ruang untuk menormalisasi bobot milik unit sendiri.')
                        ->warning()
                        ->send(),
                    default => Notification::make()
                        ->title('Belum ada CPL untuk dinormalisasi')
                        ->warning()
                        ->send(),
                };
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $kurikulum = $this->getKurikulum();

        if (! $kurikulum) {
            return [
                'kurikulum' => null,
                'mks' => collect(),
                'cplBoks' => collect(),
                'bobots' => collect(),
                'totals' => collect(),
                'cellEditable' => collect(),
                'mkAsalMap' => collect(),
            ];
        }

        $unitId = $kurikulum->academic_unit_id;
        $kurikulumId = $kurikulum->id;

        $mks = $this->mkTampil($kurikulum);

        $cplBoks = CplBokAdaptasiScope::scopeVisibleCplBok(CplBok::query(), $kurikulumId)
            ->with(['cpl.academicUnit', 'bok.academicUnit'])
            ->get()
            ->sortBy(fn (CplBok $cplBok): string => $cplBok->cpl->kode.'/'.$cplBok->bok->kode)
            ->values();

        $pivotRows = CplMk::query()
            ->whereIn('mk_id', $mks->pluck('id'))
            ->whereIn('cpl_bok_id', $cplBoks->pluck('id'))
            ->get();

        $bobots = $pivotRows->mapWithKeys(fn (CplMk $pivot): array => [
            $pivot->mk_id.'/'.$pivot->cpl_bok_id => (float) $pivot->bobot,
        ]);

        $totals = $pivotRows
            ->groupBy('mk_id')
            ->map(fn ($rows): float => CplMk::jumlahBobot($rows->pluck('bobot')));

        $cellEditable = collect();

        foreach ($mks as $mk) {
            $mkEditable = CplBokAdaptasiScope::canEditCplMkCell($mk, $unitId);

            foreach ($cplBoks as $cplBok) {
                $cellEditable[$mk->id.'/'.$cplBok->id] = $mkEditable;
            }
        }

        $mkAsalMap = $mks->mapWithKeys(fn (Mk $mk): array => [$mk->id => $mk->academic_unit_id !== $unitId]);

        return [
            'kurikulum' => $kurikulum,
            'mks' => $mks,
            'cplBoks' => $cplBoks,
            'bobots' => $bobots,
            'totals' => $totals,
            'cplKodeMap' => CplBokAdaptasiScope::displayKodeMapCpl($cplBoks->pluck('cpl')->unique('id'), $unitId),
            'bokKodeMap' => CplBokAdaptasiScope::displayKodeMapBok($cplBoks->pluck('bok')->unique('id'), $unitId),
            'cellEditable' => $cellEditable,
            'mkAsalMap' => $mkAsalMap,
        ];
    }
}

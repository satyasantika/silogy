<?php

namespace App\Modules\Kurikulum\Filament\Pages;

use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Kurikulum\Filament\Pages\Concerns\InteraksiMatrixPage;
use App\Modules\Kurikulum\Services\NormalisasiBobotCplMkService;
use App\Modules\Kurikulum\Support\CplBokAdaptasiScope;
use App\Modules\MK\Models\Mk;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Matriks interaksi CPL ↔ MK: kolom CPL (via BoK), baris mata kuliah,
 * irisan berupa bobot kontribusi (pivot cpl_mk); total bobot dihitung
 * PER KOLOM (per CplBok, dijumlah lintas MK) — target invariannya tepat
 * 100%. Turut menampilkan MK/CplBok milik unit lain yang tersingkap lewat
 * adaptasi MK — baris tetap read-only kecuali setidaknya satu sisinya
 * (MK atau CplBok) benar-benar milik unit ini.
 */
class CplMkMatrix extends Page
{
    use InteraksiMatrixPage;

    protected string $view = 'filament.modules.kurikulum.pages.cpl-mk-matrix';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Interaksi';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'CPL ↔ MK';

    protected static ?string $title = 'Interaksi CPL ↔ MK (bobot)';

    protected static ?string $slug = 'interaksi/cpl-mk';

    public function updateBobot(string $mkId, string $cplBokId, ?string $bobot): void
    {
        $unitId = $this->getKurikulum()?->academic_unit_id;

        if ($unitId === null || ! CplBokAdaptasiScope::isVisibleMkCplBokCell($mkId, $cplBokId, $unitId)) {
            Notification::make()
                ->title('Sel interaksi tidak valid untuk kurikulum ini')
                ->warning()
                ->send();

            return;
        }

        $mk = Mk::query()->findOrFail($mkId);
        $cplBok = CplBok::query()->with(['cpl', 'bok'])->findOrFail($cplBokId);

        if (! CplBokAdaptasiScope::canEditCplMkCell($mk, $cplBok, $unitId)) {
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

        CplMk::query()->updateOrCreate(
            ['mk_id' => $mkId, 'cpl_bok_id' => $cplBokId],
            ['bobot' => min((float) $bobot, 100)],
        );
    }

    /**
     * Tombol "Normalisasi" per kolom CplBok — muncul bila total bobot MK
     * yang berinteraksi dengan CplBok tsb belum tepat 100%. Hanya baris
     * yang benar-benar bisa diedit unit ini yang diredistribusi; baris
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
                'Bobot tiap MK yang berinteraksi dengan CPL (via BoK) ini akan disesuaikan secara '
                .'proporsional lalu dibulatkan ke 2 desimal, sehingga totalnya tepat 100%. Baris MK '
                .'yang bukan milik unit Anda tidak akan disentuh.',
            )
            ->modalSubmitActionLabel('Normalisasi')
            ->action(function (array $arguments): void {
                $unitId = $this->getKurikulum()?->academic_unit_id;
                $cplBok = CplBok::query()->with(['cpl', 'bok'])->find($arguments['cplBokId'] ?? null);

                if ($unitId === null || ! $cplBok instanceof CplBok) {
                    return;
                }

                $hasil = app(NormalisasiBobotCplMkService::class)->normalisasi($cplBok, $unitId);

                match ($hasil['status']) {
                    'dinormalisasi' => Notification::make()
                        ->title('Bobot MK berhasil dinormalisasi')
                        ->body(sprintf(
                            'Total sebelumnya %.2f%% untuk %d MK, kini totalnya tepat 100%%.',
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
                        ->body('Bobot dari MK unit lain sudah mencapai/melebihi 100% — tidak ada ruang untuk menormalisasi bobot MK milik sendiri.')
                        ->warning()
                        ->send(),
                    default => Notification::make()
                        ->title('Belum ada MK untuk dinormalisasi')
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

        $adaptedMkIds = CplBokAdaptasiScope::adaptedMkIds($unitId);

        $mks = Mk::query()
            ->where(fn ($query) => $query
                ->where('academic_unit_id', $unitId)
                ->when(
                    $adaptedMkIds->isNotEmpty(),
                    fn ($q) => $q->orWhereIn('id', $adaptedMkIds),
                ))
            ->orderBy('nama')
            ->get();

        $cplBoks = CplBokAdaptasiScope::scopeVisibleCplBok(CplBok::query(), $unitId)
            ->with(['cpl', 'bok'])
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
            ->groupBy('cpl_bok_id')
            ->map(fn ($rows): float => (float) $rows->sum('bobot'));

        $cellEditable = collect();

        foreach ($mks as $mk) {
            foreach ($cplBoks as $cplBok) {
                $cellEditable[$mk->id.'/'.$cplBok->id] = CplBokAdaptasiScope::canEditCplMkCell($mk, $cplBok, $unitId);
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

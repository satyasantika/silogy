<?php

namespace App\Modules\Kurikulum\Filament\Pages;

use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Kurikulum\Filament\Pages\Concerns\InteraksiMatrixPage;
use App\Modules\Kurikulum\Support\CplBokAdaptasiScope;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Matriks interaksi CPL ↔ BoK: kolom bahan kajian (BoK), baris CPL,
 * irisan berupa switch keterkaitan (pivot cpl_bok). Turut menampilkan
 * CPL/BoK milik unit lain yang tersingkap lewat adaptasi MK — pasangan
 * boleh diedit selama minimal satu sisinya (CPL atau BoK) milik unit ini.
 */
class CplBokMatrix extends Page
{
    use InteraksiMatrixPage;

    protected string $view = 'filament.modules.kurikulum.pages.cpl-bok-matrix';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Interaksi';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'CPL ↔ BoK';

    protected static ?string $title = 'Interaksi CPL ↔ BoK';

    protected static ?string $slug = 'interaksi/cpl-bok';

    public function toggle(string $cplId, string $bokId): void
    {
        $unitId = $this->getKurikulum()?->academic_unit_id;

        if ($unitId === null || ! CplBokAdaptasiScope::isVisiblePair($cplId, $bokId, $unitId)) {
            Notification::make()
                ->title('Pasangan CPL/BoK tidak valid untuk kurikulum ini')
                ->warning()
                ->send();

            return;
        }

        $cpl = Cpl::query()->findOrFail($cplId);
        $bok = Bok::query()->findOrFail($bokId);

        if (! CplBokAdaptasiScope::canToggleCplBok($cpl, $bok, $unitId)) {
            Notification::make()
                ->title('Pasangan ini murni milik unit lain')
                ->body('CPL dan BoK ini sama-sama bukan milik unit Anda, sehingga tidak dapat diubah dari sini.')
                ->warning()
                ->send();

            return;
        }

        $pivot = CplBok::query()
            ->where('cpl_id', $cplId)
            ->where('bok_id', $bokId)
            ->first();

        if ($pivot) {
            if ($this->cplBokMemilikiBobotMk($pivot)) {
                Notification::make()
                    ->title('Tidak dapat melepas pemetaan')
                    ->body('Hapus bobot pada interaksi CPL ↔ MK terlebih dahulu sebelum melepas centang.')
                    ->warning()
                    ->send();

                return;
            }

            $pivot->delete();

            return;
        }

        CplBok::query()->create([
            'cpl_id' => $cplId,
            'bok_id' => $bokId,
        ]);
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
                'boks' => collect(),
                'cpls' => collect(),
                'terpetakan' => collect(),
                'terkunciBobot' => collect(),
                'editableSisi' => collect(),
                'cplAsalMap' => collect(),
                'bokAsalMap' => collect(),
            ];
        }

        $unitId = $kurikulum->academic_unit_id;

        $cpls = CplBokAdaptasiScope::scopeVisibleCpl(Cpl::query()->with('academicUnit'), $unitId)
            ->orderBy('kode')
            ->get();

        $boks = CplBokAdaptasiScope::scopeVisibleBok(Bok::query()->with('academicUnit'), $unitId)
            ->orderBy('kode')
            ->get();

        $pivotRows = CplBok::query()
            ->whereIn('cpl_id', $cpls->pluck('id'))
            ->whereIn('bok_id', $boks->pluck('id'))
            ->get();

        $terpetakan = $pivotRows->mapWithKeys(fn (CplBok $pivot): array => [
            $pivot->cpl_id.'/'.$pivot->bok_id => true,
        ]);

        $cplBokIdsBerbobot = CplMk::query()
            ->whereIn('cpl_bok_id', $pivotRows->pluck('id'))
            ->where('bobot', '>', 0)
            ->pluck('cpl_bok_id')
            ->unique();

        $terkunciBobot = $pivotRows
            ->filter(fn (CplBok $pivot): bool => $cplBokIdsBerbobot->contains($pivot->id))
            ->mapWithKeys(fn (CplBok $pivot): array => [
                $pivot->cpl_id.'/'.$pivot->bok_id => true,
            ]);

        $editableSisi = collect();

        foreach ($cpls as $cpl) {
            foreach ($boks as $bok) {
                $editableSisi[$cpl->id.'/'.$bok->id] = CplBokAdaptasiScope::canToggleCplBok($cpl, $bok, $unitId);
            }
        }

        $cplAsalMap = $cpls->mapWithKeys(fn (Cpl $cpl): array => [$cpl->id => $cpl->academic_unit_id !== $unitId]);
        $bokAsalMap = $boks->mapWithKeys(fn (Bok $bok): array => [$bok->id => $bok->academic_unit_id !== $unitId]);

        return [
            'kurikulum' => $kurikulum,
            'boks' => $boks,
            'cpls' => $cpls,
            'terpetakan' => $terpetakan,
            'terkunciBobot' => $terkunciBobot,
            'editableSisi' => $editableSisi,
            'cplKodeMap' => CplBokAdaptasiScope::displayKodeMapCpl($cpls, $unitId),
            'bokKodeMap' => CplBokAdaptasiScope::displayKodeMapBok($boks, $unitId),
            'cplAsalMap' => $cplAsalMap,
            'bokAsalMap' => $bokAsalMap,
        ];
    }

    private function cplBokMemilikiBobotMk(CplBok $cplBok): bool
    {
        return CplMk::query()
            ->where('cpl_bok_id', $cplBok->id)
            ->where('bobot', '>', 0)
            ->exists();
    }
}

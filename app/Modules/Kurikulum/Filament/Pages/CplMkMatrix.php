<?php

namespace App\Modules\Kurikulum\Filament\Pages;

use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Kurikulum\Filament\Pages\Concerns\InteraksiMatrixPage;
use App\Modules\MK\Models\Mk;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Matriks interaksi CPL ↔ MK: kolom CPL (via BoK), baris mata kuliah,
 * irisan berupa bobot kontribusi (pivot cpl_mk); total bobot per MK
 * dihitung otomatis dan ditampilkan setelah nama MK.
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
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $kurikulum = $this->getKurikulum();

        if (! $kurikulum) {
            return ['kurikulum' => null, 'mks' => collect(), 'cplBoks' => collect(), 'bobots' => collect(), 'totals' => collect()];
        }

        $mks = Mk::query()
            ->where('academic_unit_id', $kurikulum->academic_unit_id)
            ->orderBy('nama')
            ->get();

        $cplBoks = CplBok::query()
            ->whereHas('cpl', fn ($query) => $query->where('academic_unit_id', $kurikulum->academic_unit_id))
            ->with(['cpl', 'bok'])
            ->get()
            ->sortBy(fn (CplBok $cplBok): string => $cplBok->cpl->kode.'/'.$cplBok->bok->kode)
            ->values();

        $pivotRows = CplMk::query()
            ->whereIn('mk_id', $mks->pluck('id'))
            ->get();

        $bobots = $pivotRows->mapWithKeys(fn (CplMk $pivot): array => [
            $pivot->mk_id.'/'.$pivot->cpl_bok_id => (float) $pivot->bobot,
        ]);

        $totals = $pivotRows
            ->groupBy('mk_id')
            ->map(fn ($rows): float => (float) $rows->sum('bobot'));

        return [
            'kurikulum' => $kurikulum,
            'mks' => $mks,
            'cplBoks' => $cplBoks,
            'bobots' => $bobots,
            'totals' => $totals,
        ];
    }
}

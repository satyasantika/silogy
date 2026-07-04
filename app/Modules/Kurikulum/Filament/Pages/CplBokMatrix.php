<?php

namespace App\Modules\Kurikulum\Filament\Pages;

use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\Kurikulum\Filament\Pages\Concerns\InteraksiMatrixPage;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Matriks interaksi CPL ↔ BoK: kolom bahan kajian (BoK), baris CPL,
 * irisan berupa switch keterkaitan (pivot cpl_bok).
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
        $pivot = CplBok::query()
            ->where('cpl_id', $cplId)
            ->where('bok_id', $bokId)
            ->first();

        if ($pivot) {
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
            return ['kurikulum' => null, 'boks' => collect(), 'cpls' => collect(), 'terpetakan' => collect()];
        }

        $cpls = Cpl::query()
            ->where('academic_unit_id', $kurikulum->academic_unit_id)
            ->orderBy('kode')
            ->get();

        $boks = Bok::query()
            ->where('academic_unit_id', $kurikulum->academic_unit_id)
            ->orderBy('kode')
            ->get();

        $terpetakan = CplBok::query()
            ->whereIn('cpl_id', $cpls->pluck('id'))
            ->get()
            ->mapWithKeys(fn (CplBok $pivot): array => [
                $pivot->cpl_id.'/'.$pivot->bok_id => true,
            ]);

        return [
            'kurikulum' => $kurikulum,
            'boks' => $boks,
            'cpls' => $cpls,
            'terpetakan' => $terpetakan,
        ];
    }
}

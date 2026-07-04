<?php

namespace App\Modules\Kurikulum\Filament\Pages;

use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplProfilLulusan;
use App\Modules\Kurikulum\Filament\Pages\Concerns\InteraksiMatrixPage;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Matriks interaksi Profil ↔ CPL: kolom profil lulusan, baris CPL,
 * irisan berupa switch keterkaitan (pivot cpl_profil_lulusan).
 */
class ProfilCplMatrix extends Page
{
    use InteraksiMatrixPage;

    protected string $view = 'filament.modules.kurikulum.pages.profil-cpl-matrix';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Interaksi';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Profil ↔ CPL';

    protected static ?string $title = 'Interaksi Profil ↔ CPL';

    protected static ?string $slug = 'interaksi/profil-cpl';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess()
            && (KurikulumTerpilih::current()?->academicUnit?->isProdi() ?? false);
    }

    public function toggle(string $cplId, string $profilId): void
    {
        $pivot = CplProfilLulusan::query()
            ->where('cpl_id', $cplId)
            ->where('profil_lulusan_id', $profilId)
            ->first();

        if ($pivot) {
            $pivot->delete();

            return;
        }

        CplProfilLulusan::query()->create([
            'cpl_id' => $cplId,
            'profil_lulusan_id' => $profilId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $kurikulum = $this->getKurikulum();

        if (! $kurikulum || ! ($kurikulum->academicUnit?->isProdi() ?? false)) {
            return ['kurikulum' => $kurikulum, 'profils' => collect(), 'cpls' => collect(), 'terpetakan' => collect()];
        }

        $profils = ProfilLulusan::query()
            ->where('kurikulum_id', $kurikulum->id)
            ->orderBy('urutan')
            ->orderBy('kode')
            ->get();

        $cpls = Cpl::query()
            ->where('academic_unit_id', $kurikulum->academic_unit_id)
            ->orderBy('kode')
            ->get();

        $terpetakan = CplProfilLulusan::query()
            ->whereIn('cpl_id', $cpls->pluck('id'))
            ->get()
            ->mapWithKeys(fn (CplProfilLulusan $pivot): array => [
                $pivot->cpl_id.'/'.$pivot->profil_lulusan_id => true,
            ]);

        return [
            'kurikulum' => $kurikulum,
            'profils' => $profils,
            'cpls' => $cpls,
            'terpetakan' => $terpetakan,
        ];
    }
}

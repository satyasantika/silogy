<?php

namespace App\Modules\MK\Filament\Pages;

use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Filament\Pages\Concerns\InteraksiKoordinatorMatrixPage;
use App\Modules\MK\Filament\Resources\PerubahanCpmkResource;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Support\GerbangPerubahanCpmk;
use App\Support\Filament\NavigationGroupPeran;
use App\Support\Filament\NavigationSortPeran;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Matriks interaksi CPL ↔ CPMK: kolom CPL, baris CPMK, irisan berupa
 * centang pemetaan (pivot mk_cpmk).
 */
class CplCpmkMatrix extends Page
{
    use HasKoordinatorMkScope;
    use InteraksiKoordinatorMatrixPage;

    protected string $view = 'filament.modules.mk.pages.cpl-cpmk-matrix';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroupPeran::resolve('Interaksi');
    }

    public static function getNavigationSort(): ?int
    {
        return NavigationSortPeran::resolve('cpl-cpmk', 4);
    }

    protected static ?string $navigationLabel = 'CPL ↔ CPMK';

    protected static ?string $title = 'Interaksi CPL ↔ CPMK';

    protected static ?string $slug = 'interaksi/cpl-cpmk';

    public function toggle(string $cpmkId, string $cplId): void
    {
        $mk = $this->getMkTerpilih();
        $cpmk = $mk instanceof Mk
            ? Cpmk::query()->where('mk_id', $mk->id)->find($cpmkId)
            : null;

        if (! $mk instanceof Mk || ! $cpmk instanceof Cpmk) {
            $this->tolakToggle('CPMK tidak valid', 'CPMK tidak ditemukan pada mata kuliah yang sedang dipilih.');

            return;
        }

        $cplMks = CplMk::query()
            ->where('mk_id', $mk->id)
            ->whereHas('cplBok', fn ($query) => $query->where('cpl_id', $cplId))
            ->get();

        if ($cplMks->isEmpty()) {
            $this->tolakToggle(
                'Pemetaan CPL–MK belum ada',
                'Berikan bobot pada interaksi CPL ↔ MK terlebih dahulu untuk MK ini.',
            );

            return;
        }

        $semesterId = SemesterTerpilih::currentId($mk->id) ?? SemesterTerpilih::defaultId();

        if (! GerbangPerubahanCpmk::bolehUbah($mk, filled($semesterId) ? (string) $semesterId : null, auth()->user())) {
            $this->tolakToggle(
                'Perubahan CPMK belum diizinkan',
                'CPMK semester ini sudah berjalan. Ajukan usulan perubahan CPMK dan tunggu persetujuan Tim Kurikulum.',
            );

            return;
        }

        $existing = MkCpmk::query()
            ->where('cpmk_id', $cpmkId)
            ->whereHas(
                'cplMk.cplBok',
                fn ($query) => $query->where('cpl_id', $cplId),
            )
            ->get();

        if ($existing->isNotEmpty()) {
            if ($existing->contains(fn (MkCpmk $mkCpmk): bool => $mkCpmk->subcpmks()->exists())) {
                Notification::make()
                    ->title('Tidak dapat melepas pemetaan')
                    ->body('Hapus Sub-CPMK terkait terlebih dahulu sebelum melepas centang.')
                    ->warning()
                    ->send();

                return;
            }

            MkCpmk::query()->whereIn('id', $existing->pluck('id'))->delete();

            return;
        }

        foreach ($cplMks as $cplMk) {
            MkCpmk::query()->updateOrCreate(
                [
                    'cpl_mk_id' => $cplMk->id,
                    'cpmk_id' => $cpmkId,
                ],
                ['bobot' => 0],
            );
        }
    }

    private function tolakToggle(string $judul, string $isi): void
    {
        Notification::make()
            ->title($judul)
            ->body($isi)
            ->warning()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $kurikulum = $this->getKurikulum();

        if (! $kurikulum instanceof Kurikulum) {
            return array_merge([
                'kurikulum' => null,
                'cpmks' => collect(),
                'cpls' => collect(),
                'terpetakan' => collect(),
                'terkunciSubcpmk' => collect(),
                'gerbangTertutup' => false,
                'usulanUrl' => PerubahanCpmkResource::getUrl('index'),
            ], $this->mkTerpilihViewData());
        }

        $mk = $this->getMkTerpilih();

        if (! $mk instanceof Mk) {
            return array_merge([
                'kurikulum' => $kurikulum,
                'cpmks' => collect(),
                'cpls' => collect(),
                'terpetakan' => collect(),
                'terkunciSubcpmk' => collect(),
                'gerbangTertutup' => false,
                'usulanUrl' => PerubahanCpmkResource::getUrl('index'),
            ], $this->mkTerpilihViewData());
        }

        $semesterId = SemesterTerpilih::currentId($mk->id) ?? SemesterTerpilih::defaultId();

        $cpmks = Cpmk::query()
            ->where('mk_id', $mk->id)
            ->orderBy('kode')
            ->get();
        $cpls = Cpl::query()
            ->whereHas(
                'cplBoks.cplMks',
                fn (Builder $query): Builder => $query->where('mk_id', $mk->id),
            )
            ->orderBy('kode')
            ->get();

        $pivotRows = MkCpmk::query()
            ->whereIn('cpmk_id', $cpmks->pluck('id'))
            ->with(['cplMk.cplBok'])
            ->get();

        $terpetakan = $pivotRows->mapWithKeys(function (MkCpmk $pivot): array {
            $cplId = $pivot->cplMk?->cplBok?->cpl_id;

            if (blank($cplId)) {
                return [];
            }

            return [$pivot->cpmk_id.'/'.$cplId => true];
        });

        $mkCpmkIdsBerSubcpmk = MkCpmk::query()
            ->whereIn('cpmk_id', $cpmks->pluck('id'))
            ->whereHas('subcpmks')
            ->with('cplMk.cplBok')
            ->get();

        $terkunciSubcpmk = $mkCpmkIdsBerSubcpmk->mapWithKeys(function (MkCpmk $pivot): array {
            $cplId = $pivot->cplMk?->cplBok?->cpl_id;

            if (blank($cplId)) {
                return [];
            }

            return [$pivot->cpmk_id.'/'.$cplId => true];
        });

        return array_merge([
            'kurikulum' => $kurikulum,
            'cpmks' => $cpmks,
            'cpls' => $cpls,
            'terpetakan' => $terpetakan,
            'terkunciSubcpmk' => $terkunciSubcpmk,
            'gerbangTertutup' => GerbangPerubahanCpmk::butuhPersetujuan(
                $mk,
                filled($semesterId) ? (string) $semesterId : null,
                auth()->user(),
            ),
            'usulanUrl' => PerubahanCpmkResource::getUrl('index'),
        ], $this->mkTerpilihViewData());
    }
}

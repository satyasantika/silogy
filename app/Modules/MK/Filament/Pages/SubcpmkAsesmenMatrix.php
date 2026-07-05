<?php

namespace App\Modules\MK\Filament\Pages;

use App\Models\User;
use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Filament\Pages\Concerns\InteraksiKoordinatorMatrixPage;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\MK\Support\PenawaranMkScope;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Matriks interaksi Sub-CPMK ↔ Asesmen: kolom Sub-CPMK, baris komponen
 * penilaian (asesmen), irisan berupa bobot kontribusi.
 */
class SubcpmkAsesmenMatrix extends Page
{
    use HasKoordinatorMkScope;
    use InteraksiKoordinatorMatrixPage;

    protected string $view = 'filament.modules.mk.pages.subcpmk-asesmen-matrix';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Interaksi';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Sub-CPMK ↔ Asesmen';

    protected static ?string $title = 'Interaksi Sub-CPMK ↔ Asesmen (bobot)';

    protected static ?string $slug = 'interaksi/subcpmk-asesmen';

    public ?string $semesterTerpilihId = null;

    public function mount(): void
    {
        $mkId = MkTerpilih::currentId();
        $this->semesterTerpilihId = SemesterTerpilih::currentId($mkId);
    }

    public function updatedSemesterTerpilihId(?string $value): void
    {
        $mkId = MkTerpilih::currentId();

        if (filled($mkId) && filled($value)) {
            SemesterTerpilih::set($mkId, $value);
        }
    }

    public function updateBobot(string $komponenPenilaianId, string $subcpmkId, ?string $bobot): void
    {
        $bobot = trim((string) $bobot);

        if ($bobot === '' || ! is_numeric($bobot) || (float) $bobot <= 0) {
            SubcpmkKomponenPenilaian::query()
                ->where('komponen_penilaian_id', $komponenPenilaianId)
                ->where('subcpmk_id', $subcpmkId)
                ->delete();

            return;
        }

        $komponen = KomponenPenilaian::query()
            ->with('kelasMk')
            ->findOrFail($komponenPenilaianId);

        SubcpmkKomponenPenilaian::query()->updateOrCreate(
            [
                'komponen_penilaian_id' => $komponenPenilaianId,
                'subcpmk_id' => $subcpmkId,
            ],
            [
                'bobot' => min((float) $bobot, 100),
                'semester_id' => $komponen->kelasMk?->semester_id,
            ],
        );
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
                'semesterOptions' => [],
                'tampilkanFilterSemester' => false,
                'asesmen' => collect(),
                'subcpmks' => collect(),
                'bobots' => collect(),
                'totals' => collect(),
            ], $this->mkTerpilihViewData());
        }

        $mk = $this->getMkTerpilih();

        if (! $mk instanceof \App\Modules\MK\Models\Mk) {
            return array_merge([
                'kurikulum' => $kurikulum,
                'semesterOptions' => [],
                'tampilkanFilterSemester' => false,
                'asesmen' => collect(),
                'subcpmks' => collect(),
                'bobots' => collect(),
                'totals' => collect(),
            ], $this->mkTerpilihViewData());
        }

        $semesterOptions = SemesterTerpilih::berlakuUntukUser()
            ? SemesterTerpilih::options($mk->id)
            : [];
        $tampilkanFilterSemester = SemesterTerpilih::berlakuUntukUser();

        if ($tampilkanFilterSemester) {
            $semesterId = filled($this->semesterTerpilihId)
                ? $this->semesterTerpilihId
                : SemesterTerpilih::currentId($mk->id);
        } else {
            $semesterId = SemesterTerpilih::defaultId();
        }

        if (blank($semesterId) || ($tampilkanFilterSemester && ! array_key_exists((string) $semesterId, SemesterTerpilih::optionsSemua()))) {
            return array_merge([
                'kurikulum' => $kurikulum,
                'semesterOptions' => $semesterOptions,
                'tampilkanFilterSemester' => $tampilkanFilterSemester,
                'asesmen' => collect(),
                'subcpmks' => collect(),
                'bobots' => collect(),
                'totals' => collect(),
            ], $this->mkTerpilihViewData());
        }

        if ($tampilkanFilterSemester) {
            $this->semesterTerpilihId = (string) $semesterId;
            SemesterTerpilih::set($mk->id, (string) $semesterId);
        }

        $user = auth()->user();

        $subcpmks = Subcpmk::query()
            ->with(['mkCpmk.cpmk'])
            ->where('semester_id', $semesterId)
            ->whereHas(
                'mkCpmk.cpmk',
                fn ($cpmkQuery) => $cpmkQuery->where('mk_id', $mk->id),
            )
            ->orderBy('kode')
            ->get();

        $asesmen = KomponenPenilaian::query()
            ->with(['kelasMk.mkUnit.mk', 'evaluasi'])
            ->whereHas(
                'kelasMk',
                fn ($kelasQuery) => $kelasQuery
                    ->where('semester_id', $semesterId)
                    ->whereHas(
                        'mkUnit',
                        fn ($mkUnitQuery) => $mkUnitQuery->where('mk_id', $mk->id),
                    ),
            )
            ->when(
                $user instanceof User && PenawaranMkScope::isKoordinatorMkOnly($user),
                fn ($query) => $query->whereHas(
                    'kelasMk',
                    fn ($kelasQuery) => $kelasQuery->where(function ($scoped) use ($user, $mk): void {
                        $scoped->where('koordinator_mk_id', $user->id)
                            ->orWhereHas(
                                'mkUnit.mk',
                                fn ($mkQuery) => $mkQuery
                                    ->where('id', $mk->id)
                                    ->where('koordinator_mk_id', $user->id),
                            );
                    }),
                ),
            )
            ->orderBy('nama')
            ->get();

        $pivotRows = SubcpmkKomponenPenilaian::query()
            ->whereIn('komponen_penilaian_id', $asesmen->pluck('id'))
            ->whereIn('subcpmk_id', $subcpmks->pluck('id'))
            ->get();

        $bobots = $pivotRows->mapWithKeys(fn (SubcpmkKomponenPenilaian $pivot): array => [
            $pivot->komponen_penilaian_id.'/'.$pivot->subcpmk_id => (float) $pivot->bobot,
        ]);

        $totals = $pivotRows
            ->groupBy('komponen_penilaian_id')
            ->map(fn ($rows): float => (float) $rows->sum('bobot'));

        return array_merge([
            'kurikulum' => $kurikulum,
            'semesterOptions' => $semesterOptions,
            'tampilkanFilterSemester' => $tampilkanFilterSemester,
            'asesmen' => $asesmen,
            'subcpmks' => $subcpmks,
            'bobots' => $bobots,
            'totals' => $totals,
        ], $this->mkTerpilihViewData());
    }
}

<?php

namespace App\Modules\MK\Filament\Resources\MkResource\Pages;

use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Filament\Resources\MkResource;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Support\Filament\Pages\BaseSimpleEditRecord;
use Filament\Actions\DeleteAction;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

class EditMk extends BaseSimpleEditRecord
{
    protected static string $resource = MkResource::class;

    /** Id penawaran (MkUnit) yang rincian semesternya sedang dibuka. */
    public ?string $penawaranDetailId = null;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler($this->getSubmitFormLivewireMethodName()),
                $this->getFormActionsContentComponent(),
                View::make('filament.modules.mk.partials.mk-edit-penawaran')
                    ->viewData(fn (): array => [
                        ...$this->rekapPenawaranMk(),
                        'penawaranDetailId' => $this->penawaranDetailId,
                    ]),
            ]);
    }

    public function toggleDetailPenawaran(?string $mkUnitId): void
    {
        if (blank($mkUnitId)) {
            $this->penawaranDetailId = null;

            return;
        }

        $this->penawaranDetailId = $this->penawaranDetailId === $mkUnitId
            ? null
            : $mkUnitId;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['sks'] = (int) ($data['sks_teori'] ?? 0)
            + (int) ($data['sks_praktik'] ?? 0)
            + (int) ($data['sks_lapangan'] ?? 0);

        return $data;
    }

    /**
     * Rekap seluruh penawaran (MkUnit) untuk MK ini — lintas prodi/kurikulum —
     * beserta agregat kelas/mahasiswa di seluruh semester kalender.
     *
     * @return array{
     *     penawaran: list<array{
     *         id: string,
     *         kode: string,
     *         unit: string,
     *         kurikulum: string,
     *         semester_ke: int|null,
     *         is_active: bool,
     *         jumlah_kelas: int,
     *         jumlah_mahasiswa: int,
     *         per_semester: list<array{
     *             semester_id: string,
     *             nama: string,
     *             kode: string,
     *             jumlah_kelas: int,
     *             jumlah_mahasiswa: int
     *         }>
     *     }>,
     *     total_kelas: int,
     *     total_mahasiswa: int
     * }
     */
    public function rekapPenawaranMk(): array
    {
        /** @var Mk $mk */
        $mk = $this->getRecord();

        $units = MkUnit::query()
            ->where('mk_id', $mk->id)
            ->with(['academicUnit', 'kurikulum'])
            ->orderBy('kode')
            ->get()
            ->sortBy(fn (MkUnit $unit): string => (string) ($unit->academicUnit?->nama ?? $unit->kode))
            ->values();

        if ($units->isEmpty()) {
            return [
                'penawaran' => [],
                'total_kelas' => 0,
                'total_mahasiswa' => 0,
            ];
        }

        $kelasSemua = KelasMk::query()
            ->whereIn('mk_unit_id', $units->modelKeys())
            ->with('semester')
            ->withCount('mahasiswas')
            ->get()
            ->groupBy('mk_unit_id');

        $penawaran = $units->map(function (MkUnit $unit) use ($kelasSemua): array {
            /** @var Collection<int, KelasMk> $kelasUnit */
            $kelasUnit = $kelasSemua->get($unit->id, collect());

            $perSemester = $kelasUnit
                ->groupBy('semester_id')
                ->map(function (Collection $kelasSemester): array {
                    /** @var KelasMk $pertama */
                    $pertama = $kelasSemester->first();
                    $semester = $pertama->semester;

                    return [
                        'semester_id' => (string) $pertama->semester_id,
                        'nama' => (string) ($semester?->nama ?? '—'),
                        'kode' => (string) ($semester?->kode ?? '—'),
                        'jumlah_kelas' => $kelasSemester->count(),
                        'jumlah_mahasiswa' => (int) $kelasSemester->sum('mahasiswas_count'),
                    ];
                })
                ->sortByDesc('kode')
                ->values()
                ->all();

            $kurikulum = $unit->kurikulum;
            $labelKurikulum = $kurikulum
                ? trim("{$kurikulum->nama}".(filled($kurikulum->tahun) ? " ({$kurikulum->tahun})" : ''))
                : '—';

            return [
                'id' => $unit->id,
                'kode' => (string) $unit->kode,
                'unit' => (string) ($unit->academicUnit?->nama_lengkap ?? '—'),
                'kurikulum' => $labelKurikulum,
                'semester_ke' => $unit->semester_ke,
                'is_active' => (bool) $unit->is_active,
                'jumlah_kelas' => $kelasUnit->count(),
                'jumlah_mahasiswa' => (int) $kelasUnit->sum('mahasiswas_count'),
                'per_semester' => $perSemester,
            ];
        })->all();

        return [
            'penawaran' => $penawaran,
            'total_kelas' => (int) collect($penawaran)->sum('jumlah_kelas'),
            'total_mahasiswa' => (int) collect($penawaran)->sum('jumlah_mahasiswa'),
        ];
    }
}

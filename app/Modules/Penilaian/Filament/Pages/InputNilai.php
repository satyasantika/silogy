<?php

namespace App\Modules\Penilaian\Filament\Pages;

use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use App\Modules\Penilaian\Policies\InputNilaiPolicy;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InputNilai extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $navigationLabel = 'Input Nilai';

    protected static string|\UnitEnum|null $navigationGroup = 'Penilaian';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'penilaian/input-nilai';

    protected static ?string $title = 'Input Nilai';

    protected string $view = 'filament.modules.penilaian.pages.input-nilai';

    public ?string $kelasMkId = null;

    /**
     * @var array<string, array<string, string|null>>
     */
    public array $nilai = [];

    /**
     * @var list<array{id: string, nim: string, nama: string}>
     */
    public array $rows = [];

    /**
     * @var list<array{id: string, label: string}>
     */
    public array $columns = [];

    public bool $showKalkulasiBadge = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && app(InputNilaiPolicy::class)->access($user);
    }

    public function mount(): void
    {
        $semesterAktifId = Semester::query()
            ->where('status_aktif', true)
            ->value('id');

        $kelas = $this->kelasMkQuery($semesterAktifId)->first();

        if ($kelas !== null) {
            $this->kelasMkId = $kelas->id;
            $this->loadMatrix();
        }
    }

    public function updatedKelasMkId(): void
    {
        $this->showKalkulasiBadge = false;
        $this->loadMatrix();
    }

    /**
     * @return array<string, string>
     */
    public function getKelasMkOptionsProperty(): array
    {
        $semesterAktifId = Semester::query()
            ->where('status_aktif', true)
            ->value('id');

        return $this->kelasMkQuery($semesterAktifId)
            ->with(['mkUnit.mk', 'semester'])
            ->get()
            ->mapWithKeys(fn (KelasMk $kelas): array => [
                $kelas->id => sprintf(
                    '%s – Kelas %s (%s)',
                    $kelas->mkUnit?->mk?->nama ?? '—',
                    $kelas->kode_kelas,
                    $kelas->semester?->kode ?? '—',
                ),
            ])
            ->all();
    }

    public function loadMatrix(): void
    {
        $this->nilai = [];
        $this->rows = [];
        $this->columns = [];

        if ($this->kelasMkId === null) {
            return;
        }

        $kelasMk = KelasMk::query()->find($this->kelasMkId);

        if (! $kelasMk instanceof KelasMk) {
            return;
        }

        Gate::authorize('inputNilai', $kelasMk);

        $this->columns = SubcpmkKomponenPenilaian::query()
            ->whereHas(
                'komponenPenilaian',
                fn ($query) => $query->where('kelas_mk_id', $kelasMk->id),
            )
            ->with(['komponenPenilaian', 'subcpmk'])
            ->get()
            ->map(fn (SubcpmkKomponenPenilaian $skp): array => [
                'id' => $skp->id,
                'label' => sprintf(
                    '%s / %s',
                    $skp->komponenPenilaian?->kode ?? $skp->komponenPenilaian?->nama ?? '—',
                    $skp->subcpmk?->kode ?? '—',
                ),
            ])
            ->values()
            ->all();

        $kelasMkMahasiswas = KelasMkMahasiswa::query()
            ->where('kelas_mk_id', $kelasMk->id)
            ->with('mahasiswa')
            ->join('mahasiswas', 'mahasiswas.id', '=', 'kelas_mk_mahasiswa.mahasiswa_id')
            ->orderBy('mahasiswas.nama')
            ->select('kelas_mk_mahasiswa.*')
            ->get();

        $this->rows = $kelasMkMahasiswas
            ->map(fn (KelasMkMahasiswa $kmm): array => [
                'id' => $kmm->id,
                'nim' => $kmm->mahasiswa?->nim ?? '—',
                'nama' => $kmm->mahasiswa?->nama ?? '—',
            ])
            ->values()
            ->all();

        $skpIds = collect($this->columns)->pluck('id');
        $kmmIds = collect($this->rows)->pluck('id');

        if ($skpIds->isEmpty() || $kmmIds->isEmpty()) {
            return;
        }

        $existing = NilaiMahasiswa::query()
            ->whereIn('kelas_mk_mahasiswa_id', $kmmIds)
            ->whereIn('subcpmk_komponenpenilaian_id', $skpIds)
            ->get()
            ->groupBy('kelas_mk_mahasiswa_id');

        foreach ($this->rows as $row) {
            $this->nilai[$row['id']] = [];

            foreach ($this->columns as $column) {
                $nilai = $existing
                    ->get($row['id'])
                    ?->firstWhere('subcpmk_komponenpenilaian_id', $column['id']);

                $this->nilai[$row['id']][$column['id']] = $nilai?->nilai !== null
                    ? (string) $nilai->nilai
                    : null;
            }
        }
    }

    public function save(): void
    {
        if ($this->kelasMkId === null) {
            return;
        }

        $kelasMk = KelasMk::query()->findOrFail($this->kelasMkId);

        Gate::authorize('inputNilai', $kelasMk);

        $allowedKmmIds = KelasMkMahasiswa::query()
            ->where('kelas_mk_id', $kelasMk->id)
            ->pluck('id')
            ->all();

        $allowedSkpIds = SubcpmkKomponenPenilaian::query()
            ->whereHas(
                'komponenPenilaian',
                fn ($query) => $query->where('kelas_mk_id', $kelasMk->id),
            )
            ->pluck('id')
            ->all();

        foreach ($this->nilai as $kmmId => $cells) {
            if (! in_array($kmmId, $allowedKmmIds, true)) {
                throw ValidationException::withMessages([
                    'nilai' => 'Data mahasiswa tidak valid untuk kelas ini.',
                ]);
            }

            foreach ($cells as $skpId => $value) {
                if (! in_array($skpId, $allowedSkpIds, true)) {
                    throw ValidationException::withMessages([
                        'nilai' => 'Data komponen penilaian tidak valid untuk kelas ini.',
                    ]);
                }

                if ($value === null || $value === '') {
                    continue;
                }

                $numeric = (float) $value;

                if ($numeric < 0 || $numeric > 100) {
                    throw ValidationException::withMessages([
                        "nilai.{$kmmId}.{$skpId}" => 'Nilai harus antara 0 dan 100.',
                    ]);
                }

                NilaiMahasiswa::query()->updateOrCreate(
                    [
                        'subcpmk_komponenpenilaian_id' => $skpId,
                        'kelas_mk_mahasiswa_id' => $kmmId,
                    ],
                    [
                        'nilai' => $numeric,
                    ],
                );
            }
        }

        Notification::make()
            ->title('Tersimpan')
            ->success()
            ->send();

        $this->showKalkulasiBadge = true;
        $this->loadMatrix();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importCsv')
                ->label('Import CSV')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->disabled()
                ->tooltip('Coming soon MVP'),
        ];
    }

    /**
     * @return Builder<KelasMk>
     */
    protected function kelasMkQuery(?string $semesterAktifId): Builder
    {
        $query = KelasMk::query()
            ->where('dosen_pengampu_id', auth()->id());

        if ($semesterAktifId !== null) {
            $query->where('semester_id', $semesterAktifId);
        }

        return $query->orderBy('kode_kelas');
    }
}

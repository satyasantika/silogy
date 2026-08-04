<?php

namespace App\Modules\Penilaian\Filament\Pages;

use App\Models\User;
use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Filament\Pages\Concerns\InteraksiKoordinatorMatrixPage;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\MK\Support\PenawaranMkScope;
use App\Modules\Penilaian\Filament\Pages\Concerns\HasLaporanKelasMk;
use App\Modules\Penilaian\Services\PenilaianDosenService;
use App\Support\Filament\NavigationGroupPeran;
use App\Support\Filament\NavigationSortPeran;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Laporan (Portofolio, Evaluasi CPL, Hasil Analisis per Mahasiswa, Laporan
 * Lengkap) untuk Koordinator MK — sama seperti tab Laporan milik Dosen
 * Pengampu (lihat InputNilai), tapi lintas seluruh kelas & dosen pengampu
 * pada MK yang dikoordinatori, bukan cuma kelas milik sendiri.
 */
class LaporanKoordinator extends Page
{
    use HasLaporanKelasMk;
    use InteraksiKoordinatorMatrixPage;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroupPeran::resolve('Penilaian');
    }

    public static function getNavigationSort(): ?int
    {
        return NavigationSortPeran::resolve('laporan-koordinator', 1);
    }

    protected static ?string $navigationLabel = 'Laporan';

    protected static ?string $title = 'Laporan Koordinator MK';

    protected static ?string $slug = 'penilaian/laporan-koordinator';

    protected string $view = 'filament.modules.penilaian.pages.laporan-koordinator';

    public ?string $semesterTerpilihId = null;

    public ?string $kelasMkId = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        // Cukup role Koordinator Mata Kuliah (boleh multi-role) + MK sedang
        // dikerjakan — tidak bergantung KurikulumTerpilih / pivot prodi.
        return $user->can('lihat_laporan')
            && $user->hasRole('Koordinator Mata Kuliah')
            && MkTerpilih::current() !== null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->hasRole('Koordinator Mata Kuliah')
            && static::canAccess();
    }

    public function mount(): void
    {
        $mkId = MkTerpilih::currentId();
        $this->semesterTerpilihId = SemesterTerpilih::currentId($mkId);

        $this->pilihKelasPertama();
    }

    public function updatedSemesterTerpilihId(?string $value): void
    {
        $mkId = MkTerpilih::currentId();

        if (filled($mkId) && filled($value)) {
            SemesterTerpilih::set($mkId, $value);
        }

        $this->pilihKelasPertama();
    }

    public function pilihKelas(string $kelasMkId): void
    {
        $kelas = $this->kelasMkQuery()->whereKey($kelasMkId)->first();

        if ($kelas === null) {
            return;
        }

        $this->kelasMkId = $kelas->id;
        $this->loadLaporan();
    }

    protected function pilihKelasPertama(): void
    {
        $kelas = $this->kelasMkQuery()->orderBy('kode_kelas')->first();
        $this->kelasMkId = $kelas?->id;
        $this->loadLaporan();
    }

    public function loadLaporan(): void
    {
        $this->resetDataLaporan();

        if ($this->kelasMkId === null) {
            return;
        }

        $kelasMk = KelasMk::query()->with(['mkUnit', 'dosenPengampu'])->find($this->kelasMkId);

        if (! $kelasMk instanceof KelasMk) {
            return;
        }

        // Defense-in-depth: kelas sudah di-scope saat dipilih (mount/pilihKelas),
        // tapi divalidasi ulang di sini terhadap query yang sama sebelum dimuat.
        if (! $this->kelasMkQuery()->whereKey($kelasMk->id)->exists()) {
            $this->kelasMkId = null;

            return;
        }

        $this->penugasanBelumSelesai = ! $kelasMk->penugasanSelesai();

        if ($this->penugasanBelumSelesai) {
            return;
        }

        $this->muatDataLaporan($kelasMk);
    }

    public function getMkTerpilihProperty(): ?Mk
    {
        return $this->getMkTerpilih();
    }

    public function getSemesterTerpilihProperty(): string
    {
        return SemesterTerpilih::label($this->semesterTerpilihId);
    }

    /**
     * Seluruh kelas pada MK+semester terpilih — lintas dosen pengampu,
     * masing-masing diberi keterangan siapa dosen pengampunya.
     *
     * @return list<array{id: string, kode_kelas: string, dosen_pengampu_nama: string, jumlah_mahasiswa: int, rata_rata: float|null, sudah_dinilai: bool}>
     */
    public function getKelasCardsProperty(): array
    {
        return $this->kelasMkQuery()
            ->with('dosenPengampu')
            ->orderBy('kode_kelas')
            ->get()
            ->map(fn (KelasMk $kelas): array => array_merge(
                ['id' => $kelas->id],
                ['dosen_pengampu_nama' => $kelas->dosenPengampu?->full_name ?? '—'],
                PenilaianDosenService::ringkasanKelas($kelas),
            ))
            ->values()
            ->all();
    }

    /**
     * @return Builder<KelasMk>
     */
    protected function kelasMkQuery(): Builder
    {
        $mkId = MkTerpilih::currentId();

        if ($mkId === null) {
            return KelasMk::query()->whereRaw('1 = 0');
        }

        $user = auth()->user();

        return PenawaranMkScope::kelasMkQueryUntukMk($mkId, $user)
            ->when(
                filled($this->semesterTerpilihId),
                fn (Builder $query): Builder => $query->where('semester_id', $this->semesterTerpilihId),
            );
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $mk = $this->getMkTerpilih();
        $tampilkanFilterSemester = $mk instanceof Mk && SemesterTerpilih::berlakuUntukUser();

        return array_merge([
            'tampilkanFilterSemester' => $tampilkanFilterSemester,
            'semesterOptions' => ($tampilkanFilterSemester && $mk instanceof Mk)
                ? SemesterTerpilih::options($mk->id)
                : [],
        ], $this->mkTerpilihViewData());
    }
}

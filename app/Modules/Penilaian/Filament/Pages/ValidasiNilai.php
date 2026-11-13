<?php

namespace App\Modules\Penilaian\Filament\Pages;

use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\Mk;
use App\Modules\Penilaian\Filament\Pages\Concerns\HasLaporanKelasMk;
use App\Modules\Penilaian\Policies\ValidasiNilaiPolicy;
use App\Modules\Penilaian\Services\PenilaianMatrixService;
use App\Modules\Penilaian\Services\ValidasiNilaiService;
use App\Modules\Penilaian\Support\PenilaianSemesterTerpilih;
use App\Modules\Penilaian\Support\StatusValidasiNilai;
use App\Modules\Penilaian\Support\ValidatorNilaiScope;
use App\Support\Filament\DelegasiMenu;
use App\Support\Filament\NavigationGroupPeran;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Halaman Validator Nilai: daftar kelas dalam cakupan, tampilan baca-saja
 * laporan satu kelas (partial yang sama dengan Dosen/Koordinator), serta aksi
 * Validasi / Batalkan validasi.
 */
class ValidasiNilai extends Page
{
    use HasLaporanKelasMk;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroupPeran::resolve('Penilaian');
    }

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Validasi Nilai';

    protected static ?string $title = 'Validasi Nilai';

    protected static ?string $slug = 'penilaian/validasi-nilai';

    protected string $view = 'filament.modules.penilaian.pages.validasi-nilai';

    public ?string $semesterTerpilihId = null;

    public ?string $kelasMkId = null;

    /**
     * Memo hasil hitung untuk SATU request saja (properti privat tidak ikut
     * snapshot Livewire). Kunci memuat id semester/kelas sehingga berganti
     * pilihan otomatis memakai entri baru; validasi/batal mengosongkannya.
     *
     * @var array<string, mixed>
     */
    private array $memo = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if (DelegasiMenu::peranAktifDosenBukanAdmin($user)) {
            return false;
        }

        return app(ValidasiNilaiPolicy::class)->access($user);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->semesterTerpilihId = PenilaianSemesterTerpilih::currentId();
    }

    public function updatedSemesterTerpilihId(?string $value): void
    {
        PenilaianSemesterTerpilih::set($value);
        $this->tutupKelas();
    }

    /**
     * kelasMkId adalah properti publik yang bisa diubah klien; arahkan lewat
     * pemeriksaan cakupan yang sama dengan bukaKelas().
     */
    public function updatedKelasMkId(?string $value): void
    {
        $this->tutupKelas();

        if (filled($value)) {
            $this->bukaKelas($value);
        }
    }

    public function bukaKelas(string $kelasMkId): void
    {
        $kelas = KelasMk::query()->with(['mkUnit.mk', 'dosenPengampu'])->find($kelasMkId);

        if (! $kelas instanceof KelasMk || ! Gate::allows('lihatValidasiNilai', $kelas)) {
            return;
        }

        $this->kelasMkId = $kelas->id;
        $this->muatLaporan($kelas);
    }

    public function tutupKelas(): void
    {
        $this->kelasMkId = null;
        $this->resetDataLaporan();
    }

    protected function muatLaporan(KelasMk $kelas): void
    {
        $this->resetDataLaporan();

        $this->penugasanBelumSelesai = ! $kelas->penugasanSelesai();

        if ($this->penugasanBelumSelesai) {
            $this->muatStatusValidasi($kelas, false);

            return;
        }

        $this->muatDataLaporan($kelas);
    }

    public function getMkTerpilihProperty(): ?Mk
    {
        $kelas = $this->kelasAktif();

        return $kelas === null ? null : $kelas->mkUnit->mk;
    }

    public function getSemesterTerpilihProperty(): string
    {
        return PenilaianSemesterTerpilih::label($this->semesterTerpilihId);
    }

    /**
     * Kelas yang sedang dibuka, bila masih dalam cakupan.
     */
    protected function kelasAktif(): ?KelasMk
    {
        if ($this->kelasMkId === null) {
            return null;
        }

        $id = $this->kelasMkId;

        return $this->ingat("kelas:{$id}", fn (): ?KelasMk => KelasMk::query()->with(['mkUnit.mk', 'dosenPengampu'])->find($id));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $hitung
     * @return T
     */
    private function ingat(string $kunci, callable $hitung): mixed
    {
        if (! array_key_exists($kunci, $this->memo)) {
            $this->memo[$kunci] = $hitung();
        }

        return $this->memo[$kunci];
    }

    /**
     * @return array{nilai_total: int, nilai_terisi: int, mahasiswa_belum_lengkap: int, status_kelengkapan: string}
     */
    private function kelengkapan(KelasMk $kelas): array
    {
        return $this->ingat("kelengkapan:{$kelas->id}", fn (): array => app(PenilaianMatrixService::class)->kelengkapanKelas($kelas));
    }

    /**
     * Banner "Data belum lengkap" memakai kelengkapan yang sama dengan daftar.
     *
     * @return array{nilai_total: int, nilai_terisi: int, mahasiswa_belum_lengkap: int, status_kelengkapan: string}|null
     */
    public function getKelengkapanKelasAktifProperty(): ?array
    {
        $kelas = $this->kelasAktif();

        return $kelas === null ? null : $this->kelengkapan($kelas);
    }

    /**
     * Alasan validasi tidak tersedia untuk kelas yang dibuka (null bila tersedia
     * atau belum ada kelas dibuka).
     */
    public function getAlasanTidakBisaValidasiProperty(): ?string
    {
        $kelas = $this->kelasAktif();
        $user = auth()->user();

        if ($kelas === null || ! $user instanceof User) {
            return null;
        }

        return $this->ingat(
            "alasan:{$kelas->id}",
            fn (): ?string => app(ValidasiNilaiPolicy::class)->alasanTidakBisaValidasi($user, $kelas, $this->kelengkapan($kelas)),
        );
    }

    /**
     * Kebijakan validasi (termasuk penolakan mode hanya-baca) untuk kelas yang
     * dibuka, sekali per request.
     */
    protected function bolehValidasi(): bool
    {
        $kelas = $this->kelasAktif();

        return $kelas !== null && $this->ingat(
            "boleh-validasi:{$kelas->id}",
            fn (): bool => Gate::allows('validasiNilai', [$kelas, $this->kelengkapan($kelas)]),
        );
    }

    protected function bolehBatalkanValidasi(): bool
    {
        $kelas = $this->kelasAktif();

        return $kelas !== null && $this->ingat(
            "boleh-batal:{$kelas->id}",
            fn (): bool => Gate::allows('batalkanValidasiNilai', $kelas),
        );
    }

    /**
     * Daftar kelas dalam cakupan pada semester terpilih. Status validasi dari
     * baris tersimpan saja — sidik tidak dihitung di daftar.
     *
     * @return list<array{id: string, mk_nama: string, mk_kode: string, kode_kelas: string, dosen: string, nilai_terisi: int, nilai_total: int, status_kelengkapan: string, status_validasi: string, divalidasi_at: ?string}>
     */
    public function getDaftarKelasProperty(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        return $this->ingat("daftar:{$this->semesterTerpilihId}", fn (): array => $this->hitungDaftarKelas($user));
    }

    /**
     * @return list<array{id: string, mk_nama: string, mk_kode: string, kode_kelas: string, dosen: string, nilai_terisi: int, nilai_total: int, status_kelengkapan: string, status_validasi: string, divalidasi_at: ?string}>
     */
    private function hitungDaftarKelas(User $user): array
    {
        $service = app(ValidasiNilaiService::class);

        $kelasList = ValidatorNilaiScope::kelasMkQuery($user)
            ->when(
                filled($this->semesterTerpilihId),
                fn (Builder $query): Builder => $query->where('semester_id', $this->semesterTerpilihId),
            )
            ->with(['mkUnit.mk', 'dosenPengampu', 'validasiNilai'])
            ->get()
            ->sortBy([
                fn (KelasMk $a, KelasMk $b): int => strcmp((string) $a->mkUnit->mk->nama, (string) $b->mkUnit->mk->nama),
                fn (KelasMk $a, KelasMk $b): int => strcmp($a->kode_kelas, $b->kode_kelas),
            ]);

        $daftar = [];

        foreach ($kelasList as $kelas) {
            $kelengkapan = $this->kelengkapan($kelas);
            $divalidasiAt = $kelas->validasiNilai?->divalidasi_at;

            $daftar[] = [
                'id' => $kelas->id,
                'mk_nama' => (string) $kelas->mkUnit->mk->nama,
                'mk_kode' => (string) $kelas->mkUnit->kode,
                'kode_kelas' => $kelas->kode_kelas,
                'dosen' => $kelas->dosenPengampu?->namaDenganGelar() ?? '—',
                'nilai_terisi' => $kelengkapan['nilai_terisi'],
                'nilai_total' => $kelengkapan['nilai_total'],
                'status_kelengkapan' => $kelengkapan['status_kelengkapan'],
                'status_validasi' => $service->status($kelas)->value,
                'divalidasi_at' => $divalidasiAt === null ? null : Carbon::parse($divalidasiAt)->format('d/m/Y H:i'),
            ];
        }

        return $daftar;
    }

    public function validasiAction(): Action
    {
        return Action::make('validasi')
            ->label('Validasi')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Validasi nilai kelas?')
            ->modalDescription('Dengan memvalidasi, Anda menyatakan isi laporan kelas ini sudah diperiksa. Laporan baru dapat dicetak setelah divalidasi.')
            ->visible(fn (): bool => $this->kelasMkId !== null
                && $this->statusValidasi !== StatusValidasiNilai::Tervalidasi->value
                && $this->bolehValidasi())
            ->action(function (): void {
                $kelas = $this->kelasAktif();
                $user = auth()->user();

                if ($kelas === null || ! $user instanceof User) {
                    return;
                }

                Gate::authorize('validasiNilai', $kelas);

                $sidikLama = $this->sidikLaporan;
                $this->muatLaporan($kelas);

                if ($sidikLama === null || $this->sidikLaporan === null || $this->sidikLaporan !== $sidikLama) {
                    Notification::make()
                        ->title('Data berubah sejak laporan dibuka. Periksa kembali sebelum memvalidasi.')
                        ->danger()
                        ->send();

                    return;
                }

                app(ValidasiNilaiService::class)->validasi($kelas, $user, $this->sidikLaporan);
                $this->memo = [];
                $this->muatStatusValidasi($kelas, true);

                Notification::make()->title('Nilai kelas divalidasi')->success()->send();
            });
    }

    public function batalkanValidasiAction(): Action
    {
        return Action::make('batalkanValidasi')
            ->label('Batalkan validasi')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Batalkan validasi nilai kelas?')
            ->modalDescription('Laporan kelas ini tidak dapat dicetak lagi sampai divalidasi ulang.')
            ->visible(fn (): bool => $this->statusValidasi !== StatusValidasiNilai::Belum->value
                && $this->bolehBatalkanValidasi())
            ->action(function (): void {
                $kelas = $this->kelasAktif();

                if ($kelas === null) {
                    return;
                }

                Gate::authorize('batalkanValidasiNilai', $kelas);

                app(ValidasiNilaiService::class)->batalkan($kelas);
                $this->memo = [];
                $this->muatLaporan($kelas);

                Notification::make()->title('Validasi dibatalkan')->success()->send();
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'semesterOptions' => PenilaianSemesterTerpilih::options(),
        ];
    }
}

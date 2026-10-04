<?php

namespace App\Modules\Simulasi\Filament\Pages;

use App\Modules\Simulasi\Exceptions\KapasitasSandboxPenuhException;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Services\SimulasiService;
use App\Support\Filament\Concerns\ForcesFullPageRender;
use Database\Seeders\Support\SimulasiAkademikBuilder;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Mengelola sandbox simulasi: membuka mode latihan, menyiapkan sandbox, dan menghapusnya.
 *
 * Seperti DaftarKurikulumSuperAdmin, halaman ini memegang gerbang otorisasinya
 * sendiri lewat canAccess(). Ia tidak bisa bersandar pada policy: config
 * filament-shield memakai define_via_gate => false sehingga Super Admin tidak
 * punya jalan pintas, dan seluruh policy akademik justru MENOLAK Super Admin
 * secara struktural (lihat DelegasiMenu).
 */
class PusatSimulasi extends Page implements HasActions
{
    use ForcesFullPageRender;
    use InteractsWithActions;

    protected string $view = 'filament.modules.simulasi.pages.pusat-simulasi';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    // Menu Super Admin tampil datar tanpa header grup.
    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Simulasi';

    protected static ?string $title = 'Pusat Simulasi';

    protected static ?string $slug = 'simulasi';

    public static function canAccess(): bool
    {
        return (bool) config('simulasi.aktif')
            && (auth()->user()?->hasRole('Super Admin') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    /**
     * @return Collection<int, SimulasiJalan>
     */
    public function daftar(): Collection
    {
        return app(SimulasiService::class)->daftar();
    }

    /**
     * @return array<string, int>
     */
    public function totalArtefak(): array
    {
        return app(SimulasiService::class)->totalArtefak();
    }

    public function cobaPeranAktif(): bool
    {
        return app(SimulasiService::class)->cobaPeranTerbuka();
    }

    public function cobaPeranDilarangInstans(): bool
    {
        return app(SimulasiService::class)->cobaPeranDilarangInstans();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->siapkanAction(),
            $this->cobaPeranAction(),
            $this->hapusSemuaAction(),
        ];
    }

    protected function siapkanAction(): Action
    {
        return Action::make('siapkan')
            ->label('Siapkan Sandbox')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('primary')
            ->modalHeading('Siapkan satu sandbox')
            ->modalDescription(
                'Dibangun satu paket data (Universitas → Fakultas → Prodi Simulasi, 18 akun, mahasiswa) '
                .'yang kelak diklaim satu pengunjung. Data inti TIDAK disentuh dan tidak dapat melihat isinya. '
                .'Setelah Siapkan ditekan, jendela ini berganti menjadi layar progres yang menampilkan tiap tahap pembangunan.'
            )
            ->schema([
                Select::make('mode')
                    ->label('Jenis contoh')
                    ->options([
                        SimulasiJalan::MODE_TERISI => 'Contoh terisi — kurikulum sampai nilai sudah terisi',
                        SimulasiJalan::MODE_KOSONG => 'Contoh kosong — pengunjung mengisi sendiri dari kurikulum sampai nilai',
                    ])
                    ->default(SimulasiJalan::MODE_TERISI)
                    ->required()
                    ->live(),
                Select::make('jumlah_mk')
                    ->label('Jumlah mata kuliah')
                    ->helperText('Mata kuliah prodi yang sudah terisi, diambil berurutan dari Kalkulus I.')
                    ->options(array_combine(
                        range(1, SimulasiAkademikBuilder::MAKS_MK),
                        array_map(fn (int $n): string => $n.' mata kuliah', range(1, SimulasiAkademikBuilder::MAKS_MK)),
                    ))
                    ->default((int) config('simulasi.jumlah_mk', SimulasiAkademikBuilder::MAKS_MK))
                    ->visible(fn (Get $get): bool => $get('mode') !== SimulasiJalan::MODE_KOSONG)
                    ->required(fn (Get $get): bool => $get('mode') !== SimulasiJalan::MODE_KOSONG),
            ])
            ->modalSubmitActionLabel('Siapkan')
            ->action(function (array $data, Action $action): void {
                abort_unless(static::canAccess(), 403);

                $mode = in_array($data['mode'] ?? null, SimulasiJalan::MODE, true)
                    ? $data['mode']
                    : SimulasiJalan::MODE_TERISI;

                $simulasi = app(SimulasiService::class);
                DB::connection()->disableQueryLog();

                try {
                    $jalan = $simulasi->mulai(
                        pemicu: auth()->user(),
                        mode: $mode,
                        jumlahMk: isset($data['jumlah_mk']) ? (int) $data['jumlah_mk'] : null,
                    );
                    $simulasi->luncurkan($jalan);
                } catch (KapasitasSandboxPenuhException $galat) {
                    Notification::make()->title('Kapasitas penuh')->body($galat->getMessage())->warning()->send();

                    return;
                } catch (Throwable $galat) {
                    // Pada mode di tempat (tanpa latar belakang) sandbox sudah ditandai gagal
                    // dan dibongkar; layar progres tetap menampilkan sebabnya.
                    if (! isset($jalan)) {
                        Notification::make()->title('Pembangunan sandbox gagal')->body($galat->getMessage())->danger()->persistent()->send();

                        return;
                    }
                }

                // Modal pengisian tertutup sendiri; modal progres di tampilan halaman dibuka.
                $this->progresJalanId = (string) $jalan->getKey();
                $this->dispatch('open-modal', id: 'progres-sandbox');
            });
    }

    /**
     * ID sandbox yang progresnya sedang ditampilkan di modal progres.
     * Modal-nya ada di tampilan halaman (bukan Action) supaya tetap berada di
     * dalam akar komponen Livewire dan bisa dipantau dengan wire:poll.
     */
    public ?string $progresJalanId = null;

    /**
     * @return array<string, mixed>|null
     */
    public function progres(): ?array
    {
        abort_unless(static::canAccess(), 403);

        if ($this->progresJalanId === null) {
            return null;
        }

        $jalan = SimulasiJalan::query()->find($this->progresJalanId);

        return $jalan === null ? null : app(SimulasiService::class)->progres($jalan);
    }

    public function tutupProgres(): void
    {
        $this->progresJalanId = null;
    }

    /**
     * Sakelar mode latihan — sengaja di sini, bukan di .env, supaya keputusan
     * membuka dan MENCABUTNYA bisa diambil dalam hitungan detik tanpa deploy.
     */
    protected function cobaPeranAction(): Action
    {
        $simulasi = app(SimulasiService::class);
        $terbuka = $simulasi->cobaPeranTerbuka();

        return Action::make('alihkanCobaPeran')
            ->label($terbuka ? 'Tutup mode latihan' : 'Buka mode latihan')
            ->icon($terbuka ? Heroicon::OutlinedLockClosed : Heroicon::OutlinedLockOpen)
            ->color($terbuka ? 'warning' : 'gray')
            ->visible(fn (): bool => ! $simulasi->cobaPeranDilarangInstans())
            ->requiresConfirmation()
            ->modalHeading($terbuka ? 'Tutup mode latihan' : 'Buka mode latihan')
            ->modalDescription($terbuka
                ? 'Tombol "Coba sebagai ‹peran›" akan hilang dari halaman panduan. '
                  .'Tab yang sudah terbuka tetap berjalan sampai kedaluwarsa atau sandboxnya dihapus.'
                : 'Setelah dibuka, SIAPA PUN yang membuka halaman panduan dapat masuk TANPA KATA SANDI '
                  .'ke sandbox miliknya sendiri. Itu aman karena akun sandbox hanya ditugaskan ke unit '
                  .'sandbox itu dan tidak dapat melihat data inti maupun sandbox orang lain.')
            ->modalSubmitActionLabel($terbuka ? 'Ya, tutup' : 'Ya, buka mode latihan')
            ->action(function () use ($simulasi, $terbuka): void {
                abort_unless(static::canAccess(), 403);

                $simulasi->aturCobaPeran(! $terbuka);

                Notification::make()
                    ->title($terbuka ? 'Mode latihan ditutup' : 'Mode latihan dibuka')
                    ->success()
                    ->send();
            });
    }

    protected function hapusSemuaAction(): Action
    {
        return Action::make('hapusSemua')
            ->label('Hapus Semua Sandbox')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => $this->daftar()->isNotEmpty())
            ->requiresConfirmation()
            ->modalHeading('Hapus seluruh sandbox simulasi')
            ->modalDescription(fn (): string => 'Akan dibongkar '.$this->daftar()->count().' sandbox beserta semua akun, '
                .'kurikulum, kelas, dan nilainya. Data inti, peran, izin, semester, dan master evaluasi TIDAK '
                .'ikut dihapus. Tindakan ini tidak dapat dibatalkan.')
            ->schema([
                TextInput::make('konfirmasi')
                    ->label('Ketik HAPUS SIMULASI untuk melanjutkan')
                    ->required()
                    ->rule('in:HAPUS SIMULASI')
                    ->validationMessages(['in' => 'Ketik persis: HAPUS SIMULASI']),
            ])
            ->modalSubmitActionLabel('Hapus sekarang')
            ->action(function (): void {
                abort_unless(static::canAccess(), 403);

                @set_time_limit(0);

                $jumlah = app(SimulasiService::class)->hapusSemua();

                Notification::make()
                    ->title('Sandbox dihapus')
                    ->body($jumlah.' sandbox dibongkar.')
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    public function hapusSatu(string $id): void
    {
        abort_unless(static::canAccess(), 403);

        $jalan = SimulasiJalan::query()->masihAda()->find($id);

        if ($jalan === null) {
            return;
        }

        @set_time_limit(0);
        app(SimulasiService::class)->hapus($jalan);

        Notification::make()->title('Sandbox dihapus')->success()->send();
    }
}

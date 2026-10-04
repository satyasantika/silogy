<?php

namespace App\Modules\Simulasi\Filament\Pages;

use App\Modules\Simulasi\Exceptions\KapasitasSandboxPenuhException;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Services\SimulasiService;
use App\Support\Filament\Concerns\ForcesFullPageRender;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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
            ->label('Siapkan Contoh')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('primary')
            ->modalHeading('Siapkan contoh simulasi')
            ->modalDescription(
                'Contoh terisi satu salinan bersama yang hanya-baca untuk semua pengunjung; menyiapkannya lagi '
                .'membangun salinan baru lalu menggantikan yang lama setelah selesai. Contoh kosong ditambahkan '
                .'ke kolam untuk diklaim satu pengunjung. Data inti TIDAK disentuh. Setelah Siapkan ditekan, '
                .'jendela ini berganti menjadi layar progres.'
            )
            ->schema([
                Select::make('mode')
                    ->label('Jenis contoh')
                    ->options([
                        SimulasiJalan::MODE_TERISI => 'Contoh terisi — salinan bersama, hanya-baca (membangun ulang)',
                        SimulasiJalan::MODE_KOSONG => 'Contoh kosong — satu kurikulum dan satu MK kosong, untuk kolam',
                    ])
                    ->default(SimulasiJalan::MODE_TERISI)
                    ->required(),
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
                        bersama: $mode === SimulasiJalan::MODE_TERISI,
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

        // Semua tampilan dan keputusan dihitung ULANG pada setiap render dan saat
        // dijalankan (closure), bukan dibekukan saat action dibangun. Header actions
        // Filament di-cache per komponen, sehingga nilai yang dibaca di sini sekali
        // saja membuat tombol tetap berlabel lama setelah status berubah, dan klik
        // kedua menulis nilai yang sama alih-alih kebalikannya.
        return Action::make('alihkanCobaPeran')
            ->label(fn (): string => $simulasi->cobaPeranTerbuka() ? 'Tutup mode latihan' : 'Buka mode latihan')
            ->icon(fn (): Heroicon => $simulasi->cobaPeranTerbuka() ? Heroicon::OutlinedLockClosed : Heroicon::OutlinedLockOpen)
            ->color(fn (): string => $simulasi->cobaPeranTerbuka() ? 'warning' : 'gray')
            ->visible(fn (): bool => ! $simulasi->cobaPeranDilarangInstans())
            ->requiresConfirmation()
            ->modalHeading(fn (): string => $simulasi->cobaPeranTerbuka() ? 'Tutup mode latihan' : 'Buka mode latihan')
            ->modalDescription(fn (): string => $simulasi->cobaPeranTerbuka()
                ? 'Tombol "Coba sebagai ‹peran›" akan hilang dari halaman panduan. '
                  .'Tab yang sudah terbuka tetap berjalan sampai kedaluwarsa atau sandboxnya dihapus.'
                : 'Setelah dibuka, SIAPA PUN yang membuka halaman panduan dapat masuk TANPA KATA SANDI '
                  .'ke sandbox miliknya sendiri. Itu aman karena akun sandbox hanya ditugaskan ke unit '
                  .'sandbox itu dan tidak dapat melihat data inti maupun sandbox orang lain.')
            ->modalSubmitActionLabel(fn (): string => $simulasi->cobaPeranTerbuka() ? 'Ya, tutup' : 'Ya, buka mode latihan')
            ->action(function () use ($simulasi): void {
                abort_unless(static::canAccess(), 403);

                // Dibaca saat dijalankan: bila admin lain sudah mengubahnya, klik ini
                // tetap membalik keadaan terkini, bukan menimpa dengan nilai usang.
                $nyalakan = ! $simulasi->cobaPeranTerbuka();

                $simulasi->aturCobaPeran($nyalakan);

                Notification::make()
                    ->title($nyalakan ? 'Mode latihan dibuka' : 'Mode latihan ditutup')
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

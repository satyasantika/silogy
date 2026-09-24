<?php

namespace App\Modules\Simulasi\Filament\Pages;

use App\Modules\Simulasi\DataObjects\StatusSimulasi;
use App\Modules\Simulasi\Services\SimulasiService;
use App\Modules\Simulasi\Support\AkunSimulasi;
use App\Support\Filament\Concerns\ForcesFullPageRender;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Menyalakan dan mematikan data simulasi.
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

    public function status(): StatusSimulasi
    {
        return app(SimulasiService::class)->status();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function akun(): array
    {
        return AkunSimulasi::akun();
    }

    public function sandiSimulasi(): string
    {
        return AkunSimulasi::SANDI;
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
            $this->buatAction(),
            $this->cobaPeranAction(),
            $this->bangunUlangAction(),
            $this->hapusAction(),
        ];
    }

    protected function buatAction(): Action
    {
        return Action::make('buat')
            ->label('Buat Simulasi')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('primary')
            ->visible(fn (): bool => ! $this->status()->ada)
            ->requiresConfirmation()
            ->modalHeading('Buat data simulasi')
            ->modalDescription(
                'Akan dibuat pohon unit tersendiri (Universitas Simulasi → Fakultas Simulasi → '
                .'Prodi Simulasi), akun sim-* dengan kata sandi '.AkunSimulasi::SANDI.', mahasiswa '
                .'simulasi, dan seluruh rantai OBE di atasnya sampai nilai serta hasil kalkulasi. '
                .'Unit, akun, kurikulum, dan nilai yang sudah ada TIDAK disentuh. '
                .'Proses berjalan langsung dan memakan waktu sekitar 10–60 detik — '
                .'jangan tutup halaman ini.'
            )
            ->modalSubmitActionLabel('Ya, buat simulasi')
            ->action(fn () => $this->jalankanPembangunan(ulang: false));
    }

    /**
     * Sakelar mode latihan — sengaja di sini, bukan di .env.
     *
     * Orang yang berwenang memutuskan boleh-tidaknya mode latihan dibuka sering
     * kali tidak punya akses menyunting .env di peladen. Menaruhnya di sini
     * membuat keputusan itu bisa diambil, dan yang lebih penting DICABUT, dalam
     * hitungan detik tanpa menyentuh berkas konfigurasi maupun menunggu deploy.
     */
    protected function cobaPeranAction(): Action
    {
        $simulasi = app(SimulasiService::class);
        $terbuka = $simulasi->cobaPeranTerbuka();

        return Action::make('alihkanCobaPeran')
            ->label($terbuka ? 'Tutup mode latihan' : 'Buka mode latihan')
            ->icon($terbuka ? Heroicon::OutlinedLockClosed : Heroicon::OutlinedLockOpen)
            ->color($terbuka ? 'warning' : 'gray')
            ->visible(fn (): bool => $this->status()->ada && ! $simulasi->cobaPeranDilarangInstans())
            ->requiresConfirmation()
            ->modalHeading($terbuka ? 'Tutup mode latihan' : 'Buka mode latihan')
            ->modalDescription($terbuka
                ? 'Tombol "Coba sebagai ‹peran›" akan hilang dari halaman panduan. '
                  .'Orang tetap bisa masuk dengan mengetik sendiri akun sim-* dan kata sandinya.'
                : 'Setelah dibuka, SIAPA PUN yang membuka halaman panduan dapat masuk '
                  .'TANPA KATA SANDI ke akun latihan dan menulis data di sana. '
                  .'Itu aman karena akun sim-* hanya ditugaskan ke unit simulasi, sehingga '
                  .'tidak bisa menyentuh data yang sesungguhnya — tetapi apa pun yang '
                  .'mereka ubah di dalam simulasi akan terlihat oleh pengunjung berikutnya. '
                  .'Sakelar ini bisa ditutup lagi kapan saja dari halaman ini.')
            ->modalSubmitActionLabel($terbuka ? 'Ya, tutup' : 'Ya, buka mode latihan')
            ->action(function () use ($simulasi, $terbuka): void {
                abort_unless(static::canAccess(), 403);

                $simulasi->aturCobaPeran(! $terbuka);

                Notification::make()
                    ->title($terbuka ? 'Mode latihan ditutup' : 'Mode latihan dibuka')
                    ->body($terbuka
                        ? 'Tombol "Coba sebagai ‹peran›" tidak lagi tampil di halaman panduan.'
                        : 'Pengunjung kini bisa mencoba tiap peran langsung dari halaman panduan.')
                    ->success()
                    ->send();
            });
    }

    protected function bangunUlangAction(): Action
    {
        return Action::make('bangunUlang')
            ->label('Bangun Ulang')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (): bool => $this->status()->ada)
            ->requiresConfirmation()
            ->modalHeading('Bangun ulang data simulasi')
            ->modalDescription(
                'Data simulasi sekarang akan dibongkar lebih dulu, lalu dibangun kembali dari awal. '
                .'Semua perubahan yang dibuat pengunjung selama mencoba peran akan hilang.'
            )
            ->modalSubmitActionLabel('Ya, bangun ulang')
            ->action(fn () => $this->jalankanPembangunan(ulang: true));
    }

    protected function hapusAction(): Action
    {
        return Action::make('hapus')
            ->label('Hapus Simulasi')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => $this->status()->ada)
            ->requiresConfirmation()
            ->modalHeading('Hapus data simulasi')
            ->modalDescription(fn (): string => $this->ringkasanPenghapusan())
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

                $hasil = app(SimulasiService::class)->hapus();

                Notification::make()
                    ->title('Data simulasi dihapus')
                    ->body($hasil->ringkasSingkat())
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    protected function ringkasanPenghapusan(): string
    {
        $status = $this->status();
        $baris = [];

        foreach ($status->cacah as $label => $jumlah) {
            $baris[] = $jumlah.' '.$label;
        }

        foreach ($status->turunan as $label => $jumlah) {
            $baris[] = $jumlah.' '.strtolower($label);
        }

        return 'Akan dihapus: '.implode(', ', $baris).'. '
            ."\n\n"
            .'TIDAK ikut dihapus: unit akademik nyata, peran, izin, semester, master evaluasi, '
            .'dan setiap baris yang tidak tercatat sebagai buatan simulasi. '
            .'Tindakan ini tidak dapat dibatalkan.';
    }

    protected function jalankanPembangunan(bool $ulang): void
    {
        abort_unless(static::canAccess(), 403);

        // Tidak ada queue worker pada tumpukan silogy, jadi pembangunan memang
        // berjalan sinkron. Query log dimatikan karena satu kali pembangunan
        // menyisipkan ribuan baris nilai.
        @set_time_limit(0);
        DB::connection()->disableQueryLog();

        try {
            $hasil = $ulang
                ? app(SimulasiService::class)->bangunUlang(auth()->user())
                : app(SimulasiService::class)->buat(auth()->user());
        } catch (Throwable $galat) {
            Notification::make()
                ->title('Pembangunan simulasi gagal')
                ->body($galat->getMessage().' Artefak yang terlanjur lahir tetap tercatat — '
                    .'tekan Hapus Simulasi untuk membersihkannya.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('Data simulasi siap')
            ->body($hasil->ringkasSingkat())
            ->success()
            ->persistent()
            ->send();
    }
}

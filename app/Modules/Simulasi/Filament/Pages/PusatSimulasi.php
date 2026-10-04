<?php

namespace App\Modules\Simulasi\Filament\Pages;

use App\Modules\Panduan\Support\PeranPanduan;
use App\Modules\Simulasi\Exceptions\KapasitasSandboxPenuhException;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Services\RuangSimulasi;
use App\Modules\Simulasi\Services\SimulasiService;
use App\Modules\Simulasi\Support\PengaturanSimulasi;
use App\Support\Filament\Concerns\ForcesFullPageRender;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
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
        return app(SimulasiService::class)->daftar()->where('bersama', false)->values();
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
            $this->pengaturanAction(),
            $this->cobaPeranAction(),
            $this->hapusSemuaAction(),
        ];
    }

    /** Batas ruang per penekanan bila pembangunan berjalan di dalam permintaan (tanpa latar belakang). */
    public const BATAS_DI_TEMPAT = 3;

    protected function siapkanAction(): Action
    {
        return Action::make('siapkan')
            ->label('Siapkan Ruang')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->color('primary')
            ->modalHeading('Siapkan ruang simulasi')
            ->modalDescription(
                'Tiap ruang berisi satu program studi dengan satu kurikulum dan satu mata kuliah kosong, '
                .'enam akun (satu per peran), dan sebuah token. Bagikan token itu kepada peserta: mereka memasukkan '
                .'token lalu memilih satu peran. Data inti TIDAK disentuh. Setelah Siapkan ditekan, jendela ini '
                .'berganti menjadi layar progres.'
            )
            ->schema([
                TextInput::make('jumlah')
                    ->label('Jumlah ruang')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->default(1)
                    ->required()
                    ->helperText(fn (): string => 'Kapasitas tersisa: '.$this->sisaKapasitas().' ruang.'),
            ])
            ->modalSubmitActionLabel('Siapkan')
            ->action(function (array $data): void {
                abort_unless(static::canAccess(), 403);

                $simulasi = app(SimulasiService::class);
                DB::connection()->disableQueryLog();

                $diminta = max(1, (int) ($data['jumlah'] ?? 1));
                $sisa = $this->sisaKapasitas();

                if ($sisa < 1) {
                    Notification::make()->title('Kapasitas penuh')
                        ->body('Naikkan kapasitas di Pengaturan atau hapus ruang yang tak terpakai.')->warning()->send();

                    return;
                }

                $jumlah = min($diminta, $sisa);

                if (! config('simulasi.latar_belakang', true)) {
                    $jumlah = min($jumlah, self::BATAS_DI_TEMPAT);
                }

                $daftar = [];

                try {
                    for ($i = 0; $i < $jumlah; $i++) {
                        $daftar[] = $simulasi->mulai(pemicu: auth()->user(), mode: SimulasiJalan::MODE_KOSONG);
                    }

                    $simulasi->luncurkan($daftar);
                } catch (KapasitasSandboxPenuhException $galat) {
                    Notification::make()->title('Kapasitas penuh')->body($galat->getMessage())->warning()->send();

                    if ($daftar === []) {
                        return;
                    }
                } catch (Throwable $galat) {
                    // Pada mode di tempat sandbox yang gagal sudah ditandai gagal dan dibongkar;
                    // layar progres tetap menampilkan sebabnya.
                    if ($daftar === []) {
                        Notification::make()->title('Pembangunan ruang gagal')->body($galat->getMessage())->danger()->persistent()->send();

                        return;
                    }
                }

                if ($jumlah < $diminta) {
                    Notification::make()->title('Jumlah dikurangi')
                        ->body("Dibangun {$jumlah} dari {$diminta} ruang diminta (kapasitas atau batas mode di tempat).")->warning()->send();
                }

                $this->progresIds = array_map(fn (SimulasiJalan $j): string => (string) $j->getKey(), $daftar);
                $this->dispatch('open-modal', id: 'progres-sandbox');
            });
    }

    protected function pengaturanAction(): Action
    {
        return Action::make('pengaturan')
            ->label('Pengaturan')
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->color('gray')
            ->modalHeading('Pengaturan ruang simulasi')
            ->modalDescription('Disimpan di basis data dan berlaku segera. Tidak memerlukan akses ke server.')
            ->fillForm(fn (): array => PengaturanSimulasi::semua())
            ->schema(collect(PengaturanSimulasi::DEFINISI)->map(
                fn (array $d, string $kunci) => TextInput::make($kunci)
                    ->label($d['label'].' ('.$d['satuan'].')')
                    ->numeric()->integer()->required()
                    ->minValue($d['min'])->maxValue($d['maks'])
            )->values()->all())
            ->modalSubmitActionLabel('Simpan')
            ->action(function (array $data): void {
                abort_unless(static::canAccess(), 403);

                foreach (array_keys(PengaturanSimulasi::DEFINISI) as $kunci) {
                    if (isset($data[$kunci])) {
                        PengaturanSimulasi::atur($kunci, (int) $data[$kunci]);
                    }
                }

                Notification::make()->title('Pengaturan disimpan')->success()->send();
            });
    }

    public function sisaKapasitas(): int
    {
        return max(0, PengaturanSimulasi::ambil('kapasitas') - app(SimulasiService::class)->jumlahRuang());
    }

    /** @var list<string> */
    public array $progresIds = [];

    /**
     * Progres ruang yang sedang dibangun (yang pertama belum selesai, atau yang
     * terakhir bila semuanya sudah). Ruang dibangun berurutan oleh satu proses.
     *
     * @return array<string, mixed>|null
     */
    public function progres(): ?array
    {
        abort_unless(static::canAccess(), 403);

        if ($this->progresIds === []) {
            return null;
        }

        $simulasi = app(SimulasiService::class);
        $jalanList = SimulasiJalan::query()->whereIn('id', $this->progresIds)->get()->keyBy('id');
        $selesai = 0;
        $aktif = null;
        $ke = 0;

        foreach ($this->progresIds as $i => $id) {
            $jalan = $jalanList->get($id);

            // Hilang = gagal dan sudah dibongkar; tetap dihitung agar layar tidak macet.
            if ($jalan === null) {
                $selesai++;

                continue;
            }

            if ($jalan->status === SimulasiJalan::STATUS_SELESAI) {
                $selesai++;
                $aktif ??= $jalan;
                $ke = $i + 1;

                continue;
            }

            if ($jalan->status === SimulasiJalan::STATUS_BERJALAN || $jalan->status === SimulasiJalan::STATUS_GAGAL) {
                $aktif = $jalan;
                $ke = $i + 1;

                break;
            }
        }

        if ($aktif === null) {
            return null;
        }

        $p = $simulasi->progres($aktif);
        $total = count($this->progresIds);
        $p['ruang_ke'] = max(1, $ke);
        $p['ruang_total'] = $total;
        $p['ruang_selesai'] = $selesai;
        $p['semua_selesai'] = $selesai === $total;

        // Dengan banyak ruang, pesan "selesai" dan penutupan menunggu ruang terakhir.
        if ($total > 1 && ! $p['semua_selesai']) {
            $p['selesai'] = false;
            $p['berjalan'] = $p['berjalan'] || $p['status'] === SimulasiJalan::STATUS_SELESAI;
        }

        return $p;
    }

    public function tutupProgres(): void
    {
        $this->progresIds = [];
    }

    /** Ruang yang dibangun ulang sebagai token baru atas permintaan Super Admin. */
    public function gantiToken(string $id): void
    {
        abort_unless(static::canAccess(), 403);

        $ruang = SimulasiJalan::query()->masihAda()->where('bersama', false)->find($id);

        if ($ruang === null) {
            return;
        }

        $baru = app(SimulasiService::class)->gantiPin($ruang);

        Notification::make()->title('Token diganti')->body("Token baru ruang {$ruang->kode()}: {$baru}")->success()->send();
    }

    public function lepasPeran(string $id, string $peran): void
    {
        abort_unless(static::canAccess(), 403);

        app(RuangSimulasi::class)->lepasPeran($id, $peran);

        Notification::make()->title('Peran dilepas')->success()->send();
    }

    /** @return array<string, list<string>> */
    public function peranTerisi(): array
    {
        return app(RuangSimulasi::class)->peranTerisiSemua();
    }

    public function jumlahPeran(): int
    {
        return count(PeranPanduan::bisaDicoba());
    }

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
            ->label('Hapus Semua Ruang')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => $this->daftar()->isNotEmpty())
            ->requiresConfirmation()
            ->modalHeading('Hapus seluruh ruang simulasi')
            ->modalDescription(fn (): string => 'Akan dibongkar '.$this->daftar()->count().' ruang beserta semua akun, '
                .'kurikulum, dan isinya, dan semua peserta yang sedang di dalamnya terputus. Data inti, peran, izin, '
                .'semester, dan master evaluasi TIDAK ikut dihapus. Tindakan ini tidak dapat dibatalkan.')
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
                    ->title('Ruang dihapus')
                    ->body($jumlah.' ruang dibongkar.')
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

        Notification::make()->title('Ruang dihapus')->success()->send();
    }
}

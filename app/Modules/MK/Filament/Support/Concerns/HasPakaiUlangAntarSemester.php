<?php

namespace App\Modules\MK\Filament\Support\Concerns;

use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Support\Filament\Concerns\ClearsImporModalPreviewOnUnmount;
use App\Support\Filament\Concerns\ReadsMountedActionData;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * "Gunakan data semester lain": pilih semester sumber, tentukan mau memakai
 * SEPAKET atau memilih sebagian, lalu konfirmasi.
 *
 * Menggantikan HasSalinAntarSemesterMassal. Bentuk modalnya sengaja sama —
 * yang berubah adalah maknanya: baris tidak lagi DISALIN menjadi ID baru,
 * melainkan baris lama DILAMPIRKAN ke semester tujuan sehingga ID-nya tetap.
 * Karena itu tidak ada lagi pilihan "timpa data lama": duplikatnya adalah
 * baris yang sama persis, dan menimpanya justru akan mengubah semester
 * sumber yang nilainya mungkin sudah final.
 *
 * Sumber semester DISINKRON lewat properti public + afterStateUpdated()
 * (BUKAN Get()/Set() untuk membaca sibling state dari dalam hook komponen
 * lain) — pola sama persis HasImporMassal::$importMassalRowsLive. Alasannya:
 * Filament v4 PartialsComponentHook memakai partial key berbeda antara
 * request mountAction pertama kali dan request update berikutnya pada
 * action yang sama, sehingga DOM browser gagal menemukan elemen match dan
 * diam-diam membuang HTML pratinjau yang baru dihitung (tanpa error). Get()
 * dari dalam hook komponen lain juga sengaja skip menelusuri anak-kontainer
 * komponen yang sedang dievaluasi, jadi tidak selalu menemukan state yang
 * benar. forceRender() memaksa render penuh (bypass sistem partial itu);
 * Get()/mountedActionDataTerkini() hanya dipakai sebagai jaring aman
 * berlapis, bukan sumber utama.
 */
trait HasPakaiUlangAntarSemester
{
    use ClearsImporModalPreviewOnUnmount;
    use ReadsMountedActionData;

    public const STATUS_BARU = 'baru';

    public const STATUS_SUDAH = 'sudah_terpakai';

    public const STATUS_TERBLOKIR = 'terblokir';

    /**
     * Salinan langsung semester sumber terpilih saat ini, disinkronkan
     * lewat afterStateUpdated() pada Select — lihat catatan trait di atas.
     * Harus public supaya ikut disimpan/dipulihkan Livewire antar request.
     */
    public ?string $pakaiUlangSumberLive = null;

    /**
     * @var array<string, list<array<string, mixed>>>
     */
    protected array $pakaiUlangBarisCache = [];

    /** Label entitas untuk judul modal & teks petunjuk, mis. "Sub-CPMK". */
    abstract protected function pakaiUlangEntitasLabel(): string;

    abstract protected function pakaiUlangMkId(): ?string;

    abstract protected function pakaiUlangTargetSemesterId(): ?string;

    /**
     * @return list<array{line: int, label: string, status: string, keterangan: string}>
     */
    abstract protected function pakaiUlangResolveBaris(string $sumberSemesterId, string $mkId, string $targetSemesterId): array;

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{dilampirkan: int, dilewati: int, gagal: list<string>}
     */
    abstract protected function pakaiUlangJalankan(array $rows, string $sumberSemesterId, string $mkId, string $targetSemesterId): array;

    /**
     * ID semester (selain semester tujuan) yang sudah punya data untuk MK
     * ini — dasar opsi dropdown sumber. Tombolnya disembunyikan total bila
     * daftar ini kosong.
     *
     * @return list<string>
     */
    abstract protected function pakaiUlangSemesterIdsDenganData(string $mkId): array;

    /**
     * Prasyarat tambahan sebelum tombol boleh tampil (opsional, default
     * selalu terpenuhi) — override bila entitas ini butuh data lain sudah
     * ada dulu di semester tujuan.
     */
    protected function pakaiUlangPrasyaratTerpenuhi(): bool
    {
        return true;
    }

    protected function makePakaiUlangAction(): Action
    {
        return Action::make('pakaiUlangAntarSemester')
            ->label('Gunakan data semester lain')
            ->icon(Heroicon::OutlinedArrowPathRoundedSquare)
            ->color('gray')
            ->modalHeading('Gunakan '.$this->pakaiUlangEntitasLabel().' dari semester lain')
            ->modalWidth(Width::SixExtraLarge)
            ->modalSubmitActionLabel('Gunakan sekarang')
            // Kosongkan pratinjau saat modal dibuka (jaring aman) DAN saat
            // ditutup (lihat ClearsImporModalPreviewOnUnmount). Tetap panggil
            // $schema->fill() supaya default field ikut ter-reset — lihat
            // CanBeMounted::getMountUsing().
            ->mountUsing(function (Schema $schema): void {
                $this->kosongkanPreviewImporModal();
                $schema->fill();
            })
            ->after(function (): void {
                $this->kosongkanPreviewImporModal();
            })
            ->visible(fn (): bool => $this->pakaiUlangMkId() !== null
                && $this->pakaiUlangTargetSemesterId() !== null
                && $this->pakaiUlangOpsiSumber() !== []
                && $this->pakaiUlangPrasyaratTerpenuhi())
            ->modalSubmitAction(fn (Action $action): Action|bool => $this->pakaiUlangPratinjauSiap()
                ? $action
                : false)
            ->schema([
                Placeholder::make('pakai_ulang_petunjuk')
                    ->hiddenLabel()
                    ->content(fn (): HtmlString => $this->renderPakaiUlangGuideBox()),
                Select::make('sumber_semester_id')
                    ->label('Ambil dari semester')
                    ->options(fn (): array => $this->pakaiUlangOpsiSumber())
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        // Sengaja TIDAK lewat Get()/Set() untuk membaca state —
                        // lihat catatan pada properti $pakaiUlangSumberLive.
                        // $state adalah nilai Select terbaru, dikirim langsung
                        // sebagai parameter closure. $set() di sini AMAN karena
                        // menulis dari hook milik field ini sendiri, bukan
                        // membaca state sibling dari hook komponen lain.
                        $this->pakaiUlangSumberLive = $state;

                        $set('baris_dipilih', $this->pakaiUlangLineBisaDipakai($state));

                        // Filament v4 PartialsComponentHook memakai partial key
                        // berbeda antara request mountAction pertama kali dan
                        // request update berikutnya pada action yang sama — paksa
                        // full render agar morph memakai jalur Livewire standar.
                        if (method_exists($this, 'forceRender')) {
                            $this->forceRender();
                        }
                    }),
                Radio::make('cakupan')
                    ->label('Yang ingin dipakai')
                    ->options([
                        'paket' => 'Sepaket — seluruh '.$this->pakaiUlangEntitasLabel().' semester tersebut',
                        'sebagian' => 'Sebagian — pilih sendiri baris yang dipakai',
                    ])
                    ->default('paket')
                    ->required()
                    ->live()
                    ->visible(fn (Get $get): bool => $this->pakaiUlangBaris(
                        $this->pakaiUlangSumberTerkini($get),
                    ) !== []),
                Placeholder::make('baris_kosong')
                    ->hiddenLabel()
                    ->content(new HtmlString('<p class="text-sm">Belum ada data pada semester sumber ini.</p>'))
                    ->visible(fn (Get $get): bool => $this->pakaiUlangBaris(
                        $this->pakaiUlangSumberTerkini($get),
                    ) === []),
                CheckboxList::make('baris_dipilih')
                    ->hiddenLabel()
                    ->options(fn (Get $get): array => $this->pakaiUlangOpsiBaris(
                        $this->pakaiUlangSumberTerkini($get),
                    ))
                    ->descriptions(fn (Get $get): array => $this->pakaiUlangDeskripsiBaris(
                        $this->pakaiUlangSumberTerkini($get),
                    ))
                    // $value adalah nomor baris (int), bukan string — jangan
                    // beri type hint string di sini: Filament menyuntikkannya
                    // apa adanya dari kunci opsi.
                    ->disableOptionWhen(fn (mixed $value, Get $get): bool => ! in_array(
                        (int) $value,
                        $this->pakaiUlangLineBisaDipakai($this->pakaiUlangSumberTerkini($get)),
                        true,
                    ))
                    ->default(fn (Get $get): array => $this->pakaiUlangLineBisaDipakai(
                        $this->pakaiUlangSumberTerkini($get),
                    ))
                    ->columns(1)
                    ->bulkToggleable()
                    ->visible(fn (Get $get): bool => ($get('cakupan') ?? 'paket') === 'sebagian'
                        && $this->pakaiUlangBaris($this->pakaiUlangSumberTerkini($get)) !== [])
                    ->helperText(fn (Get $get): string => $this->pakaiUlangRingkasanBaris(
                        $this->pakaiUlangSumberTerkini($get),
                    )),
                Placeholder::make('ringkasan_paket')
                    ->hiddenLabel()
                    ->content(fn (Get $get): HtmlString => new HtmlString(
                        '<p class="text-sm">'.e($this->pakaiUlangRingkasanBaris(
                            $this->pakaiUlangSumberTerkini($get),
                        )).'</p>',
                    ))
                    ->visible(fn (Get $get): bool => ($get('cakupan') ?? 'paket') === 'paket'
                        && $this->pakaiUlangBaris($this->pakaiUlangSumberTerkini($get)) !== []),
            ])
            ->action(function (array $data): void {
                $sumber = $this->pakaiUlangSumberLive ?? (string) ($data['sumber_semester_id'] ?? '');
                $sepaket = ((string) ($data['cakupan'] ?? 'paket')) === 'paket';

                $this->jalankanPakaiUlang(
                    $sumber,
                    $sepaket
                        ? $this->pakaiUlangLineBisaDipakai($sumber)
                        : (array) ($data['baris_dipilih'] ?? []),
                );
            });
    }

    /**
     * Semester sumber terkini. Properti live dipakai lebih dulu, lalu Get()
     * (bila tersedia dalam schema), lalu state form modal
     * (mountedActions.{i}.data.sumber_semester_id) sebagai jaring aman
     * terakhir — supaya pratinjau, checkbox, dan tombol submit tetap dapat
     * muncul meski sinkronisasi live belum sempat berjalan.
     */
    protected function pakaiUlangSumberTerkini(?Get $get = null): ?string
    {
        if (filled($this->pakaiUlangSumberLive)) {
            return $this->pakaiUlangSumberLive;
        }

        if ($get !== null && filled($raw = $get('sumber_semester_id'))) {
            return (string) $raw;
        }

        $fromMounted = $this->mountedActionDataTerkini()['sumber_semester_id'] ?? null;

        return filled($fromMounted) ? (string) $fromMounted : null;
    }

    /**
     * @return array<string, string>
     */
    protected function pakaiUlangOpsiSumber(): array
    {
        $mkId = $this->pakaiUlangMkId();
        $targetId = $this->pakaiUlangTargetSemesterId();

        if (blank($mkId) || blank($targetId)) {
            return [];
        }

        $semesterIdsDenganData = collect($this->pakaiUlangSemesterIdsDenganData($mkId))
            ->map(fn ($id): string => (string) $id)
            ->reject(fn (string $id): bool => $id === (string) $targetId)
            ->unique()
            ->all();

        if ($semesterIdsDenganData === []) {
            return [];
        }

        return collect(SemesterTerpilih::optionsSemua())
            ->only($semesterIdsDenganData)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function pakaiUlangBaris(?string $sumberSemesterId): array
    {
        $mkId = $this->pakaiUlangMkId();
        $targetId = $this->pakaiUlangTargetSemesterId();

        if (blank($sumberSemesterId) || blank($mkId) || blank($targetId)) {
            return [];
        }

        $kunci = md5(serialize([$sumberSemesterId, $mkId, $targetId]));

        return $this->pakaiUlangBarisCache[$kunci] ??= $this->pakaiUlangResolveBaris(
            $sumberSemesterId,
            $mkId,
            $targetId,
        );
    }

    /**
     * Nomor baris yang benar-benar bisa dipakai ulang — dipakai sebagai
     * default centang, sebagai isi pilihan "sepaket", dan sebagai penentu
     * checkbox mana yang dimatikan.
     *
     * @return list<int>
     */
    protected function pakaiUlangLineBisaDipakai(?string $sumberSemesterId): array
    {
        return collect($this->pakaiUlangBaris($sumberSemesterId))
            ->filter(fn (array $row): bool => $row['status'] === self::STATUS_BARU)
            ->pluck('line')
            ->map(fn ($line): int => (int) $line)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function pakaiUlangOpsiBaris(?string $sumberSemesterId): array
    {
        return collect($this->pakaiUlangBaris($sumberSemesterId))
            ->mapWithKeys(fn (array $row): array => [$row['line'] => $row['label']])
            ->all();
    }

    protected function pakaiUlangRingkasanBaris(?string $sumberSemesterId): string
    {
        $baris = collect($this->pakaiUlangBaris($sumberSemesterId));

        return sprintf(
            '%d baris terbaca — %d bisa dipakai ulang, %d sudah dipakai, %d harus dibuat baru.',
            $baris->count(),
            $baris->where('status', self::STATUS_BARU)->count(),
            $baris->where('status', self::STATUS_SUDAH)->count(),
            $baris->where('status', self::STATUS_TERBLOKIR)->count(),
        );
    }

    /**
     * Badge status + keterangan per baris, dipakai sebagai description
     * CheckboxList — warna mengikuti konvensi badge yang sudah dipakai di
     * halaman lain (mis. PenilaianDosenService::tabelKelasUnitHtml()).
     *
     * @return array<int, HtmlString>
     */
    protected function pakaiUlangDeskripsiBaris(?string $sumberSemesterId): array
    {
        $gaya = [
            self::STATUS_BARU => ['#dcfce7', '#166534', '#86efac', 'Bisa dipakai'],
            self::STATUS_SUDAH => ['#f1f5f9', '#475569', '#cbd5e1', 'Sudah dipakai'],
            self::STATUS_TERBLOKIR => ['#fee2e2', '#991b1b', '#fca5a5', 'Harus baru'],
        ];

        return collect($this->pakaiUlangBaris($sumberSemesterId))
            ->mapWithKeys(function (array $row) use ($gaya): array {
                [$background, $color, $border, $teks] = $gaya[$row['status']] ?? $gaya[self::STATUS_TERBLOKIR];

                $badge = sprintf(
                    '<span style="display:inline-flex;align-items:center;padding:3px 8px;border-radius:6px;'
                    .'font-size:11px;font-weight:600;line-height:1.4;background:%s;color:%s;border:1px solid %s;">%s</span>',
                    $background,
                    $color,
                    $border,
                    $teks,
                );

                if ($row['keterangan'] !== '') {
                    $badge .= ' <span style="font-size:11px;opacity:.75;">'.e($row['keterangan']).'</span>';
                }

                return [$row['line'] => new HtmlString($badge)];
            })
            ->all();
    }

    /** Pratinjau terbaca DAN minimal satu baris benar-benar bisa dipakai. */
    protected function pakaiUlangPratinjauSiap(): bool
    {
        return $this->pakaiUlangLineBisaDipakai($this->pakaiUlangSumberTerkini()) !== [];
    }

    protected function renderPakaiUlangGuideBox(): HtmlString
    {
        $entitas = $this->pakaiUlangEntitasLabel();

        $items = [
            'Pilih semester yang datanya ingin dipakai lagi.',
            'Pilih sepaket, atau centang sendiri baris yang dipakai.',
            $entitas.' yang dipakai ulang memakai data yang sama persis — bukan salinan baru.',
        ];

        $list = collect($items)
            ->map(fn (string $item): string => '<li>'.e($item).'</li>')
            ->join('');

        return new HtmlString(
            '<div class="rounded-lg border border-primary-600/30 bg-primary-50 p-4 text-sm text-gray-700 dark:border-primary-500/30 dark:bg-primary-950/40 dark:text-gray-200">'
            .'<p class="mb-2 font-semibold">Memakai '.e($entitas).' semester sebelumnya</p>'
            .'<ul class="list-disc space-y-1 ps-5">'.$list.'</ul>'
            .'<p class="mt-2 text-xs opacity-75">Menyuntingnya nanti akan mengubah semester lain yang memakainya juga.</p>'
            .'</div>'
        );
    }

    /**
     * @param  list<string|int>  $barisDipilih
     */
    protected function jalankanPakaiUlang(string $sumberSemesterId, array $barisDipilih): void
    {
        $mkId = $this->pakaiUlangMkId();
        $targetId = $this->pakaiUlangTargetSemesterId();

        if (blank($mkId) || blank($targetId) || blank($sumberSemesterId)) {
            Notification::make()
                ->title('Konteks MK/semester belum lengkap')
                ->danger()
                ->send();

            return;
        }

        $dipilih = array_map('intval', $barisDipilih);

        $rows = array_values(array_filter(
            $this->pakaiUlangBaris($sumberSemesterId),
            fn (array $row): bool => in_array((int) $row['line'], $dipilih, true),
        ));

        $hasil = ['dilampirkan' => 0, 'dilewati' => 0, 'gagal' => []];

        DB::transaction(function () use ($rows, $sumberSemesterId, $mkId, $targetId, &$hasil): void {
            $hasil = $this->pakaiUlangJalankan($rows, $sumberSemesterId, $mkId, $targetId);
        });

        $this->kirimNotifikasiPakaiUlang($hasil);
    }

    /**
     * @param  array{dilampirkan: int, dilewati: int, gagal: list<string>}  $hasil
     */
    private function kirimNotifikasiPakaiUlang(array $hasil): void
    {
        $ringkasan = sprintf(
            'Dipakai ulang: %d · Dilewati: %d · Gagal: %d',
            $hasil['dilampirkan'],
            $hasil['dilewati'],
            count($hasil['gagal']),
        );

        $detailGagal = $hasil['gagal'] === []
            ? ''
            : "\n".implode("\n", array_slice($hasil['gagal'], 0, 8)).(count($hasil['gagal']) > 8 ? "\n…" : '');

        $notification = Notification::make()
            ->title($this->pakaiUlangEntitasLabel().' semester lain selesai dipakai')
            ->body($ringkasan.$detailGagal);

        if ($hasil['dilampirkan'] > 0 && $hasil['gagal'] === []) {
            $notification->success();
        } elseif ($hasil['dilampirkan'] > 0) {
            $notification->warning()->persistent();
        } else {
            $notification->danger()->persistent();
        }

        $notification->send();
    }
}

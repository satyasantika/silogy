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
 * "Import dari Semester Lain": pilih semester sumber, pratinjau baris yang
 * akan disalin (baru/duplikat) dengan checkbox pilih baris, lalu konfirmasi.
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
trait HasSalinAntarSemesterMassal
{
    use ClearsImporModalPreviewOnUnmount;
    use ReadsMountedActionData;

    /**
     * Salinan langsung semester sumber terpilih saat ini, disinkronkan
     * lewat afterStateUpdated() pada Select — lihat catatan trait di atas.
     * Harus public supaya ikut disimpan/dipulihkan Livewire antar request.
     */
    public ?string $salinAntarSemesterSumberLive = null;

    /**
     * @var array<string, list<array<string, mixed>>>
     */
    protected array $salinAntarSemesterBarisCache = [];

    /** Label entitas untuk judul modal & teks petunjuk, mis. "Sub-CPMK". */
    abstract protected function salinAntarSemesterEntitasLabel(): string;

    abstract protected function salinAntarSemesterMkId(): ?string;

    abstract protected function salinAntarSemesterTargetSemesterId(): ?string;

    /**
     * @return list<array{line: int, label: string, status: string, keterangan: string}>
     */
    abstract protected function salinAntarSemesterResolveBaris(string $sumberSemesterId, string $mkId, string $targetSemesterId): array;

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{dibuat: int, diperbarui: int, dilewati: int, gagal: list<string>}
     */
    abstract protected function salinAntarSemesterJalankan(array $rows, string $modeDuplikat, string $mkId, string $targetSemesterId): array;

    /**
     * ID semester (selain semester tujuan) yang sudah punya data untuk MK
     * ini — dasar opsi dropdown sumber. Tombol "Import dari Semester Lain"
     * disembunyikan total bila daftar ini kosong.
     *
     * @return list<string>
     */
    abstract protected function salinAntarSemesterSemesterIdsDenganData(string $mkId): array;

    /**
     * Prasyarat tambahan sebelum tombol "Import dari Semester Lain" boleh
     * tampil (opsional, default selalu terpenuhi) — override bila entitas
     * ini butuh data lain sudah ada dulu di semester tujuan (mis. Asesmen
     * butuh Sub-CPMK sudah ada dulu, supaya pemetaannya tidak kosong).
     */
    protected function salinAntarSemesterPrasyaratTerpenuhi(): bool
    {
        return true;
    }

    protected function makeSalinAntarSemesterAction(): Action
    {
        return Action::make('salinAntarSemester')
            ->label('Import dari Semester Lain')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->modalHeading('Import '.$this->salinAntarSemesterEntitasLabel().' dari semester lain')
            ->modalWidth(Width::SixExtraLarge)
            ->modalSubmitActionLabel('Import sekarang')
            // Kosongkan pratinjau saat modal dibuka (jaring aman) DAN saat
            // ditutup (lihat ClearsImporModalPreviewOnUnmount). Tetap panggil
            // $schema->fill() supaya default field (termasuk baris_dipilih)
            // ikut ter-reset — lihat CanBeMounted::getMountUsing().
            ->mountUsing(function (Schema $schema): void {
                $this->kosongkanPreviewImporModal();
                $schema->fill();
            })
            ->after(function (): void {
                $this->kosongkanPreviewImporModal();
            })
            ->visible(fn (): bool => $this->salinAntarSemesterMkId() !== null
                && $this->salinAntarSemesterTargetSemesterId() !== null
                && $this->salinAntarSemesterOpsiSumber() !== []
                && $this->salinAntarSemesterPrasyaratTerpenuhi())
            ->modalSubmitAction(fn (Action $action): Action|bool => $this->salinAntarSemesterPratinjauSiap()
                ? $action
                : false)
            ->schema([
                Placeholder::make('salin_petunjuk')
                    ->hiddenLabel()
                    ->content(fn (): HtmlString => $this->renderSalinAntarSemesterGuideBox()),
                Select::make('sumber_semester_id')
                    ->label('Salin dari semester')
                    ->options(fn (): array => $this->salinAntarSemesterOpsiSumber())
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        // Sengaja TIDAK lewat Get()/Set() untuk membaca state —
                        // lihat catatan pada properti $salinAntarSemesterSumberLive.
                        // $state adalah nilai Select terbaru, dikirim langsung
                        // sebagai parameter closure. $set() di sini AMAN karena
                        // menulis dari hook milik field ini sendiri, bukan
                        // membaca state sibling dari hook komponen lain.
                        $this->salinAntarSemesterSumberLive = $state;

                        $set('baris_dipilih', collect($this->salinAntarSemesterBaris($state))
                            ->pluck('line')
                            ->all());

                        // Filament v4 PartialsComponentHook memakai partial key
                        // berbeda antara request mountAction pertama kali dan
                        // request update berikutnya pada action yang sama — paksa
                        // full render agar morph memakai jalur Livewire standar.
                        if (method_exists($this, 'forceRender')) {
                            $this->forceRender();
                        }
                    }),
                Placeholder::make('baris_kosong')
                    ->hiddenLabel()
                    ->content(new HtmlString('<p class="text-sm">Belum ada data pada semester sumber ini.</p>'))
                    ->visible(fn (Get $get): bool => $this->salinAntarSemesterBaris(
                        $this->salinAntarSemesterSumberTerkini($get),
                    ) === []),
                CheckboxList::make('baris_dipilih')
                    ->hiddenLabel()
                    ->options(fn (Get $get): array => $this->salinAntarSemesterOpsiBaris(
                        $this->salinAntarSemesterSumberTerkini($get),
                    ))
                    ->descriptions(fn (Get $get): array => $this->salinAntarSemesterDeskripsiBaris(
                        $this->salinAntarSemesterSumberTerkini($get),
                    ))
                    ->default(fn (Get $get): array => array_keys($this->salinAntarSemesterOpsiBaris(
                        $this->salinAntarSemesterSumberTerkini($get),
                    )))
                    ->columns(1)
                    ->bulkToggleable()
                    ->visible(fn (Get $get): bool => $this->salinAntarSemesterBaris(
                        $this->salinAntarSemesterSumberTerkini($get),
                    ) !== [])
                    ->helperText(fn (Get $get): string => sprintf(
                        '%d baris terbaca.',
                        count($this->salinAntarSemesterBaris($this->salinAntarSemesterSumberTerkini($get))),
                    )),
                Radio::make('mode_duplikat')
                    ->label('Tindakan untuk data duplikat')
                    ->options([
                        'lewati' => 'Lewati (biarkan data lama)',
                        'timpa' => 'Timpa data lama dengan data dari semester sumber',
                    ])
                    ->default('lewati')
                    ->required()
                    ->visible(fn (Get $get): bool => $this->salinAntarSemesterAdaDuplikat(
                        $this->salinAntarSemesterSumberTerkini($get),
                    )),
            ])
            ->action(function (array $data): void {
                $this->jalankanSalinAntarSemester(
                    $this->salinAntarSemesterSumberLive ?? (string) ($data['sumber_semester_id'] ?? ''),
                    (string) ($data['mode_duplikat'] ?? 'lewati'),
                    (array) ($data['baris_dipilih'] ?? []),
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
    protected function salinAntarSemesterSumberTerkini(?Get $get = null): ?string
    {
        if (filled($this->salinAntarSemesterSumberLive)) {
            return $this->salinAntarSemesterSumberLive;
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
    protected function salinAntarSemesterOpsiSumber(): array
    {
        $mkId = $this->salinAntarSemesterMkId();
        $targetId = $this->salinAntarSemesterTargetSemesterId();

        if (blank($mkId) || blank($targetId)) {
            return [];
        }

        $semesterIdsDenganData = collect($this->salinAntarSemesterSemesterIdsDenganData($mkId))
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
    protected function salinAntarSemesterBaris(?string $sumberSemesterId): array
    {
        $mkId = $this->salinAntarSemesterMkId();
        $targetId = $this->salinAntarSemesterTargetSemesterId();

        if (blank($sumberSemesterId) || blank($mkId) || blank($targetId)) {
            return [];
        }

        $kunci = md5(serialize([$sumberSemesterId, $mkId, $targetId]));

        return $this->salinAntarSemesterBarisCache[$kunci] ??= $this->salinAntarSemesterResolveBaris(
            $sumberSemesterId,
            $mkId,
            $targetId,
        );
    }

    /**
     * @return array<int, string>
     */
    protected function salinAntarSemesterOpsiBaris(?string $sumberSemesterId): array
    {
        return collect($this->salinAntarSemesterBaris($sumberSemesterId))
            ->mapWithKeys(fn (array $row): array => [$row['line'] => $row['label']])
            ->all();
    }

    /**
     * Badge status (Baru/Duplikat) + keterangan per baris, dipakai sebagai
     * description CheckboxList — warna mengikuti konvensi badge yang sudah
     * dipakai di halaman lain (mis. PenilaianDosenService::ringkasanKelasHtml()).
     *
     * @return array<int, HtmlString>
     */
    protected function salinAntarSemesterDeskripsiBaris(?string $sumberSemesterId): array
    {
        return collect($this->salinAntarSemesterBaris($sumberSemesterId))
            ->mapWithKeys(function (array $row): array {
                $duplikat = $row['status'] === 'duplikat';
                $background = $duplikat ? '#fef3c7' : '#dcfce7';
                $color = $duplikat ? '#92400e' : '#166534';
                $border = $duplikat ? '#fcd34d' : '#86efac';

                $badge = sprintf(
                    '<span style="display:inline-flex;align-items:center;padding:3px 8px;border-radius:6px;'
                    .'font-size:11px;font-weight:600;line-height:1.4;background:%s;color:%s;border:1px solid %s;">%s</span>',
                    $background,
                    $color,
                    $border,
                    $duplikat ? 'Duplikat' : 'Baru',
                );

                if ($row['keterangan'] !== '') {
                    $badge .= ' <span style="font-size:11px;opacity:.75;">'.e($row['keterangan']).'</span>';
                }

                return [$row['line'] => new HtmlString($badge)];
            })
            ->all();
    }

    /** Pratinjau berhasil dibaca DAN minimal satu baris masih tercentang. */
    protected function salinAntarSemesterPratinjauSiap(): bool
    {
        $rows = $this->salinAntarSemesterBaris($this->salinAntarSemesterSumberTerkini());

        if ($rows === []) {
            return false;
        }

        $dipilih = $this->mountedActionDataTerkini()['baris_dipilih'] ?? null;

        return is_array($dipilih) ? $dipilih !== [] : true;
    }

    protected function salinAntarSemesterAdaDuplikat(?string $sumberSemesterId): bool
    {
        return collect($this->salinAntarSemesterBaris($sumberSemesterId))->contains('status', 'duplikat');
    }

    protected function renderSalinAntarSemesterGuideBox(): HtmlString
    {
        $items = [
            'Pilih semester sumber.',
            'Centang baris yang ingin diimpor.',
            'Tekan tombol Import sekarang.',
        ];

        $list = collect($items)
            ->map(fn (string $item): string => '<li>'.e($item).'</li>')
            ->join('');

        return new HtmlString(
            '<div class="rounded-lg border border-primary-600/30 bg-primary-50 p-4 text-sm text-gray-700 dark:border-primary-500/30 dark:bg-primary-950/40 dark:text-gray-200">'
            .'<p class="mb-2 font-semibold">Petunjuk import dari semester lain</p>'
            .'<ul class="list-disc space-y-1 ps-5">'.$list.'</ul>'
            .'</div>'
        );
    }

    /**
     * @param  list<string>  $barisDipilih
     */
    protected function jalankanSalinAntarSemester(string $sumberSemesterId, string $modeDuplikat, array $barisDipilih): void
    {
        $mkId = $this->salinAntarSemesterMkId();
        $targetId = $this->salinAntarSemesterTargetSemesterId();

        if (blank($mkId) || blank($targetId) || blank($sumberSemesterId)) {
            Notification::make()
                ->title('Konteks MK/semester belum lengkap')
                ->danger()
                ->send();

            return;
        }

        $rows = $this->salinAntarSemesterBaris($sumberSemesterId);

        if ($barisDipilih !== []) {
            $barisDipilihSet = array_map('intval', $barisDipilih);
            $rows = array_values(array_filter(
                $rows,
                fn (array $row): bool => in_array((int) $row['line'], $barisDipilihSet, true),
            ));
        } else {
            $rows = [];
        }

        $hasil = ['dibuat' => 0, 'diperbarui' => 0, 'dilewati' => 0, 'gagal' => []];

        DB::transaction(function () use ($rows, $modeDuplikat, $mkId, $targetId, &$hasil): void {
            $hasil = $this->salinAntarSemesterJalankan($rows, $modeDuplikat, $mkId, $targetId);
        });

        $ringkasan = sprintf(
            'Berhasil dibuat: %d · Diperbarui (timpa): %d · Dilewati (duplikat): %d · Gagal: %d',
            $hasil['dibuat'],
            $hasil['diperbarui'],
            $hasil['dilewati'],
            count($hasil['gagal']),
        );

        $detailGagal = $hasil['gagal'] === []
            ? ''
            : "\n".implode("\n", array_slice($hasil['gagal'], 0, 8)).(count($hasil['gagal']) > 8 ? "\n…" : '');

        $notification = Notification::make()
            ->title('Import dari semester lain selesai')
            ->body($ringkasan.$detailGagal);

        if ($hasil['dibuat'] + $hasil['diperbarui'] > 0 && $hasil['gagal'] === []) {
            $notification->success();
        } elseif ($hasil['dibuat'] + $hasil['diperbarui'] > 0) {
            $notification->warning()->persistent();
        } else {
            $notification->danger()->persistent();
        }

        $notification->send();
    }
}

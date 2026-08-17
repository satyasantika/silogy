<?php

namespace App\Modules\MK\Filament\Pages;

use App\Models\User;
use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Filament\Pages\Concerns\InteraksiKoordinatorMatrixPage;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\MK\Support\PenawaranMkScope;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use App\Modules\Penilaian\Services\NormalisasiBobotSubcpmkService;
use App\Modules\Penilaian\Services\RencanaEvaluasiService;
use App\Modules\Penilaian\Services\SubcpmkAsesmenClipboardService;
use App\Modules\Penilaian\Services\SubcpmkAsesmenPemetaanService;
use App\Modules\Penilaian\Support\NormalisasiBobotDesimal;
use App\Support\Filament\NavigationGroupPeran;
use App\Support\Filament\NavigationSortPeran;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * Matriks interaksi Sub-CPMK ↔ Asesmen: kolom Sub-CPMK, baris komponen
 * penilaian (asesmen), irisan berupa bobot kontribusi.
 */
class SubcpmkAsesmenMatrix extends Page
{
    use HasKoordinatorMkScope;
    use InteraksiKoordinatorMatrixPage;

    protected string $view = 'filament.modules.mk.pages.subcpmk-asesmen-matrix';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroupPeran::resolve('Interaksi');
    }

    public static function getNavigationSort(): ?int
    {
        return NavigationSortPeran::resolve('subcpmk-asesmen', 5);
    }

    protected static ?string $navigationLabel = 'Sub-CPMK ↔ Asesmen';

    protected static ?string $title = 'Interaksi Sub-CPMK ↔ Asesmen (bobot)';

    protected static ?string $slug = 'interaksi/subcpmk-asesmen';

    public ?string $semesterTerpilihId = null;

    public ?string $search = null;

    public ?string $filterEvaluasiId = null;

    public string $sortBy = 'kode';

    public string $sortDirection = 'asc';

    public function mount(): void
    {
        $mkId = MkTerpilih::currentId();
        $this->semesterTerpilihId = SemesterTerpilih::currentId($mkId);
    }

    public function updatedSemesterTerpilihId(?string $value): void
    {
        $mkId = MkTerpilih::currentId();

        if (filled($mkId) && filled($value)) {
            SemesterTerpilih::set($mkId, $value);
        }
    }

    public function updateBobot(string $komponenPenilaianId, string $subcpmkId, ?string $bobot): void
    {
        $bobot = trim((string) $bobot);

        if ($bobot === '' || ! is_numeric($bobot) || (float) $bobot <= 0) {
            $this->hapusPivotBobot($komponenPenilaianId, $subcpmkId);

            return;
        }

        $komponen = KomponenPenilaian::query()->findOrFail($komponenPenilaianId);

        $existing = SubcpmkKomponenPenilaian::query()
            ->where('komponen_penilaian_id', $komponenPenilaianId)
            ->where('subcpmk_id', $subcpmkId)
            ->first();

        $batas = SubcpmkAsesmenPemetaanService::sisaBobotTersedia($komponen, $existing?->getKey());
        $nilai = round(min((float) $bobot, $batas), 2);

        // Bobot 0 (atau terpotong ke 0 karena kapasitas habis) = hapus pivot,
        // jangan biarkan baris interaksi dengan bobot nol tersimpan.
        if ($nilai <= 0) {
            $this->hapusPivotBobot($komponenPenilaianId, $subcpmkId);

            return;
        }

        SubcpmkKomponenPenilaian::query()->updateOrCreate(
            [
                'komponen_penilaian_id' => $komponenPenilaianId,
                'subcpmk_id' => $subcpmkId,
            ],
            [
                'bobot' => $nilai,
                'semester_id' => $komponen->semester_id,
            ],
        );
    }

    protected function hapusPivotBobot(string $komponenPenilaianId, string $subcpmkId): void
    {
        SubcpmkKomponenPenilaian::query()
            ->where('komponen_penilaian_id', $komponenPenilaianId)
            ->where('subcpmk_id', $subcpmkId)
            ->get()
            ->each(fn (SubcpmkKomponenPenilaian $pivot) => $pivot->delete());
    }

    /**
     * Tombol "Normalisasi" per baris asesmen — muncul bila total bobot
     * Sub-CPMK-nya belum sama dengan bobot Asesmen (lihat kondisi tampil
     * di subcpmk-asesmen-matrix.blade.php). Perilakunya sama seperti aksi
     * serupa pada card Sub-CPMK di /komponen-penilaian/{id}/edit.
     */
    public function normalisasiBobotAsesmenAction(): Action
    {
        return Action::make('normalisasiBobotAsesmen')
            ->label('Normalisasi')
            ->icon(Heroicon::OutlinedScale)
            ->color('warning')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading('Normalisasi bobot Sub-CPMK')
            ->modalDescription(function (array $arguments): string {
                $komponen = KomponenPenilaian::query()->find($arguments['komponenId'] ?? null);

                if (! $komponen instanceof KomponenPenilaian) {
                    return 'Asesmen tidak ditemukan.';
                }

                $bobotAsesmen = app(RencanaEvaluasiService::class)->formatBobot((float) $komponen->bobot);

                return 'Bobot tiap Sub-CPMK yang berinteraksi dengan asesmen ini akan disesuaikan '
                    .'secara proporsional lalu dibulatkan sesuai pilihan di bawah, sehingga totalnya tepat sama '
                    ."dengan bobot Asesmen ini ({$bobotAsesmen}).";
            })
            ->schema([
                NormalisasiBobotDesimal::field(),
            ])
            ->modalSubmitActionLabel('Normalisasi')
            ->action(function (array $data, array $arguments): void {
                $komponen = KomponenPenilaian::query()->find($arguments['komponenId'] ?? null);

                if (! $komponen instanceof KomponenPenilaian) {
                    return;
                }

                $desimal = NormalisasiBobotDesimal::dariData($data);
                $hasil = app(NormalisasiBobotSubcpmkService::class)->normalisasi($komponen, $desimal);
                $bobotAsesmen = app(RencanaEvaluasiService::class)->formatBobot((float) $komponen->bobot);

                match ($hasil['status']) {
                    'dinormalisasi' => Notification::make()
                        ->title('Bobot Sub-CPMK berhasil dinormalisasi')
                        ->body(sprintf(
                            'Total sebelumnya %.2f%% untuk %d Sub-CPMK, kini totalnya tepat sama dengan bobot Asesmen (%s).',
                            $hasil['total_sebelum'],
                            $hasil['jumlah'],
                            $bobotAsesmen,
                        ))
                        ->success()
                        ->send(),
                    'sudah_pas' => Notification::make()
                        ->title("Total bobot sudah {$bobotAsesmen}")
                        ->body('Tidak ada perubahan yang diperlukan.')
                        ->info()
                        ->send(),
                    default => Notification::make()
                        ->title('Belum ada Sub-CPMK untuk dinormalisasi')
                        ->warning()
                        ->send(),
                };
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->salinMatriksAction(),
            $this->tempelMatriksAction(),
        ];
    }

    protected function salinMatriksAction(): Action
    {
        return Action::make('salinMatriksSubcpmkAsesmen')
            ->label('Salin Matriks')
            ->icon(Heroicon::OutlinedClipboard)
            ->color('gray')
            ->visible(fn (): bool => $this->clipboardContext() !== null)
            ->modalHeading('Salin matriks Sub-CPMK ↔ Asesmen')
            ->modalDescription('Salin ke Excel, isi/ubah bobot di sana, lalu tempel kembali lewat tombol Tempel dari Excel.')
            ->modalSubmitActionLabel('Salin ke clipboard')
            ->modalCancelActionLabel('Tutup')
            ->schema([
                Placeholder::make('salin_petunjuk')
                    ->hiddenLabel()
                    ->content(fn (): HtmlString => new HtmlString(
                        '<p style="font-size:13px;opacity:.85;">'
                        .'Kolom pertama <strong>Kode Asesmen</strong>, lalu satu kolom untuk tiap kode Sub-CPMK '
                        .'(pemisah tab, siap tempel ke Excel).</p>',
                    )),
                Textarea::make('export_text')
                    ->label('Matriks bobot')
                    ->default(fn (): string => $this->teksSalinMatriks())
                    ->readOnly()
                    ->rows(12)
                    ->extraAttributes(['class' => 'font-mono text-xs']),
            ])
            ->action(function (): void {
                $teks = $this->teksSalinMatriks();

                if ($teks === '') {
                    Notification::make()
                        ->title('Tidak ada data')
                        ->body('Belum ada asesmen atau Sub-CPMK pada semester ini.')
                        ->warning()
                        ->send();

                    return;
                }

                $this->js('navigator.clipboard.writeText('.json_encode($teks).')');

                Notification::make()
                    ->title('Disalin ke clipboard')
                    ->body('Tempel ke Excel, sesuaikan bobot, lalu impor lewat Tempel dari Excel.')
                    ->success()
                    ->send();
            });
    }

    protected function tempelMatriksAction(): Action
    {
        return Action::make('tempelMatriksSubcpmkAsesmen')
            ->label('Tempel dari Excel')
            ->icon(Heroicon::OutlinedClipboardDocument)
            ->color('gray')
            ->modalWidth(Width::SixExtraLarge)
            ->visible(fn (): bool => $this->clipboardContext() !== null)
            ->modalHeading('Tempel matriks Sub-CPMK ↔ Asesmen')
            ->modalSubmitAction(false)
            ->schema([
                Wizard::make([
                    Step::make('Tempel data')
                        ->icon(Heroicon::OutlinedClipboard)
                        ->schema([
                            Placeholder::make('tempel_petunjuk')
                                ->hiddenLabel()
                                ->content(fn (): HtmlString => $this->renderTempelPetunjuk()),
                            Textarea::make('paste_data')
                                ->label('Data yang ditempel')
                                ->required()
                                ->rows(10)
                                ->live()
                                ->extraAttributes(['class' => 'font-mono text-xs'])
                                ->helperText('Wajib menyertakan baris header (Kode Asesmen + kode Sub-CPMK). Pratinjau kevalidan tersedia pada langkah berikutnya.'),
                        ]),
                    Step::make('Preview & konfirmasi')
                        ->icon(Heroicon::OutlinedEye)
                        ->schema([
                            Placeholder::make('preview')
                                ->hiddenLabel()
                                ->content(fn (Get $get): HtmlString => $this->renderTempelPreview((string) $get('paste_data'))),
                        ]),
                ])->submitAction(new HtmlString(Blade::render(
                    '<x-filament::button type="submit" icon="heroicon-m-arrow-down-tray">Terapkan sekarang</x-filament::button>',
                ))),
            ])
            ->action(function (array $data): void {
                $this->terapkanTempelan((string) ($data['paste_data'] ?? ''));
            });
    }

    public function terapkanTempelan(string $raw): void
    {
        $context = $this->clipboardContext();

        if ($context === null) {
            Notification::make()
                ->title('Matriks belum siap untuk ditempel')
                ->danger()
                ->send();

            return;
        }

        $service = app(SubcpmkAsesmenClipboardService::class);

        try {
            $preview = $service->parsePaste($raw, $context['asesmen'], $context['subcpmks'], $context['bobots']);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Data tempel tidak valid')
                ->body(collect($exception->errors())->flatten()->join(' '))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $hasil = $service->terapkan($preview);
        $ringkasan = $preview['ringkasan'];

        $body = sprintf(
            'Diperbarui: %d · Dihapus: %d · Gagal diterapkan: %d · Baris tidak dikenali: %d · Sel tidak valid: %d',
            $hasil['diperbarui'],
            $hasil['dihapus'],
            $hasil['gagal'],
            $ringkasan['baris_tidak_dikenali'],
            $ringkasan['sel_invalid'],
        );

        $adaMasalah = $ringkasan['baris_tidak_dikenali'] > 0
            || $ringkasan['sel_invalid'] > 0
            || $hasil['gagal'] > 0;

        $notification = Notification::make()
            ->title('Impor matriks selesai')
            ->body($body);

        if (($hasil['diperbarui'] + $hasil['dihapus']) > 0 && ! $adaMasalah) {
            $notification->success();
        } elseif (($hasil['diperbarui'] + $hasil['dihapus']) > 0) {
            $notification->warning()->persistent();
        } else {
            $notification->danger()->persistent();
        }

        $notification->send();
    }

    protected function renderTempelPetunjuk(): HtmlString
    {
        $context = $this->clipboardContext();

        if ($context === null) {
            return new HtmlString('<p style="font-size:13px;opacity:.8;">Belum ada asesmen atau Sub-CPMK pada semester ini.</p>');
        }

        $asesmenKodes = $context['asesmen']
            ->filter(fn (KomponenPenilaian $komponen): bool => filled($komponen->kode))
            ->pluck('kode')
            ->values();

        $subcpmkKodes = $context['subcpmks']->pluck('kode')->values();

        $daftarAsesmen = $this->renderDaftarKodePerBaris($asesmenKodes);
        $daftarSubcpmk = $this->renderDaftarKodePerBaris($subcpmkKodes);
        $contohKolom = $this->renderContohKolom($asesmenKodes, $subcpmkKodes);

        return new HtmlString(
            '<div style="font-size:13px;opacity:.9;">'
            .'<p style="margin-bottom:8px;"><strong>Format wajib:</strong> baris pertama adalah header — '
            .'kolom pertama <strong>Kode Asesmen</strong>, lalu satu kolom untuk tiap kode Sub-CPMK yang ingin diisi '
            .'(jumlah kolom Sub-CPMK bebas/dinamis, tidak harus semuanya).</p>'
            .'<p style="margin-bottom:4px;"><strong>Susun dulu di Excel</strong> (header + baris data), lalu salin blok sel-nya dan tempel di bawah ini.</p>'
            .'<ul style="margin:0 0 10px;padding-left:18px;">'
            .'<li>Pemisah tab dari Excel didukung; pipe (<code>|</code>) juga diterima.</li>'
            .'<li>Kolom dicocokkan berdasarkan kode Sub-CPMK pada header, bukan urutan posisi.</li>'
            .'<li>Baris dicocokkan berdasarkan <strong>kode asesmen saja</strong> (tanpa nama tugas) pada kolom pertama.</li>'
            .'<li>Sel kosong berarti bobot dihapus (jika sebelumnya ada); isi 0–100 untuk mengisi/mengubah bobot.</li>'
            .'</ul>'
            .'<p style="margin-bottom:4px;font-size:11px;font-weight:600;text-transform:uppercase;opacity:.7;">Contoh kolom (susun seperti ini di Excel)</p>'
            .$contohKolom
            .'<p style="margin-bottom:2px;font-size:11px;font-weight:600;opacity:.7;">KODE ASESMEN TERSEDIA (tanpa nama tugas)</p>'
            .$daftarAsesmen
            .'<p style="margin:8px 0 2px;font-size:11px;font-weight:600;opacity:.7;">KODE SUB-CPMK TERSEDIA</p>'
            .$daftarSubcpmk
            .'</div>',
        );
    }

    /**
     * @param  Collection<int, string>  $kodes
     */
    protected function renderDaftarKodePerBaris(Collection $kodes): string
    {
        if ($kodes->isEmpty()) {
            return '<p>—</p>';
        }

        $items = $kodes
            ->map(fn (string $kode): string => '<li><code>'.e($kode).'</code></li>')
            ->join('');

        return '<ul style="margin:0;padding-left:18px;max-height:140px;overflow-y:auto;">'.$items.'</ul>';
    }

    /**
     * Contoh disajikan sebagai grid (bukan teks bertab), agar terlihat
     * persis seperti susunan kolom/baris yang perlu dibuat di Excel.
     *
     * @param  Collection<int, string>  $asesmenKodes
     * @param  Collection<int, string>  $subcpmkKodes
     */
    protected function renderContohKolom(Collection $asesmenKodes, Collection $subcpmkKodes): string
    {
        $contohSubcpmk = $subcpmkKodes->take(2);
        $contohAsesmen = $asesmenKodes->take(2);

        $selStyle = 'padding:5px 10px;border:1px solid rgba(128,128,128,.35);';
        $headerStyle = $selStyle.'background:rgba(128,128,128,.1);font-weight:600;';

        $header = '<th style="'.$headerStyle.'">Kode Asesmen</th>';

        foreach ($contohSubcpmk as $kode) {
            $header .= '<th style="'.$headerStyle.'">'.e($kode).'</th>';
        }

        $body = '';

        foreach ($contohAsesmen as $kode) {
            $baris = '<td style="'.$selStyle.'font-weight:600;background:rgba(128,128,128,.04);">'.e($kode).'</td>';

            foreach ($contohSubcpmk as $ignored) {
                $baris .= '<td style="'.$selStyle.'text-align:center;">50</td>';
            }

            $body .= '<tr>'.$baris.'</tr>';
        }

        return '<div style="overflow-x:auto;margin-bottom:10px;">'
            .'<table style="border-collapse:collapse;font-size:12px;">'
            .'<thead><tr>'.$header.'</tr></thead>'
            .'<tbody>'.$body.'</tbody>'
            .'</table>'
            .'</div>';
    }

    protected function renderTempelPreview(string $raw): HtmlString
    {
        $context = $this->clipboardContext();

        if ($context === null) {
            return new HtmlString('<p style="font-size:13px;">Matriks belum siap untuk ditempel.</p>');
        }

        if (trim($raw) === '') {
            return new HtmlString('<p style="font-size:13px;">Belum ada data yang ditempel. Kembali ke langkah sebelumnya dan tempel data terlebih dahulu.</p>');
        }

        try {
            $preview = app(SubcpmkAsesmenClipboardService::class)->parsePaste(
                $raw,
                $context['asesmen'],
                $context['subcpmks'],
                $context['bobots'],
            );
        } catch (ValidationException $exception) {
            $pesan = collect($exception->errors())->flatten()->join(' ');

            return new HtmlString('<p style="font-size:13px;color:#dc2626;font-weight:600;">'.e($pesan).'</p>');
        }

        $ringkasan = $preview['ringkasan'];

        $ringkasanHtml = sprintf(
            '<p style="font-size:13px;margin-bottom:8px;"><strong>%d baris dikenali</strong>%s'
            .' — <span style="color:#16a34a;font-weight:600;">%d sel diperbarui</span>, '
            .'<span style="color:#6b7280;font-weight:600;">%d sel dihapus</span>, '
            .'<span style="color:#dc2626;font-weight:600;">%d sel tidak valid</span>.</p>',
            $ringkasan['baris_dikenali'],
            $ringkasan['baris_tidak_dikenali'] > 0
                ? sprintf(' · <span style="color:#dc2626;font-weight:600;">%d baris tidak dikenali</span>', $ringkasan['baris_tidak_dikenali'])
                : '',
            $ringkasan['sel_update'],
            $ringkasan['sel_hapus'],
            $ringkasan['sel_invalid'],
        );

        if ($preview['kolom_tidak_dikenali'] !== []) {
            $ringkasanHtml .= '<p style="font-size:12px;color:#b45309;margin-bottom:8px;">Kolom header diabaikan (kode Sub-CPMK tidak dikenali): '
                .e(implode(', ', $preview['kolom_tidak_dikenali'])).'</p>';
        }

        $header = '<th style="padding:4px 8px;text-align:left;">Kode Asesmen</th>';

        foreach ($preview['kolom'] as $kolom) {
            $header .= '<th style="padding:4px 8px;text-align:center;">'.e($kolom['kode_subcpmk']).'</th>';
        }

        $body = '';

        foreach ($preview['baris'] as $baris) {
            if (! $baris['dikenali']) {
                $body .= '<tr style="border-top:1px solid rgba(128,128,128,.25);background:rgba(220,38,38,.08);">'
                    .'<td style="padding:4px 8px;font-weight:600;">'.e($baris['kode_asesmen_input']).'</td>'
                    .'<td colspan="'.count($preview['kolom']).'" style="padding:4px 8px;color:#dc2626;">Kode asesmen tidak dikenali</td>'
                    .'</tr>';

                continue;
            }

            $cells = '<td style="padding:4px 8px;font-weight:600;">'.e($baris['kode_asesmen_input']).'</td>';

            foreach ($baris['sel'] as $sel) {
                [$warna, $isi] = match ($sel['status']) {
                    'update' => ['#16a34a', number_format((float) $sel['bobot'], 2, ',', '.')],
                    'hapus' => ['#6b7280', 'hapus'],
                    'invalid' => ['#dc2626', e($sel['raw'])],
                    default => ['#9ca3af', '—'],
                };

                $cells .= '<td style="padding:4px 8px;text-align:center;color:'.$warna.';font-weight:600;">'.$isi.'</td>';
            }

            $body .= '<tr style="border-top:1px solid rgba(128,128,128,.25);">'.$cells.'</tr>';
        }

        $tabel = '<div style="overflow-x:auto;max-height:320px;overflow-y:auto;">'
            .'<table style="width:100%;font-size:12px;border-collapse:collapse;">'
            .'<thead><tr style="text-align:left;">'.$header.'</tr></thead>'
            .'<tbody>'.$body.'</tbody></table></div>';

        $errorHtml = '';

        if ($preview['errors'] !== []) {
            $ditampilkan = array_slice($preview['errors'], 0, 8);
            $errorHtml = '<div style="margin-top:8px;font-size:12px;color:#dc2626;">'
                .implode('<br>', array_map('e', $ditampilkan))
                .(count($preview['errors']) > 8 ? '<br>…' : '')
                .'</div>';
        }

        return new HtmlString($ringkasanHtml.$tabel.$errorHtml);
    }

    protected function teksSalinMatriks(): string
    {
        $context = $this->clipboardContext();

        if ($context === null) {
            return '';
        }

        return app(SubcpmkAsesmenClipboardService::class)->exportTsv(
            $context['asesmen'],
            $context['subcpmks'],
            $context['bobots'],
        );
    }

    /**
     * @return array{mk: Mk, semesterId: string, asesmen: Collection<int, KomponenPenilaian>, subcpmks: Collection<int, Subcpmk>, bobots: Collection<array-key, float>}|null
     */
    protected function clipboardContext(): ?array
    {
        $resolved = $this->resolveMkSemesterId();

        if ($resolved === null) {
            return null;
        }

        ['mk' => $mk, 'semesterId' => $semesterId] = $resolved;

        $subcpmks = $this->subcpmkQuery($mk, $semesterId)->get();
        $asesmen = $this->baseAsesmenQuery($mk, $semesterId)->get();

        if ($subcpmks->isEmpty() || $asesmen->isEmpty()) {
            return null;
        }

        $pivotRows = SubcpmkKomponenPenilaian::query()
            ->whereIn('komponen_penilaian_id', $asesmen->pluck('id'))
            ->whereIn('subcpmk_id', $subcpmks->pluck('id'))
            ->get();

        $bobots = $pivotRows->mapWithKeys(fn (SubcpmkKomponenPenilaian $pivot): array => [
            $pivot->komponen_penilaian_id.'/'.$pivot->subcpmk_id => (float) $pivot->bobot,
        ]);

        return [
            'mk' => $mk,
            'semesterId' => $semesterId,
            'asesmen' => $asesmen,
            'subcpmks' => $subcpmks,
            'bobots' => $bobots,
        ];
    }

    /**
     * @return array{mk: Mk, semesterId: string}|null
     */
    protected function resolveMkSemesterId(): ?array
    {
        $kurikulum = $this->getKurikulum();

        if (! $kurikulum instanceof Kurikulum) {
            return null;
        }

        $mk = $this->getMkTerpilih();

        if (! $mk instanceof Mk) {
            return null;
        }

        if (SemesterTerpilih::berlakuUntukUser()) {
            $semesterId = filled($this->semesterTerpilihId)
                ? $this->semesterTerpilihId
                : SemesterTerpilih::currentId($mk->id);

            if (blank($semesterId) || ! array_key_exists((string) $semesterId, SemesterTerpilih::optionsSemua())) {
                return null;
            }
        } else {
            $semesterId = SemesterTerpilih::defaultId();

            if (blank($semesterId)) {
                return null;
            }
        }

        return ['mk' => $mk, 'semesterId' => (string) $semesterId];
    }

    /**
     * @return Builder<Subcpmk>
     */
    protected function subcpmkQuery(Mk $mk, string $semesterId): Builder
    {
        return Subcpmk::query()
            ->with(['mkCpmk.cpmk'])
            ->where('semester_id', $semesterId)
            ->whereHas(
                'mkCpmk.cpmk',
                fn (Builder $cpmkQuery): Builder => $cpmkQuery->where('mk_id', $mk->id),
            )
            ->orderBy('kode');
    }

    /**
     * @return Builder<KomponenPenilaian>
     */
    protected function baseAsesmenQuery(Mk $mk, string $semesterId): Builder
    {
        $user = auth()->user();

        return KomponenPenilaian::query()
            ->with(['mk', 'evaluasi'])
            ->where('mk_id', $mk->id)
            ->where('semester_id', $semesterId)
            ->when(
                $user instanceof User
                    && PenawaranMkScope::isKoordinatorMkOnly($user)
                    && ! static::userCanManageMkAsKoordinator($user, $mk->id),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            )
            ->orderBy('kode');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $defaults = [
            'semesterOptions' => [],
            'tampilkanFilterSemester' => false,
            'asesmen' => collect(),
            'subcpmks' => collect(),
            'bobots' => collect(),
            'totals' => collect(),
            'evaluasiOptions' => [],
            'adaAsesmenSemua' => false,
            'search' => $this->search,
            'filterEvaluasiId' => $this->filterEvaluasiId,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
        ];

        $kurikulum = $this->getKurikulum();

        if (! $kurikulum instanceof Kurikulum) {
            return array_merge($defaults, ['kurikulum' => null], $this->mkTerpilihViewData());
        }

        $mk = $this->getMkTerpilih();

        if (! $mk instanceof Mk) {
            return array_merge($defaults, ['kurikulum' => $kurikulum], $this->mkTerpilihViewData());
        }

        $semesterOptions = SemesterTerpilih::berlakuUntukUser()
            ? SemesterTerpilih::options($mk->id)
            : [];
        $tampilkanFilterSemester = SemesterTerpilih::berlakuUntukUser();

        if ($tampilkanFilterSemester) {
            $semesterId = filled($this->semesterTerpilihId)
                ? $this->semesterTerpilihId
                : SemesterTerpilih::currentId($mk->id);
        } else {
            $semesterId = SemesterTerpilih::defaultId();
        }

        if (blank($semesterId) || ($tampilkanFilterSemester && ! array_key_exists((string) $semesterId, SemesterTerpilih::optionsSemua()))) {
            return array_merge($defaults, [
                'kurikulum' => $kurikulum,
                'semesterOptions' => $semesterOptions,
                'tampilkanFilterSemester' => $tampilkanFilterSemester,
            ], $this->mkTerpilihViewData());
        }

        if ($tampilkanFilterSemester) {
            $this->semesterTerpilihId = (string) $semesterId;
            SemesterTerpilih::set($mk->id, (string) $semesterId);
        }

        $subcpmks = Subcpmk::query()
            ->with(['mkCpmk.cpmk'])
            ->where('semester_id', $semesterId)
            ->whereHas(
                'mkCpmk.cpmk',
                fn ($cpmkQuery) => $cpmkQuery->where('mk_id', $mk->id),
            )
            ->orderBy('kode')
            ->get();

        $asesmenSemua = $this->baseAsesmenQuery($mk, $semesterId)->get();

        $evaluasiOptions = $asesmenSemua
            ->pluck('evaluasi')
            ->filter()
            ->unique('id')
            ->sortBy('nama')
            ->mapWithKeys(fn ($evaluasi): array => [$evaluasi->id => $evaluasi->nama])
            ->all();

        $search = trim((string) $this->search);
        $searchLike = str_replace(['%', '_'], ['\%', '\_'], $search);

        $asesmen = $this->baseAsesmenQuery($mk, $semesterId)
            ->when(
                $search !== '',
                fn ($query) => $query->where(function ($scoped) use ($searchLike): void {
                    $scoped->where('kode', 'like', "%{$searchLike}%")
                        ->orWhere('nama', 'like', "%{$searchLike}%");
                }),
            )
            ->when(
                filled($this->filterEvaluasiId),
                fn ($query) => $query->where('evaluasi_id', $this->filterEvaluasiId),
            )
            ->get();

        $pivotRows = SubcpmkKomponenPenilaian::query()
            ->whereIn('komponen_penilaian_id', $asesmen->pluck('id'))
            ->whereIn('subcpmk_id', $subcpmks->pluck('id'))
            ->get();

        $bobots = $pivotRows->mapWithKeys(fn (SubcpmkKomponenPenilaian $pivot): array => [
            $pivot->komponen_penilaian_id.'/'.$pivot->subcpmk_id => (float) $pivot->bobot,
        ]);

        $totals = $pivotRows
            ->groupBy('komponen_penilaian_id')
            ->map(fn ($rows): float => (float) $rows->sum('bobot'));

        $asesmen = $asesmen
            ->sortBy(
                fn (KomponenPenilaian $komponen): string|float => match ($this->sortBy) {
                    'evaluasi' => mb_strtolower($komponen->evaluasi->nama),
                    'total' => (float) ($totals[$komponen->id] ?? 0),
                    default => mb_strtolower((string) ($komponen->kode ?? '')),
                },
                SORT_REGULAR,
                $this->sortDirection === 'desc',
            )
            ->values();

        return array_merge($defaults, [
            'kurikulum' => $kurikulum,
            'semesterOptions' => $semesterOptions,
            'tampilkanFilterSemester' => $tampilkanFilterSemester,
            'asesmen' => $asesmen,
            'subcpmks' => $subcpmks,
            'bobots' => $bobots,
            'totals' => $totals,
            'evaluasiOptions' => $evaluasiOptions,
            'adaAsesmenSemua' => $asesmenSemua->isNotEmpty(),
        ], $this->mkTerpilihViewData());
    }
}

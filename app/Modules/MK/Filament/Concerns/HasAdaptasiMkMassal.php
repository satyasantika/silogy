<?php

namespace App\Modules\MK\Filament\Concerns;

use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Filament\Support\BannerKurikulumDikerjakan;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\MkUnitResource;
use App\Modules\MK\Services\AdaptasiMkMassalService;
use App\Support\Filament\Concerns\ReadsMountedActionData;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

trait HasAdaptasiMkMassal
{
    use ReadsMountedActionData;

    /** Label level unit sumber untuk keterangan form. */
    protected const ADAPTASI_LEVEL = [
        'kurikulum_univ_id' => ['type' => 'university', 'label' => 'universitas'],
        'kurikulum_fakultas_id' => ['type' => 'faculty', 'label' => 'fakultas'],
        'kurikulum_prodi_id' => ['type' => 'study_program', 'label' => 'prodi'],
    ];

    /**
     * Memo hasil AdaptasiMkMassalService::resolveBaris() per konteks dalam
     * satu request: satu render membutuhkannya untuk pratinjau, visibilitas
     * radio duplikat, dan visibilitas tombol submit.
     *
     * @var array<string, list<array<string, mixed>>>
     */
    protected array $adaptasiBarisCache = [];

    protected function makeAdaptasiMkMassalAction(): Action
    {
        return Action::make('adaptasiMkMassal')
            ->label('Adaptasi MK')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('primary')
            ->modalHeading('Adaptasi MK massal')
            ->modalWidth(Width::SixExtraLarge)
            ->modalSubmitActionLabel('Adaptasi sekarang')
            // Tombol adaptasi baru muncul setelah pratinjau memuat mata
            // kuliah sungguhan, bukan sekadar keterangan "belum ada sumber".
            ->modalSubmitAction(fn (Action $action): Action|bool => $this->adaptasiPratinjauSiap()
                ? $action
                : false)
            // Satu tampilan: unit penawaran, pilihan kurikulum sumber,
            // pratinjau, dan konfirmasi duplikat tanpa tombol "Selanjutnya".
            ->schema([
                BannerKurikulumDikerjakan::placeholder(
                    'Mata kuliah terpilih akan menjadi penawaran MK pada prodi kurikulum ini.',
                ),
                Placeholder::make('adaptasi_petunjuk')
                    ->hiddenLabel()
                    ->content(fn (): HtmlString => $this->renderAdaptasiGuideBox()),
                ...$this->adaptasiSumberComponents(),
                Placeholder::make('preview')
                    ->hiddenLabel()
                    ->content(fn (Get $get): HtmlString => $this->renderAdaptasiPreview(
                        $this->adaptasiContextDariGet($get),
                    )),
                // Hanya relevan bila pratinjau menemukan MK yang sudah pernah
                // ditawarkan; tanpa duplikat, jalankan() tetap pakai 'lewati'.
                Radio::make('mode_duplikat')
                    ->label('Tindakan untuk data duplikat')
                    ->options([
                        'lewati' => 'Batal diinputkan (lewati duplikat)',
                        'timpa' => 'Timpa data lama (aktifkan kembali penawaran)',
                    ])
                    ->default('lewati')
                    ->required()
                    ->visible(fn (Get $get): bool => $this->adaptasiAdaDuplikat(
                        $this->adaptasiContextDariGet($get),
                    )),
            ])
            ->action(function (array $data): void {
                $this->jalankanAdaptasiMassal($this->adaptasiContextDariData($data), $data);
            });
    }

    /**
     * Unit penawaran tidak lagi dipilih manual: selalu prodi milik kurikulum
     * yang sedang dikerjakan, supaya adaptasi tidak pernah mendarat di prodi
     * lain daripada yang tampil pada tabel penawaran.
     */
    protected function adaptasiProdi(): ?AcademicUnit
    {
        $unit = KurikulumTerpilih::current()?->academicUnit;

        if (! $unit instanceof AcademicUnit || ! $unit->isProdi()) {
            return null;
        }

        if (! MkUnitResource::scopedTimKurikulumUnitIds()->contains($unit->id)) {
            return null;
        }

        return $unit->loadMissing('parent.parent');
    }

    /**
     * Select kurikulum sumber per level. Level tanpa kurikulum dinonaktifkan
     * beserta keterangannya, jadi jelas bahwa kosongnya pilihan bukan bug.
     *
     * @return list<Select>
     */
    protected function adaptasiSumberComponents(): array
    {
        $labelField = [
            'kurikulum_univ_id' => 'Kurikulum universitas (sumber MK)',
            'kurikulum_fakultas_id' => 'Kurikulum fakultas (sumber MK)',
            'kurikulum_prodi_id' => 'Kurikulum prodi (sumber MK)',
        ];

        $components = [];

        foreach (static::ADAPTASI_LEVEL as $key => $level) {
            $components[] = Select::make($key)
                ->label($labelField[$key])
                ->options(fn (): array => $this->adaptasiKurikulumOptions($level['type']))
                ->default(fn (): ?string => $this->adaptasiKurikulumDefault($level['type']))
                ->searchable()
                ->live()
                ->disabled(fn (): bool => $this->adaptasiKurikulumOptions($level['type']) === [])
                ->placeholder(fn (): string => $this->adaptasiKurikulumOptions($level['type']) === []
                    ? 'Belum ada kurikulum '.$level['label']
                    : '— tidak diambil —')
                ->helperText(fn (): ?string => $this->adaptasiKurikulumOptions($level['type']) === []
                    ? 'Belum ada kurikulum pada '.$level['label'].' induk prodi ini, jadi tidak ada MK yang bisa diambil dari level tersebut.'
                    : null);
        }

        return $components;
    }

    /**
     * @return list<string>
     */
    protected function adaptasiContextKeys(): array
    {
        return array_keys(static::ADAPTASI_LEVEL);
    }

    /**
     * Konteks lengkap untuk service: unit penawaran ditentukan server-side
     * dari kurikulum yang dikerjakan, hanya kurikulum sumber yang berasal
     * dari form.
     *
     * @param  array<string, mixed>  $sumber
     * @return array<string, mixed>
     */
    protected function adaptasiContext(array $sumber): array
    {
        $context = ['adaptasi_unit_id' => $this->adaptasiProdi()?->id];

        foreach ($this->adaptasiContextKeys() as $key) {
            $context[$key] = $sumber[$key] ?? null;
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    protected function adaptasiContextDariGet(Get $get): array
    {
        $sumber = [];

        foreach ($this->adaptasiContextKeys() as $key) {
            $sumber[$key] = $get($key);
        }

        return $this->adaptasiContext($sumber);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function adaptasiContextDariData(array $data): array
    {
        return $this->adaptasiContext($data);
    }

    /**
     * Konteks di luar schema (visibilitas tombol submit) — dibaca dari state
     * form modal karena Get() tidak tersedia di sana.
     *
     * @return array<string, mixed>
     */
    protected function adaptasiContextTerkini(): array
    {
        return $this->adaptasiContext($this->mountedActionDataTerkini());
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array<string, mixed>>
     */
    protected function adaptasiBaris(array $context): array
    {
        $kunci = md5(serialize($context));

        return $this->adaptasiBarisCache[$kunci] ??= app(AdaptasiMkMassalService::class)->resolveBaris($context);
    }

    /**
     * Pratinjau memuat mata kuliah sungguhan. resolveBaris() mengembalikan
     * satu baris invalid sintetis tanpa mk_id untuk kondisi "sumber belum
     * dipilih" atau "sumber tidak punya MK aktif" — itu belum terhitung data.
     *
     * @param  array<string, mixed>|null  $context
     */
    protected function adaptasiPratinjauSiap(?array $context = null): bool
    {
        $context ??= $this->adaptasiContextTerkini();

        if (blank($context['adaptasi_unit_id'] ?? null)) {
            return false;
        }

        return collect($this->adaptasiBaris($context))
            ->contains(fn (array $row): bool => filled($row['mk_id'] ?? null));
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    protected function adaptasiAdaDuplikat(?array $context = null): bool
    {
        $context ??= $this->adaptasiContextTerkini();

        if (! $this->adaptasiPratinjauSiap($context)) {
            return false;
        }

        return collect($this->adaptasiBaris($context))->contains('status', 'duplikat');
    }

    protected function renderAdaptasiGuideBox(): HtmlString
    {
        $items = [
            'Unit penawaran mengikuti prodi kurikulum yang sedang dikerjakan — ganti lewat banner kurikulum bila ingin prodi lain.',
            'Ambil otomatis daftar mata kuliah aktif dari kurikulum universitas, fakultas, dan/atau prodi yang dipilih.',
            'Pilih minimal satu kurikulum sumber; kosongkan level yang tidak ingin diadaptasi.',
            'Hanya nama mata kuliah yang dijadikan penawaran MK; kode penawaran diisi otomatis oleh sistem.',
            'Semester pelaksanaan dapat ditetapkan kemudian melalui edit penawaran.',
            'Pratinjau di bawah menandai status baru, duplikat, atau invalid; tombol adaptasi muncul setelah ada MK terbaca.',
            'Duplikat = MK yang sama sudah pernah ditawarkan pada prodi; tindakan mengikuti pilihan lewati/timpa seperti impor massal.',
        ];

        $list = collect($items)
            ->map(fn (string $item): string => '<li>'.e($item).'</li>')
            ->join('');

        return new HtmlString(
            '<div class="rounded-lg border border-primary-600/30 bg-primary-50 p-4 text-sm text-gray-700 dark:border-primary-500/30 dark:bg-primary-950/40 dark:text-gray-200">'
            .'<p class="mb-2 font-semibold">Petunjuk adaptasi MK</p>'
            .'<ul class="list-disc space-y-1 ps-5">'.$list.'</ul>'
            .'</div>'
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function renderAdaptasiPreview(array $context): HtmlString
    {
        $rows = $this->adaptasiBaris($context);

        if ($rows === []) {
            return new HtmlString('<p class="text-sm">Belum ada mata kuliah yang dapat diadaptasi.</p>');
        }

        $jumlah = ['baru' => 0, 'duplikat' => 0, 'invalid' => 0];
        $body = '';

        foreach ($rows as $row) {
            $jumlah[$row['status']]++;

            [$badge, $warna] = match ($row['status']) {
                'baru' => ['Baru', '#16a34a'],
                'duplikat' => ['Duplikat', '#d97706'],
                default => ['Invalid', '#dc2626'],
            };

            $body .= '<tr style="border-top:1px solid rgba(128,128,128,.25);">'
                .'<td style="padding:4px 8px;">'.$row['line'].'</td>'
                .'<td style="padding:4px 8px;">'.e($row['nama']).'</td>'
                .'<td style="padding:4px 8px;">'.e($row['sumber']).'</td>'
                .'<td style="padding:4px 8px;white-space:nowrap;"><span style="font-weight:600;color:'.$warna.';">'.$badge.'</span>'
                .($row['keterangan'] !== '' ? '<br><span style="font-size:11px;opacity:.8;">'.e($row['keterangan']).'</span>' : '')
                .'</td>'
                .'</tr>';
        }

        $ringkasan = sprintf(
            '<p class="text-sm" style="margin-bottom:8px;"><strong>%d mata kuliah terbaca:</strong> '
            .'<span style="color:#16a34a;font-weight:600;">%d baru</span> · '
            .'<span style="color:#d97706;font-weight:600;">%d duplikat</span> · '
            .'<span style="color:#dc2626;font-weight:600;">%d invalid</span>. '
            .'Baris invalid tidak akan diadaptasi.%s</p>',
            count($rows),
            $jumlah['baru'],
            $jumlah['duplikat'],
            $jumlah['invalid'],
            $jumlah['duplikat'] > 0 ? ' Nasib duplikat mengikuti pilihan di bawah.' : '',
        );

        $tabel = '<div style="overflow-x:auto;max-height:320px;overflow-y:auto;">'
            .'<table style="width:100%;font-size:12px;border-collapse:collapse;">'
            .'<thead><tr style="text-align:left;">'
            .'<th style="padding:4px 8px;">No</th>'
            .'<th style="padding:4px 8px;">Nama MK</th>'
            .'<th style="padding:4px 8px;">Sumber</th>'
            .'<th style="padding:4px 8px;">Status</th>'
            .'</tr></thead>'
            .'<tbody>'.$body.'</tbody></table></div>';

        return new HtmlString($ringkasan.$tabel);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $data
     */
    protected function jalankanAdaptasiMassal(array $context, array $data): void
    {
        $service = app(AdaptasiMkMassalService::class);
        $rows = $service->resolveBaris($context);

        $hasil = ['dibuat' => 0, 'diperbarui' => 0, 'dilewati' => 0, 'gagal' => []];

        DB::transaction(function () use ($service, $rows, $data, $context, &$hasil): void {
            $hasil = $service->jalankan(
                $rows,
                (string) ($data['mode_duplikat'] ?? 'lewati'),
                $context,
            );
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
            ->title('Adaptasi MK selesai')
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

    /**
     * @return array<string, string>
     */
    protected function adaptasiKurikulumOptions(string $unitType): array
    {
        $prodi = $this->adaptasiProdi();

        if (! $prodi instanceof AcademicUnit) {
            return [];
        }

        return app(AdaptasiMkMassalService::class)->kurikulumOptionsUntukAncestor($prodi, $unitType);
    }

    protected function adaptasiKurikulumDefault(string $unitType): ?string
    {
        $prodi = $this->adaptasiProdi();

        if (! $prodi instanceof AcademicUnit) {
            return null;
        }

        return app(AdaptasiMkMassalService::class)->defaultKurikulumIdUntukAncestor($prodi, $unitType);
    }
}

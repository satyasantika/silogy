<?php

namespace App\Modules\MK\Filament\Concerns;

use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\MkUnitResource;
use App\Modules\MK\Services\AdaptasiMkMassalService;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

trait HasAdaptasiMkMassal
{
    protected function makeAdaptasiMkMassalAction(): Action
    {
        $service = app(AdaptasiMkMassalService::class);
        $contextKeys = $this->adaptasiContextKeys();

        $contextFromGet = function (Get $get) use ($contextKeys): array {
            $context = [];

            foreach ($contextKeys as $key) {
                $context[$key] = $get($key);
            }

            return $context;
        };

        return Action::make('adaptasiMkMassal')
            ->label('Adaptasi MK')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('primary')
            ->modalHeading('Adaptasi MK massal')
            ->modalWidth(Width::SixExtraLarge)
            ->modalSubmitAction(false)
            ->schema([
                Wizard::make([
                    Step::make('Pilih sumber')
                        ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                        ->schema([
                            ...$this->adaptasiContextComponents(),
                            Placeholder::make('adaptasi_petunjuk')
                                ->hiddenLabel()
                                ->content(fn (): HtmlString => $this->renderAdaptasiGuideBox()),
                        ]),
                    Step::make('Preview & konfirmasi')
                        ->icon(Heroicon::OutlinedEye)
                        ->schema([
                            Placeholder::make('preview')
                                ->hiddenLabel()
                                ->content(fn (Get $get): HtmlString => $this->renderAdaptasiPreview(
                                    $contextFromGet($get),
                                )),
                            Radio::make('mode_duplikat')
                                ->label('Tindakan untuk data duplikat')
                                ->options([
                                    'lewati' => 'Batal diinputkan (lewati duplikat)',
                                    'timpa' => 'Timpa data lama (aktifkan kembali penawaran)',
                                ])
                                ->default('lewati')
                                ->required(),
                        ]),
                ])
                    ->submitAction(new HtmlString(Blade::render(
                        '<x-filament::button type="submit" icon="heroicon-m-arrow-down-tray">Adaptasi sekarang</x-filament::button>'
                    ))),
            ])
            ->action(function (array $data) use ($contextKeys, $service): void {
                $context = [];

                foreach ($contextKeys as $key) {
                    $context[$key] = $data[$key] ?? null;
                }

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
            });
    }

    /**
     * @return list<string>
     */
    protected function adaptasiContextKeys(): array
    {
        return [
            'adaptasi_unit_id',
            'kurikulum_univ_id',
            'kurikulum_fakultas_id',
            'kurikulum_prodi_id',
        ];
    }

    /**
     * @return array<int, Component|Field>
     */
    protected function adaptasiContextComponents(): array
    {
        $service = app(AdaptasiMkMassalService::class);
        $unitIds = MkUnitResource::scopedTimKurikulumUnitIds();

        return [
            Select::make('adaptasi_unit_id')
                ->label('Unit penawaran (prodi)')
                ->options(MkUnitResource::timKurikulumUnitOptions())
                ->searchable()
                ->required()
                ->live()
                ->default(function () use ($unitIds): ?string {
                    $current = KurikulumTerpilih::current();

                    if ($current?->academicUnit?->isProdi()) {
                        return $current->academic_unit_id;
                    }

                    return $unitIds->count() === 1 ? $unitIds->first() : null;
                })
                ->rule(fn (): In => Rule::in(MkUnitResource::scopedTimKurikulumUnitIds()->all()))
                ->afterStateUpdated(function (Set $set, ?string $state) use ($service): void {
                    if (blank($state)) {
                        $set('kurikulum_univ_id', null);
                        $set('kurikulum_fakultas_id', null);
                        $set('kurikulum_prodi_id', null);

                        return;
                    }

                    $prodi = AcademicUnit::query()->with('parent.parent')->find($state);

                    if (! $prodi instanceof AcademicUnit) {
                        return;
                    }

                    $set('kurikulum_univ_id', $service->defaultKurikulumIdUntukAncestor($prodi, 'university'));
                    $set('kurikulum_fakultas_id', $service->defaultKurikulumIdUntukAncestor($prodi, 'faculty'));
                    $set('kurikulum_prodi_id', $service->defaultKurikulumIdUntukAncestor($prodi, 'study_program'));
                }),

            Select::make('kurikulum_univ_id')
                ->label('Kurikulum universitas (sumber MK)')
                ->options(fn (Get $get): array => $this->adaptasiKurikulumOptions($get('adaptasi_unit_id'), 'university'))
                ->searchable()
                ->live()
                ->placeholder('— tidak diambil —'),

            Select::make('kurikulum_fakultas_id')
                ->label('Kurikulum fakultas (sumber MK)')
                ->options(fn (Get $get): array => $this->adaptasiKurikulumOptions($get('adaptasi_unit_id'), 'faculty'))
                ->searchable()
                ->live()
                ->placeholder('— tidak diambil —'),

            Select::make('kurikulum_prodi_id')
                ->label('Kurikulum prodi (sumber MK)')
                ->options(fn (Get $get): array => $this->adaptasiKurikulumOptions($get('adaptasi_unit_id'), 'study_program'))
                ->searchable()
                ->live()
                ->placeholder('— tidak diambil —'),
        ];
    }

    protected function renderAdaptasiGuideBox(): HtmlString
    {
        $items = [
            'Ambil otomatis daftar mata kuliah aktif dari kurikulum universitas, fakultas, dan/atau prodi yang dipilih.',
            'Pilih minimal satu kurikulum sumber; kosongkan level yang tidak ingin diadaptasi.',
            'Hanya nama mata kuliah yang dijadikan penawaran MK; kode penawaran diisi otomatis oleh sistem.',
            'Semester pelaksanaan dapat ditetapkan kemudian melalui edit penawaran.',
            'Pratinjau pada langkah berikutnya menandai status baru, duplikat, atau invalid.',
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
        $rows = app(AdaptasiMkMassalService::class)->resolveBaris($context);

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
            .'Baris invalid tidak akan diadaptasi; nasib duplikat mengikuti pilihan di bawah.</p>',
            count($rows),
            $jumlah['baru'],
            $jumlah['duplikat'],
            $jumlah['invalid'],
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
     * @return array<string, string>
     */
    protected function adaptasiKurikulumOptions(?string $prodiId, string $unitType): array
    {
        if (blank($prodiId)) {
            return [];
        }

        $prodi = AcademicUnit::query()->with('parent.parent')->find($prodiId);

        if (! $prodi instanceof AcademicUnit || ! $prodi->isProdi()) {
            return [];
        }

        return app(AdaptasiMkMassalService::class)->kurikulumOptionsUntukAncestor($prodi, $unitType);
    }
}

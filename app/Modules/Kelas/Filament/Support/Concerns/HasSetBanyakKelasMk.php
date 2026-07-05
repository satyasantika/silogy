<?php

namespace App\Modules\Kelas\Filament\Support\Concerns;

use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Filament\Resources\KelasMkResource;
use App\Modules\Kelas\Services\SetBanyakKelasMkService;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

trait HasSetBanyakKelasMk
{
    protected function makeSetBanyakKelasAction(): Action
    {
        $service = app(SetBanyakKelasMkService::class);

        return Action::make('setBanyakKelas')
            ->label('Set banyak kelas')
            ->icon(Heroicon::OutlinedSquaresPlus)
            ->color('primary')
            ->modalHeading('Set banyak kelas MK')
            ->modalDescription('Pilih mata kuliah per semester kurikulum, tinjau matriks kelas, lalu buat sekaligus.')
            ->modalWidth(Width::SevenExtraLarge)
            ->visible(fn (): bool => filled($this->kurikulumIdUntukSetBanyakKelas()))
            ->schema([
                Section::make('Konteks')
                    ->schema([
                        Placeholder::make('info_kurikulum')
                            ->hiddenLabel()
                            ->content(fn (): HtmlString => $this->renderInfoKurikulumAktif()),

                        Select::make('semester_id')
                            ->label('Semester operasional')
                            ->options(fn (): array => Semester::query()->orderBy('kode')->pluck('nama', 'id')->all())
                            ->searchable()
                            ->required()
                            ->live()
                            ->default(fn (): ?string => Semester::query()->where('status_aktif', true)->value('id'))
                            ->helperText('Semester kalender tempat kelas akan dibuat (berbeda dari semester ke- MK di kurikulum).')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                ...$this->schemaPenawaranPerSemester($service),

                Section::make('Preview & konfirmasi')
                    ->schema([
                        Placeholder::make('preview_matriks')
                            ->hiddenLabel()
                            ->content(fn (Get $get): HtmlString => $service->renderPreviewHtml(
                                (string) $get('semester_id'),
                                $get('penawaran_by_semester') ?? [],
                            )),

                        Radio::make('mode_duplikat')
                            ->label('Tindakan untuk kelas yang sudah ada')
                            ->options([
                                'lewati' => 'Lewati kelas duplikat (hanya buat yang baru)',
                            ])
                            ->default('lewati')
                            ->required(),
                    ])
                    ->columnSpanFull(),
            ])
            ->fillForm(fn (): array => $this->formSetBanyakKelas($service))
            ->action(function (array $data) use ($service): void {
                $kurikulumId = $this->kurikulumIdUntukSetBanyakKelas();

                if (blank($kurikulumId)) {
                    Notification::make()
                        ->title('Kurikulum belum dipilih')
                        ->body('Pilih kurikulum terlebih dahulu lewat filter Kurikulum atau widget dashboard.')
                        ->danger()
                        ->send();

                    return;
                }

                KurikulumTerpilih::set($kurikulumId);

                $terpilih = $service->penawaranTerpilih($data['penawaran_by_semester'] ?? []);

                if ($terpilih === []) {
                    Notification::make()
                        ->title('Tidak ada mata kuliah dipilih')
                        ->body('Centang minimal satu mata kuliah sebelum menyimpan.')
                        ->danger()
                        ->send();

                    return;
                }

                if (blank($data['semester_id'] ?? null)) {
                    Notification::make()
                        ->title('Semester belum dipilih')
                        ->body('Pilih semester operasional terlebih dahulu.')
                        ->danger()
                        ->send();

                    return;
                }

                $hasil = ['dibuat' => 0, 'dilewati' => 0, 'gagal' => []];

                DB::transaction(function () use ($service, $data, &$hasil): void {
                    $hasil = $service->jalankan(
                        (string) $data['semester_id'],
                        $data['penawaran_by_semester'] ?? [],
                        (string) ($data['mode_duplikat'] ?? 'lewati'),
                    );
                });

                $ringkasan = sprintf(
                    'Berhasil dibuat: %d · Dilewati (duplikat): %d · Gagal: %d',
                    $hasil['dibuat'],
                    $hasil['dilewati'],
                    count($hasil['gagal']),
                );

                $detailGagal = $hasil['gagal'] === []
                    ? ''
                    : "\n".implode("\n", array_slice($hasil['gagal'], 0, 8)).(count($hasil['gagal']) > 8 ? "\n…" : '');

                $notification = Notification::make()
                    ->title('Set banyak kelas selesai')
                    ->body($ringkasan.$detailGagal);

                if ($hasil['dibuat'] > 0 && $hasil['gagal'] === []) {
                    $notification->success();
                } elseif ($hasil['dibuat'] > 0) {
                    $notification->warning()->persistent();
                } else {
                    $notification->danger()->persistent();
                }

                $notification->send();
            });
    }

    /**
     * @return list<Section>
     */
    protected function schemaPenawaranPerSemester(SetBanyakKelasMkService $service): array
    {
        $sections = [];

        foreach (range(SetBanyakKelasMkService::SEMESTER_KE_MIN, SetBanyakKelasMkService::SEMESTER_KE_MAX) as $semesterKe) {
            $key = (string) $semesterKe;

            $sections[] = Section::make($service->labelSemesterKe($semesterKe))
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])
                        ->schema([
                            Checkbox::make("semester_kontrol.{$key}.dipilih_semua")
                                ->label('Pilih semua MK semester ini')
                                ->live()
                                ->afterStateUpdated(function (?bool $state, Set $set, Get $get) use ($semesterKe): void {
                                    $this->terapkanKontrolSemester($set, $get, $semesterKe, (bool) $state);
                                }),

                            TextInput::make("semester_kontrol.{$key}.jumlah_kelas")
                                ->label('Banyak kelas (semester ini)')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(26)
                                ->default(1)
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (?string $state, Set $set, Get $get) use ($semesterKe): void {
                                    $this->terapkanJumlahKelasSemester($set, $get, $semesterKe, (int) ($state ?: 1));
                                }),
                        ]),

                    Placeholder::make("info_semester_{$key}")
                        ->hiddenLabel()
                        ->content(function (Get $get) use ($semesterKe): HtmlString {
                            $jumlah = count($get("penawaran_by_semester.{$semesterKe}") ?? []);

                            if ($jumlah === 0) {
                                return new HtmlString(
                                    '<p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada penawaran MK pada semester ini.</p>'
                                );
                            }

                            return new HtmlString(
                                '<p class="text-sm text-gray-600 dark:text-gray-300">'
                                ."{$jumlah} mata kuliah · urut alfabetis · centang per baris atau gunakan pilih semua di atas."
                                .'</p>'
                            );
                        }),

                    Repeater::make("penawaran_by_semester.{$key}")
                        ->hiddenLabel()
                        ->live()
                        ->afterStateHydrated(function (Repeater $component, ?array $state) use ($service, $semesterKe): void {
                            if (filled($state)) {
                                return;
                            }

                            $bySemester = $service->penawaranDefaultStateBySemester(
                                $this->kurikulumIdUntukSetBanyakKelas(),
                                1,
                                KelasMkResource::scopedAccessibleUnitIds(),
                            );

                            $component->state($bySemester[(string) $semesterKe] ?? []);
                        })
                        ->table([
                            TableColumn::make('Pilih')
                                ->width('4rem')
                                ->alignCenter(),
                            TableColumn::make('Mata kuliah'),
                            TableColumn::make('Banyak kelas')
                                ->width('8rem')
                                ->alignCenter(),
                        ])
                        ->schema([
                            Hidden::make('mk_unit_id'),
                            Hidden::make('kode_penawaran'),
                            Hidden::make('nama_mk'),
                            Hidden::make('semester_ke'),

                            Checkbox::make('dipilih')
                                ->label('')
                                ->live()
                                ->extraFieldWrapperAttributes([
                                    'class' => 'flex justify-center items-center',
                                ]),

                            TextInput::make('label_mk')
                                ->label('')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('jumlah_kelas')
                                ->label('')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(26)
                                ->default(1)
                                ->required()
                                ->extraInputAttributes(['class' => 'text-center'])
                                ->extraFieldWrapperAttributes([
                                    'class' => 'flex justify-center',
                                ]),
                        ])
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->compact()
                        ->columnSpanFull(),
                ])
                ->columnSpanFull()
                ->collapsible()
                ->collapsed(fn (Get $get): bool => count($get("penawaran_by_semester.{$key}") ?? []) === 0);
        }

        return $sections;
    }

    protected function terapkanKontrolSemester(Set $set, Get $get, int $semesterKe, bool $dipilihSemua): void
    {
        $key = (string) $semesterKe;
        $jumlah = max(1, min(26, (int) ($get("semester_kontrol.{$key}.jumlah_kelas") ?: 1)));

        $baris = collect($get("penawaran_by_semester.{$key}") ?? [])
            ->map(fn (array $item): array => [
                ...$item,
                'dipilih' => $dipilihSemua,
                'jumlah_kelas' => $jumlah,
            ])
            ->all();

        $set("penawaran_by_semester.{$key}", $baris);
    }

    protected function terapkanJumlahKelasSemester(Set $set, Get $get, int $semesterKe, int $jumlah): void
    {
        $key = (string) $semesterKe;
        $jumlah = max(1, min(26, $jumlah));

        $set("semester_kontrol.{$key}.jumlah_kelas", $jumlah);

        $baris = collect($get("penawaran_by_semester.{$key}") ?? [])
            ->map(fn (array $item): array => [
                ...$item,
                'jumlah_kelas' => $jumlah,
            ])
            ->all();

        $set("penawaran_by_semester.{$key}", $baris);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formSetBanyakKelas(SetBanyakKelasMkService $service): array
    {
        $kurikulumId = $this->kurikulumIdUntukSetBanyakKelas();

        if (filled($kurikulumId)) {
            KurikulumTerpilih::set($kurikulumId);
        }

        return [
            'semester_id' => Semester::query()->where('status_aktif', true)->value('id'),
            'penawaran_by_semester' => $service->penawaranDefaultStateBySemester(
                $kurikulumId,
                1,
                KelasMkResource::scopedAccessibleUnitIds(),
            ),
            'semester_kontrol' => $service->semesterKontrolDefaultState(),
            'mode_duplikat' => 'lewati',
        ];
    }

    protected function kurikulumIdUntukSetBanyakKelas(): ?string
    {
        return KurikulumTerpilih::currentId();
    }

    protected function renderInfoKurikulumAktif(): HtmlString
    {
        $kurikulumId = $this->kurikulumIdUntukSetBanyakKelas();
        $kurikulum = filled($kurikulumId)
            ? Kurikulum::query()->with('academicUnit')->find($kurikulumId)
            : null;

        if (! $kurikulum instanceof Kurikulum) {
            return new HtmlString(
                '<p class="text-sm text-amber-600 dark:text-amber-400">'
                .'Kurikulum yang sedang dikerjakan belum tersedia. Pilih kurikulum lewat filter atau widget dashboard.'
                .'</p>'
            );
        }

        return new HtmlString(
            '<p class="text-sm text-gray-600 dark:text-gray-300">'
            .'<strong>Kurikulum aktif:</strong> '.e(KurikulumTerpilih::label($kurikulum))
            .'</p>'
        );
    }
}

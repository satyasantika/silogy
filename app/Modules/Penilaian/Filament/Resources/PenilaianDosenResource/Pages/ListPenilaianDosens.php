<?php

namespace App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource\Pages;

use App\Models\User;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource;
use App\Modules\Penilaian\Services\DosenPengampuSintesysImportService;
use App\Modules\Penilaian\Support\PenilaianSemesterTerpilih;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class ListPenilaianDosens extends ListRecords
{
    protected static string $resource = PenilaianDosenResource::class;

    /**
     * Dukung tautan langsung (mis. dari widget dashboard) yang menyertakan
     * ?semester_id=... agar halaman ini langsung terfilter ke semester itu.
     */
    public function mount(): void
    {
        parent::mount();

        $semesterId = request()->query('semester_id');

        if (is_string($semesterId) && filled($semesterId)) {
            PenilaianSemesterTerpilih::set($semesterId);
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->headerActions([
                $this->makeImporSintesysAction(),
            ]);
    }

    protected function makeImporSintesysAction(): Action
    {
        return Action::make('importSintesysDosenPengampu')
            ->label('Tarik data')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('primary')
            ->modalHeading('Tarik kelas & peserta dari Sintesys')
            ->modalSubmitActionLabel('Impor sekarang')
            ->visible(fn (): bool => auth()->user() instanceof User)
            ->schema(function (): array {
                $dosen = auth()->user();
                $semester = $this->semesterTerpilih();

                if (! $dosen instanceof User || ! $semester instanceof Semester) {
                    return [
                        Placeholder::make('konteks_kosong')
                            ->hiddenLabel()
                            ->content(new HtmlString(
                                '<p style="font-size:13px;color:#991b1b;">Pilih semester terlebih dahulu lewat filter di atas tabel.</p>',
                            )),
                        Hidden::make('preview_status')->default('validasi'),
                        Hidden::make('payload_json')->default('[]'),
                    ];
                }

                $service = app(DosenPengampuSintesysImportService::class);
                $preview = $service->preview($dosen, $semester);

                return [
                    Placeholder::make('konteks_info')
                        ->hiddenLabel()
                        ->content(new HtmlString($service->ringkasanPreviewHtml($dosen, $semester, $preview))),
                    Hidden::make('preview_status')->default($preview['status']),
                    Hidden::make('payload_json')->default(fn (): string => json_encode($preview['payload'] ?? [])),
                ];
            })
            ->action(function (array $data): void {
                $dosen = auth()->user();
                $semester = $this->semesterTerpilih();

                if (! $dosen instanceof User || ! $semester instanceof Semester) {
                    Notification::make()
                        ->title('Semester belum dapat ditentukan')
                        ->body('Pilih semester terlebih dahulu lewat filter di atas tabel.')
                        ->danger()
                        ->send();

                    return;
                }

                $status = $data['preview_status'] ?? null;

                if ($status !== 'ok') {
                    Notification::make()
                        ->title($status === 'kosong' ? 'Tidak ada data untuk diimpor' : 'Gagal mengambil data dari Sintesys')
                        ->body('Buka kembali dialog "Tarik data" untuk memeriksa ulang ketersediaan data.')
                        ->warning()
                        ->send();

                    return;
                }

                $payload = json_decode((string) ($data['payload_json'] ?? '[]'), true);

                if (! is_array($payload) || $payload === []) {
                    Notification::make()
                        ->title('Data pratinjau sudah kedaluwarsa')
                        ->body('Buka kembali dialog "Tarik data" untuk mengambil data terbaru.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $hasil = app(DosenPengampuSintesysImportService::class)
                        ->import($payload, $dosen, $semester);
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title('Impor gagal')
                        ->body(collect($exception->errors())->flatten()->join(' '))
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                $total = $hasil['kelas_dibuat'] + $hasil['kelas_diperbarui']
                    + $hasil['peserta_terdaftar'] + $hasil['peserta_sudah_terdaftar'];
                $totalGagal = count($hasil['errors']);

                $ringkasan = sprintf(
                    "Kelas dibuat: %d · Kelas diperbarui: %d · Peserta terdaftar: %d\n".'Gagal: %d data',
                    $hasil['kelas_dibuat'],
                    $hasil['kelas_diperbarui'],
                    $hasil['peserta_terdaftar'] + $hasil['peserta_sudah_terdaftar'],
                    $totalGagal,
                );

                if ($hasil['mahasiswa_dibuat'] > 0) {
                    $ringkasan .= sprintf(' (%d mahasiswa baru dibuat)', $hasil['mahasiswa_dibuat']);
                }

                $detailGagal = $hasil['errors'] === []
                    ? ''
                    : "\n".implode("\n", array_slice($hasil['errors'], 0, 10)).(count($hasil['errors']) > 10 ? "\n…" : '');

                $notification = Notification::make()
                    ->title(sprintf('Tarik dari Sintesys selesai — %s', $semester->kode))
                    ->body($ringkasan.$detailGagal);

                if ($total > 0 && $totalGagal === 0) {
                    $notification->success();
                } elseif ($total > 0) {
                    $notification->warning()->persistent();
                } else {
                    $notification->danger()->persistent();
                }

                $notification->send();

                $this->resetTable();
            });
    }

    protected function semesterTerpilih(): ?Semester
    {
        $semesterId = PenilaianSemesterTerpilih::currentId();

        if (blank($semesterId)) {
            return null;
        }

        $semester = Semester::query()->find($semesterId);

        return $semester instanceof Semester ? $semester : null;
    }
}

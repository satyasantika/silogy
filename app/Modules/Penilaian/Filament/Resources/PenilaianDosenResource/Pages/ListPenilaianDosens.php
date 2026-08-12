<?php

namespace App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource\Pages;

use App\Models\User;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource;
use App\Modules\Penilaian\Services\DosenPengampuSintesysImportService;
use App\Modules\Penilaian\Services\PenilaianDosenService;
use App\Modules\Penilaian\Support\PenilaianSemesterTerpilih;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
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

    /**
     * Hapus kelas dari tabel /penilaian (ikon kecil di kolom aksi).
     * Cascade kontrak mahasiswa + nilai; komponen asesmen MK+semester tetap.
     */
    public function hapusKelasAction(): Action
    {
        return Action::make('hapusKelas')
            ->label('Hapus kelas')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(function (array $arguments): string {
                $kelas = KelasMk::query()->find($arguments['kelasMkId'] ?? null);

                return $kelas instanceof KelasMk
                    ? 'Hapus Kelas '.$kelas->kode_kelas.'?'
                    : 'Hapus kelas?';
            })
            ->modalDescription(
                'Menghapus kelas ini juga menghapus kontrak mahasiswa ke kelas tersebut '
                .'(pendaftaran peserta), nilai yang sudah diinput, dan hasil ketercapaian terkait. '
                .'Komponen asesmen mata kuliah pada semester ini tetap ada. Tindakan ini tidak dapat dibatalkan.'
            )
            ->modalSubmitActionLabel('Ya, hapus kelas')
            ->action(function (array $arguments): void {
                $user = auth()->user();
                $kelas = KelasMk::query()->find($arguments['kelasMkId'] ?? null);

                if ($user === null || ! $kelas instanceof KelasMk) {
                    return;
                }

                Gate::forUser($user)->authorize('delete', $kelas);

                if (PenilaianDosenService::ringkasanKelas($kelas)['sudah_dinilai']) {
                    Notification::make()
                        ->title('Kelas tidak dapat dihapus')
                        ->body('Kelas yang sudah dinilai tidak boleh dihapus. Hapus atau ubah nilai terlebih dahulu bila perlu.')
                        ->danger()
                        ->send();

                    return;
                }

                $kode = $kelas->kode_kelas;
                $jumlahPeserta = $kelas->kelasMkMahasiswas()->count();

                $kelas->delete();

                Notification::make()
                    ->title('Kelas '.$kode.' dihapus')
                    ->body($jumlahPeserta > 0
                        ? "Kontrak {$jumlahPeserta} mahasiswa ke kelas ini juga dihapus."
                        : 'Kelas tanpa peserta telah dihapus.')
                    ->success()
                    ->send();

                $this->resetTable();
            });
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->description(fn (): HtmlString => $this->deskripsiKpiPengampu())
            ->headerActions([
                $this->makeImporSintesysAction(),
            ]);
    }

    /**
     * KPI bento di atas card prodi (semester terpilih).
     */
    protected function deskripsiKpiPengampu(): HtmlString
    {
        $dosen = auth()->user();

        if (! $dosen instanceof User) {
            return new HtmlString('');
        }

        return new HtmlString(view('filament.modules.penilaian.partials.penilaian-dosen-kpi',
            PenilaianDosenService::rekapKpiPengampu($dosen, PenilaianSemesterTerpilih::currentId()),
        )->render());
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

                if ($hasil['peserta_dihapus'] > 0) {
                    $ringkasan .= sprintf(' · Peserta dihapus (pindah/keluar kelas): %d', $hasil['peserta_dihapus']);
                }

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

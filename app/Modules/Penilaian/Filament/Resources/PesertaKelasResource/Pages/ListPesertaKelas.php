<?php

namespace App\Modules\Penilaian\Filament\Resources\PesertaKelasResource\Pages;

use App\Models\User;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kelas\Services\PesertaKelasSintesysImportService;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Filament\Support\Concerns\HasImporMkSemesterKonteks;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\Penilaian\Filament\Resources\PesertaKelasResource;
use App\Modules\Penilaian\Policies\PesertaKelasPolicy;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class ListPesertaKelas extends ListRecords
{
    use HasImporMassal;
    use HasImporMkSemesterKonteks;

    protected static string $resource = PesertaKelasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeImporMassalAction()
                ->visible(fn (): bool => $this->bolehKelolaPeserta()),
            $this->makeImporSintesysAction(),
        ];
    }

    protected function bolehKelolaPeserta(): bool
    {
        $mkId = MkTerpilih::currentId();
        $user = auth()->user();

        if (blank($mkId) || ! $user instanceof User) {
            return false;
        }

        return app(PesertaKelasPolicy::class)->manageMk($user, $mkId);
    }

    protected function importModalHeading(): string
    {
        return 'Impor peserta massal ke kelas mata kuliah ini';
    }

    /**
     * @return list<string>
     */
    protected function importContextKeys(): array
    {
        return $this->importMkSemesterContextKeys();
    }

    /**
     * @return array<int, Component|Field>
     */
    protected function importContextComponents(): array
    {
        return $this->importMkSemesterContextComponents(
            PesertaKelasResource::scopedKoordinatorMkOptions(),
        );
    }

    protected function importColumns(): array
    {
        return [
            ['key' => 'nim', 'label' => 'nim', 'wajib' => true],
            ['key' => 'nama', 'label' => 'nama', 'wajib' => true],
            ['key' => 'kelas', 'label' => 'kelas', 'wajib' => true],
        ];
    }

    protected function importSupportsOverwrite(): bool
    {
        return false;
    }

    protected function importHelperNote(): string
    {
        return 'Kelas dibuat otomatis bila kode kelas belum ada pada mata kuliah dan semester ini. '
            .'Mahasiswa dengan NIM yang belum terdaftar akan dibuat otomatis pada program studi pemilik mata kuliah ini.';
    }

    /**
     * @return list<string>
     */
    protected function importExampleRows(): array
    {
        return [
            "227000001\tContoh Mahasiswa Satu\tA",
            "227000002\tContoh Mahasiswa Dua\tA",
        ];
    }

    protected function resolveImportRow(array $data, array $context): array
    {
        $validasiKonteks = $this->validasiKonteksImporMkSemester($context);

        if ($validasiKonteks !== null) {
            return $validasiKonteks;
        }

        $context = $this->normalizeImportContext($context);
        $mkId = (string) $context['import_mk_id'];
        $semesterId = (string) $context['import_semester_id'];

        $mkUnit = $this->resolveMkUnitUntukMk($mkId);

        if (! $mkUnit instanceof MkUnit) {
            return [
                'status' => 'invalid',
                'keterangan' => 'Mata kuliah ini belum memiliki kode penawaran pada program studi manapun.',
            ];
        }

        $nim = trim($data['nim'] ?? '');
        $kodeKelas = strtoupper(trim($data['kelas'] ?? ''));

        $mahasiswaProdiLain = Mahasiswa::query()
            ->where('nim', $nim)
            ->where('academic_unit_id', '!=', $mkUnit->academic_unit_id)
            ->exists();

        if ($mahasiswaProdiLain) {
            return ['status' => 'invalid', 'keterangan' => "Mahasiswa NIM \"{$nim}\" sudah terdaftar pada prodi lain."];
        }

        $dedup = mb_strtolower($mkId.'|'.$semesterId.'|'.$kodeKelas.'|'.$nim);

        $kelas = KelasMk::query()
            ->where('mk_unit_id', $mkUnit->id)
            ->where('semester_id', $semesterId)
            ->where('kode_kelas', $kodeKelas)
            ->first();

        if ($kelas instanceof KelasMk) {
            $mahasiswa = Mahasiswa::query()
                ->where('nim', $nim)
                ->where('academic_unit_id', $mkUnit->academic_unit_id)
                ->first();

            if ($mahasiswa instanceof Mahasiswa) {
                $terdaftar = KelasMkMahasiswa::query()
                    ->where('kelas_mk_id', $kelas->id)
                    ->where('mahasiswa_id', $mahasiswa->id)
                    ->exists();

                if ($terdaftar) {
                    return [
                        'status' => 'duplikat',
                        'keterangan' => 'Sudah terdaftar di kelas ini.',
                        'existing_id' => $mahasiswa->id,
                        'dedup' => $dedup,
                    ];
                }
            }
        }

        return ['status' => 'baru', 'keterangan' => '', 'dedup' => $dedup];
    }

    protected function createImportRow(array $data, array $context): void
    {
        $context = $this->normalizeImportContext($context);
        $mkUnit = $this->resolveMkUnitUntukMk((string) $context['import_mk_id']);

        if (! $mkUnit instanceof MkUnit) {
            return;
        }

        $kodeKelas = strtoupper(trim($data['kelas']));

        $kelas = KelasMk::query()
            ->where('mk_unit_id', $mkUnit->id)
            ->where('semester_id', $context['import_semester_id'])
            ->where('kode_kelas', $kodeKelas)
            ->first();

        if (! $kelas instanceof KelasMk) {
            $kelas = KelasMk::query()->create([
                'mk_unit_id' => $mkUnit->id,
                'semester_id' => $context['import_semester_id'],
                'kode_kelas' => $kodeKelas,
                'koordinator_mk_id' => $mkUnit->mk?->koordinator_mk_id,
            ]);
        }

        $nim = trim($data['nim']);

        $mahasiswa = Mahasiswa::query()
            ->where('nim', $nim)
            ->where('academic_unit_id', $mkUnit->academic_unit_id)
            ->first();

        if (! $mahasiswa instanceof Mahasiswa) {
            $nama = trim($data['nama']);

            $mahasiswa = Mahasiswa::query()->create([
                'nim' => $nim,
                'nama' => $nama !== '' ? $nama : null,
                'academic_unit_id' => $mkUnit->academic_unit_id,
            ]);
        }

        KelasMkMahasiswa::query()->firstOrCreate([
            'kelas_mk_id' => $kelas->id,
            'mahasiswa_id' => $mahasiswa->id,
        ]);
    }

    protected function makeImporSintesysAction(): Action
    {
        return Action::make('importSintesysPesertaKelas')
            ->label('Tarik dari Sintesys')
            ->icon(Heroicon::OutlinedArrowDownOnSquareStack)
            ->color('primary')
            ->modalHeading('Tarik peserta dari Sintesys untuk mata kuliah terpilih')
            ->modalSubmitActionLabel('Impor sekarang')
            ->visible(fn (): bool => $this->bolehKelolaPeserta())
            ->schema(function (): array {
                $konteks = $this->resolveKonteksSintesysMk();

                if ($konteks === null) {
                    return [
                        Placeholder::make('konteks_kosong')
                            ->hiddenLabel()
                            ->content(new HtmlString(
                                '<p style="font-size:13px;color:#991b1b;">Mata kuliah/semester belum dapat ditentukan, atau mata '
                                .'kuliah ini belum memiliki kode penawaran pada program studi manapun.</p>',
                            )),
                    ];
                }

                ['semester' => $semester, 'mkUnit' => $mkUnit] = $konteks;

                $preview = $this->previewSintesysMk($mkUnit, $semester);

                $namaMk = $mkUnit->mk?->nama ?? '—';

                $ringkasan = match ($preview['status']) {
                    'error' => sprintf(
                        '<p style="font-size:13px;color:#991b1b;">Gagal mengambil data dari Sintesys untuk mata kuliah <strong>%s</strong> '
                        .'(%s) · tahun akademik <strong>%s</strong>: %s</p>',
                        e($namaMk),
                        e($mkUnit->kode),
                        e($semester->nama),
                        e((string) $preview['pesan']),
                    ),
                    'kosong' => sprintf(
                        '<p style="font-size:13px;color:#92400e;">Tidak ada data ditemukan dari Sintesys untuk mata kuliah <strong>%s</strong> '
                        .'(%s) · tahun akademik <strong>%s</strong>. Tidak ada yang bisa diimpor.</p>',
                        e($namaMk),
                        e($mkUnit->kode),
                        e($semester->nama),
                    ),
                    default => sprintf(
                        '<p style="font-size:13px;color:#166534;">Ditemukan <strong>%d kelas</strong> dan <strong>%d peserta</strong> '
                        .'siap diimpor untuk mata kuliah <strong>%s</strong> (%s) · tahun akademik <strong>%s</strong>.</p>',
                        $preview['jumlah_kelas'],
                        $preview['jumlah_peserta'],
                        e($namaMk),
                        e($mkUnit->kode),
                        e($semester->nama),
                    ),
                };

                return [
                    Placeholder::make('konteks_info')
                        ->hiddenLabel()
                        ->content(new HtmlString($ringkasan)),
                    Hidden::make('preview_status')->default($preview['status']),
                    Hidden::make('payload_json')->default(fn (): string => json_encode($preview['payload'] ?? [])),
                ];
            })
            ->action(function (array $data): void {
                $konteks = $this->resolveKonteksSintesysMk();

                if ($konteks === null) {
                    Notification::make()
                        ->title('Mata kuliah/semester belum dapat ditentukan')
                        ->danger()
                        ->send();

                    return;
                }

                ['semester' => $semester, 'mkUnit' => $mkUnit] = $konteks;

                if (! $this->bolehKelolaPeserta()) {
                    Notification::make()
                        ->title('Anda tidak memiliki akses untuk mengelola peserta mata kuliah ini')
                        ->danger()
                        ->send();

                    return;
                }

                $status = $data['preview_status'] ?? null;

                if ($status !== 'ok') {
                    Notification::make()
                        ->title($status === 'kosong' ? 'Tidak ada data untuk diimpor' : 'Gagal mengambil data dari Sintesys')
                        ->body('Buka kembali dialog "Tarik dari Sintesys" untuk memeriksa ulang ketersediaan data.')
                        ->warning()
                        ->send();

                    return;
                }

                $payload = json_decode((string) ($data['payload_json'] ?? '[]'), true);

                if (! is_array($payload) || $payload === []) {
                    Notification::make()
                        ->title('Data pratinjau sudah kedaluwarsa')
                        ->body('Buka kembali dialog "Tarik dari Sintesys" untuk mengambil data terbaru.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $hasil = app(PesertaKelasSintesysImportService::class)->import($payload, $mkUnit, $semester);
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
                    ->title(sprintf('Tarik dari Sintesys selesai — %s (%s)', $mkUnit->mk?->nama ?? $mkUnit->kode, $semester->kode))
                    ->body($ringkasan.$detailGagal);

                if ($total > 0 && $totalGagal === 0) {
                    $notification->success();
                } elseif ($total > 0) {
                    $notification->warning()->persistent();
                } else {
                    $notification->danger()->persistent();
                }

                $notification->send();
            });
    }

    /**
     * Cek ketersediaan data Sintesys secara sinkron (pratinjau sebelum impor
     * sungguhan), di-cache singkat per user + MK unit + semester supaya
     * pratinjau dan impor sungguhan konsisten (tidak memanggil API dua kali).
     *
     * @return array{status: 'ok'|'kosong'|'error', pesan: ?string, payload: ?array<string, mixed>, jumlah_kelas: int, jumlah_peserta: int}
     */
    protected function previewSintesysMk(MkUnit $mkUnit, Semester $semester): array
    {
        $cacheKey = sprintf('sintesys-preview-mk:%s:%s:%s', auth()->id(), $mkUnit->id, $semester->id);

        return Cache::remember($cacheKey, now()->addMinutes(3), function () use ($mkUnit, $semester): array {
            $endpoint = (string) config('services.sintesys.endpoint');
            $token = (string) config('services.sintesys.token');

            if ($endpoint === '' || $token === '') {
                return [
                    'status' => 'error',
                    'pesan' => 'Konfigurasi API Sintesys (endpoint/token) belum diisi pada server.',
                    'payload' => null,
                    'jumlah_kelas' => 0,
                    'jumlah_peserta' => 0,
                ];
            }

            $mkUnit->loadMissing('academicUnit');

            try {
                $response = Http::withToken($token)
                    ->timeout(60)
                    ->post($endpoint, [
                        'tahun_akademik' => $semester->kode,
                        'kode_prodi' => (string) $mkUnit->academicUnit?->code,
                        'kode_matakuliah' => $mkUnit->kode,
                        'nidn' => null,
                        'nuptk' => null,
                        'email' => null,
                    ]);
            } catch (\Throwable $exception) {
                return [
                    'status' => 'error',
                    'pesan' => $exception->getMessage(),
                    'payload' => null,
                    'jumlah_kelas' => 0,
                    'jumlah_peserta' => 0,
                ];
            }

            if ($response->failed()) {
                return [
                    'status' => 'error',
                    'pesan' => "Permintaan API Sintesys gagal (HTTP {$response->status()}).",
                    'payload' => null,
                    'jumlah_kelas' => 0,
                    'jumlah_peserta' => 0,
                ];
            }

            $payload = $response->json();

            if (! is_array($payload)) {
                return [
                    'status' => 'error',
                    'pesan' => 'Respons API Sintesys bukan JSON yang valid.',
                    'payload' => null,
                    'jumlah_kelas' => 0,
                    'jumlah_peserta' => 0,
                ];
            }

            $data = $payload['data'] ?? null;
            $jumlahKelas = is_array($data) ? count($data) : 0;
            $jumlahPeserta = 0;

            if (is_array($data)) {
                foreach ($data as $item) {
                    if (is_array($item) && is_array($item['peserta'] ?? null)) {
                        $jumlahPeserta += count($item['peserta']);
                    }
                }
            }

            if ($jumlahKelas === 0) {
                return [
                    'status' => 'kosong',
                    'pesan' => null,
                    'payload' => $payload,
                    'jumlah_kelas' => 0,
                    'jumlah_peserta' => 0,
                ];
            }

            return [
                'status' => 'ok',
                'pesan' => null,
                'payload' => $payload,
                'jumlah_kelas' => $jumlahKelas,
                'jumlah_peserta' => $jumlahPeserta,
            ];
        });
    }

    /**
     * @return array{semester: Semester, mkUnit: MkUnit}|null
     */
    protected function resolveKonteksSintesysMk(): ?array
    {
        $mkId = MkTerpilih::currentId();

        if (blank($mkId)) {
            return null;
        }

        $mkUnit = $this->resolveMkUnitUntukMk($mkId);

        if (! $mkUnit instanceof MkUnit) {
            return null;
        }

        $semesterId = SemesterTerpilih::currentId($mkId) ?? SemesterTerpilih::defaultId();

        if (blank($semesterId)) {
            return null;
        }

        $semester = Semester::query()->find($semesterId);

        if (! $semester instanceof Semester) {
            return null;
        }

        return ['semester' => $semester, 'mkUnit' => $mkUnit];
    }

    /**
     * Penawaran (MkUnit) mata kuliah ini pada prodi kurikulum terpilih; bila
     * belum ada konteks kurikulum, ambil penawaran aktif pertama yang ada.
     */
    protected function resolveMkUnitUntukMk(string $mkId): ?MkUnit
    {
        $kurikulum = KurikulumTerpilih::current();

        if ($kurikulum instanceof Kurikulum) {
            $mkUnit = MkUnit::query()
                ->with(['mk', 'academicUnit'])
                ->where('mk_id', $mkId)
                ->where('academic_unit_id', $kurikulum->academic_unit_id)
                ->first();

            if ($mkUnit instanceof MkUnit) {
                return $mkUnit;
            }
        }

        return MkUnit::query()
            ->with(['mk', 'academicUnit'])
            ->where('mk_id', $mkId)
            ->orderByDesc('is_active')
            ->first();
    }
}

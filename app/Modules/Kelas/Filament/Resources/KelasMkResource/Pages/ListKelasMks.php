<?php

namespace App\Modules\Kelas\Filament\Resources\KelasMkResource\Pages;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Filament\Resources\KelasMkResource;
use App\Modules\Kelas\Filament\Support\Concerns\HasSetBanyakKelasMk;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkSintesysImport;
use App\Modules\Kelas\Services\KelasMkJsonImportService;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Models\MkUnit;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class ListKelasMks extends ListRecords
{
    use HasImporMassal;
    use HasSetBanyakKelasMk;

    protected static string $resource = KelasMkResource::class;

    protected function kurikulumIdUntukSetBanyakKelas(): ?string
    {
        $fromFilter = $this->tableFilters['kurikulum_terpilih']['value'] ?? null;

        if (filled($fromFilter)) {
            KurikulumTerpilih::set((string) $fromFilter);

            return (string) $fromFilter;
        }

        return KurikulumTerpilih::currentId();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ActionGroup::make([
                $this->makeSetBanyakKelasAction(),
                $this->makeImporMassalAction(),
                $this->makeImporJsonAction(),
                $this->makeImporSintesysAction(),
            ])
                ->label('Opsi lain')
                ->icon(Heroicon::OutlinedEllipsisHorizontal)
                ->color('gray')
                ->iconButton()
                ->tooltip('Opsi lain: set banyak kelas, impor massal, impor JSON, atau impor dari Sintesys')
                ->visible(fn (): bool => KelasMkResource::canCreate()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.modules.kelas.partials.sintesys-import-progress')
                    ->viewData(fn (): array => ['import' => $this->latestSintesysImport()])
                    ->visible(fn (): bool => $this->latestSintesysImport() !== null),
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    protected function latestSintesysImport(): ?KelasMkSintesysImport
    {
        $userId = auth()->id();

        if (blank($userId)) {
            return null;
        }

        return KelasMkSintesysImport::query()
            ->where('dibuat_oleh', $userId)
            ->latest('created_at')
            ->first();
    }

    protected function makeImporSintesysAction(): Action
    {
        return Action::make('importSintesysKelasMk')
            ->label('Impor dari Sintesys')
            ->icon(Heroicon::OutlinedArrowDownOnSquareStack)
            ->color('primary')
            ->modalHeading('Impor kelas MK + peserta dari Sintesys')
            ->modalSubmitActionLabel('Impor sekarang')
            ->schema(function (): array {
                $konteks = $this->resolveKonteksSintesys();

                if ($konteks === null) {
                    return [
                        Placeholder::make('konteks_kosong')
                            ->hiddenLabel()
                            ->content(new HtmlString(
                                '<p style="font-size:13px;color:#991b1b;">Tahun akademik/semester dan program studi belum dapat '
                                .'ditentukan. Pilih semester dan/atau kurikulum terlebih dahulu lewat filter di atas tabel.</p>',
                            )),
                    ];
                }

                ['semester' => $semester, 'academicUnit' => $academicUnit] = $konteks;

                $preview = $this->previewSintesys($semester, $academicUnit);

                $ringkasan = match ($preview['status']) {
                    'error' => sprintf(
                        '<p style="font-size:13px;color:#991b1b;">Gagal mengambil data dari Sintesys untuk tahun akademik <strong>%s</strong> '
                        .'· program studi <strong>%s</strong>: %s</p>',
                        e($semester->nama),
                        e($academicUnit->nama_lengkap),
                        e((string) $preview['pesan']),
                    ),
                    'kosong' => sprintf(
                        '<p style="font-size:13px;color:#92400e;">Tidak ada data ditemukan dari Sintesys untuk tahun akademik '
                        .'<strong>%s</strong> · program studi <strong>%s</strong>. Tidak ada yang bisa diimpor.</p>',
                        e($semester->nama),
                        e($academicUnit->nama_lengkap),
                    ),
                    default => sprintf(
                        '<p style="font-size:13px;color:#166534;">Ditemukan <strong>%d kelas</strong> dan <strong>%d peserta</strong> '
                        .'siap diimpor untuk tahun akademik <strong>%s</strong> · program studi <strong>%s</strong>.</p>',
                        $preview['jumlah_kelas'],
                        $preview['jumlah_peserta'],
                        e($semester->nama),
                        e($academicUnit->nama_lengkap),
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
                $konteks = $this->resolveKonteksSintesys();

                if ($konteks === null) {
                    Notification::make()
                        ->title('Tahun akademik/prodi belum dapat ditentukan')
                        ->body('Pilih semester dan/atau kurikulum terlebih dahulu lewat filter di atas tabel.')
                        ->danger()
                        ->send();

                    return;
                }

                ['semester' => $semester, 'academicUnit' => $academicUnit] = $konteks;

                if (! KelasMkResource::scopedAccessibleUnitIds()->contains($academicUnit->id)) {
                    Notification::make()
                        ->title('Anda tidak memiliki akses ke program studi ini')
                        ->danger()
                        ->send();

                    return;
                }

                $status = $data['preview_status'] ?? null;

                if ($status !== 'ok') {
                    Notification::make()
                        ->title($status === 'kosong' ? 'Tidak ada data untuk diimpor' : 'Gagal mengambil data dari Sintesys')
                        ->body('Buka kembali dialog "Impor dari Sintesys" untuk memeriksa ulang ketersediaan data.')
                        ->warning()
                        ->send();

                    return;
                }

                $payload = json_decode((string) ($data['payload_json'] ?? '[]'), true);

                if (! is_array($payload) || $payload === []) {
                    Notification::make()
                        ->title('Data pratinjau sudah kedaluwarsa')
                        ->body('Buka kembali dialog "Impor dari Sintesys" untuk mengambil data terbaru.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $hasil = app(KelasMkJsonImportService::class)->import(
                        $payload,
                        KelasMkResource::scopedAccessibleUnitIds(),
                    );
                } catch (ValidationException $exception) {
                    $pesanGagal = collect($exception->errors())->flatten()->join(' ');

                    KelasMkSintesysImport::query()->create([
                        'semester_id' => $semester->id,
                        'academic_unit_id' => $academicUnit->id,
                        'tahun_akademik' => $semester->kode,
                        'kode_prodi' => (string) $academicUnit->code,
                        'status' => 'failed',
                        'pesan_gagal' => $pesanGagal,
                        'dibuat_oleh' => auth()->id(),
                    ]);

                    Notification::make()
                        ->title('Impor gagal')
                        ->body($pesanGagal)
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                $total = $hasil['kelas_dibuat'] + $hasil['kelas_diperbarui']
                    + $hasil['peserta_terdaftar'] + $hasil['peserta_sudah_terdaftar'];

                KelasMkSintesysImport::query()->create([
                    'semester_id' => $semester->id,
                    'academic_unit_id' => $academicUnit->id,
                    'tahun_akademik' => $semester->kode,
                    'kode_prodi' => (string) $academicUnit->code,
                    'status' => 'completed',
                    'total' => $total,
                    'processed' => $total,
                    'kelas_dibuat' => $hasil['kelas_dibuat'],
                    'kelas_diperbarui' => $hasil['kelas_diperbarui'],
                    'peserta_terdaftar' => $hasil['peserta_terdaftar'],
                    'peserta_sudah_terdaftar' => $hasil['peserta_sudah_terdaftar'],
                    'peserta_dihapus' => $hasil['peserta_dihapus'],
                    'errors' => $hasil['errors'],
                    'dibuat_oleh' => auth()->id(),
                ]);

                $totalGagal = count($hasil['errors']);

                $ringkasan = sprintf(
                    "Berhasil: %d data · Gagal: %d data\n".
                    'Kelas dibuat: %d · Kelas diperbarui: %d · Peserta terdaftar: %d',
                    $total,
                    $totalGagal,
                    $hasil['kelas_dibuat'],
                    $hasil['kelas_diperbarui'],
                    $hasil['peserta_terdaftar'] + $hasil['peserta_sudah_terdaftar'],
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
                    ->title(sprintf('Impor dari Sintesys selesai — %s (%s)', $hasil['academic_unit_nama'], $hasil['semester_kode']))
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
     * Cek ketersediaan data Sintesys secara sinkron (untuk pratinjau sebelum
     * impor sungguhan dijalankan). Hasil di-cache singkat per kombinasi
     * user + semester + prodi supaya tidak memanggil API dua kali (sekali
     * saat modal dibuka, sekali lagi saat form disubmit) dan supaya isi
     * pratinjau konsisten dengan yang benar-benar diimpor.
     *
     * @return array{status: 'ok'|'kosong'|'error', pesan: ?string, payload: ?array<string, mixed>, jumlah_kelas: int, jumlah_peserta: int}
     */
    protected function previewSintesys(Semester $semester, AcademicUnit $academicUnit): array
    {
        $cacheKey = sprintf('sintesys-preview:%s:%s:%s', auth()->id(), $semester->id, $academicUnit->id);

        return Cache::remember($cacheKey, now()->addMinutes(3), function () use ($semester, $academicUnit): array {
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

            try {
                $response = Http::withToken($token)
                    ->timeout(60)
                    ->post($endpoint, [
                        'tahun_akademik' => $semester->kode,
                        'kode_prodi' => (string) $academicUnit->code,
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
     * @return array{semester: Semester, academicUnit: AcademicUnit}|null
     */
    protected function resolveKonteksSintesys(): ?array
    {
        $semesterId = $this->tableFilters['semester_id']['value'] ?? null;

        $semester = filled($semesterId)
            ? Semester::query()->find($semesterId)
            : Semester::query()->where('status_aktif', true)->first();

        if (! $semester instanceof Semester) {
            return null;
        }

        $academicUnit = $this->resolveAcademicUnitSintesys();

        if (! $academicUnit instanceof AcademicUnit) {
            return null;
        }

        return ['semester' => $semester, 'academicUnit' => $academicUnit];
    }

    protected function resolveAcademicUnitSintesys(): ?AcademicUnit
    {
        $kurikulumId = $this->kurikulumIdUntukSetBanyakKelas();

        if (filled($kurikulumId)) {
            $kurikulum = Kurikulum::query()->find($kurikulumId);

            if ($kurikulum instanceof Kurikulum && filled($kurikulum->academic_unit_id)) {
                $academicUnit = AcademicUnit::query()->find($kurikulum->academic_unit_id);

                if ($academicUnit instanceof AcademicUnit) {
                    return $academicUnit;
                }
            }
        }

        $fromFilter = $this->tableFilters['academic_unit_id']['value'] ?? null;

        if (filled($fromFilter)) {
            return AcademicUnit::query()->find($fromFilter);
        }

        $unitIds = KelasMkResource::scopedAccessibleUnitIds();

        if ($unitIds->count() === 1) {
            return AcademicUnit::query()->find($unitIds->first());
        }

        return null;
    }

    protected function makeImporJsonAction(): Action
    {
        return Action::make('importJsonKelasMk')
            ->label('Impor dari JSON')
            ->icon(Heroicon::OutlinedCodeBracketSquare)
            ->color('gray')
            ->modalHeading('Impor kelas MK + peserta dari JSON')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Impor sekarang')
            ->schema([
                Placeholder::make('import_json_petunjuk')
                    ->hiddenLabel()
                    ->content(fn (): HtmlString => new HtmlString(
                        '<div style="font-size:13px;opacity:.9;">'
                        .'<p style="margin-bottom:6px;">Struktur JSON: <code>tahun_akademik</code> (kode semester, mis. 20252), '
                        .'<code>kode_prodi</code> (kode prodi), lalu <code>data[]</code> berisi <code>kode_mk</code>, <code>kelas</code>, '
                        .'<code>dosen_pengampu.nidn</code>, dan <code>peserta[].npm</code>.</p>'
                        .'<ul style="margin:0;padding-left:18px;">'
                        .'<li>Kelas MK dibuat otomatis (bila belum ada) berdasarkan kode_mk + kelas + semester; koordinator MK diisi dari data MK.</li>'
                        .'<li>Dosen pengampu dicocokkan lewat NIDN.</li>'
                        .'<li>Setiap peserta (NPM) didaftarkan ke kelasnya masing-masing.</li>'
                        .'<li>Field lain (nama_mk, sks, nilai, komponen_evaluasi, dst.) diabaikan.</li>'
                        .'</ul>'
                        .'</div>',
                    )),

                Radio::make('sumber')
                    ->label('Sumber data')
                    ->options([
                        'file' => 'Upload file JSON',
                        'api' => 'Ambil dari API',
                    ])
                    ->default('file')
                    ->live()
                    ->required(),

                FileUpload::make('json_file')
                    ->label('File JSON')
                    ->acceptedFileTypes(['application/json', 'text/plain', 'text/json'])
                    ->maxSize(32768)
                    ->disk('local')
                    ->directory('impor-kelas-mk-json')
                    ->visibility('private')
                    ->helperText('Maksimum 32 MB.')
                    ->required(fn (Get $get): bool => $get('sumber') === 'file')
                    ->visible(fn (Get $get): bool => $get('sumber') === 'file'),

                TextInput::make('api_url')
                    ->label('Endpoint URL')
                    ->url()
                    ->required(fn (Get $get): bool => $get('sumber') === 'api')
                    ->visible(fn (Get $get): bool => $get('sumber') === 'api'),

                Select::make('api_method')
                    ->label('Metode HTTP')
                    ->options(['POST' => 'POST', 'GET' => 'GET'])
                    ->default('POST')
                    ->required(fn (Get $get): bool => $get('sumber') === 'api')
                    ->visible(fn (Get $get): bool => $get('sumber') === 'api'),

                TextInput::make('api_bearer')
                    ->label('Bearer token')
                    ->password()
                    ->revealable()
                    ->visible(fn (Get $get): bool => $get('sumber') === 'api'),

                Textarea::make('api_body')
                    ->label('Body request (JSON)')
                    ->rows(6)
                    ->extraInputAttributes(['class' => 'font-mono text-xs'])
                    ->helperText('Diisi sesuai kebutuhan endpoint Anda; boleh dikosongkan untuk GET tanpa body.')
                    ->visible(fn (Get $get): bool => $get('sumber') === 'api'),
            ])
            ->action(function (array $data): void {
                try {
                    $raw = $this->ambilJsonKelasMkMentah($data);
                } catch (\Throwable $exception) {
                    Notification::make()
                        ->title('Gagal mengambil data')
                        ->body($exception->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                $payload = json_decode($raw, true);

                if (! is_array($payload)) {
                    Notification::make()
                        ->title('JSON tidak valid')
                        ->body('Data yang diterima bukan JSON yang valid.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $hasil = app(KelasMkJsonImportService::class)->import(
                        $payload,
                        KelasMkResource::scopedAccessibleUnitIds(),
                    );
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
                    "Berhasil: %d data · Gagal: %d data\n".
                    'Kelas dibuat: %d · Kelas diperbarui: %d · Peserta terdaftar: %d',
                    $total,
                    $totalGagal,
                    $hasil['kelas_dibuat'],
                    $hasil['kelas_diperbarui'],
                    $hasil['peserta_terdaftar'] + $hasil['peserta_sudah_terdaftar'],
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
                    ->title(sprintf('Impor JSON kelas MK selesai — %s (%s)', $hasil['academic_unit_nama'], $hasil['semester_kode']))
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
     * @param  array<string, mixed>  $data
     */
    protected function ambilJsonKelasMkMentah(array $data): string
    {
        if (($data['sumber'] ?? 'file') === 'file') {
            $path = $data['json_file'] ?? null;

            if (blank($path)) {
                throw new \RuntimeException('File JSON belum dipilih.');
            }

            $raw = Storage::disk('local')->get($path);
            Storage::disk('local')->delete($path);

            if ($raw === null) {
                throw new \RuntimeException('Gagal membaca file yang diunggah.');
            }

            return $raw;
        }

        $url = trim((string) ($data['api_url'] ?? ''));

        if ($url === '') {
            throw new \RuntimeException('Endpoint API belum diisi.');
        }

        $method = strtoupper((string) ($data['api_method'] ?? 'POST'));
        $bearer = trim((string) ($data['api_bearer'] ?? ''));
        $bodyRaw = trim((string) ($data['api_body'] ?? ''));
        $bodyArray = $bodyRaw !== '' ? (json_decode($bodyRaw, true) ?? []) : [];

        $request = Http::timeout(60);

        if ($bearer !== '') {
            $request = $request->withToken($bearer);
        }

        $response = $method === 'GET'
            ? $request->get($url, $bodyArray)
            : $request->post($url, $bodyArray);

        if ($response->failed()) {
            throw new \RuntimeException("Permintaan API gagal (HTTP {$response->status()}).");
        }

        return $response->body();
    }

    protected function importModalHeading(): string
    {
        return 'Impor kelas MK massal';
    }

    /**
     * @return list<string>
     */
    protected function importContextKeys(): array
    {
        return ['import_semester_id'];
    }

    /**
     * @return array<int, Component|Field>
     */
    protected function importContextComponents(): array
    {
        return [
            Select::make('import_semester_id')
                ->label('Semester')
                ->options(fn (): array => Semester::query()->orderBy('kode')->pluck('nama', 'id')->all())
                ->searchable()
                ->required()
                ->default(fn (): ?string => Semester::query()->where('status_aktif', true)->value('id')),
        ];
    }

    protected function importColumns(): array
    {
        return [
            ['key' => 'kode_penawaran', 'label' => 'kode penawaran MK', 'wajib' => true],
            ['key' => 'kode_kelas', 'label' => 'kode kelas', 'wajib' => true],
            ['key' => 'username_dosen', 'label' => 'username dosen pengampu', 'wajib' => false],
            ['key' => 'username_koordinator', 'label' => 'username koordinator', 'wajib' => false],
        ];
    }

    protected function importHelperNote(): string
    {
        return 'Kode penawaran MK = kode pada menu Penawaran MK di unit Anda. '
            .'Koordinator kosong akan diisi otomatis dari koordinator MK.';
    }

    /**
     * @return list<string>
     */
    protected function importExampleRows(): array
    {
        return [
            "ADP101\tA\tdosen\t",
            "ADP101\tB\tdosen\t",
        ];
    }

    protected function resolveImportRow(array $data, array $context): array
    {
        if (blank($context['import_semester_id'] ?? null)) {
            return ['status' => 'invalid', 'keterangan' => 'Pilih semester terlebih dahulu.'];
        }

        $mkUnit = $this->cariMkUnit($data['kode_penawaran']);

        if (! $mkUnit) {
            return ['status' => 'invalid', 'keterangan' => "Penawaran MK '{$data['kode_penawaran']}' tidak ditemukan pada unit yang dapat Anda kelola."];
        }

        foreach (['username_dosen', 'username_koordinator'] as $key) {
            if ($data[$key] !== '' && ! User::query()->where('username', $data[$key])->exists()) {
                return ['status' => 'invalid', 'keterangan' => "Pengguna '{$data[$key]}' tidak ditemukan."];
            }
        }

        $dedup = mb_strtolower($data['kode_penawaran'].'/'.$data['kode_kelas']);

        $existing = KelasMk::query()
            ->where('mk_unit_id', $mkUnit->id)
            ->where('semester_id', $context['import_semester_id'])
            ->where('kode_kelas', $data['kode_kelas'])
            ->first();

        if ($existing) {
            return [
                'status' => 'duplikat',
                'keterangan' => 'Kelas dengan kode ini sudah ada pada penawaran dan semester tersebut.',
                'existing_id' => $existing->id,
                'dedup' => $dedup,
            ];
        }

        return ['status' => 'baru', 'keterangan' => '', 'dedup' => $dedup];
    }

    protected function createImportRow(array $data, array $context): void
    {
        $mkUnit = $this->cariMkUnit($data['kode_penawaran']);

        KelasMk::query()->create([
            'mk_unit_id' => $mkUnit?->id,
            'semester_id' => $context['import_semester_id'],
            'kode_kelas' => $data['kode_kelas'],
            'dosen_pengampu_id' => $this->userId($data['username_dosen']),
            'koordinator_mk_id' => $this->userId($data['username_koordinator'])
                ?? $mkUnit?->mk?->koordinator_mk_id,
        ]);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        $kelas = KelasMk::query()->findOrFail($existingId);

        $kelas->update([
            'dosen_pengampu_id' => $this->userId($data['username_dosen']) ?? $kelas->dosen_pengampu_id,
            'koordinator_mk_id' => $this->userId($data['username_koordinator']) ?? $kelas->koordinator_mk_id,
        ]);
    }

    protected function cariMkUnit(string $kodePenawaran): ?MkUnit
    {
        $unitIds = KelasMkResource::scopedAccessibleUnitIds();

        return MkUnit::query()
            ->with('mk')
            ->whereIn('academic_unit_id', $unitIds)
            ->where('kode', $kodePenawaran)
            ->first();
    }

    protected function userId(string $username): ?string
    {
        if ($username === '') {
            return null;
        }

        return User::query()->where('username', $username)->value('id');
    }
}

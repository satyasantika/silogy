<?php

namespace App\Modules\MK\Filament\Resources\MkUnitResource\Pages;

use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kelas\Services\PesertaKelasSintesysImportService;
use App\Modules\Kurikulum\Filament\Support\BannerKurikulumDikerjakan;
use App\Modules\MK\Filament\Resources\MkUnitResource;
use App\Modules\MK\Models\MkUnit;
use App\Support\Filament\Pages\BaseEditRecord;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class EditMkUnit extends BaseEditRecord
{
    protected static string $resource = MkUnitResource::class;

    /**
     * Semester kode ≥ nilai ini memakai Sintesys; sebelumnya memakai Simak
     * (contoh: 20242 dan ke bawah → Simak, 20251 dan seterusnya → Sintesys).
     */
    public const KODE_AWAL_SINTESYS = '20251';

    /** Semester kalender untuk tarik kontrak & rekap kelas. */
    public ?string $semesterKontrakId = null;

    /** Kelas yang sedang ditampilkan daftar mahasiswanya. */
    public ?string $kelasDetailId = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->semesterKontrakId = Semester::query()
            ->where('status_aktif', true)
            ->value('id')
            ?? Semester::query()->orderByDesc('kode')->value('id');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.modules.mk.partials.mk-unit-edit-banner')
                    ->viewData(fn (): array => [
                        'banner' => BannerKurikulumDikerjakan::html(
                            'Penawaran MK ini dikelola pada kurikulum prodi yang dikerjakan; tarik kontrak kelas mengikuti semester kalender yang dipilih di bawah.',
                        ),
                    ]),
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler($this->getSubmitFormLivewireMethodName()),
                // Simpan/Kembali di atas card kontrak supaya penyimpanan data
                // penawaran tidak tertutup blok rekap kelas di bawah.
                $this->getFormActionsContentComponent(),
                View::make('filament.modules.mk.partials.mk-unit-kontrak-kelas')
                    ->viewData(fn (): array => [
                        'semesterOptions' => $this->semesterKontrakOptions(),
                        'semesterKontrakId' => $this->semesterKontrakId,
                        'labelTombolTarik' => $this->labelTombolTarikKontrak(),
                        'rekapKelas' => $this->rekapKelasUntukSemester(),
                        'kelasDetailId' => $this->kelasDetailId,
                        'detailMahasiswa' => $this->detailMahasiswaKelas(),
                        'kelasDetail' => $this->kelasDetailTerpilih(),
                    ]),
            ]);
    }

    public function updatedSemesterKontrakId(?string $value): void
    {
        $this->kelasDetailId = null;
    }

    public function pilihDetailKelas(?string $kelasId): void
    {
        if (blank($kelasId)) {
            $this->kelasDetailId = null;

            return;
        }

        $ada = collect($this->rekapKelasUntukSemester())
            ->contains(fn (array $baris): bool => $baris['id'] === $kelasId);

        $this->kelasDetailId = $ada ? $kelasId : null;
    }

    public function tarikKontrakKelas(): void
    {
        /** @var MkUnit $mkUnit */
        $mkUnit = $this->getRecord();
        $mkUnit->loadMissing(['academicUnit', 'mk']);

        $semester = filled($this->semesterKontrakId)
            ? Semester::query()->find($this->semesterKontrakId)
            : null;

        if (! $semester instanceof Semester) {
            Notification::make()
                ->title('Pilih semester terlebih dahulu')
                ->warning()
                ->send();

            return;
        }

        $sumber = $this->sumberTarikUntukSemester($semester);
        $labelSumber = $this->labelSumberTarik($sumber);

        if (blank($mkUnit->kode) || blank($mkUnit->academicUnit?->code)) {
            Notification::make()
                ->title('Kode penawaran atau kode prodi belum lengkap')
                ->body("Pastikan penawaran MK punya kode dan unit penawaran prodi sebelum menarik data dari {$labelSumber}.")
                ->danger()
                ->send();

            return;
        }

        $preview = $this->previewKontrakKelas($mkUnit, $semester, $sumber);

        if ($preview['status'] !== 'ok') {
            Notification::make()
                ->title($preview['status'] === 'kosong'
                    ? 'Tidak ada data kontrak pada semester ini'
                    : "Gagal menarik data dari {$labelSumber}")
                ->body($preview['pesan'] ?? 'Periksa koneksi atau coba semester lain.')
                ->warning()
                ->send();

            return;
        }

        try {
            $hasil = app(PesertaKelasSintesysImportService::class)
                ->import($preview['payload'] ?? [], $mkUnit, $semester);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Impor gagal')
                ->body(collect($exception->errors())->flatten()->join(' '))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Cache::forget($this->cacheKeyKontrak($mkUnit, $semester, $sumber));

        $this->kelasDetailId = null;

        $total = $hasil['kelas_dibuat'] + $hasil['kelas_diperbarui']
            + $hasil['peserta_terdaftar'] + $hasil['peserta_sudah_terdaftar'];
        $totalGagal = count($hasil['errors']);

        $ringkasan = sprintf(
            'Kelas dibuat: %d · Kelas diperbarui: %d · Peserta terdaftar: %d · Gagal: %d',
            $hasil['kelas_dibuat'],
            $hasil['kelas_diperbarui'],
            $hasil['peserta_terdaftar'] + $hasil['peserta_sudah_terdaftar'],
            $totalGagal,
        );

        $detailGagal = $hasil['errors'] === []
            ? ''
            : "\n".implode("\n", array_slice($hasil['errors'], 0, 8)).(count($hasil['errors']) > 8 ? "\n…" : '');

        $notification = Notification::make()
            ->title(sprintf('Tarik kontrak selesai (%s) — %s · %s', $labelSumber, $mkUnit->kode, $semester->nama))
            ->body($ringkasan.$detailGagal);

        if ($total > 0 && $totalGagal === 0) {
            $notification->success();
        } elseif ($total > 0) {
            $notification->warning()->persistent();
        } else {
            $notification->danger()->persistent();
        }

        $notification->send();
    }

    /**
     * Semester ≥ 20251 → Sintesys; sebelumnya → Simak.
     *
     * @return 'sintesys'|'simak'
     */
    public function sumberTarikUntukSemester(?Semester $semester): string
    {
        if (! $semester instanceof Semester || blank($semester->kode)) {
            return 'sintesys';
        }

        return strcmp((string) $semester->kode, self::KODE_AWAL_SINTESYS) >= 0
            ? 'sintesys'
            : 'simak';
    }

    public function labelSumberTarik(string $sumber): string
    {
        return $sumber === 'simak' ? 'Simak' : 'Sintesys';
    }

    public function labelTombolTarikKontrak(): string
    {
        $semester = filled($this->semesterKontrakId)
            ? Semester::query()->find($this->semesterKontrakId)
            : null;

        return 'Tarik data '.$this->labelSumberTarik($this->sumberTarikUntukSemester($semester));
    }

    /**
     * @return array<string, string>
     */
    protected function semesterKontrakOptions(): array
    {
        return Semester::query()
            ->orderByDesc('kode')
            ->get()
            ->mapWithKeys(fn (Semester $semester): array => [
                $semester->id => "{$semester->nama} ({$semester->kode})",
            ])
            ->all();
    }

    /**
     * @return list<array{
     *     id: string,
     *     kode_kelas: string,
     *     dosen: string,
     *     jumlah_mahasiswa: int,
     * }>
     */
    public function rekapKelasUntukSemester(): array
    {
        if (blank($this->semesterKontrakId)) {
            return [];
        }

        /** @var MkUnit $mkUnit */
        $mkUnit = $this->getRecord();

        return KelasMk::query()
            ->where('mk_unit_id', $mkUnit->id)
            ->where('semester_id', $this->semesterKontrakId)
            ->with('dosenPengampu')
            ->withCount('mahasiswas')
            ->orderBy('kode_kelas')
            ->get()
            ->map(fn (KelasMk $kelas): array => [
                'id' => $kelas->id,
                'kode_kelas' => $kelas->kode_kelas,
                'dosen' => $kelas->dosenPengampu?->full_name ?? '— belum ditetapkan —',
                'jumlah_mahasiswa' => (int) $kelas->mahasiswas_count,
            ])
            ->all();
    }

    public function kelasDetailTerpilih(): ?array
    {
        if (blank($this->kelasDetailId)) {
            return null;
        }

        return collect($this->rekapKelasUntukSemester())
            ->firstWhere('id', $this->kelasDetailId);
    }

    /**
     * @return list<array{nim: string, nama: string}>
     */
    public function detailMahasiswaKelas(): array
    {
        if (blank($this->kelasDetailId)) {
            return [];
        }

        /** @var MkUnit $mkUnit */
        $mkUnit = $this->getRecord();

        $kelas = KelasMk::query()
            ->whereKey($this->kelasDetailId)
            ->where('mk_unit_id', $mkUnit->id)
            ->when(
                filled($this->semesterKontrakId),
                fn ($query) => $query->where('semester_id', $this->semesterKontrakId),
            )
            ->first();

        if (! $kelas instanceof KelasMk) {
            return [];
        }

        return KelasMkMahasiswa::query()
            ->where('kelas_mk_id', $kelas->id)
            ->with('mahasiswa')
            ->get()
            ->sortBy(fn (KelasMkMahasiswa $pivot): string => (string) ($pivot->mahasiswa?->nim ?? ''))
            ->values()
            ->map(fn (KelasMkMahasiswa $pivot): array => [
                'nim' => (string) ($pivot->mahasiswa?->nim ?? '—'),
                'nama' => (string) ($pivot->mahasiswa?->nama ?? '—'),
            ])
            ->all();
    }

    /**
     * @param  'sintesys'|'simak'  $sumber
     * @return array{status: 'ok'|'kosong'|'error', pesan: ?string, payload: ?array<string, mixed>, jumlah_kelas: int, jumlah_peserta: int}
     */
    protected function previewKontrakKelas(MkUnit $mkUnit, Semester $semester, string $sumber): array
    {
        $cacheKey = $this->cacheKeyKontrak($mkUnit, $semester, $sumber);
        $labelSumber = $this->labelSumberTarik($sumber);

        return Cache::remember($cacheKey, now()->addMinutes(3), function () use ($mkUnit, $semester, $sumber, $labelSumber): array {
            $endpoint = (string) config("services.{$sumber}.endpoint");
            $token = (string) config("services.{$sumber}.token");

            if ($endpoint === '' || $token === '') {
                return [
                    'status' => 'error',
                    'pesan' => "Konfigurasi API {$labelSumber} (endpoint/token) belum diisi pada server.",
                    'payload' => null,
                    'jumlah_kelas' => 0,
                    'jumlah_peserta' => 0,
                ];
            }

            $mkUnit->loadMissing('academicUnit');

            $body = [
                'tahun_akademik' => is_numeric($semester->kode) ? (int) $semester->kode : $semester->kode,
                'kode_prodi' => (string) $mkUnit->academicUnit?->code,
                'kode_matakuliah' => $mkUnit->kode,
            ];

            try {
                $response = Http::withToken($token)
                    ->timeout(60)
                    ->post($endpoint, $body);
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
                    'pesan' => "Permintaan API {$labelSumber} gagal (HTTP {$response->status()}).",
                    'payload' => null,
                    'jumlah_kelas' => 0,
                    'jumlah_peserta' => 0,
                ];
            }

            $payload = $response->json();

            if (! is_array($payload)) {
                return [
                    'status' => 'error',
                    'pesan' => "Respons API {$labelSumber} bukan JSON yang valid.",
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
                    'pesan' => sprintf(
                        'Tidak ada kontrak untuk %s pada tahun akademik %s.',
                        $mkUnit->kode,
                        $semester->kode,
                    ),
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

    protected function cacheKeyKontrak(MkUnit $mkUnit, Semester $semester, string $sumber): string
    {
        return sprintf(
            'kontrak-preview-mk-unit:%s:%s:%s:%s',
            $sumber,
            auth()->id(),
            $mkUnit->id,
            $semester->id,
        );
    }
}

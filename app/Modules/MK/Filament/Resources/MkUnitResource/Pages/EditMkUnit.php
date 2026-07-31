<?php

namespace App\Modules\MK\Filament\Resources\MkUnitResource\Pages;

use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Filament\Support\BannerKurikulumDikerjakan;
use App\Modules\MK\Filament\Resources\MkUnitResource;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Services\MkUnitTarikKontrakService;
use App\Support\Filament\Pages\BaseEditRecord;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class EditMkUnit extends BaseEditRecord
{
    protected static string $resource = MkUnitResource::class;

    /**
     * @see MkUnitTarikKontrakService::KODE_AWAL_SINTESYS
     */
    public const KODE_AWAL_SINTESYS = MkUnitTarikKontrakService::KODE_AWAL_SINTESYS;

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

    /**
     * Modal konfirmasi pratinjau sebelum impor (pola /peserta-kelas).
     */
    public function tarikKontrakKelasAction(): Action
    {
        return Action::make('tarikKontrakKelas')
            ->label(fn (): string => $this->labelTombolTarikKontrak())
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('primary')
            ->modalHeading(fn (): string => $this->labelTombolTarikKontrak())
            ->modalSubmitActionLabel('Impor sekarang')
            ->schema(function (): array {
                $semester = filled($this->semesterKontrakId)
                    ? Semester::query()->find($this->semesterKontrakId)
                    : null;

                if (! $semester instanceof Semester) {
                    return [
                        Placeholder::make('konteks_kosong')
                            ->hiddenLabel()
                            ->content(new HtmlString(
                                '<p style="font-size:13px;color:#991b1b;">Pilih semester terlebih dahulu sebelum menarik data kontrak.</p>',
                            )),
                        Hidden::make('preview_status')->default('validasi'),
                        Hidden::make('payload_json')->default('[]'),
                    ];
                }

                /** @var MkUnit $mkUnit */
                $mkUnit = $this->getRecord();
                $service = app(MkUnitTarikKontrakService::class);
                $sumber = $service->sumberUntukSemester($semester);
                $preview = $service->preview($mkUnit, $semester, $sumber);

                return [
                    Placeholder::make('konteks_info')
                        ->hiddenLabel()
                        ->content(new HtmlString($service->ringkasanPreviewHtml($mkUnit, $semester, $preview))),
                    Hidden::make('preview_status')->default($preview['status']),
                    Hidden::make('payload_json')->default(fn (): string => json_encode($preview['payload'] ?? [])),
                ];
            })
            ->action(function (array $data): void {
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

                $status = $data['preview_status'] ?? null;

                if ($status !== 'ok') {
                    $service = app(MkUnitTarikKontrakService::class);
                    $labelSumber = $service->labelSumber($service->sumberUntukSemester($semester));

                    Notification::make()
                        ->title($status === 'kosong' ? 'Tidak ada data untuk diimpor' : "Gagal mengambil data dari {$labelSumber}")
                        ->body('Buka kembali dialog tarik data untuk memeriksa ulang ketersediaan data.')
                        ->warning()
                        ->send();

                    return;
                }

                /** @var MkUnit $mkUnit */
                $mkUnit = $this->getRecord();
                $payload = json_decode((string) ($data['payload_json'] ?? '[]'), true);

                $service = app(MkUnitTarikKontrakService::class);
                $result = $service->tarik($mkUnit, $semester, is_array($payload) ? $payload : null);
                $service->kirimNotifikasi($result);

                if ($result['status'] === 'ok') {
                    $this->kelasDetailId = null;
                }
            });
    }

    /**
     * Semester ≥ 20251 → Sintesys; sebelumnya → Simak.
     *
     * @return 'sintesys'|'simak'
     */
    public function sumberTarikUntukSemester(?Semester $semester): string
    {
        return app(MkUnitTarikKontrakService::class)->sumberUntukSemester($semester);
    }

    public function labelSumberTarik(string $sumber): string
    {
        return app(MkUnitTarikKontrakService::class)->labelSumber($sumber);
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
}

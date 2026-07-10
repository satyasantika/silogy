<?php

namespace App\Modules\Penilaian\Filament\Pages;

use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\MK\Models\Mk;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use App\Modules\Penilaian\Policies\InputNilaiPolicy;
use App\Modules\Penilaian\Services\InputNilaiMatrixClipboardService;
use App\Modules\Penilaian\Services\PenilaianDosenService;
use App\Modules\Penilaian\Support\PenilaianMkTerpilih;
use App\Modules\Penilaian\Support\PenilaianSemesterTerpilih;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class InputNilai extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $navigationLabel = 'Input Nilai';

    protected static string|\UnitEnum|null $navigationGroup = 'Penilaian';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'penilaian/input-nilai';

    protected static ?string $title = 'Input Nilai';

    protected string $view = 'filament.modules.penilaian.pages.input-nilai';

    public ?string $mkId = null;

    public ?string $kelasMkId = null;

    /**
     * @var array<string, array<string, string|null>>
     */
    public array $nilai = [];

    /**
     * @var list<array{id: string, nim: string, nama: string}>
     */
    public array $rows = [];

    /**
     * @var list<array{id: string, label: string, asesmen: string, subcpmk: string, evaluasi: string|null}>
     */
    public array $columns = [];

    public bool $showKalkulasiBadge = false;

    public bool $penugasanBelumSelesai = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && app(InputNilaiPolicy::class)->access($user);
    }

    public function mount(): void
    {
        $kelasMkIdParam = request()->query('kelas_mk_id');
        $mkIdParam = request()->query('mk_id');

        $kelas = is_string($kelasMkIdParam)
            ? $this->kelasMkQuery(null)->whereKey($kelasMkIdParam)->first()
            : null;

        if ($kelas !== null) {
            $this->mkId = $kelas->mkUnit?->mk_id;
            PenilaianSemesterTerpilih::set($kelas->semester_id);
        } elseif (is_string($mkIdParam)) {
            $this->mkId = $mkIdParam;
        } else {
            $this->mkId = PenilaianMkTerpilih::currentId();
        }

        if ($kelas === null && $this->mkId !== null) {
            $kelas = $this->kelasMkQueryUntukMk($this->mkId, PenilaianSemesterTerpilih::currentId())->first();
        }

        if ($kelas === null) {
            $kelas = $this->kelasMkQuery(PenilaianSemesterTerpilih::currentId())->first();

            if ($kelas !== null) {
                $this->mkId = $kelas->mkUnit?->mk_id;
            }
        }

        PenilaianMkTerpilih::set($this->mkId);

        if ($kelas !== null) {
            $this->kelasMkId = $kelas->id;
            $this->loadMatrix();
        }
    }

    public function updatedKelasMkId(): void
    {
        $this->showKalkulasiBadge = false;
        $this->loadMatrix();
    }

    public function pilihKelas(string $kelasMkId): void
    {
        $kelas = $this->kelasMkQuery(null)->whereKey($kelasMkId)->first();

        if ($kelas === null) {
            return;
        }

        $this->kelasMkId = $kelas->id;
        $this->showKalkulasiBadge = false;
        $this->loadMatrix();
    }

    public function getMkTerpilihProperty(): ?Mk
    {
        if ($this->mkId === null) {
            return null;
        }

        return Mk::query()->find($this->mkId);
    }

    public function getSemesterTerpilihProperty(): string
    {
        return PenilaianSemesterTerpilih::label();
    }

    /**
     * @return list<array{id: string, kode_kelas: string, jumlah_mahasiswa: int, rata_rata: float|null, sudah_dinilai: bool}>
     */
    public function getKelasCardsProperty(): array
    {
        $mk = $this->getMkTerpilihProperty();
        $user = auth()->user();

        if ($mk === null || ! $user instanceof User) {
            return [];
        }

        return PenilaianDosenService::kelasUntukMk($mk, $user, PenilaianSemesterTerpilih::currentId())
            ->map(fn (KelasMk $kelas): array => array_merge(
                ['id' => $kelas->id],
                PenilaianDosenService::ringkasanKelas($kelas),
            ))
            ->values()
            ->all();
    }

    /**
     * @return array{jumlah_mahasiswa: int, rata_rata: float|null, sudah_dinilai: bool}
     */
    public function getRingkasanSeluruhKelasProperty(): array
    {
        $mk = $this->getMkTerpilihProperty();
        $user = auth()->user();

        if ($mk === null || ! $user instanceof User) {
            return ['jumlah_mahasiswa' => 0, 'rata_rata' => null, 'sudah_dinilai' => false];
        }

        return PenilaianDosenService::ringkasanSeluruhKelas($mk, $user, PenilaianSemesterTerpilih::currentId());
    }

    /**
     * @return Builder<KelasMk>
     */
    protected function kelasMkQueryUntukMk(string $mkId, ?string $semesterId): Builder
    {
        return $this->kelasMkQuery($semesterId)
            ->whereHas('mkUnit', fn (Builder $query): Builder => $query->where('mk_id', $mkId));
    }

    public function loadMatrix(): void
    {
        $this->nilai = [];
        $this->rows = [];
        $this->columns = [];

        if ($this->kelasMkId === null) {
            return;
        }

        $kelasMk = KelasMk::query()->find($this->kelasMkId);

        if (! $kelasMk instanceof KelasMk) {
            return;
        }

        // Dosen menilai hanya setelah koordinator MK menyelesaikan penugasan.
        $this->penugasanBelumSelesai = ! $kelasMk->penugasanSelesai();

        if ($this->penugasanBelumSelesai) {
            return;
        }

        Gate::authorize('inputNilai', $kelasMk);

        $this->columns = SubcpmkKomponenPenilaian::query()
            ->whereHas(
                'komponenPenilaian',
                fn ($query) => $query->where('kelas_mk_id', $kelasMk->id),
            )
            ->with(['komponenPenilaian.evaluasi', 'subcpmk'])
            ->get()
            ->sortBy([
                fn (SubcpmkKomponenPenilaian $skp): string => $skp->komponenPenilaian?->kode ?? '',
                fn (SubcpmkKomponenPenilaian $skp): string => $skp->subcpmk?->kode ?? '',
            ])
            ->map(fn (SubcpmkKomponenPenilaian $skp): array => [
                'id' => $skp->id,
                'asesmen' => $skp->komponenPenilaian?->nama
                    ?? $skp->komponenPenilaian?->kode
                    ?? '—',
                'subcpmk' => $skp->subcpmk?->kode ?? '—',
                'evaluasi' => $skp->komponenPenilaian?->evaluasi?->nama,
                'label' => sprintf(
                    '%s / %s',
                    $skp->komponenPenilaian?->kode ?? $skp->komponenPenilaian?->nama ?? '—',
                    $skp->subcpmk?->kode ?? '—',
                ),
            ])
            ->values()
            ->all();

        $kelasMkMahasiswas = KelasMkMahasiswa::query()
            ->where('kelas_mk_id', $kelasMk->id)
            ->with('mahasiswa')
            ->join('mahasiswas', 'mahasiswas.id', '=', 'kelas_mk_mahasiswa.mahasiswa_id')
            ->orderBy('mahasiswas.nama')
            ->select('kelas_mk_mahasiswa.*')
            ->get();

        $this->rows = $kelasMkMahasiswas
            ->map(fn (KelasMkMahasiswa $kmm): array => [
                'id' => $kmm->id,
                'nim' => $kmm->mahasiswa?->nim ?? '—',
                'nama' => $kmm->mahasiswa?->nama ?? '—',
            ])
            ->values()
            ->all();

        $skpIds = collect($this->columns)->pluck('id');
        $kmmIds = collect($this->rows)->pluck('id');

        if ($skpIds->isEmpty() || $kmmIds->isEmpty()) {
            return;
        }

        $existing = NilaiMahasiswa::query()
            ->whereIn('kelas_mk_mahasiswa_id', $kmmIds)
            ->whereIn('subcpmk_komponenpenilaian_id', $skpIds)
            ->get()
            ->groupBy('kelas_mk_mahasiswa_id');

        foreach ($this->rows as $row) {
            $this->nilai[$row['id']] = [];

            foreach ($this->columns as $column) {
                $nilai = $existing
                    ->get($row['id'])
                    ?->firstWhere('subcpmk_komponenpenilaian_id', $column['id']);

                $this->nilai[$row['id']][$column['id']] = $nilai?->nilai !== null
                    ? (string) $nilai->nilai
                    : null;
            }
        }
    }

    public function save(): void
    {
        if ($this->kelasMkId === null) {
            return;
        }

        $kelasMk = KelasMk::query()->findOrFail($this->kelasMkId);

        Gate::authorize('inputNilai', $kelasMk);

        $allowedKmmIds = KelasMkMahasiswa::query()
            ->where('kelas_mk_id', $kelasMk->id)
            ->pluck('id')
            ->all();

        $allowedSkpIds = SubcpmkKomponenPenilaian::query()
            ->whereHas(
                'komponenPenilaian',
                fn ($query) => $query->where('kelas_mk_id', $kelasMk->id),
            )
            ->pluck('id')
            ->all();

        foreach ($this->nilai as $kmmId => $cells) {
            if (! in_array($kmmId, $allowedKmmIds, true)) {
                throw ValidationException::withMessages([
                    'nilai' => 'Data mahasiswa tidak valid untuk kelas ini.',
                ]);
            }

            foreach ($cells as $skpId => $value) {
                if (! in_array($skpId, $allowedSkpIds, true)) {
                    throw ValidationException::withMessages([
                        'nilai' => 'Data komponen penilaian tidak valid untuk kelas ini.',
                    ]);
                }

                if ($value === null || $value === '') {
                    continue;
                }

                $numeric = (float) $value;

                if ($numeric < 0 || $numeric > 100) {
                    throw ValidationException::withMessages([
                        "nilai.{$kmmId}.{$skpId}" => 'Nilai harus antara 0 dan 100.',
                    ]);
                }

                NilaiMahasiswa::query()->updateOrCreate(
                    [
                        'subcpmk_komponenpenilaian_id' => $skpId,
                        'kelas_mk_mahasiswa_id' => $kmmId,
                    ],
                    [
                        'nilai' => $numeric,
                    ],
                );
            }
        }

        Notification::make()
            ->title('Tersimpan')
            ->success()
            ->send();

        $this->showKalkulasiBadge = true;
        $this->loadMatrix();
    }

    public function applyTempel(string $raw): void
    {
        if (! $this->matrixSiapClipboard()) {
            throw ValidationException::withMessages([
                'paste_data' => 'Matriks nilai belum siap untuk ditempel.',
            ]);
        }

        $kelasMk = KelasMk::query()->findOrFail($this->kelasMkId);
        Gate::authorize('inputNilai', $kelasMk);

        $result = app(InputNilaiMatrixClipboardService::class)->applyPaste(
            $raw,
            $this->rows,
            $this->columns,
            $this->nilai,
        );

        $this->nilai = $result['nilai'];

        $body = sprintf(
            '%d sel diperbarui pada %d mahasiswa. Klik Simpan untuk menyimpan ke database.',
            $result['applied_cells'],
            $result['matched_rows'],
        );

        if ($result['errors'] !== []) {
            $body .= ' Peringatan: '.implode(' ', array_slice($result['errors'], 0, 3));

            if (count($result['errors']) > 3) {
                $body .= ' …';
            }
        }

        Notification::make()
            ->title('Matriks diperbarui')
            ->body($body)
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->makeSalinNilaiAction(),
            $this->makeTempelNilaiAction(),
        ];
    }

    protected function makeSalinNilaiAction(): Action
    {
        return Action::make('salinNilai')
            ->label('Salin matriks')
            ->icon(Heroicon::OutlinedClipboard)
            ->color('gray')
            ->visible(fn (): bool => $this->matrixSiapClipboard())
            ->modalHeading('Salin matriks nilai')
            ->modalDescription('Salin ke Excel, edit nilai, lalu tempel kembali lewat tombol Tempel dari Excel.')
            ->modalSubmitActionLabel('Salin ke clipboard')
            ->modalCancelActionLabel('Tutup')
            ->schema([
                Placeholder::make('salin_petunjuk')
                    ->hiddenLabel()
                    ->content(fn (): HtmlString => new HtmlString(
                        '<p style="font-size:13px;opacity:.85;">'
                        .'Kolom: <strong>NIM</strong>, <strong>Nama</strong>, lalu tiap kolom asesmen/Sub-CPMK '
                        .'(pemisah tab, siap tempel ke Excel).</p>'
                    )),
                Textarea::make('export_text')
                    ->label('Matriks nilai')
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
                        ->body('Pilih kelas MK yang memiliki mahasiswa dan komponen penilaian.')
                        ->warning()
                        ->send();

                    return;
                }

                $this->js('navigator.clipboard.writeText('.json_encode($teks).')');

                Notification::make()
                    ->title('Disalin ke clipboard')
                    ->body('Tempel ke Excel, sesuaikan nilai, lalu impor lewat Tempel dari Excel.')
                    ->success()
                    ->send();
            });
    }

    protected function makeTempelNilaiAction(): Action
    {
        return Action::make('tempelNilai')
            ->label('Tempel dari Excel')
            ->icon(Heroicon::OutlinedClipboardDocument)
            ->color('gray')
            ->visible(fn (): bool => $this->matrixSiapClipboard())
            ->modalHeading('Tempel matriks nilai')
            ->modalDescription('Tempel blok sel dari Excel (termasuk baris header NIM/Nama).')
            ->modalSubmitActionLabel('Terapkan ke matriks')
            ->modalCancelActionLabel('Batal')
            ->schema([
                Placeholder::make('tempel_petunjuk')
                    ->hiddenLabel()
                    ->content(fn (): HtmlString => new HtmlString(
                        '<div style="font-size:13px;opacity:.85;">'
                        .'<p style="margin-bottom:8px;"><strong>Petunjuk:</strong></p>'
                        .'<ul style="margin:0;padding-left:18px;">'
                        .'<li>Gunakan format yang sama dengan hasil salin matriks (NIM, Nama, kolom nilai).</li>'
                        .'<li>Pemisah tab dari Excel didukung; pipe (<code>|</code>) juga diterima.</li>'
                        .'<li>Baris dicocokkan berdasarkan NIM; nilai kosong akan dikosongkan.</li>'
                        .'<li>Setelah diterapkan, klik <strong>Simpan</strong> pada halaman.</li>'
                        .'</ul></div>'
                    )),
                Textarea::make('paste_data')
                    ->label('Data yang ditempel')
                    ->rows(12)
                    ->required()
                    ->extraAttributes(['class' => 'font-mono text-xs']),
            ])
            ->action(function (array $data): void {
                $this->applyTempel((string) ($data['paste_data'] ?? ''));
            });
    }

    protected function teksSalinMatriks(): string
    {
        if (! $this->matrixSiapClipboard()) {
            return '';
        }

        return app(InputNilaiMatrixClipboardService::class)->exportTsv(
            $this->rows,
            $this->columns,
            $this->nilai,
        );
    }

    protected function matrixSiapClipboard(): bool
    {
        return $this->kelasMkId !== null
            && ! $this->penugasanBelumSelesai
            && $this->rows !== []
            && $this->columns !== [];
    }

    /**
     * @return Builder<KelasMk>
     */
    protected function kelasMkQuery(?string $semesterAktifId): Builder
    {
        $query = KelasMk::query()
            ->where('dosen_pengampu_id', auth()->id());

        if ($semesterAktifId !== null) {
            $query->where('semester_id', $semesterAktifId);
        }

        return $query->orderBy('kode_kelas');
    }
}

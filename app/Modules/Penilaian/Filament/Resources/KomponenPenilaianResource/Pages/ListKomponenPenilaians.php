<?php

namespace App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages;

use App\Models\User;
use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\MK\Filament\Resources\SubcpmkResource;
use App\Modules\MK\Filament\Support\Concerns\HasImporMkSemesterKonteks;
use App\Modules\MK\Filament\Support\Concerns\HasSalinAntarSemesterMassal;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Support\MkPipeline;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\Penilaian\Filament\Pages\LaporanKoordinator;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource;
use App\Modules\Penilaian\Filament\Resources\PesertaKelasResource;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Services\AsesmenImporService;
use App\Modules\Penilaian\Services\EvaluasiResolverService;
use App\Modules\Penilaian\Services\KomponenPenilaianSalinSemesterService;
use App\Modules\Penilaian\Services\NormalisasiBobotKomponenService;
use App\Modules\Penilaian\Services\RencanaEvaluasiService;
use App\Modules\Penilaian\Services\SubcpmkAsesmenPemetaanService;
use App\Modules\Penilaian\Support\NormalisasiBobotDesimal;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Field;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;

class ListKomponenPenilaians extends ListRecords
{
    use HasImporMassal;
    use HasImporMkSemesterKonteks;
    use HasSalinAntarSemesterMassal;

    protected static string $resource = KomponenPenilaianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeImporMassalAction()
                ->visible(fn (): bool => KomponenPenilaianResource::canCreate() && $this->adaKomponenPenilaianSemester()),
            CreateAction::make(),
        ];
    }

    protected function getTableEmptyStateActions(): array
    {
        return [
            $this->makeImporMassalAction()
                ->visible(fn (): bool => KomponenPenilaianResource::canCreate()),
            $this->makeIsiSubcpmkDuluAction(),
            $this->makeSalinAntarSemesterAction(),
        ];
    }

    /**
     * Muncul menggantikan "Import dari Semester Lain" ketika Sub-CPMK
     * semester tujuan belum diisi — pemetaan Sub-CPMK pada komponen
     * penilaian yang disalin baru bisa terpasang kalau Sub-CPMK-nya sudah
     * ada dulu di semester itu (lihat KomponenPenilaianSalinSemesterService).
     */
    protected function makeIsiSubcpmkDuluAction(): Action
    {
        return Action::make('isiSubcpmkDulu')
            ->label('Isi dulu Sub-CPMK')
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->color('warning')
            ->url(fn (): string => SubcpmkResource::getUrl('index'))
            ->visible(fn (): bool => $this->salinAntarSemesterMkId() !== null
                && $this->salinAntarSemesterTargetSemesterId() !== null
                && $this->salinAntarSemesterOpsiSumber() !== []
                && ! $this->salinAntarSemesterPrasyaratTerpenuhi());
    }

    protected function salinAntarSemesterPrasyaratTerpenuhi(): bool
    {
        $mkId = MkTerpilih::currentId();
        $semesterId = $this->salinAntarSemesterTargetSemesterId();

        if (blank($mkId) || blank($semesterId)) {
            return false;
        }

        return Subcpmk::query()
            ->where('semester_id', $semesterId)
            ->whereHas('mkCpmk.cpmk', fn ($query) => $query->where('mk_id', $mkId))
            ->exists();
    }

    protected function salinAntarSemesterEntitasLabel(): string
    {
        return 'komponen penilaian';
    }

    protected function salinAntarSemesterMkId(): ?string
    {
        return MkTerpilih::currentId();
    }

    protected function salinAntarSemesterTargetSemesterId(): ?string
    {
        $mkId = MkTerpilih::currentId();

        if (blank($mkId)) {
            return null;
        }

        return SemesterTerpilih::currentId($mkId) ?? SemesterTerpilih::defaultId();
    }

    protected function salinAntarSemesterResolveBaris(string $sumberSemesterId, string $mkId, string $targetSemesterId): array
    {
        return app(KomponenPenilaianSalinSemesterService::class)->resolveBaris($sumberSemesterId, $mkId, $targetSemesterId);
    }

    protected function salinAntarSemesterJalankan(array $rows, string $modeDuplikat, string $mkId, string $targetSemesterId): array
    {
        return app(KomponenPenilaianSalinSemesterService::class)->jalankan($rows, $modeDuplikat, $mkId, $targetSemesterId);
    }

    protected function salinAntarSemesterSemesterIdsDenganData(string $mkId): array
    {
        return app(KomponenPenilaianSalinSemesterService::class)->semesterIdsDenganData($mkId);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                // Banner kiri + Normalisasi Bobot kanan; tombol hanya bila total belum 100%.
                Flex::make([
                    View::make('filament.modules.penilaian.partials.bobot-dan-normalisasi')
                        ->viewData(fn (): array => [
                            'ringkasan' => $this->getTotalBobotRingkasan(),
                        ]),
                    Actions::make([
                        $this->normalisasiBobotAction(),
                    ])
                        ->alignment(Alignment::End)
                        ->verticalAlignment(VerticalAlignment::Center),
                ])
                    ->from('sm')
                    ->verticalAlignment(VerticalAlignment::Center)
                    ->extraAttributes(['style' => 'margin-top:16px;gap:12px;'])
                    ->visible(fn (): bool => $this->adaKomponenPenilaianSemester()
                        && $this->getTotalBobotRingkasan() !== null),
                View::make('filament.modules.penilaian.partials.rencana-evaluasi-table')
                    ->viewData(fn (): array => [
                        'rencana' => $this->getRencanaEvaluasi(),
                    ])
                    ->visible(fn (): bool => $this->adaKomponenPenilaianSemester()),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
                ...$this->asesmenPipelineNavComponents(),
            ]);
    }

    /**
     * Nav langkah Asesmen: back tetap ke Sub-CPMK, tapi next diganti dua
     * tombol (Laporan & Mahasiswa) yang bisa dituju langsung begitu ada
     * komponen penilaian pada semester terpilih — tidak seperti langkah
     * pipeline lain, di sini tidak boleh cuma satu next tunggal.
     *
     * @return array<int, Component>
     */
    protected function asesmenPipelineNavComponents(): array
    {
        $prev = MkPipeline::navFor('asesmen')['prev'];

        $backAction = Action::make('mkPipelineBack')
            ->label(fn (): string => '« '.($prev['label'] ?? ''))
            ->url(fn (): string => $prev['url'] ?? '#')
            ->visible(fn (): bool => $prev !== null)
            ->color('gray')
            ->button();

        $laporanAction = Action::make('mkPipelineLaporan')
            ->label('Laporan »')
            ->url(fn (): string => LaporanKoordinator::getUrl())
            ->visible(fn (): bool => $this->bolehLanjutAsesmen() && LaporanKoordinator::canAccess())
            ->color('primary')
            ->button();

        $mahasiswaAction = Action::make('mkPipelineMahasiswa')
            ->label('Mahasiswa »')
            ->url(fn (): string => PesertaKelasResource::getUrl('index'))
            ->visible(fn (): bool => $this->bolehLanjutAsesmen() && PesertaKelasResource::canAccess())
            ->color('primary')
            ->button();

        return [
            Flex::make([
                Actions::make([$backAction])->alignment(Alignment::Start),
                Actions::make([$laporanAction, $mahasiswaAction])->alignment(Alignment::End),
            ])
                ->from('sm')
                ->verticalAlignment(VerticalAlignment::Center)
                ->extraAttributes(['style' => 'margin-top:16px;gap:12px;'])
                ->key('mk-pipeline-nav'),
        ];
    }

    protected function bolehLanjutAsesmen(): bool
    {
        $mk = MkTerpilih::current();
        $user = auth()->user();

        if (! $mk instanceof Mk || ! $user instanceof User) {
            return false;
        }

        return MkPipeline::hasAsesmenDataUntukSemesterTerpilih($mk, $user);
    }

    public function normalisasiBobotAction(): Action
    {
        return Action::make('normalisasiBobot')
            ->label('Normalisasi Bobot')
            ->icon('heroicon-o-scale')
            ->color('warning')
            ->button()
            ->requiresConfirmation()
            ->modalHeading('Normalisasi bobot komponen')
            ->modalDescription(
                'Bobot tiap asesmen pada mata kuliah dan semester ini akan disesuaikan secara '
                .'proporsional lalu dibulatkan sesuai pilihan di bawah, sehingga totalnya '
                .'tepat 100%. Tindakan ini mengubah nilai bobot yang sudah tersimpan.',
            )
            ->schema([
                NormalisasiBobotDesimal::field(),
            ])
            ->modalSubmitActionLabel('Normalisasi')
            // Tampil hanya saat ada komponen dan total bobot belum tepat 100%.
            ->visible(fn (): bool => $this->bolehNormalisasiBobot())
            ->action(function (array $data): void {
                $mkId = MkTerpilih::currentId();
                $service = app(RencanaEvaluasiService::class);
                $semesterId = filled($mkId) ? $service->resolveSemesterId($mkId) : null;

                if (blank($mkId) || blank($semesterId)) {
                    Notification::make()
                        ->title('Pilih mata kuliah terlebih dahulu')
                        ->warning()
                        ->send();

                    return;
                }

                $desimal = NormalisasiBobotDesimal::dariData($data);
                $hasil = app(NormalisasiBobotKomponenService::class)
                    ->normalisasi((string) $mkId, (string) $semesterId, $desimal);

                match ($hasil['status']) {
                    'dinormalisasi' => Notification::make()
                        ->title('Bobot berhasil dinormalisasi')
                        ->body(sprintf(
                            'Total sebelumnya %s untuk %d asesmen, kini totalnya tepat 100%%.',
                            $service->formatBobot($hasil['total_sebelum']),
                            $hasil['jumlah_asesmen'],
                        ))
                        ->success()
                        ->send(),
                    'sudah_pas' => Notification::make()
                        ->title('Total bobot sudah 100%')
                        ->body('Tidak ada perubahan yang diperlukan.')
                        ->info()
                        ->send(),
                    default => Notification::make()
                        ->title('Belum ada asesmen untuk dinormalisasi')
                        ->warning()
                        ->send(),
                };
            });
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getRencanaEvaluasi(): ?array
    {
        $mkId = MkTerpilih::currentId();

        if (blank($mkId)) {
            return null;
        }

        $service = app(RencanaEvaluasiService::class);

        return $service->build($mkId, $service->resolveSemesterId($mkId));
    }

    /**
     * Card Tabel Rencana Evaluasi hanya tampil bila sudah ada komponen
     * penilaian pada MK + semester terpilih.
     */
    protected function adaKomponenPenilaianSemester(): bool
    {
        $mkId = MkTerpilih::currentId();

        if (blank($mkId)) {
            return false;
        }

        $semesterId = app(RencanaEvaluasiService::class)->resolveSemesterId($mkId);

        if (blank($semesterId)) {
            return false;
        }

        return KomponenPenilaian::query()
            ->where('mk_id', $mkId)
            ->where('semester_id', $semesterId)
            ->exists();
    }

    protected function bolehNormalisasiBobot(): bool
    {
        if (! KomponenPenilaianResource::canCreate()) {
            return false;
        }

        if (! $this->adaKomponenPenilaianSemester()) {
            return false;
        }

        $ringkasan = $this->getTotalBobotRingkasan();

        return $ringkasan !== null && ! $ringkasan['sudah_pas'];
    }

    /**
     * @return array{total: float, selisih: float, sudah_pas: bool, status: 'success'|'warning'|'danger', keterangan: string}|null
     */
    protected function getTotalBobotRingkasan(): ?array
    {
        $rencana = $this->getRencanaEvaluasi();

        if ($rencana === null) {
            return null;
        }

        return app(RencanaEvaluasiService::class)->ringkasanTotalBobot($rencana['total_bobot']);
    }

    protected function importModalHeading(): string
    {
        return 'Impor asesmen massal';
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
            KomponenPenilaianResource::scopedKoordinatorMkOptions(),
        );
    }

    protected function importColumns(): array
    {
        return [
            ['key' => 'kode_asesmen', 'label' => 'kode', 'wajib' => true],
            ['key' => 'nama_tugas', 'label' => 'nama penugasan', 'wajib' => true],
            ['key' => 'bobot_tugas', 'label' => 'bobot (%)', 'wajib' => true],
            ['key' => 'komponen_penilaian', 'label' => 'komponen', 'wajib' => true],
            ['key' => 'kode_subcpmk', 'label' => 'SubCPMK', 'wajib' => false],
        ];
    }

    protected function importHelperNote(): string
    {
        return 'Asesmen diterapkan ke semua kelas MK mata kuliah ini pada semester terpilih. '
            .'Sub-CPMK opsional; bila diisi, bobot interaksi Sub-CPMK ↔ asesmen dibagi merata (100 ÷ jumlah Sub-CPMK per tugas).';
    }

    /**
     * @return list<string|HtmlString>
     */
    protected function importInstructionsExtra(): array
    {
        return [
            new HtmlString($this->renderImportKomponenPenilaianReferensiHtml()),
        ];
    }

    protected function renderImportKomponenPenilaianReferensiHtml(): string
    {
        $evaluasis = Evaluasi::query()
            ->orderBy('kode')
            ->get(['kode', 'nama']);

        if ($evaluasis->isEmpty()) {
            return '<p class="text-xs opacity-80">Belum ada master evaluasi. Hubungi admin sistem.</p>';
        }

        $baris = $evaluasis
            ->map(fn (Evaluasi $evaluasi): string => '<tr style="border-top:1px solid rgba(128,128,128,.2);">'
                .'<td style="padding:4px 8px;font-family:ui-monospace,monospace;">'.e($evaluasi->kode).'</td>'
                .'<td style="padding:4px 8px;">'.e($evaluasi->nama).'</td>'
                .'</tr>')
            ->join('');

        return '<div>'
            .'<p class="mb-1.5">Kolom <strong>komponen</strong>: isi <strong>kode</strong> atau <strong>nama</strong> '
            .'dari master evaluasi berikut (pencocokan tidak peka huruf besar/kecil).</p>'
            .'<div style="overflow-x:auto;margin-top:8px;">'
            .'<table style="width:100%;max-width:480px;font-size:12px;border-collapse:collapse;">'
            .'<thead><tr style="text-align:left;border-bottom:1px solid rgba(128,128,128,.35);">'
            .'<th style="padding:4px 8px;">Kode</th>'
            .'<th style="padding:4px 8px;">Nama</th>'
            .'</tr></thead>'
            .'<tbody>'.$baris.'</tbody>'
            .'</table>'
            .'</div>'
            .'</div>';
    }

    /**
     * @return list<string>
     */
    protected function importExampleRows(): array
    {
        return [
            "Asesmen01\tKuis Konseptual dan Ringkasan Tertulis Terstruktur\t8\tQuiz\tSubCPMK01.1",
            "Asesmen01\tKuis Konseptual dan Ringkasan Tertulis Terstruktur\t8\tQuiz\tSubCPMK01.2",
            "Asesmen02\tUTS Teori\t42\tUjian Tengah Semester\t",
        ];
    }

    protected function resolveImportRow(array $data, array $context): array
    {
        $validasiKonteks = $this->validasiKonteksImporMkSemester($context);

        if ($validasiKonteks !== null) {
            return $validasiKonteks;
        }

        $context = $this->normalizeImportContext($context);

        if (blank(trim($data['kode_asesmen'] ?? ''))) {
            return ['status' => 'invalid', 'keterangan' => 'Kode wajib diisi.'];
        }

        if (blank(trim($data['nama_tugas'] ?? ''))) {
            return ['status' => 'invalid', 'keterangan' => 'Nama penugasan wajib diisi.'];
        }

        if ($data['bobot_tugas'] === '' || ! is_numeric($data['bobot_tugas'])) {
            return ['status' => 'invalid', 'keterangan' => 'Bobot harus berupa angka.'];
        }

        $bobot = (float) $data['bobot_tugas'];

        if ($bobot < 0 || $bobot > 100) {
            return ['status' => 'invalid', 'keterangan' => 'Bobot harus antara 0 dan 100.'];
        }

        $validasiEvaluasi = EvaluasiResolverService::validasi($data['komponen_penilaian'] ?? '');

        if (! $validasiEvaluasi['valid']) {
            return ['status' => 'invalid', 'keterangan' => $validasiEvaluasi['keterangan']];
        }

        $validasiSubcpmk = SubcpmkAsesmenPemetaanService::validasiKodeSubcpmk(
            $data['kode_subcpmk'] ?? '',
            (string) $context['import_mk_id'],
            (string) $context['import_semester_id'],
        );

        if (! $validasiSubcpmk['valid']) {
            return ['status' => 'invalid', 'keterangan' => $validasiSubcpmk['keterangan']];
        }

        return AsesmenImporService::resolveBaris(
            $data,
            (string) $context['import_mk_id'],
            (string) $context['import_semester_id'],
        );
    }

    protected function createImportRow(array $data, array $context): void
    {
        $context = $this->normalizeImportContext($context);
        $mkId = (string) $context['import_mk_id'];
        $semesterId = (string) $context['import_semester_id'];

        $komponen = AsesmenImporService::buatAtauPerbaruiKomponen($data, $mkId, $semesterId);

        AsesmenImporService::terapkanPemetaanSubcpmk($komponen, $data, $mkId, $semesterId);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        $context = $this->normalizeImportContext($context);
        $kodeAsesmen = KomponenPenilaian::query()->whereKey($existingId)->value('kode');

        if (blank($kodeAsesmen)) {
            return;
        }

        AsesmenImporService::perbarui(
            (string) $kodeAsesmen,
            $data,
            (string) $context['import_mk_id'],
            (string) $context['import_semester_id'],
        );
    }
}

<?php

namespace App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages;

use App\Modules\MK\Filament\Support\Concerns\HasImporMkSemesterKonteks;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\MK\Support\PenawaranMkScope;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Services\AsesmenImporService;
use App\Modules\Penilaian\Services\EvaluasiResolverService;
use App\Modules\Penilaian\Services\NormalisasiBobotKomponenService;
use App\Modules\Penilaian\Services\RencanaEvaluasiService;
use App\Modules\Penilaian\Services\SubcpmkAsesmenPemetaanService;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Field;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;

class ListKomponenPenilaians extends ListRecords
{
    use HasImporMassal;
    use HasImporMkSemesterKonteks;

    protected static string $resource = KomponenPenilaianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeImporMassalAction()
                ->visible(fn (): bool => KomponenPenilaianResource::canCreate()),
            CreateAction::make(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                View::make('filament.modules.penilaian.partials.total-bobot-banner')
                    ->viewData(fn (): array => [
                        'ringkasan' => $this->getTotalBobotRingkasan(),
                    ])
                    ->visible(fn (): bool => $this->getTotalBobotRingkasan() !== null),
                EmbeddedTable::make(),
                Actions::make([$this->normalisasiBobotAction()])
                    ->alignment(Alignment::Start)
                    ->key('normalisasi-bobot-actions'),
                View::make('filament.modules.penilaian.partials.rencana-evaluasi-table')
                    ->viewData(fn (): array => [
                        'rencana' => $this->getRencanaEvaluasi(),
                    ])
                    ->visible(fn (): bool => $this->getRencanaEvaluasi() !== null),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    protected function normalisasiBobotAction(): Action
    {
        return Action::make('normalisasiBobot')
            ->label('Normalisasi Bobot')
            ->icon('heroicon-o-scale')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Normalisasi bobot komponen')
            ->modalDescription(
                'Bobot tiap asesmen pada mata kuliah dan semester ini akan disesuaikan secara '
                .'proporsional lalu dibulatkan ke bilangan bulat terdekat, sehingga totalnya '
                .'tepat 100%. Tindakan ini mengubah nilai bobot yang sudah tersimpan.',
            )
            ->modalSubmitActionLabel('Normalisasi')
            ->visible(fn (): bool => KomponenPenilaianResource::canCreate()
                && ($ringkasan = $this->getTotalBobotRingkasan()) !== null
                && ! $ringkasan['sudah_pas'])
            ->action(function (): void {
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

                $hasil = app(NormalisasiBobotKomponenService::class)
                    ->normalisasi((string) $mkId, (string) $semesterId);

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
            'Asesmen01|Kuis Konseptual dan Ringkasan Tertulis Terstruktur|8|Quiz|SubCPMK01.1',
            'Asesmen01|Kuis Konseptual dan Ringkasan Tertulis Terstruktur|8|Quiz|SubCPMK01.2',
            'Asesmen02|UTS Teori|42|Ujian Tengah Semester|',
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

        if (PenawaranMkScope::kelasMkUntukMkSemester(
            (string) $context['import_mk_id'],
            (string) $context['import_semester_id'],
        )->isEmpty()) {
            return ['status' => 'invalid', 'keterangan' => 'Belum ada kelas MK untuk mata kuliah dan semester ini.'];
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

<?php

namespace App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages;

use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Filament\Support\Concerns\HasImporMkSemesterKonteks;
use App\Modules\MK\Support\PenawaranMkScope;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Services\AsesmenImporService;
use App\Modules\Penilaian\Services\EvaluasiResolverService;
use App\Modules\Penilaian\Services\SubcpmkAsesmenPemetaanService;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Field;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
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
            ['key' => 'kode_asesmen', 'label' => 'kode asesmen', 'wajib' => true],
            ['key' => 'nama_tugas', 'label' => 'nama tugas', 'wajib' => true],
            ['key' => 'bobot_tugas', 'label' => 'bobot tugas (%)', 'wajib' => true],
            ['key' => 'komponen_penilaian', 'label' => 'komponen penilaian', 'wajib' => true],
            ['key' => 'kode_subcpmk', 'label' => 'kode Sub-CPMK terpetakan', 'wajib' => false],
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
            .'<p class="mb-1.5">Kolom <strong>komponen penilaian</strong>: isi <strong>kode</strong> atau <strong>nama</strong> '
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
            return ['status' => 'invalid', 'keterangan' => 'Kode asesmen wajib diisi.'];
        }

        if (blank(trim($data['nama_tugas'] ?? ''))) {
            return ['status' => 'invalid', 'keterangan' => 'Nama tugas wajib diisi.'];
        }

        if ($data['bobot_tugas'] === '' || ! is_numeric($data['bobot_tugas'])) {
            return ['status' => 'invalid', 'keterangan' => 'Bobot tugas harus berupa angka.'];
        }

        $bobot = (float) $data['bobot_tugas'];

        if ($bobot < 0 || $bobot > 100) {
            return ['status' => 'invalid', 'keterangan' => 'Bobot tugas harus antara 0 dan 100.'];
        }

        $validasiEvaluasi = EvaluasiResolverService::validasi($data['komponen_penilaian'] ?? '');

        if (! $validasiEvaluasi['valid']) {
            return ['status' => 'invalid', 'keterangan' => $validasiEvaluasi['keterangan']];
        }

        $kelasMks = PenawaranMkScope::kelasMkUntukMkSemester(
            (string) $context['import_mk_id'],
            (string) $context['import_semester_id'],
        );

        $kelasMk = $kelasMks->first();

        if (! $kelasMk instanceof KelasMk) {
            return ['status' => 'invalid', 'keterangan' => 'Belum ada kelas MK untuk mata kuliah dan semester ini.'];
        }

        $validasiSubcpmk = SubcpmkAsesmenPemetaanService::validasiKodeSubcpmk(
            $data['kode_subcpmk'] ?? '',
            $kelasMk,
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
        $kelasMks = PenawaranMkScope::kelasMkUntukMkSemester(
            (string) $context['import_mk_id'],
            (string) $context['import_semester_id'],
        );
        $kelasMk = $kelasMks->firstOrFail();

        $komponens = AsesmenImporService::buatAtauPerbaruiSemuaKelas(
            $data,
            (string) $context['import_mk_id'],
            (string) $context['import_semester_id'],
        );

        AsesmenImporService::terapkanPemetaanSubcpmkSemuaKelas($komponens, $data, $kelasMk);
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

        AsesmenImporService::perbaruiSemuaKelas(
            (string) $kodeAsesmen,
            $data,
            (string) $context['import_mk_id'],
            (string) $context['import_semester_id'],
        );
    }
}

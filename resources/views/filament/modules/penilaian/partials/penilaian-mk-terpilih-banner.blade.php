{{-- Banner MK terpilih untuk Input Nilai (dosen): gradient hijau SILOGY,
     data dari PenilaianMkTerpilih + semester penilaian — bukan MkTerpilih korma.
     $sebagaiHeaderPanel: flat ke panel kartu kelas (tanpa margin, radius hanya atas via overflow parent). --}}
@php
    use App\Modules\MK\Services\MataKuliahKoordinatorService;
    use App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource;

    $mk = $mk ?? $this->mkTerpilih;
    $semesterLabel = $semesterLabel ?? $this->semesterTerpilih;
    $gantiUrl = $gantiUrl ?? PenilaianDosenResource::getUrl('index');
    $sebagaiHeaderPanel = (bool) ($sebagaiHeaderPanel ?? false);
    $bannerShell = $sebagaiHeaderPanel
        ? 'border-radius:0;margin:0;padding:14px 16px;color:#fff;background:linear-gradient(120deg,#007000 0%,#009900 55%,#0b3914 100%);'
        : 'margin-bottom:16px;border-radius:14px;padding:14px 16px;color:#fff;background:linear-gradient(120deg,#007000 0%,#009900 55%,#0b3914 100%);box-shadow:0 10px 24px -16px rgba(11,57,20,.85);';
    $warningShell = $sebagaiHeaderPanel
        ? 'border-radius:0;margin:0;padding:14px 16px;border:none;border-bottom:1px solid #fcd34d;'
        : 'margin-bottom:16px;border-radius:14px;padding:14px 16px;border:1px solid #fcd34d;';
@endphp

@if ($mk)
    @php
        $sks = (int) $mk->total_sks;
        $unitMeta = MataKuliahKoordinatorService::labelUnitPadaCard($mk);
        $kodeMk = $mk->mkUnits->first()?->kode;
        $metaSekunder = collect([$kodeMk, $unitMeta !== '—' ? $unitMeta : null])
            ->filter(fn (?string $bagian): bool => filled($bagian))
            ->implode(' · ');
    @endphp

    <div
        style="{{ $bannerShell }}"
        data-silogy="penilaian-mk-terpilih-banner"
        role="banner"
        aria-label="Mata kuliah terpilih"
    >
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
            <div style="flex:0 0 auto;display:flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.18);">
                @svg('heroicon-o-book-open', ['style' => 'width:24px;height:24px;'])
            </div>

            <div style="flex:1 1 220px;min-width:0;">
                <div style="font-size:10.5px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;opacity:.85;">
                    Mata kuliah terpilih
                </div>
                <div style="font-size:15px;font-weight:700;line-height:1.35;word-break:break-word;">
                    {{ $mk->nama }}
                </div>
                @if ($metaSekunder)
                    <div style="margin-top:2px;font-size:12px;opacity:.85;">{{ $metaSekunder }}</div>
                @endif
            </div>

            <div style="flex:0 0 auto;display:flex;align-items:center;flex-wrap:wrap;gap:6px;">
                <span style="padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;background:rgba(255,255,255,.2);">
                    {{ $sks }} SKS
                </span>
                <span style="padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;background:rgba(255,255,255,.2);">
                    Semester: {{ $semesterLabel }}
                </span>
                <a
                    href="{{ $gantiUrl }}"
                    style="display:inline-flex;align-items:center;gap:6px;margin-inline-start:4px;padding:5px 12px;border-radius:8px;font-size:12px;font-weight:600;line-height:1.3;text-decoration:none;background:#f3f4f6;color:#1f2937;box-shadow:0 1px 2px rgba(0,0,0,.08);"
                >
                    @svg('heroicon-o-arrows-right-left', ['style' => 'width:14px;height:14px;'])
                    Ganti
                </a>
            </div>
        </div>
    </div>
@else
    <div class="silogy-tone-warning" style="{{ $warningShell }}" data-silogy="penilaian-mk-terpilih-banner" role="banner">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <div style="flex:0 0 auto;display:flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:12px;background:rgba(180,83,9,.12);">
                @svg('heroicon-o-exclamation-triangle', ['style' => 'width:22px;height:22px;'])
            </div>
            <div style="flex:1 1 220px;min-width:0;font-size:13px;line-height:1.55;">
                <div style="font-weight:700;">Belum ada mata kuliah terpilih.</div>
                <div style="opacity:.9;">Pilih mata kuliah lewat halaman Penilaian, lalu kembali ke sini.</div>
            </div>
            <div style="flex:0 0 auto;">
                <a
                    href="{{ $gantiUrl }}"
                    style="display:inline-flex;align-items:center;padding:6px 12px;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;background:#f59e0b;color:#111827;"
                >
                    Pilih mata kuliah
                </a>
            </div>
        </div>
    </div>
@endif

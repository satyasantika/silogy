{{-- Gaya selaras banner kurikulum yang dikerjakan (Profil/CPL/BoK):
     gradient hijau, nama MK sebagai fokus utama; kurikulum & unit sekunder.
     $sebagaiHeaderPanel: flat ke panel kartu; $catatan di mode panel jadi pelengkap body parent. --}}
@php
    use App\Modules\Kurikulum\Support\KurikulumTerpilih;
    use App\Modules\MK\Services\MataKuliahKoordinatorService;
    use App\Modules\MK\Support\MkTerpilih;

    $gantiUrl = $gantiUrl ?? \App\Modules\MK\Filament\Resources\MataKuliahKoordinatorResource::getUrl('index');
    $mk = $mk ?? MkTerpilih::current();
    $kurikulum = $kurikulum ?? KurikulumTerpilih::current();
    $catatan = $catatan ?? null;
    $sebagaiHeaderPanel = (bool) ($sebagaiHeaderPanel ?? false);
    $tampilCatatanDiStrip = filled($catatan) && ! $sebagaiHeaderPanel;
    $bannerShell = $sebagaiHeaderPanel
        ? 'border-radius:0;margin:0;padding:14px 16px;color:#fff;background:linear-gradient(120deg,#007000 0%,#009900 55%,#0b3914 100%);'
        : 'border-radius:14px;padding:14px 16px;color:#fff;background:linear-gradient(120deg,#007000 0%,#009900 55%,#0b3914 100%);box-shadow:0 10px 24px -16px rgba(11,57,20,.85);';
    $warningShell = $sebagaiHeaderPanel
        ? 'border-radius:0;margin:0;padding:14px 16px;border:none;border-bottom:1px solid #fcd34d;'
        : 'border-radius:14px;padding:14px 16px;border:1px solid #fcd34d;';
@endphp

@if ($mk)
    @php
        $mkLabel = MkTerpilih::label($mk, $kurikulum);
        $sks = (int) $mk->total_sks;
        $kurikulumMeta = $kurikulum
            ? $kurikulum->nama.(filled($kurikulum->tahun) ? ' · '.$kurikulum->tahun : '')
            : null;
        $unitMeta = MataKuliahKoordinatorService::labelUnitPadaCard($mk);
        $metaSekunder = collect([$kurikulumMeta, $unitMeta])
            ->filter(fn (?string $bagian): bool => filled($bagian) && $bagian !== '—')
            ->implode(' · ');
    @endphp

    <div @if ($sebagaiHeaderPanel) data-silogy="banner-mk-header-panel" @endif style="{{ $bannerShell }}">
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
            <div style="flex:0 0 auto;display:flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.18);">
                @svg('heroicon-o-book-open', ['style' => 'width:24px;height:24px;'])
            </div>

            <div style="flex:1 1 220px;min-width:0;">
                <div style="font-size:10.5px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;opacity:.85;">
                    Mata kuliah yang dikerjakan
                </div>
                <div style="font-size:15px;font-weight:700;line-height:1.35;word-break:break-word;">
                    {{ $mkLabel }}
                </div>
                @if ($metaSekunder)
                    <div style="margin-top:2px;font-size:12px;opacity:.85;">{{ $metaSekunder }}</div>
                @endif
            </div>

            <div style="flex:0 0 auto;display:flex;align-items:center;flex-wrap:wrap;gap:6px;">
                <span style="padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;background:rgba(255,255,255,.2);">
                    {{ $sks }} SKS
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

        @if ($tampilCatatanDiStrip)
            <div style="margin-top:10px;padding-top:8px;border-top:1px solid rgba(255,255,255,.25);font-size:12px;line-height:1.5;opacity:.95;">
                {{ $catatan }}
            </div>
        @endif
    </div>
@else
    <div class="silogy-tone-warning" @if ($sebagaiHeaderPanel) data-silogy="banner-mk-header-panel" @endif style="{{ $warningShell }}">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <div style="flex:0 0 auto;display:flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:12px;background:rgba(180,83,9,.12);">
                @svg('heroicon-o-exclamation-triangle', ['style' => 'width:22px;height:22px;'])
            </div>
            <div style="flex:1 1 220px;min-width:0;font-size:13px;line-height:1.55;">
                <div style="font-weight:700;">Belum ada mata kuliah yang dikerjakan.</div>
                <div style="opacity:.9;">Pilih mata kuliah lewat halaman Mata Kuliah, lalu kembali ke sini.</div>
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

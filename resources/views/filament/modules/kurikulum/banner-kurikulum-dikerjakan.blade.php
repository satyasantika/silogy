{{-- Gaya ditulis inline supaya banner tetap tampil utuh baik di halaman
     maupun di dalam modal aksi, tanpa bergantung pada kelas Tailwind yang
     belum tentu ikut terkompilasi.

     $aksi berisi tombol Ganti/Pilih kurikulum bila banner dipakai di halaman;
     pada modal slot itu dibiarkan kosong. --}}
@php($aksi = $aksi ?? null)
@php($arahan = $arahan ?? null)

@if ($kurikulum)
    <div style="border-radius:14px;padding:14px 16px;color:#fff;background:linear-gradient(120deg,#1d4ed8 0%,#4338ca 55%,#6d28d9 100%);box-shadow:0 10px 24px -16px rgba(30,64,175,.9);">
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
            <div style="flex:0 0 auto;display:flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.18);">
                @svg('heroicon-o-academic-cap', ['style' => 'width:24px;height:24px;'])
            </div>

            <div style="flex:1 1 220px;min-width:0;">
                <div style="font-size:10.5px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;opacity:.85;">
                    Kurikulum yang dikerjakan
                </div>
                <div style="font-size:15px;font-weight:700;line-height:1.35;word-break:break-word;">
                    {{ $kurikulum->nama }}
                </div>
                @if ($hierarki)
                    <div style="margin-top:2px;font-size:12px;opacity:.85;">{{ $hierarki }}</div>
                @endif
            </div>

            <div style="flex:0 0 auto;display:flex;align-items:center;flex-wrap:wrap;gap:6px;">
                <span style="padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;background:rgba(255,255,255,.2);">
                    Tahun {{ $kurikulum->tahun }}
                </span>
                <span style="padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;background:{{ $kurikulum->is_active ? 'rgba(34,197,94,.95)' : 'rgba(255,255,255,.2)' }};">
                    {{ $kurikulum->is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
                @if ($aksi)
                    <div class="silogy-kurikulum-banner-ganti" style="margin-inline-start:4px;">{{ $aksi }}</div>
                @endif
            </div>
        </div>

        @if ($catatan)
            <div style="margin-top:10px;padding-top:8px;border-top:1px solid rgba(255,255,255,.25);font-size:12px;line-height:1.5;opacity:.95;">
                {{ $catatan }}
            </div>
        @endif
    </div>
@else
    <div style="border-radius:14px;padding:14px 16px;background:#fffbeb;border:1px solid #fcd34d;color:#92400e;">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <div style="flex:0 0 auto;display:flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:12px;background:rgba(180,83,9,.12);">
                @svg('heroicon-o-exclamation-triangle', ['style' => 'width:22px;height:22px;'])
            </div>
            <div style="flex:1 1 220px;min-width:0;font-size:13px;line-height:1.55;">
                <div style="font-weight:700;">{{ $peringatan }}</div>
                @if ($arahan)
                    <div style="opacity:.9;">{{ $arahan }}</div>
                @endif
            </div>
            @if ($aksi)
                <div class="silogy-kurikulum-banner-ganti" style="flex:0 0 auto;">{{ $aksi }}</div>
            @endif
        </div>
    </div>
@endif

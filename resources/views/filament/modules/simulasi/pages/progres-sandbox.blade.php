@php
    /** @var array<string, mixed> $p */
    $format = fn (?float $d): string => $d === null ? '' : ($d < 1 ? '< 1 dtk' : number_format($d, $d < 10 ? 1 : 0, ',', '.').' dtk');
    $mmss = sprintf('%02d:%02d', intdiv($p['berlalu'], 60), $p['berlalu'] % 60);
    $warna = $p['gagal'] ? '#c1121f' : ($p['selesai'] ? '#15803d' : '#b45309');
    $banyak = ($p['ruang_total'] ?? 1) > 1;
    $judul = $banyak ? 'Ruang ke-'.$p['ruang_ke'].' dari '.$p['ruang_total'] : 'Ruang simulasi';
    $semuaNol = collect($p['cacah'])->sum() === 0;
@endphp

<div @if ($p['berjalan']) wire:poll.1s @endif
     data-progres-sandbox role="status" aria-live="polite"
     style="display:flex;flex-direction:column;gap:18px;">

    <style>
        @keyframes sim-putar { to { transform: rotate(360deg); } }
        .sim-putar { animation: sim-putar .9s linear infinite; transform-origin: 50% 50%; }
        .sim-denyut { animation: sim-denyut 1.4s ease-in-out infinite; }
        @keyframes sim-denyut { 50% { opacity: .45; } }
        @media (prefers-reduced-motion: reduce) { .sim-putar, .sim-denyut { animation: none; } }
    </style>

    {{-- Kepala: jenis, kode, waktu --}}
    <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;">
        <div>
            <p style="font-weight:700;font-size:15px;">{{ $judul }}</p>
            <p style="font-size:12px;opacity:.7;">
                @if (! empty($p['pin']))
                    Token <span style="font-family:ui-monospace,Menlo,monospace;font-weight:700;letter-spacing:.12em;">{{ $p['pin'] }}</span> ·
                @endif
                Kode <span style="font-family:ui-monospace,Menlo,monospace;">{{ $p['kode'] }}</span>
                @if ($banyak)
                    · {{ $p['ruang_selesai'] }} dari {{ $p['ruang_total'] }} ruang selesai
                @endif
            </p>
        </div>
        <div style="text-align:right;">
            <p style="font-size:12px;opacity:.7;">{{ $p['berjalan'] ? 'Sudah berjalan' : 'Total waktu' }}</p>
            <p style="font-weight:700;font-size:18px;font-variant-numeric:tabular-nums;">{{ $mmss }}</p>
        </div>
    </div>

    {{-- Bilah progres --}}
    <div>
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px;">
            <span style="font-weight:600;">
                @if ($p['gagal'])
                    Berhenti di tahap {{ min($p['tahap_selesai'] + 1, $p['tahap_total']) }} dari {{ $p['tahap_total'] }}
                @elseif ($p['selesai'])
                    Semua {{ $p['tahap_total'] }} tahap selesai
                @else
                    Tahap {{ min($p['tahap_selesai'] + 1, $p['tahap_total']) }} dari {{ $p['tahap_total'] }}
                @endif
            </span>
            <span style="font-variant-numeric:tabular-nums;font-weight:700;">{{ $p['persen'] }}%</span>
        </div>
        <div role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $p['persen'] }}"
             style="height:10px;border-radius:999px;background:rgba(128,128,128,.2);overflow:hidden;">
            <div style="height:100%;width:{{ $p['persen'] }}%;background:{{ $warna }};border-radius:999px;transition:width .5s ease;"></div>
        </div>
    </div>

    {{-- Daftar tahap --}}
    <ol style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;">
        @foreach ($p['tahap'] as $i => $t)
            @php
                $s = $t['state'];
                $ikonWarna = match ($s) { 'selesai' => '#15803d', 'berjalan' => '#b45309', 'gagal' => '#c1121f', default => 'rgba(128,128,128,.55)' };
            @endphp
            <li style="display:flex;gap:12px;align-items:flex-start;padding:8px 0;{{ $loop->last ? '' : 'border-bottom:1px solid rgba(128,128,128,.14);' }}{{ $s === 'menunggu' ? 'opacity:.6;' : '' }}">
                <span style="flex:none;width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;color:{{ $ikonWarna }};">
                    @if ($s === 'selesai')
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="9" fill="currentColor" opacity=".15"/><path d="M5.5 10.5l3 3 6-6.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    @elseif ($s === 'berjalan')
                        <svg class="sim-putar" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2.5" opacity=".25"/><path d="M10 2a8 8 0 0 1 8 8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
                    @elseif ($s === 'gagal')
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="9" fill="currentColor" opacity=".15"/><path d="M6.5 6.5l7 7M13.5 6.5l-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    @else
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.5"/></svg>
                    @endif
                </span>
                <span style="flex:1;min-width:0;">
                    <span style="display:block;font-size:13.5px;font-weight:{{ $s === 'berjalan' || $s === 'gagal' ? '700' : '600' }};{{ $s === 'berjalan' ? 'color:#b45309;' : '' }}{{ $s === 'gagal' ? 'color:#c1121f;' : '' }}">
                        {{ $i + 1 }}. {{ $t['label'] }}
                    </span>
                    <span style="display:block;font-size:12px;opacity:.72;line-height:1.5;">{{ $t['keterangan'] }}</span>
                </span>
                <span style="flex:none;font-size:12px;font-variant-numeric:tabular-nums;opacity:.75;{{ $s === 'berjalan' ? 'font-weight:700;color:#b45309;opacity:1;' : '' }}">
                    @if ($s === 'berjalan')
                        <span class="sim-denyut">{{ $format($t['detik']) }}</span>
                    @elseif ($s === 'selesai')
                        {{ $format($t['detik']) }}
                    @endif
                </span>
            </li>
        @endforeach
    </ol>

    {{-- Hitungan langsung --}}
    @if (! $p['gagal'])
        <div>
            <p style="font-size:12px;opacity:.7;margin-bottom:8px;">Data yang sudah terbentuk{{ $p['berjalan'] ? ' (diperbarui langsung)' : '' }}</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(104px,1fr));gap:8px;">
                @foreach ($p['cacah'] as $nama => $jumlah)
                    <div style="border:1px solid rgba(128,128,128,.25);border-radius:10px;padding:8px 10px;{{ $jumlah === 0 ? 'opacity:.55;' : '' }}">
                        <p style="font-size:11px;opacity:.75;">{{ $nama }}</p>
                        <p style="font-size:17px;font-weight:700;font-variant-numeric:tabular-nums;">{{ number_format($jumlah, 0, ',', '.') }}</p>
                    </div>
                @endforeach
            </div>
            @if ($p['mode'] === 'kosong' && $p['selesai'])
                <p style="font-size:12px;opacity:.7;margin-top:8px;">
                    Kurikulum, CPL, MK, kelas, dan nilai sengaja 0: pada ruang simulasi, peserta yang mengisinya sendiri.
                </p>
            @endif
        </div>
    @endif

    {{-- Keadaan akhir / catatan --}}
    @if ($p['gagal'])
        <div style="border:1px solid #c1121f;background:rgba(193,18,31,.08);border-radius:10px;padding:12px 14px;">
            <p style="font-weight:700;color:#c1121f;margin-bottom:4px;">Pembangunan gagal</p>
            <p style="font-size:13px;line-height:1.6;word-break:break-word;font-family:ui-monospace,Menlo,monospace;">{{ $p['galat'] }}</p>
            <p style="font-size:12px;opacity:.8;margin-top:8px;line-height:1.6;">
                Ruang setengah jadi sudah dibongkar otomatis, jadi tidak ada sisa data. Tutup jendela ini lalu coba lagi.
                Bila galat yang sama berulang, salin pesan di atas untuk penelusuran.
            </p>
        </div>
    @elseif ($p['selesai'])
        <div style="border:1px solid #15803d;background:rgba(21,128,61,.08);border-radius:10px;padding:12px 14px;">
            <p style="font-weight:700;color:#15803d;margin-bottom:4px;">{{ $banyak ? 'Semua ruang siap dipakai' : 'Ruang siap dipakai' }}</p>
            <p style="font-size:12.5px;line-height:1.6;opacity:.85;">
                @if ($banyak)
                    Token tiap ruang ada di daftar <strong>Ruang</strong> pada halaman ini. Bagikan satu token per kelompok enam peserta.
                @elseif (! empty($p['pin']))
                    Bagikan token <span style="font-family:ui-monospace,Menlo,monospace;font-weight:700;letter-spacing:.12em;">{{ $p['pin'] }}</span>
                    kepada peserta. Mereka membuka halaman <strong>/ruang</strong>, memasukkan token, lalu memilih satu peran.
                @endif
                Data inti tidak tersentuh.
            </p>
        </div>
    @elseif ($p['macet'])
        <div style="border:1px solid #b45309;background:rgba(180,83,9,.08);border-radius:10px;padding:12px 14px;">
            <p style="font-weight:700;color:#b45309;margin-bottom:4px;">Tidak ada kemajuan lebih dari 2 menit</p>
            <p style="font-size:12.5px;line-height:1.6;opacity:.85;">
                Proses di server mungkin terhenti (batas memori atau waktu). Ruang ini akan disapu otomatis bila tetap macet
                lebih dari 30 menit, atau hapus sekarang lewat daftar di halaman ini.
            </p>
        </div>
    @else
        <p style="font-size:12.5px;line-height:1.6;opacity:.75;">
            Proses berjalan di server. Jendela ini boleh ditutup: pembangunan tetap selesai dan ruang muncul di daftar.
        </p>
    @endif
</div>

@php
    /** @var list<array{judul: string, rincian: string, nama: string, peran: string, langkah: string}> $butir */
    $adaPeranLain = collect($butir)->contains(fn ($b) => $b['peran'] !== $peranAktif);
    $judulKotak = $adaPeranLain
        ? 'Belum bisa dilanjutkan — menunggu langkah peran lain'
        : 'Catatan urutan pengisian — lengkapi dulu langkah berikut';
@endphp

<div data-syarat-hulu role="note"
     style="border:1px solid rgba(180,83,9,.45);border-left:5px solid #b45309;background:rgba(180,83,9,.07);border-radius:10px;padding:14px 16px;margin-bottom:16px;">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true" style="flex:none;color:#b45309;">
            <circle cx="10" cy="10" r="9" fill="currentColor" opacity=".15"/>
            <path d="M10 5.5v5.2M10 13.6v.1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <p style="font-weight:700;font-size:14px;color:#b45309;">{{ $judulKotak }}</p>
    </div>

    <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:12px;">
        @foreach ($butir as $b)
            @php($milikSaya = $b['peran'] === $peranAktif)
            <li style="{{ $loop->first ? '' : 'border-top:1px solid rgba(128,128,128,.2);padding-top:12px;' }}">
                <p style="font-weight:700;font-size:13.5px;">{{ $b['judul'] }}</p>
                <p style="font-size:12.5px;line-height:1.6;opacity:.85;margin-top:2px;">{{ $b['rincian'] }}</p>
                <div style="display:flex;flex-wrap:wrap;gap:8px 14px;align-items:center;margin-top:8px;font-size:12px;">
                    <span style="display:inline-flex;align-items:center;gap:6px;">
                        <span style="opacity:.7;">Ditangani oleh</span>
                        <span style="font-weight:700;border-radius:999px;padding:2px 10px;background:{{ $milikSaya ? 'rgba(21,128,61,.15)' : 'rgba(180,83,9,.18)' }};color:{{ $milikSaya ? '#15803d' : '#b45309' }};">
                            {{ $b['nama'] }}{{ $milikSaya ? ' (peran Anda saat ini)' : '' }}
                        </span>
                    </span>
                    <span style="opacity:.85;"><strong>Caranya:</strong> {{ $b['langkah'] }}</span>
                </div>
            </li>
        @endforeach
    </ul>

    <p style="font-size:11.5px;opacity:.65;margin-top:12px;line-height:1.55;">
        Keterangan ini hanya tampil di ruang latihan dan hilang sendiri setelah syaratnya terpenuhi.
        Untuk mencoba peran lain, buka peran itu di tab baru dari halaman panduan (satu tab per peran).
        Ingin langsung melihat hasil akhirnya? Pilih “Lihat contoh terisi”.
    </p>
</div>

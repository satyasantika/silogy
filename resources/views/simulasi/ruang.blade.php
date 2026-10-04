@extends('layouts.publik')

@section('judul', 'Ruang simulasi — SILOGY')
@section('deskripsi', 'Masukkan token ruang dari fasilitator, lalu pilih peran yang akan Anda perankan.')

@push('gaya')
    @include('panduan.partials.gaya-panduan')
    <style>
        .rs-token { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-top: 14px; }
        .rs-token input {
            font: 700 1.4rem ui-monospace, Menlo, monospace; letter-spacing: .35em; text-transform: uppercase;
            width: 11ch; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--c-card-border2);
            background: var(--c-card, transparent); color: var(--c-text);
        }
        .rs-peran { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 14px; margin-top: 18px; }
        .rs-kartu { border: 1px solid var(--c-card-border2); border-radius: 14px; padding: 16px; display: flex; flex-direction: column; gap: 8px; }
        .rs-kartu.rs-terisi { opacity: .55; }
        .rs-kartu h3 { margin: 0; font-size: 1.02rem; }
        .rs-kartu p { margin: 0; color: var(--c-muted); font-size: .88rem; line-height: 1.55; }
        .rs-kartu form { margin-top: auto; }
        .rs-lencana { font-size: .78rem; font-weight: 700; color: var(--c-muted); }
        .rs-tombol-mati { cursor: not-allowed; }
    </style>
@endpush

@section('konten')
<section class="pd-hero">
    <div class="pd-wrap">
        <a class="pd-kembali" href="{{ route('panduan.indeks') }}">
            <i class="bi bi-arrow-left"></i> Panduan
        </a>
        <span class="pd-eyebrow"><i class="bi bi-door-open"></i> Ruang simulasi</span>
        <h1>{{ $ruang === null ? 'Masuk ke ruang simulasi' : 'Pilih peran Anda' }}</h1>
        <p class="pd-lead">
            @if ($ruang === null)
                Masukkan token ruang yang diberikan fasilitator. Satu ruang berisi satu program studi
                dengan satu kurikulum dan satu mata kuliah kosong, yang diisi bersama oleh semua peran di ruang itu.
            @else
                Ruang <strong style="font-family: ui-monospace, Menlo, monospace; letter-spacing: .15em;">{{ $ruang->pin }}</strong>.
                Satu peran hanya bisa dipegang satu peserta. Peran terbuka di tab baru; keluar dari peran
                akan membebaskannya kembali.
            @endif
        </p>

        @if (session('panduan_galat'))
            <div class="pd-galat">{{ session('panduan_galat') }}</div>
        @endif

        @if ($ruang === null)
            <form method="POST" action="{{ route('simulasi.ruang.periksa') }}" class="rs-token">
                @csrf
                <input type="text" name="pin" value="{{ old('pin', $pinIsian) }}" maxlength="12" required autofocus
                       autocomplete="off" autocapitalize="characters" spellcheck="false" aria-label="Token ruang" placeholder="······">
                <button type="submit" class="pd-btn"><i class="bi bi-arrow-right-circle"></i> Masuk ruang</button>
            </form>
        @else
            <div class="rs-peran" data-ruang-peran>
                @foreach ($peran as $slug => $p)
                    <div class="rs-kartu {{ $p['terisi'] ? 'rs-terisi' : '' }}" data-peran="{{ $slug }}" data-terisi="{{ $p['terisi'] ? '1' : '0' }}">
                        <i class="bi {{ $p['ikon'] }}" style="font-size:1.4rem;"></i>
                        <h3>{{ $p['label'] }}</h3>
                        <p>{{ $p['ringkas'] }}</p>
                        <form method="POST" action="{{ route('simulasi.ruang.masuk', ['pin' => $ruang->pin, 'peran' => $slug]) }}" target="_blank">
                            @csrf
                            @if ($p['terisi'])
                                <button type="button" class="pd-btn pd-btn-luar rs-tombol-mati" disabled aria-disabled="true">
                                    <i class="bi bi-person-lock"></i> Sudah terisi
                                </button>
                            @else
                                <button type="submit" class="pd-btn" data-masuk>
                                    <i class="bi bi-box-arrow-in-right"></i> Masuk sebagai peran ini
                                </button>
                            @endif
                        </form>
                    </div>
                @endforeach
            </div>

            <p style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <a class="pd-btn pd-btn-luar" href="{{ route('simulasi.ruang.tampil', ['pin' => $ruang->pin]) }}">
                    <i class="bi bi-arrow-clockwise"></i> Segarkan
                </a>
                <a class="pd-btn pd-btn-luar" href="{{ route('simulasi.ruang') }}">
                    <i class="bi bi-door-closed"></i> Ganti ruang
                </a>
                <span class="rs-lencana">Halaman ini menyegarkan diri tiap 10 detik.</span>
            </p>
        @endif
    </div>
</section>
@endsection

@if ($ruang !== null)
    @push('skrip')
        <script>
            // Memperbarui status tombol peran tanpa memuat ulang halaman,
            // supaya peran yang dilepas atau diambil orang lain langsung terlihat.
            (function () {
                const url = @json(route('simulasi.ruang.status', ['pin' => $ruang->pin]));
                const kirim = @json(csrf_token());
                async function segarkan() {
                    try {
                        const r = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                        if (!r.ok) { if (r.status === 404) location.reload(); return; }
                        const data = await r.json();
                        document.querySelectorAll('[data-ruang-peran] .rs-kartu').forEach(function (kartu) {
                            const slug = kartu.dataset.peran;
                            const terisi = !!data.terisi[slug];
                            if ((kartu.dataset.terisi === '1') === terisi) return;
                            location.reload();
                        });
                    } catch (e) { /* jaringan putus: coba lagi pada putaran berikutnya */ }
                }
                setInterval(segarkan, 10000);
            })();
        </script>
    @endpush
@endif

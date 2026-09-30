@extends('layouts.publik')

@section('judul', $halaman->judul . ' — SILOGY')
@section('deskripsi', $definisi['ringkas'])

@push('gaya')
    @include('panduan.partials.gaya-panduan')
@endpush

@section('konten')
<section class="pd-hero">
    <div class="pd-wrap">
        <a class="pd-kembali" href="{{ route('panduan.indeks') }}">
            <i class="bi bi-arrow-left"></i> Semua panduan
        </a>
        <span class="pd-eyebrow"><i class="bi {{ $definisi['ikon'] }}"></i> {{ $definisi['label'] }}</span>
        <h1>{{ $halaman->judul }}</h1>
        <p class="pd-lead">{{ $definisi['ringkas'] }}</p>

        @if (session('panduan_galat'))
            <div class="pd-galat">{{ session('panduan_galat') }}</div>
        @endif

        @if ($akunLatihan)
            <div class="pd-coba">
                <div class="pd-coba-teks">
                    <strong>Coba sendiri dengan data latihan</strong>
                    @if ($simulasiAda)
                        <p>
                            Akun latihan: <span class="pd-akun">{{ $akunLatihan }}</span> ·
                            kata sandi <span class="pd-akun">{{ $sandiLatihan }}</span>.
                            Anda akan bekerja pada <strong>data simulasi</strong>, bukan data yang sesungguhnya.
                        </p>
                    @else
                        <p>
                            Data latihan belum disiapkan. Minta Super Admin menekan
                            <strong>Buat Simulasi</strong> pada menu Simulasi.
                        </p>
                    @endif
                </div>

                @if ($simulasiAda && $cobaAktif)
                    <form method="POST" action="{{ route('panduan.coba', ['peran' => $halaman->slug]) }}">
                        @csrf
                        <button type="submit" class="pd-btn">
                            <i class="bi bi-box-arrow-in-right"></i> Coba sebagai {{ $definisi['label'] }}
                        </button>
                    </form>
                @else
                    <a class="pd-btn pd-btn-luar" href="{{ route('filament.admin.auth.login') }}">
                        <i class="bi bi-box-arrow-in-right"></i> Masuk ke SILOGY
                    </a>
                @endif
            </div>
        @endif
    </div>
</section>

<section class="pd-badan">
    <div class="pd-wrap">
        <div class="pd-kolom">
            <nav class="pd-daftar-isi" aria-label="Daftar isi">
                @if (count($halaman->daftarIsi))
                    <h2>Isi panduan</h2>
                    @foreach ($halaman->daftarIsi as $butir)
                        <a class="taraf-{{ $butir['taraf'] }}" href="#{{ $butir['id'] }}">{{ $butir['teks'] }}</a>
                    @endforeach
                @endif
            </nav>

            <article class="pd-isi">
                {!! $halaman->isi !!}

                <div class="pd-lanjut">
                    <h2>Panduan peran lain</h2>
                    <div class="pd-kisi">
                        @foreach ($peran as $slug => $lain)
                            @continue($slug === $halaman->slug)
                            <a class="pd-kartu" href="{{ route('panduan.peran', ['peran' => $slug]) }}">
                                <i class="bi {{ $lain['ikon'] }}"></i>
                                <h3>{{ $lain['label'] }}</h3>
                                <p>{{ $lain['ringkas'] }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            </article>
        </div>
    </div>
</section>
@endsection

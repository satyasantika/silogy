@extends('layouts.publik')

@section('judul', $halaman->judul . ' — SILOGY')
@section('deskripsi', $definisi['ringkas'])

@push('gaya')
    @include('panduan.partials.gaya-panduan')
@endpush

@section('konten')
<section class="pd-hero">
    <div class="pd-wrap">
        <a class="pd-kembali" href="{{ route('panduan.level', ['level' => $level]) }}">
            <i class="bi bi-arrow-left"></i> Peran di tingkat {{ $semuaLevel[$level] }}
        </a>
        <span class="pd-eyebrow"><i class="bi {{ $definisi['ikon'] }}"></i> {{ $definisi['label'] }}</span>
        <h1>{{ $halaman->judul }}</h1>
        <p class="pd-lead">{{ $definisi['ringkas'] }}</p>

        @if (session('panduan_galat'))
            <div class="pd-galat">{{ session('panduan_galat') }}</div>
        @endif

        @if ($bisaDicoba)
            <div class="pd-coba">
                <div class="pd-coba-teks">
                    <strong>Coba sendiri dengan data latihan</strong>
                    @if ($cobaAktif)
                        <p>
                            Tingkat:
                            @foreach ($semuaLevel as $kunci => $label)
                                <a href="{{ route('panduan.peran', ['peran' => $halaman->slug, 'level' => $kunci]) }}"
                                   style="{{ $kunci === $level ? 'font-weight:700;text-decoration:underline;' : '' }}">{{ $label }}</a>{{ $loop->last ? '' : ' · ' }}
                            @endforeach
                            <br>
                            Peran terbuka di <strong>tab baru</strong> dengan login sendiri, sehingga Anda bisa
                            membuka beberapa peran sekaligus. Anda bekerja pada <strong>data simulasi</strong> milik
                            Anda, bukan data yang sesungguhnya.
                            @if ($punyaPilihanMode)
                                <br>
                                <strong>Contoh terisi</strong>: kurikulum sampai nilai sudah terisi, tinggal dilihat.
                                <strong>Coba mengisi sendiri</strong>: mulai dari kosong dan isi dari awal, bersama
                                peran lain di tab lain.
                            @else
                                <br>
                                Peran ini hanya membaca, sehingga yang tersedia adalah contoh terisi.
                            @endif
                        </p>
                    @else
                        <p>Ruang latihan belum dibuka. Minta Super Admin membukanya dari menu Simulasi.</p>
                    @endif
                </div>

                @if ($cobaAktif)
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <form method="POST" action="{{ route('panduan.coba', ['peran' => $halaman->slug]) }}" target="_blank">
                            @csrf
                            <input type="hidden" name="level" value="{{ $level }}">
                            <input type="hidden" name="mode" value="terisi">
                            <button type="submit" class="pd-btn" title="Kurikulum sampai nilai sudah terisi">
                                <i class="bi bi-eye"></i> Lihat contoh terisi ({{ $semuaLevel[$level] }})
                            </button>
                        </form>
                        @if ($punyaPilihanMode)
                            <form method="POST" action="{{ route('panduan.coba', ['peran' => $halaman->slug]) }}" target="_blank">
                                @csrf
                                <input type="hidden" name="level" value="{{ $level }}">
                                <input type="hidden" name="mode" value="kosong">
                                <button type="submit" class="pd-btn pd-btn-luar" title="Mulai dari kosong dan isi sendiri">
                                    <i class="bi bi-pencil"></i> Coba mengisi sendiri ({{ $semuaLevel[$level] }})
                                </button>
                            </form>
                        @endif
                    </div>
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

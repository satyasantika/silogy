@extends('layouts.publik')

@section('judul', 'Panduan tingkat '.$labelLevel.' — SILOGY')
@section('deskripsi', 'Panduan SILOGY untuk peran di tingkat '.$labelLevel.'.')

@push('gaya')
    @include('panduan.partials.gaya-panduan')
@endpush

@section('konten')
<section class="pd-hero">
    <div class="pd-wrap">
        <a class="pd-kembali" href="{{ route('panduan.indeks') }}">
            <i class="bi bi-arrow-left"></i> Semua tingkat
        </a>
        <span class="pd-eyebrow"><i class="bi bi-layers"></i> Tingkat {{ $labelLevel }}</span>
        <h1>Peran di tingkat {{ $labelLevel }}</h1>
        <p class="pd-lead">
            Pilih peran Anda. Panduan dan tombol latihan di bawahnya mengikuti tingkat {{ $labelLevel }}.
        </p>

        @if (session('panduan_galat'))
            <div class="pd-galat">{{ session('panduan_galat') }}</div>
        @endif

        <p style="margin-top: 14px; display: flex; gap: 8px; flex-wrap: wrap;">
            @foreach ($semuaLevel as $kunci => $label)
                <a class="pd-btn {{ $kunci === $level ? '' : 'pd-btn-luar' }}"
                   href="{{ route('panduan.level', ['level' => $kunci]) }}">{{ $label }}</a>
            @endforeach
        </p>
    </div>
</section>

<section class="pd-badan">
    <div class="pd-wrap">
        <div class="pd-kisi">
            @foreach ($peran as $slug => $definisi)
                <div class="pd-kartu" style="display: flex; flex-direction: column; gap: 10px;">
                    <i class="bi {{ $definisi['ikon'] }}"></i>
                    <h3>{{ $definisi['label'] }}</h3>
                    <p>{{ $definisi['ringkas'] }}</p>

                    <div style="margin-top: auto; display: flex; gap: 8px; flex-wrap: wrap;">
                        <a class="pd-btn pd-btn-luar"
                           href="{{ route('panduan.peran', ['peran' => $slug, 'level' => $level]) }}">
                            <i class="bi bi-book"></i> Baca panduan
                        </a>

                        @if (! $levelBisaDicoba)
                            <span class="pd-btn pd-btn-luar" style="opacity:.6;cursor:default;" title="Simulasi hanya untuk Program Studi">
                                <i class="bi bi-info-circle"></i> Simulasi di tingkat Program Studi
                            </span>
                        @elseif ($cobaAktif)
                            <form method="POST" action="{{ route('panduan.coba', ['peran' => $slug]) }}" target="_blank">
                                @csrf
                                <input type="hidden" name="level" value="{{ $level }}">
                                <input type="hidden" name="mode" value="terisi">
                                <button type="submit" class="pd-btn" title="Contoh terisi, hanya untuk dilihat">
                                    <i class="bi bi-eye"></i> Lihat contoh terisi
                                </button>
                            </form>
                            @if (\App\Modules\Panduan\Support\PeranPanduan::punyaPilihanMode($slug))
                                <form method="POST" action="{{ route('panduan.coba', ['peran' => $slug]) }}" target="_blank">
                                    @csrf
                                    <input type="hidden" name="level" value="{{ $level }}">
                                    <input type="hidden" name="mode" value="kosong">
                                    <button type="submit" class="pd-btn pd-btn-luar" title="Mulai dari kosong dan isi sendiri">
                                        <i class="bi bi-pencil"></i> Coba mengisi sendiri
                                    </button>
                                </form>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="pd-lanjut">
            <p style="color: var(--c-body); line-height: 1.75;">
                Peran Koordinator MK dan Dosen Pengampu bekerja per kelas mata kuliah; pada tingkat
                {{ $labelLevel }} Anda hanya melihat mata kuliah dan kelas milik tingkat ini.
                @if (! $levelBisaDicoba)
                    <br><strong>Simulasi</strong> saat ini difokuskan pada tingkat Program Studi. Panduan tingkat
                    {{ $labelLevel }} tetap bisa dibaca, dan latihannya dapat dicoba di tingkat Program Studi.
                @endif
            </p>
        </div>
    </div>
</section>
@endsection

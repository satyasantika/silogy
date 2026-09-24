@extends('layouts.publik')

@section('judul', 'Panduan Pengguna — SILOGY')
@section('deskripsi', 'Panduan SILOGY per peran: Super Admin, Admin Unit, Tim Kurikulum, Koordinator MK, Dosen Pengampu, Pimpinan, dan Auditor Mutu.')

@push('gaya')
    @include('panduan.partials.gaya-panduan')
@endpush

@section('konten')
<section class="pd-hero">
    <div class="pd-wrap">
        <span class="pd-eyebrow"><i class="bi bi-book"></i> Panduan Pengguna</span>
        <h1>Cara memakai SILOGY sesuai tugas Anda</h1>
        <p class="pd-lead">
            SILOGY mengelola kurikulum berbasis OBE dan capaian pembelajaran di Universitas
            Siliwangi. Tiap peran mengerjakan satu ruas rantai yang sama, jadi buka panduan yang
            sesuai dengan tugas Anda. Angka merah pada gambar menunjuk tombol yang harus diklik.
        </p>

        @if ($simulasiAda && $cobaAktif)
            <div class="pd-coba">
                <div class="pd-coba-teks">
                    <strong>Ada data latihan yang bisa Anda coba.</strong>
                    <p>
                        Buka panduan peran mana pun, lalu tekan <em>Coba sebagai ‹peran›</em> untuk
                        langsung masuk dan mencobanya sendiri. Semua yang Anda lakukan di sana
                        memakai data simulasi, bukan data yang sesungguhnya.
                    </p>
                </div>
            </div>
        @endif
    </div>
</section>

<section class="pd-badan">
    <div class="pd-wrap">
        <div class="pd-kisi">
            @foreach ($peran as $slug => $definisi)
                <a class="pd-kartu" href="{{ route('panduan.peran', ['peran' => $slug]) }}">
                    <i class="bi {{ $definisi['ikon'] }}"></i>
                    <h3>{{ $definisi['label'] }}</h3>
                    <p>{{ $definisi['ringkas'] }}</p>
                </a>
            @endforeach
        </div>

        <div class="pd-lanjut">
            <h2>Rantai data yang sama untuk semua peran</h2>
            <p style="color: var(--c-body); line-height: 1.75;">
                Profil Lulusan → CPL → Bahan Kajian → Mata Kuliah → CPMK → Sub-CPMK →
                Komponen Penilaian → Nilai → Laporan. Setiap peran mengisi satu ruas.
                Melompati satu ruas membuat laporan di ujungnya kosong — itulah sebab
                urutannya dijaga ketat oleh sistem.
            </p>
            <p style="margin-top: 14px;">
                <a class="pd-btn pd-btn-luar" href="{{ route('panduan.peran', ['peran' => 'alur-end-to-end']) }}">
                    <i class="bi bi-signpost-split"></i> Lihat alur lengkapnya
                </a>
            </p>
        </div>
    </div>
</section>
@endsection

@extends('layouts.publik')

@section('judul', 'Panduan Pengguna — SILOGY')
@section('deskripsi', 'Panduan SILOGY menurut tingkat unit: Universitas, Fakultas, dan Program Studi, lalu menurut peran.')

@push('gaya')
    @include('panduan.partials.gaya-panduan')
@endpush

@section('konten')
@php
    $ikonLevel = ['univ' => 'bi-bank', 'fak' => 'bi-building', 'prodi' => 'bi-mortarboard'];
    $ringkasLevel = [
        'univ' => 'Rektor, admin dan tim kurikulum universitas, serta mata kuliah umum tingkat universitas.',
        'fak' => 'Dekan, admin dan tim kurikulum fakultas, serta mata kuliah tingkat fakultas.',
        'prodi' => 'Kaprodi, tim kurikulum, koordinator MK, dosen pengampu, sampai nilai kelas.',
    ];
@endphp
<section class="pd-hero">
    <div class="pd-wrap">
        <span class="pd-eyebrow"><i class="bi bi-book"></i> Panduan Pengguna</span>
        <h1>Mulai dari tingkat unit Anda</h1>
        <p class="pd-lead">
            Tampilan dan wewenang di SILOGY mengikuti tingkat unit tempat Anda ditugaskan.
            Pilih tingkat Anda lebih dulu, lalu buka panduan untuk peran Anda di tingkat itu.
        </p>

        @if ($cobaAktif)
            <div class="pd-coba">
                <div class="pd-coba-teks">
                    <strong>Ada ruang latihan yang bisa Anda coba.</strong>
                    <p>
                        Di halaman peran, pilih <em>Lihat contoh terisi</em> untuk melihat hasil akhirnya.
                        Untuk berlatih mengisi dari kosong, pakai <em>Masuk ruang simulasi</em>: masukkan token
                        dari fasilitator, lalu pilih satu peran. Setiap peran terbuka di tab baru dengan login
                        sendiri. Semua yang Anda ubah hanya terjadi pada data ruang itu.
                    </p>
                    <p style="margin-top:10px;">
                        <a class="pd-btn" href="{{ route('simulasi.ruang') }}"><i class="bi bi-door-open"></i> Masuk ruang simulasi</a>
                    </p>
                </div>
            </div>
        @endif
    </div>
</section>

<section class="pd-badan">
    <div class="pd-wrap">
        <div class="pd-kisi">
            @foreach ($level as $kunci => $label)
                <a class="pd-kartu" href="{{ route('panduan.level', ['level' => $kunci]) }}">
                    <i class="bi {{ $ikonLevel[$kunci] }}"></i>
                    <h3>{{ $label }}</h3>
                    <p>{{ $ringkasLevel[$kunci] }}</p>
                </a>
            @endforeach
        </div>

        <div class="pd-lanjut">
            <h2>Rantai data yang sama untuk semua tingkat</h2>
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

@extends('errors.layout', ['urlBeranda' => $urlPanduan])

@section('code', '410')
@section('status', 'Sesi berakhir')
@section('title', 'Sesi simulasi ini telah berakhir')
@section('icon', 'bi-hourglass-bottom')
@section('accent', '#fbbf24')

@section('message')
    Tab latihan ini sudah tidak aktif: terlalu lama tidak dipakai, perannya sudah dilepas,
    atau ruang simulasinya sudah ditutup. Data simulasi terpisah dari data SILOGY yang
    sebenarnya, jadi tidak ada yang hilang dari akun Anda.
@endsection

@section('tambahan')
    <p class="err-message">
        Untuk berlatih lagi, kembali ke Panduan lalu pilih peran yang ingin dicoba.
    </p>
@endsection

{{-- Satu-satunya jalan keluar. Tanpa Kembali/Dashboard/Masuk: semuanya akan
     membawa pengunjung lagi ke tab yang sudah mati atau ke halaman login.
     target="_top" agar tetap berfungsi saat Livewire menampilkan halaman ini
     di dalam modal (iframe) pada permintaan AJAX. --}}
@section('aksi')
    <a href="{{ $urlPanduan }}" target="_top" class="err-btn err-btn-primary">
        <i class="bi bi-book"></i>
        Kembali ke Panduan
    </a>
@endsection

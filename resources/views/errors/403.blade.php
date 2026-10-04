@extends('errors.layout')

@section('code', '403')
@section('status', 'Forbidden')
@section('title', 'Akses ditolak')
@section('icon', 'bi-shield-lock-fill')
@section('accent', '#fbbf24')

@section('tambahan')
    {{-- Hanya berisi untuk akun ruang latihan: menjelaskan syarat hulu yang membuat halaman ini terkunci. --}}
    <div style="text-align:left;margin:18px 0 6px;">
        {!! \App\Modules\Simulasi\Support\SyaratHulu::renderUntukPenolakan(request()->path()) !!}
    </div>
@endsection

@section('message')
    Anda tidak memiliki izin untuk mengakses halaman atau tindakan ini.
    Jika menurut Anda ini keliru, hubungi administrator unit atau tim LPMPP.
@endsection

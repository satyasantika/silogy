@extends('errors.layout')

@section('code', '500')
@section('status', 'Server Error')
@section('title', 'Terjadi gangguan server')
@section('icon', 'bi-exclamation-octagon-fill')
@section('accent', '#f87171')

@section('message')
    Sistem mengalami kendala saat memproses permintaan Anda.
    Silakan coba beberapa saat lagi atau hubungi tim dukungan jika masalah berlanjut.
@endsection

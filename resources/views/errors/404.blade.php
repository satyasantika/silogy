@extends('errors.layout')

@section('code', '404')
@section('status', 'Not Found')
@section('title', 'Halaman tidak ditemukan')
@section('icon', 'bi-signpost-split-fill')
@section('accent', '#4ade80')

@section('message')
    Alamat yang Anda buka tidak ada, sudah dipindahkan, atau tautannya sudah tidak valid.
    Periksa kembali URL atau gunakan menu navigasi untuk melanjutkan.
@endsection

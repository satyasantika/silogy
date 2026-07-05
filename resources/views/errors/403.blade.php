@extends('errors.layout')

@section('code', '403')
@section('status', 'Forbidden')
@section('title', 'Akses ditolak')
@section('icon', 'bi-shield-lock-fill')
@section('accent', '#fbbf24')

@section('message')
    Anda tidak memiliki izin untuk mengakses halaman atau tindakan ini.
    Jika menurut Anda ini keliru, hubungi administrator unit atau tim LPMPP.
@endsection

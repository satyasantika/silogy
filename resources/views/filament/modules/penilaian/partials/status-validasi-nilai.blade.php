@php
    $statusValidasiEnum = \App\Modules\Penilaian\Support\StatusValidasiNilai::from($this->statusValidasi);
    $nadaValidasi = match ($statusValidasiEnum) {
        \App\Modules\Penilaian\Support\StatusValidasiNilai::Tervalidasi => 'success',
        \App\Modules\Penilaian\Support\StatusValidasiNilai::PerluUlang => 'warning',
        default => 'neutral',
    };
@endphp
<span data-silogy="status-validasi-nilai" class="silogy-badge silogy-tone-{{ $nadaValidasi }}">
    {{ $statusValidasiEnum->label() }}
</span>

@php
    $statusValidasiEnum = \App\Modules\Penilaian\Support\StatusValidasiNilai::from($statusValidasiTampil ?? $this->statusValidasi);
    $nadaValidasi = match ($statusValidasiEnum) {
        \App\Modules\Penilaian\Support\StatusValidasiNilai::Tervalidasi => 'success',
        \App\Modules\Penilaian\Support\StatusValidasiNilai::PerluUlang => 'warning',
        default => 'neutral',
    };
@endphp
@if ($this->validasiDiwajibkan || $statusValidasiEnum !== \App\Modules\Penilaian\Support\StatusValidasiNilai::Belum)
<span data-silogy="status-validasi-nilai" class="silogy-badge silogy-tone-{{ $nadaValidasi }}">
    {{ $statusValidasiEnum->label() }}
</span>
@endif

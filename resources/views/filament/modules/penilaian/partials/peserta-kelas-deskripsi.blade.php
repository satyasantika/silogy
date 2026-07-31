{{-- Banner MK + KPI bento di dalam card tabel (/peserta-kelas), pola seperti /subcpmk. --}}
@php
    $kpi = $kpi ?? [];
@endphp

<div class="silogy-peserta-deskripsi">
    {!! \App\Modules\MK\Support\MkTerpilih::bannerHtml() !!}

    @include('filament.modules.penilaian.partials.peserta-kelas-kpi', $kpi)
</div>

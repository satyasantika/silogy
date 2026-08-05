{{-- Kepala satu kartu /peserta-kelas: banner MK + bento KPI. --}}
@php
    $kpi = is_array($kpi ?? null) ? $kpi : [];
@endphp
<div data-silogy="peserta-kelas-panel-head">
    @include('filament.modules.mk.partials.mk-terpilih-banner-inner', [
        'catatan' => null,
        'sebagaiHeaderPanel' => true,
    ])
    <div data-silogy="peserta-kelas-bento" class="silogy-peserta-card-bento">
        @include('filament.modules.penilaian.partials.peserta-kelas-kpi', $kpi)
    </div>
</div>

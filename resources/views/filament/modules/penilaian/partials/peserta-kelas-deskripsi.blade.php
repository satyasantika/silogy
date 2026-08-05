{{-- Banner MK sebagai header panel; KPI bento di body (pola input-nilai). --}}
@php
    $kpi = $kpi ?? [];
    $kpiHtml = view('filament.modules.penilaian.partials.peserta-kelas-kpi', $kpi)->render();
@endphp
{!! \App\Modules\MK\Support\MkTerpilih::bannerHtml(null, $kpiHtml) !!}

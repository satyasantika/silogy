{{-- Wrapper halaman matriks korma: banner MK sebagai header panel; $catatan → body. --}}
@php
    use App\Support\Filament\SilogyBannerPanel;

    $catatan = $catatan ?? null;
    $bodyHtml = $bodyHtml ?? null;
    $banner = view('filament.modules.mk.partials.mk-terpilih-banner-inner', [
        'catatan' => null,
        'sebagaiHeaderPanel' => true,
        'gantiUrl' => $gantiUrl ?? null,
        'mk' => $mk ?? null,
        'kurikulum' => $kurikulum ?? null,
    ])->render();
@endphp
{!! SilogyBannerPanel::wrap($banner, $catatan, $bodyHtml, marginBottom: true) !!}

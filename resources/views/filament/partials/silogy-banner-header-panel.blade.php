{{-- Panel kartu: banner hijau sebagai header (flat), pelengkap/konten di body bawah.
     Pola sama /penilaian/input-nilai (data-silogy=input-nilai-pilih-kelas). --}}
@php
    $banner = $banner ?? '';
    $pelengkap = $pelengkap ?? null;
    $bodyHtml = $bodyHtml ?? null;
    $marginBottom = (bool) ($marginBottom ?? true);
    $adaBody = filled($pelengkap) || filled($bodyHtml);
@endphp
<div
    data-silogy="banner-header-panel"
    style="border-radius:14px;overflow:hidden;border:1px solid rgba(128,128,128,.2);background:var(--gray-50, #f9fafb);{{ $marginBottom ? 'margin-bottom:16px;' : '' }}"
>
    {!! $banner !!}

    @if ($adaBody)
        <div style="padding:14px 16px 16px;">
            @if (filled($pelengkap))
                <p style="margin:0 0 {{ filled($bodyHtml) ? '12px' : '0' }};font-size:13px;line-height:1.55;opacity:.88;">
                    {{ $pelengkap }}
                </p>
            @endif
            @if (filled($bodyHtml))
                {!! $bodyHtml !!}
            @endif
        </div>
    @endif
</div>

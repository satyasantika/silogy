@if ($ringkasan !== null)
    @php
        $palet = match ($ringkasan['status']) {
            'success' => ['tone' => 'silogy-tone-success', 'border' => 'rgba(22,163,74,.35)'],
            'warning' => ['tone' => 'silogy-tone-warning', 'border' => 'rgba(217,119,6,.35)'],
            default => ['tone' => 'silogy-tone-danger', 'border' => 'rgba(220,38,38,.35)'],
        };
    @endphp
    <div class="{{ $palet['tone'] }}" style="margin-bottom:16px;padding:10px 14px;border-radius:10px;border:1px solid {{ $palet['border'] }};font-size:13px;">
        <span style="font-weight:700;">{{ $ringkasan['keterangan'] }}</span>
    </div>
@endif

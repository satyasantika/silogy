@if ($ringkasan !== null)
    @php
        $palet = match ($ringkasan['status']) {
            'success' => ['bg' => 'rgba(22,163,74,.08)', 'border' => 'rgba(22,163,74,.35)', 'text' => '#166534'],
            'warning' => ['bg' => 'rgba(217,119,6,.08)', 'border' => 'rgba(217,119,6,.35)', 'text' => '#92400e'],
            default => ['bg' => 'rgba(220,38,38,.08)', 'border' => 'rgba(220,38,38,.35)', 'text' => '#991b1b'],
        };
    @endphp
    <div style="margin-bottom:16px;padding:10px 14px;border-radius:10px;border:1px solid {{ $palet['border'] }};background:{{ $palet['bg'] }};font-size:13px;">
        <span style="font-weight:700;color:{{ $palet['text'] }};">{{ $ringkasan['keterangan'] }}</span>
    </div>
@endif

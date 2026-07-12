<div style="overflow-x:auto;">
    <table style="width:100%;min-width:360px;border-collapse:collapse;font-size:12px;">
        <thead>
            <tr style="background:rgba(128,128,128,.08);text-align:center;">
                <th style="padding:8px;">Nilai Huruf</th>
                <th style="padding:8px;">Jumlah Mahasiswa</th>
                <th style="padding:8px;">Persentase (%)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($distribusi as $baris)
                <tr style="border-top:1px solid rgba(128,128,128,.15);text-align:center;">
                    <td style="padding:8px;">{{ $baris['huruf'] }}</td>
                    <td style="padding:8px;">{{ $baris['jumlah'] }}</td>
                    <td style="padding:8px;">{{ rtrim(rtrim(number_format($baris['persentase'], 2, '.', ''), '0'), '.') }}%</td>
                </tr>
            @endforeach
            <tr style="border-top:2px solid rgba(128,128,128,.3);text-align:center;font-weight:700;background:rgba(128,128,128,.06);">
                <td style="padding:8px;">TOTAL</td>
                <td style="padding:8px;">{{ collect($distribusi)->sum('jumlah') }}</td>
                <td style="padding:8px;">
                    {{ collect($distribusi)->sum('jumlah') > 0 ? '100' : '0' }}%
                </td>
            </tr>
        </tbody>
    </table>
</div>

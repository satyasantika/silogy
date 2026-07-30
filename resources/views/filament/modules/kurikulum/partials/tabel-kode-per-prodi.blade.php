@if ($baris->isEmpty())
    <p style="font-size:13px;opacity:.7;">
        Belum ada prodi yang mengadaptasi mata kuliah ini.
    </p>
@else
    <div style="overflow-x:auto;">
        <table style="width:100%;min-width:320px;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:rgba(128,128,128,.08);text-align:left;">
                    <th style="padding:8px;">Prodi</th>
                    <th style="padding:8px;">Kode</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($baris as $b)
                    <tr wire:key="kode-per-prodi-{{ $loop->index }}" style="border-top:1px solid rgba(128,128,128,.15);">
                        <td style="padding:8px;">{{ $b['prodi'] }}</td>
                        <td style="padding:8px;">{{ $b['kode'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

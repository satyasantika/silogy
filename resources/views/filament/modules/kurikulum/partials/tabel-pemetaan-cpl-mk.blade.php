@if (empty($pemetaan))
    <p style="font-size:13px;opacity:.7;">
        Belum ada CPL yang dibebankan pada mata kuliah di program studi ini.
    </p>
@else
    <div style="overflow-x:auto;">
        <table style="width:100%;min-width:640px;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:rgba(128,128,128,.08);text-align:left;">
                    <th style="padding:8px;width:40%;">Capaian Pembelajaran Lulusan</th>
                    <th style="padding:8px;">Nama Mata Kuliah (Kode)</th>
                    <th style="padding:8px;text-align:center;">SKS</th>
                    <th style="padding:8px;text-align:end;">Kontribusi MK</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pemetaan as $baris)
                    @foreach ($baris['mk_rows'] as $i => $mkRow)
                        <tr wire:key="pemetaan-{{ $baris['cpl_kode'] }}-{{ $mkRow['mk_id'] }}" style="border-top:1px solid rgba(128,128,128,.15);vertical-align:top;">
                            @if ($i === 0)
                                <td rowspan="{{ count($baris['mk_rows']) }}" style="padding:8px;background:rgba(37,99,235,.04);">
                                    <div style="display:flex;flex-direction:column;gap:2px;">
                                        <strong style="font-size:14px;color:#1d4ed8;">{{ $baris['cpl_kode'] }}</strong>
                                        <span style="font-size:12px;opacity:.75;">{{ $baris['cpl_deskripsi'] }}</span>
                                    </div>
                                </td>
                            @endif
                            <td style="padding:8px;">{{ $mkRow['nama'] }} ({{ $mkRow['kode'] }})</td>
                            <td style="padding:8px;text-align:center;">
                                <span style="display:inline-block;padding:1px 10px;border-radius:999px;font-size:11px;font-weight:600;background:#e0e7ff;color:#3730a3;">
                                    {{ $mkRow['sks'] }}
                                </span>
                            </td>
                            <td style="padding:8px;text-align:end;">
                                {{ rtrim(rtrim(number_format($mkRow['kontribusi'], 2, '.', ''), '0'), '.') }}%
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
@endif

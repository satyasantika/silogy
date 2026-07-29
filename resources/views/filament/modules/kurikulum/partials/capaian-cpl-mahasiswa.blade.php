@if (empty($capaian))
    <p style="font-size:13px;opacity:.7;">
        Mahasiswa ini belum memiliki hasil penilaian pada mata kuliah kurikulum yang dikerjakan.
    </p>
@else
    <div>
        <canvas
            wire:key="radar-cpl-mahasiswa-{{ $mahasiswaId }}"
            x-data
            x-init="renderRadarSilogy($el, @js(['labels' => collect($capaian)->pluck('cpl_kode')->all(), 'data' => collect($capaian)->pluck('nilai_rata_rata')->all()]), '#2563eb')"
        ></canvas>
    </div>

    <div style="margin-top:12px;overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:rgba(128,128,128,.08);text-align:left;">
                    <th style="padding:6px;">CPL</th>
                    <th style="padding:6px;">Deskripsi</th>
                    <th style="padding:6px;text-align:center;">Rerata Nilai</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($capaian as $baris)
                    <tr style="border-top:1px solid rgba(128,128,128,.15);">
                        <td style="padding:6px;font-weight:600;">{{ $baris['cpl_kode'] }}</td>
                        <td style="padding:6px;">{{ $baris['cpl_deskripsi'] }}</td>
                        <td style="padding:6px;text-align:center;">{{ number_format($baris['nilai_rata_rata'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

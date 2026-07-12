@php
    use App\Modules\Penilaian\Services\RencanaEvaluasiService;

    $service = app(RencanaEvaluasiService::class);
@endphp

@if ($rencana !== null)
    @php
        $ringkasan = $service->ringkasanTotalBobot($rencana['total_bobot']);
        $warnaRingkasan = match ($ringkasan['status']) {
            'success' => '#166534',
            'warning' => '#92400e',
            default => '#991b1b',
        };

        $warnaAsesmen = '#2563eb';
    @endphp
    <div style="margin-top:1.5rem;">
        <div style="border:1px solid rgba(128,128,128,.25);border-radius:12px;overflow:hidden;background:transparent;">
            <div style="padding:12px 16px;border-bottom:1px solid rgba(128,128,128,.2);">
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;">
                    <div style="display:inline-flex;align-items:center;justify-content:center;border-radius:12px;background:rgba(59,130,246,.12);color:#2563eb;min-height:56px;padding:0 14px;">
                        <x-filament::icon icon="heroicon-o-clipboard-document-list" style="width:24px;height:24px;" />
                    </div>
                    <div>
                        <div style="font-weight:600;font-size:1.125rem;line-height:1.3;">Tabel Rencana Evaluasi</div>
                        <div style="font-size:12px;opacity:.7;margin-top:2px;">Assessment Plan berdasarkan komponen evaluasi</div>
                    </div>
                </div>
            </div>

            <div style="padding:12px 16px;background:rgba(128,128,128,.04);">
                <div style="overflow-x:auto;border:1px solid rgba(128,128,128,.2);border-radius:12px;background:transparent;box-shadow:0 1px 2px rgba(0,0,0,.04);">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="background:rgba(128,128,128,.08);">
                                <th style="padding:10px 12px;text-align:left;font-size:11px;text-transform:uppercase;opacity:.7;">Komponen Evaluasi</th>
                                <th style="padding:10px 12px;text-align:left;font-size:11px;text-transform:uppercase;opacity:.7;">Bentuk Asesmen</th>
                                <th style="padding:10px 12px;text-align:right;font-size:11px;text-transform:uppercase;opacity:.7;white-space:nowrap;">Bobot (%)</th>
                                <th style="padding:10px 12px;text-align:left;font-size:11px;text-transform:uppercase;opacity:.7;">Mengukur CPL</th>
                                <th style="padding:10px 12px;text-align:left;font-size:11px;text-transform:uppercase;opacity:.7;">Mengukur CPMK</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rencana['groups'] as $group)
                                <tr style="background:rgba(128,128,128,.12);">
                                    <th colspan="5" style="padding:8px 12px;text-align:center;font-weight:700;">
                                        {{ $group['label'] }} ({{ $service->formatBobot($group['bobot_persen']) }})
                                    </th>
                                </tr>

                                @foreach ($group['rows'] as $row)
                                    <tr style="border-top:1px solid rgba(128,128,128,.15);vertical-align:middle;">
                                        <td style="padding:10px 12px;">{{ $row['evaluasi_nama'] }}</td>
                                        <td style="padding:10px 12px;">
                                            @if ($row['asesmen'] === [])
                                                -
                                            @else
                                                @foreach ($row['asesmen'] as $asesmen)
                                                    <table style="margin-bottom:{{ $loop->last ? '0' : '6px' }};font-size:12px;">
                                                        <tbody>
                                                            <tr>
                                                                <td style="padding:0 8px 0 0;white-space:nowrap;vertical-align:top;">{{ $asesmen['kode'] }}:</td>
                                                                <td style="padding:0;vertical-align:top;">
                                                                    {{ $asesmen['nama'] }}
                                                                    (bobot: <span style="color:{{ $warnaAsesmen }};font-weight:700;">{{ $service->formatBobot($asesmen['bobot']) }}</span>)
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                @endforeach
                                            @endif
                                        </td>
                                        <td style="padding:10px 12px;text-align:right;white-space:nowrap;">
                                            {{ $service->formatBobot($row['bobot_total']) }}
                                        </td>
                                        <td style="padding:10px 12px;">
                                            {{ $row['cpl_kodes'] !== [] ? implode(', ', $row['cpl_kodes']) : '-' }}
                                        </td>
                                        <td style="padding:10px 12px;">
                                            {{ $row['cpmk_kodes'] !== [] ? implode(', ', $row['cpmk_kodes']) : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach

                            <tr style="background:rgba(128,128,128,.12);border-top:1px solid rgba(128,128,128,.2);">
                                <th colspan="2" style="padding:10px 12px;text-align:right;font-weight:700;">Total Bobot</th>
                                <th style="padding:10px 12px;text-align:right;font-weight:700;white-space:nowrap;">
                                    {{ $service->formatBobot($rencana['total_bobot']) }}
                                </th>
                                <th colspan="2" style="padding:10px 12px;text-align:left;font-weight:700;white-space:nowrap;color:{{ $warnaRingkasan }};">
                                    @if ($ringkasan['sudah_pas'])
                                        Sudah pas 100%
                                    @else
                                        {{ $ringkasan['selisih'] > 0 ? 'Kurang' : 'Lebih' }} {{ $service->formatBobot(abs($ringkasan['selisih'])) }}
                                    @endif
                                </th>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endif

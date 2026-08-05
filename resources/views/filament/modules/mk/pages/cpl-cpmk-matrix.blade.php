<x-filament-panels::page>
    <div
        data-silogy="banner-header-panel"
        style="border-radius:14px;overflow:hidden;border:1px solid rgba(128,128,128,.2);background:var(--gray-50, #f9fafb);"
    >
        @include('filament.modules.mk.partials.mk-terpilih-banner-inner', [
            'catatan' => null,
            'sebagaiHeaderPanel' => true,
        ])

        <div style="padding:14px 16px 16px;">
            @if (! $kurikulum)
                <p style="font-size:13px;opacity:.75;">
                    Pilih kurikulum lewat widget di dashboard atau filter pada halaman Mata Kuliah.
                </p>
            @elseif (! $mkTerpilih)
                <p style="font-size:13px;opacity:.75;">
                    Pilih mata kuliah dari halaman Mata Kuliah terlebih dahulu.
                </p>
            @elseif ($cpmks->isEmpty())
                <p style="font-size:13px;opacity:.75;">
                    Matriks membutuhkan minimal satu CPMK pada mata kuliah terpilih.
                </p>
            @elseif ($cpls->isEmpty())
                <p style="font-size:13px;opacity:.75;">
                    Petakan CPL ke mata kuliah ini terlebih dahulu lewat interaksi CPL ↔ MK (beri bobot pada irisan).
                </p>
            @else
                <div style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;opacity:.55;margin-bottom:8px;">
                    Interaksi CPL ↔ CPMK
                </div>
                <p style="margin:0 0 12px;font-size:12px;line-height:1.5;opacity:.8;">
                    Centang irisan untuk memetakan CPMK (baris) ke CPL (kolom). CPL–MK harus sudah dipetakan lewat interaksi CPL ↔ MK.
                </p>
                <div style="overflow-x:auto;" wire:key="matriks-cpl-cpmk">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="text-align:left;border-bottom:2px solid rgba(128,128,128,.35);">
                            <th style="padding:8px;">CPMK \ CPL</th>
                            @foreach ($cpls as $cpl)
                                <th style="padding:8px;text-align:center;white-space:nowrap;">
                                    @include('filament.modules.kurikulum.partials.kode-keterangan-trigger', [
                                        'jenis' => 'CPL',
                                        'kode' => $cpl->kode,
                                        'deskripsi' => $cpl->deskripsi,
                                    ])
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cpmks as $cpmk)
                            <tr style="border-bottom:1px solid rgba(128,128,128,.2);">
                                <td style="padding:8px;white-space:nowrap;">
                                    <strong>
                                        @include('filament.modules.kurikulum.partials.kode-keterangan-trigger', [
                                            'jenis' => 'CPMK',
                                            'kode' => $cpmk->kode,
                                            'deskripsi' => $cpmk->deskripsi,
                                        ])
                                    </strong>
                                </td>
                                @foreach ($cpls as $cpl)
                                    @php
                                        $kunciSel = $cpmk->id.'/'.$cpl->id;
                                        $sudahTerpetakan = $terpetakan->has($kunciSel);
                                        $terkunci = $terkunciSubcpmk->has($kunciSel);
                                    @endphp
                                    <td style="padding:8px;text-align:center;">
                                        <input
                                            type="checkbox"
                                            style="width:18px;height:18px;accent-color:#16a34a;{{ $terkunci ? 'cursor:not-allowed;opacity:.65;' : 'cursor:pointer;' }}"
                                            @if (! $terkunci)
                                                wire:click="toggle('{{ $cpmk->id }}', '{{ $cpl->id }}')"
                                            @endif
                                            @disabled($terkunci)
                                            @checked($sudahTerpetakan)
                                            @if ($terkunci)
                                                title="Hapus Sub-CPMK terkait terlebih dahulu"
                                            @endif
                                        />
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-sm" style="margin-top:8px;opacity:.7;">
                Pemetaan CPL–CPMK yang sudah memiliki Sub-CPMK tidak dapat dilepas centang dari halaman ini.
            </p>
            @endif
        </div>
    </div>
</x-filament-panels::page>

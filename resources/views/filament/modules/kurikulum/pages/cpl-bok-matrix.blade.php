<x-filament-panels::page>
    <div
        data-silogy="banner-header-panel"
        style="border-radius:14px;overflow:hidden;border:1px solid rgba(128,128,128,.2);background:var(--gray-50, #f9fafb);"
    >
        @livewire('silogy.kurikulum-terpilih-banner', ['catatan' => null, 'sebagaiHeaderPanel' => true])

        <div style="padding:14px 16px 16px;">
            <p style="margin:0 0 12px;font-size:13px;line-height:1.55;opacity:.88;">
                Pemetaan CPL ke bahan kajian (BoK) yang dicentang di bawah tersimpan pada kurikulum ini.
            </p>

            @if (! $kurikulum)
                <p style="font-size:13px;opacity:.75;">
                    Pilih kurikulum dari halaman Kurikulum terlebih dahulu.
                </p>
            @elseif ($cpls->isEmpty() || $boks->isEmpty())
                <p style="font-size:13px;opacity:.75;">
                    Matriks membutuhkan minimal satu CPL dan satu bahan kajian (BoK) pada kurikulum terpilih.
                </p>
            @else
                <div style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;opacity:.55;margin-bottom:8px;">
                    Interaksi CPL ↔ BoK
                </div>
                <p style="margin:0 0 12px;font-size:12px;line-height:1.5;opacity:.8;">
                    Centang irisan untuk memetakan CPL (baris) ke bahan kajian (kolom). CPL/BoK bertanda † berasal dari adaptasi MK unit lain — pasangan yang murni milik unit lain tidak dapat diubah dari sini.
                </p>
                <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="text-align:left;border-bottom:2px solid rgba(128,128,128,.35);">
                            <th style="padding:8px;">CPL \ BoK</th>
                            @foreach ($boks as $bok)
                                <th style="padding:8px;text-align:center;">
                                    @include('filament.modules.kurikulum.partials.kode-keterangan-trigger', [
                                        'jenis' => 'BoK',
                                        'kode' => $bokKodeMap[$bok->id],
                                        'nama' => $bok->nama,
                                        'deskripsi' => $bok->deskripsi,
                                        'meta' => $bokAsalMap[$bok->id]
                                            ? 'Adaptasi dari '.($bok->academicUnit->nama_lengkap ?? '—')
                                            : null,
                                    ])
                                    @if ($bokAsalMap[$bok->id])
                                        <sup style="color:#b45309;">†</sup>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cpls as $cpl)
                            <tr style="border-bottom:1px solid rgba(128,128,128,.2);">
                                <td style="padding:8px;white-space:nowrap;">
                                    <strong>
                                        @include('filament.modules.kurikulum.partials.kode-keterangan-trigger', [
                                            'jenis' => 'CPL',
                                            'kode' => $cplKodeMap[$cpl->id],
                                            'deskripsi' => $cpl->deskripsi,
                                            'meta' => $cplAsalMap[$cpl->id]
                                                ? 'Adaptasi dari '.($cpl->academicUnit->nama_lengkap ?? '—')
                                                : null,
                                        ])
                                    </strong>
                                    @if ($cplAsalMap[$cpl->id])
                                        <sup style="color:#b45309;">†</sup>
                                    @endif
                                </td>
                                @foreach ($boks as $bok)
                                    @php
                                        $kunciSel = $cpl->id.'/'.$bok->id;
                                        $sudahTerpetakan = $terpetakan->has($kunciSel);
                                        $terkunciReferensi = $terkunciBobot->has($kunciSel);
                                        $bisaDiedit = $editableSisi[$kunciSel] ?? false;
                                        $terkunci = $terkunciReferensi || ! $bisaDiedit;
                                        $titleTerkunci = ! $bisaDiedit
                                            ? 'Pasangan ini murni milik unit lain, tidak dapat diubah dari sini'
                                            : ($terkunciReferensi ? 'Hapus bobot pada interaksi CPL ↔ MK terlebih dahulu' : null);
                                    @endphp
                                    <td style="padding:8px;text-align:center;">
                                        <input
                                            type="checkbox"
                                            style="width:18px;height:18px;accent-color:#16a34a;{{ $terkunci ? 'cursor:not-allowed;opacity:.65;' : 'cursor:pointer;' }}"
                                            @if (! $terkunci)
                                                wire:click="toggle('{{ $cpl->id }}', '{{ $bok->id }}')"
                                            @endif
                                            @disabled($terkunci)
                                            @checked($sudahTerpetakan)
                                            @if ($titleTerkunci)
                                                title="{{ $titleTerkunci }}"
                                            @endif
                                        />
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

                {{-- Petunjuk constraint: collapsible + hanya jika ada sel terkunci bobot.
                     Tidak bersaing dengan matriks sebagai fokus utama. --}}
                @if ($terkunciBobot->isNotEmpty())
                    <details
                        data-silogy="cpl-bok-kunci-bobot-hint"
                        style="margin-top:10px;max-width:42rem;border-top:1px solid rgba(128,128,128,.18);padding-top:8px;"
                    >
                        <summary
                            style="cursor:pointer;list-style:none;display:inline-flex;align-items:center;gap:6px;
                                font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;
                                color:#92400e;opacity:.72;user-select:none;"
                        >
                            <span aria-hidden="true" style="font-size:12px;line-height:1;">ⓘ</span>
                            <span>{{ $terkunciBobot->count() }} irisan terkunci · CPL ↔ MK</span>
                        </summary>
                        <p style="margin:8px 0 0;font-size:12px;line-height:1.55;color:inherit;opacity:.62;max-width:36rem;">
                            Centang yang sudah berbobot di matriks CPL ↔ MK tidak bisa dilepas di sini.
                            Hapus bobotnya di Interaksi → CPL ↔ MK terlebih dahulu.
                        </p>
                    </details>
                    <style>
                        [data-silogy="cpl-bok-kunci-bobot-hint"] > summary::-webkit-details-marker { display: none; }
                        [data-silogy="cpl-bok-kunci-bobot-hint"][open] > summary { opacity: .9; }
                        @media (prefers-reduced-motion: no-preference) {
                            [data-silogy="cpl-bok-kunci-bobot-hint"][open] > p {
                                animation: silogy-cpl-bok-hint-in 220ms ease-out both;
                            }
                        }
                        @keyframes silogy-cpl-bok-hint-in {
                            from { opacity: 0; transform: translateY(-3px); }
                            to { opacity: .62; transform: translateY(0); }
                        }
                    </style>
                @endif
            @endif
        </div>
    </div>
</x-filament-panels::page>

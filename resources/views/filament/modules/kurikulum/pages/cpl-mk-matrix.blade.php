<x-filament-panels::page>
    <div
        data-silogy="banner-header-panel"
        style="border-radius:14px;overflow:hidden;border:1px solid rgba(128,128,128,.2);background:var(--gray-50, #f9fafb);"
    >
        @livewire('silogy.kurikulum-terpilih-banner', ['catatan' => null, 'sebagaiHeaderPanel' => true])

        <div style="padding:14px 16px 16px;">
            <p style="margin:0 0 12px;font-size:13px;line-height:1.55;opacity:.88;">
                Bobot kontribusi MK terhadap CPL (via BoK) yang diisi di bawah tersimpan pada kurikulum ini.
            </p>

            @if (! $kurikulum)
                <p style="font-size:13px;opacity:.75;">
                    Pilih kurikulum dari halaman Kurikulum terlebih dahulu.
                </p>
            @elseif ($mks->isEmpty() || $cplBoks->isEmpty())
                <p style="font-size:13px;opacity:.75;">
                    Matriks membutuhkan minimal satu mata kuliah dan satu pemetaan CPL–BoK (lihat menu Interaksi → CPL ↔ BoK) pada kurikulum terpilih.
                </p>
            @else
                <div style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;opacity:.55;margin-bottom:8px;">
                    Interaksi CPL ↔ MK (bobot)
                </div>
                <p style="margin:0 0 12px;font-size:12px;line-height:1.5;opacity:.8;">
                    Isi bobot (%) kontribusi tiap MK (baris) terhadap CPL via BoK (kolom). Kosongkan atau isi 0 untuk menghapus. Total per baris MK dihitung otomatis dan harus tepat 100%. MK/CPL bertanda † berasal dari adaptasi MK unit lain — sel yang murni milik unit lain bersifat baca-saja.
                </p>
                <div style="overflow-x:auto;" wire:key="matriks-cpl-mk">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="text-align:left;border-bottom:2px solid rgba(128,128,128,.35);">
                            <th class="cpl-mk-sticky cpl-mk-sticky-head" style="position:sticky;left:0;z-index:2;padding:8px;">MK \ CPL (via BoK)</th>
                            @foreach ($cplBoks as $cplBok)
                                @php($cplAsing = $cplBok->cpl && $cplBok->cpl->academic_unit_id !== $kurikulum->academic_unit_id)
                                @php($bokAsing = $cplBok->bok && $cplBok->bok->academic_unit_id !== $kurikulum->academic_unit_id)
                                <th style="padding:8px;text-align:center;white-space:nowrap;">
                                    @include('filament.modules.kurikulum.partials.kode-keterangan-trigger', [
                                        'jenis' => 'CPL',
                                        'kode' => $cplKodeMap[$cplBok->cpl_id] ?? $cplBok->cpl?->kode,
                                        'deskripsi' => $cplBok->cpl?->deskripsi,
                                        'meta' => $cplAsing
                                            ? 'Adaptasi dari '.($cplBok->cpl?->academicUnit?->nama_lengkap ?? '—')
                                            : null,
                                    ])
                                    @if ($cplAsing)
                                        <sup style="color:#b45309;">†</sup>
                                    @endif
                                    <br>
                                    <span style="font-weight:400;opacity:.7;">
                                        @include('filament.modules.kurikulum.partials.kode-keterangan-trigger', [
                                            'jenis' => 'BoK',
                                            'kode' => $bokKodeMap[$cplBok->bok_id] ?? $cplBok->bok?->kode,
                                            'nama' => $cplBok->bok?->nama,
                                            'deskripsi' => $cplBok->bok?->deskripsi,
                                            'meta' => $bokAsing
                                                ? 'Adaptasi dari '.($cplBok->bok?->academicUnit?->nama_lengkap ?? '—')
                                                : null,
                                        ])
                                        @if ($bokAsing)
                                            <sup style="color:#b45309;">†</sup>
                                        @endif
                                    </span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($mks as $mk)
                            <tr style="border-bottom:1px solid rgba(128,128,128,.2);">
                                <td class="cpl-mk-sticky cpl-mk-sticky-cell" style="position:sticky;left:0;z-index:1;padding:8px;">
                                    <strong>{{ $mk->nama }}</strong>
                                    @if ($mkAsalMap[$mk->id] ?? false)
                                        <sup style="color:#b45309;" title="Adaptasi dari unit lain">†</sup>
                                    @endif
                                    @php($total = (float) ($totals[$mk->id] ?? 0))
                                    @php($selisih = $total - 100.0)
                                    @php($warnaBadge = match (true) {
                                        $total <= 0 => '#9ca3af',
                                        abs($selisih) <= 0.01 => '#16a34a',
                                        $selisih > 0.01 => '#dc2626',
                                        default => '#d97706',
                                    })
                                    @php($perluNormalisasi = $total > 0 && abs($selisih) > 0.01)
                                    <br>
                                    <span style="display:inline-block;margin-top:4px;padding:1px 8px;border-radius:9999px;font-size:11px;font-weight:700;color:#fff;background:{{ $warnaBadge }};">
                                        Σ {{ rtrim(rtrim(number_format($total, 2, ',', '.'), '0'), ',') }}%
                                    </span>
                                    @if ($perluNormalisasi)
                                        <div style="margin-top:4px;">
                                            <x-filament::actions
                                                :actions="[($this->normalisasiBobotCplMkAction())(['mkId' => $mk->id])]"
                                            />
                                        </div>
                                    @endif
                                </td>
                                @foreach ($cplBoks as $cplBok)
                                    @php($kunciSel = $mk->id.'/'.$cplBok->id)
                                    @php($bisaDiedit = $cellEditable[$kunciSel] ?? false)
                                    @php($nilaiBobotSel = $bobots[$kunciSel] ?? '')
                                    <td style="padding:6px;text-align:center;">
                                        @if ($bisaDiedit)
                                            <input
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                style="width:74px;padding:4px 6px;border:1px solid rgba(128,128,128,.4);border-radius:6px;background:transparent;text-align:center;"
                                                value="{{ $nilaiBobotSel }}"
                                                wire:key="bobot-input-{{ $mk->id }}-{{ $cplBok->id }}-{{ $nilaiBobotSel }}"
                                                wire:change="updateBobot('{{ $mk->id }}', '{{ $cplBok->id }}', $event.target.value)"
                                            />
                                        @else
                                            <span
                                                style="display:inline-block;width:74px;padding:4px 6px;opacity:.65;cursor:not-allowed;"
                                                title="Sel ini murni milik unit lain, tidak dapat diubah dari sini"
                                            >{{ $nilaiBobotSel !== '' ? rtrim(rtrim(number_format((float) $nilaiBobotSel, 2, ',', '.'), '0'), ',').'%' : '—' }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

<style>
        .cpl-mk-sticky {
            border-right: 1px solid rgba(128, 128, 128, 0.25);
            box-shadow: 4px 0 8px -4px rgba(0, 0, 0, 0.12);
            max-width: 250px;
        }

        .cpl-mk-sticky-head {
            background: #f4f4f5;
        }

        .cpl-mk-sticky-cell {
            background: #ffffff;
            white-space: normal;
            overflow-wrap: break-word;
        }

        .dark .cpl-mk-sticky-head {
            background: #27272a;
        }

        .dark .cpl-mk-sticky-cell {
            background: #18181b;
        }
    </style>
</x-filament-panels::page>

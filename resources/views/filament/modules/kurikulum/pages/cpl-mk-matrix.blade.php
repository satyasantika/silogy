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
                    Isi bobot (%) kontribusi tiap MK (baris) terhadap CPL via BoK (kolom). Total per baris MK dihitung otomatis dan tidak boleh melebihi 100%.
                    Bila rekap sudah 100%, sel CPL yang masih kosong dikunci (readonly) — turunkan dulu bobot CPL yang sudah terisi agar ada sisa kuota, baru isi CPL lain.
                    Kosongkan atau isi 0 untuk menghapus. MK/CPL bertanda † berasal dari adaptasi MK unit lain — sel yang murni milik unit lain bersifat baca-saja.
                </p>
                <div
                    style="overflow-x:auto;"
                    wire:key="matriks-cpl-mk"
                    x-data
                    x-init="
                        window.silogyRekapCplMk = window.silogyRekapCplMk || function (values, target, mkId) {
                            return {
                                values: Object.assign({}, values || {}),
                                target: Number(target) || 100,
                                mkId: mkId,
                                rekapTotal: 0,
                                init() {
                                    const terapkanSnapshot = () => {
                                        const mentah = this.$el?.getAttribute('data-rekap-snapshot');
                                        if (! mentah) {
                                            this.refreshRekap();
                                            return;
                                        }
                                        try {
                                            this.values = Object.assign({}, JSON.parse(mentah));
                                        } catch (e) {
                                            this.refreshRekap();
                                            return;
                                        }
                                        this.refreshRekap();
                                    };

                                    terapkanSnapshot();
                                    this.$el.addEventListener('livewire:morph', terapkanSnapshot);
                                    if (window.Livewire?.hook) {
                                        Livewire.hook('commit', ({ succeed }) => {
                                            succeed(() => queueMicrotask(terapkanSnapshot));
                                        });
                                    }
                                },
                                round1(nilai) {
                                    return Math.round((Number(nilai) || 0) * 10) / 10;
                                },
                                keSepersepuluh(nilai) {
                                    if (nilai === null || nilai === undefined || nilai === '') {
                                        return 0;
                                    }
                                    return Math.round(this.round1(nilai) * 10);
                                },
                                hitungTotal() {
                                    return Object.values(this.values).reduce((sum, nilai) => sum + this.keSepersepuluh(nilai), 0) / 10;
                                },
                                refreshRekap() {
                                    this.rekapTotal = this.hitungTotal();
                                },
                                get total() { return this.rekapTotal; },
                                get selisih() { return this.round1(this.rekapTotal - this.target); },
                                get pas() { return this.selisih === 0; },
                                get warna() {
                                    if (this.rekapTotal <= 0) return '#9ca3af';
                                    if (this.pas) return '#16a34a';
                                    if (this.selisih > 0) return '#dc2626';
                                    return '#d97706';
                                },
                                get perluNorm() {
                                    return this.rekapTotal > 0 && !this.pas;
                                },
                                get kuotaPenuh() {
                                    return this.round1(this.target - this.rekapTotal) <= 0;
                                },
                                formatBobot(nilai) {
                                    const angka = this.round1(nilai);
                                    if (angka === 0) return '0';
                                    return String(angka).replace('.', ',');
                                },
                                nilaiSel(cplBokId) {
                                    const nilai = this.values[cplBokId];
                                    if (nilai === null || nilai === undefined || nilai === '') {
                                        return 0;
                                    }
                                    return this.round1(nilai);
                                },
                                maxUntuk(cplBokId) {
                                    let lain = 0;
                                    Object.entries(this.values).forEach(([id, nilai]) => {
                                        if (id === String(cplBokId)) {
                                            return;
                                        }
                                        lain += this.keSepersepuluh(nilai);
                                    });
                                    return Math.max(0, (Math.round(this.target * 10) - lain) / 10);
                                },
                                terkunci(cplBokId) {
                                    return this.nilaiSel(cplBokId) <= 0 && this.kuotaPenuh;
                                },
                                setLokal(cplBokId, mentah, el) {
                                    const teks = String(mentah ?? '').trim();
                                    const next = Object.assign({}, this.values);
                                    if (teks === '' || Number.isNaN(Number(teks))) {
                                        next[cplBokId] = null;
                                        this.values = next;
                                        this.refreshRekap();
                                        return null;
                                    }
                                    let angka = this.round1(teks);
                                    const maks = this.maxUntuk(cplBokId);
                                    if (angka > maks) {
                                        angka = maks;
                                    }
                                    if (angka <= 0) {
                                        next[cplBokId] = null;
                                        this.values = next;
                                        this.refreshRekap();
                                        if (el) {
                                            el.value = '';
                                        }
                                        return '';
                                    }
                                    next[cplBokId] = angka;
                                    this.values = next;
                                    this.refreshRekap();
                                    const tampil = String(angka);
                                    if (el && String(el.value).trim() !== tampil) {
                                        el.value = tampil;
                                    }
                                    return tampil;
                                },
                                simpan(cplBokId, mentah, el) {
                                    this.setLokal(cplBokId, mentah, el);
                                    const kirim = this.nilaiSel(cplBokId) > 0 ? String(this.values[cplBokId]) : '';
                                    if (el) {
                                        el.value = kirim;
                                    }
                                    this.$wire.updateBobot(this.mkId, cplBokId, kirim);
                                },
                            };
                        };
                    "
                >
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="text-align:left;border-bottom:2px solid rgba(128,128,128,.35);">
                            <th class="cpl-mk-sticky cpl-mk-sticky-head" style="position:sticky;left:0;z-index:2;padding:8px;">MK \ CPL (via BoK)</th>
                            @foreach ($cplBoks as $cplBok)
                                @php
                                    $cplAsing = $cplBok->cpl && $cplBok->cpl->academic_unit_id !== $kurikulum->academic_unit_id;
                                    $bokAsing = $cplBok->bok && $cplBok->bok->academic_unit_id !== $kurikulum->academic_unit_id;
                                @endphp
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
                            @php
                                $total = round((float) ($totals[$mk->id] ?? 0), \App\Modules\CPL\Models\CplMk::DESIMAL_BOBOT);
                                $selisih = round($total - 100.0, \App\Modules\CPL\Models\CplMk::DESIMAL_BOBOT);
                                $pasRekap = \App\Modules\CPL\Models\CplMk::rekapPas($total);
                                $warnaBadge = match (true) {
                                    $total <= 0 => '#9ca3af',
                                    $pasRekap => '#16a34a',
                                    $selisih > 0 => '#dc2626',
                                    default => '#d97706',
                                };
                                $perluNormalisasi = $total > 0 && ! $pasRekap;
                                $nilaiAwal = [];
                                foreach ($cplBoks as $cplAwal) {
                                    $kunciSel = $mk->id.'/'.$cplAwal->id;
                                    $nilaiAwal[$cplAwal->id] = $bobots->has($kunciSel)
                                        ? round((float) $bobots[$kunciSel], 1)
                                        : null;
                                }
                                $nilaiAwalJson = json_encode($nilaiAwal, JSON_THROW_ON_ERROR);
                                $totalTampil = rtrim(rtrim(number_format($total, 1, ',', '.'), '0'), ',');
                            @endphp
                            <tr
                                wire:key="baris-mk-{{ $mk->id }}-{{ md5($nilaiAwalJson) }}"
                                data-silogy="baris-mk-cpl"
                                data-mk="{{ $mk->id }}"
                                data-rekap-snapshot='@json($nilaiAwal)'
                                data-rekap-total="{{ $total }}"
                                style="border-bottom:1px solid rgba(128,128,128,.2);"
                                x-data="silogyRekapCplMk({{ $nilaiAwalJson }}, 100, '{{ $mk->id }}')"
                            >
                                <td class="cpl-mk-sticky cpl-mk-sticky-cell" style="position:sticky;left:0;z-index:1;padding:8px;">
                                    <strong>{{ $mk->nama }}</strong>
                                    @if ($mkAsalMap[$mk->id] ?? false)
                                        <sup style="color:#b45309;" title="Adaptasi dari unit lain">†</sup>
                                    @endif
                                    <br>
                                    <span
                                        data-silogy="rekap-bobot-cpl-mk"
                                        data-total="{{ $total }}"
                                        style="display:inline-block;margin-top:4px;padding:1px 8px;border-radius:9999px;font-size:11px;font-weight:700;color:#fff;background:{{ $warnaBadge }};"
                                        :style="'display:inline-block;margin-top:4px;padding:1px 8px;border-radius:9999px;font-size:11px;font-weight:700;color:#fff;background:' + warna"
                                        :data-total="rekapTotal"
                                        x-text="'Σ ' + formatBobot(rekapTotal) + '%'"
                                    >Σ {{ $totalTampil }}%</span>
                                    <div
                                        data-silogy="normalisasi-cpl-mk"
                                        style="margin-top:4px;{{ $perluNormalisasi ? '' : 'display:none;' }}"
                                        x-cloak
                                        x-show="perluNorm"
                                    >
                                        <x-filament::actions
                                            :actions="[($this->normalisasiBobotCplMkAction())(['mkId' => $mk->id])]"
                                        />
                                    </div>
                                </td>
                                @foreach ($cplBoks as $cplBok)
                                    @php
                                        $kunciSel = $mk->id.'/'.$cplBok->id;
                                        $bisaDiedit = $cellEditable[$kunciSel] ?? false;
                                        $nilaiBobotSel = $bobots[$kunciSel] ?? '';
                                        $nilaiNumSel = $nilaiBobotSel === '' ? 0.0 : round((float) $nilaiBobotSel, 1);
                                        $nilaiBobotSel = $nilaiBobotSel === '' ? '' : (abs($nilaiNumSel - round($nilaiNumSel)) < 0.05
                                            ? (string) (int) round($nilaiNumSel)
                                            : number_format($nilaiNumSel, 1, '.', ''));
                                        $sisaKuotaBaris = round(max(0.0, 100.0 - $total), 1);
                                        $terkunciSSR = $bisaDiedit && $nilaiNumSel <= 0 && $sisaKuotaBaris <= 0;
                                        $maxSelSSR = max(0.0, round(100.0 - ($total - $nilaiNumSel), 1));
                                    @endphp
                                    <td style="padding:6px;text-align:center;">
                                        @if ($bisaDiedit)
                                            <input
                                                type="number"
                                                min="0"
                                                max="{{ $maxSelSSR }}"
                                                step="0.1"
                                                data-silogy="bobot-sel"
                                                data-cplbok="{{ $cplBok->id }}"
                                                @if ($terkunciSSR) data-terkunci="1" readonly @endif
                                                :data-terkunci="terkunci('{{ $cplBok->id }}') ? '1' : null"
                                                :readonly="terkunci('{{ $cplBok->id }}')"
                                                :max="maxUntuk('{{ $cplBok->id }}')"
                                                :title="terkunci('{{ $cplBok->id }}')
                                                    ? 'Rekap MK sudah 100% — turunkan bobot CPL lain terlebih dahulu'
                                                    : 'Maks. ' + maxUntuk('{{ $cplBok->id }}') + '%'"
                                                :style="terkunci('{{ $cplBok->id }}')
                                                    ? 'width:74px;padding:4px 6px;border:1px solid rgba(128,128,128,.35);border-radius:6px;background:rgba(128,128,128,.12);text-align:center;cursor:not-allowed;opacity:.65;'
                                                    : 'width:74px;padding:4px 6px;border:1px solid rgba(128,128,128,.4);border-radius:6px;background:transparent;text-align:center;'"
                                                style="width:74px;padding:4px 6px;border:1px solid rgba(128,128,128,.4);border-radius:6px;background:transparent;text-align:center;{{ $terkunciSSR ? 'cursor:not-allowed;opacity:.65;background:rgba(128,128,128,.12);' : '' }}"
                                                value="{{ $nilaiBobotSel }}"
                                                wire:key="bobot-input-{{ $mk->id }}-{{ $cplBok->id }}-{{ $nilaiBobotSel }}"
                                                @input="setLokal('{{ $cplBok->id }}', $event.target.value, $event.target)"
                                                @change="simpan('{{ $cplBok->id }}', $event.target.value, $event.target)"
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

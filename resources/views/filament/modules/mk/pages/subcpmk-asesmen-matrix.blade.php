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
            @elseif ($tampilkanFilterSemester && $semesterOptions === [])
                <p style="font-size:13px;opacity:.75;">
                    Tambahkan data semester terlebih dahulu agar filter operasional tersedia.
                </p>
            @else
                @if ($tampilkanFilterSemester)
                    <div style="margin-bottom:14px;max-width:420px;">
                        <label for="semester-terpilih" style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">
                            Semester
                        </label>
                        <select
                            id="semester-terpilih"
                            wire:model.live="semesterTerpilihId"
                            style="width:100%;padding:8px 10px;border:1px solid rgba(128,128,128,.4);border-radius:8px;background:transparent;font-size:13px;"
                        >
                            @foreach ($semesterOptions as $semesterId => $semesterNama)
                                <option value="{{ $semesterId }}">{{ $semesterNama }}</option>
                            @endforeach
                        </select>
                        <p style="margin-top:6px;font-size:12px;opacity:.75;">Semester diambil dari master semester.</p>
                    </div>
                @endif

                @if (! $adaAsesmenSemua || $subcpmks->isEmpty())
                    <p style="font-size:13px;opacity:.75;">
                        Matriks membutuhkan minimal satu asesmen dan satu Sub-CPMK pada semester terpilih.
                    </p>
                @else
                    <div style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;opacity:.55;margin-bottom:8px;">
                        Interaksi Sub-CPMK ↔ Asesmen (bobot)
                    </div>
                    <p style="margin:0 0 12px;font-size:12px;line-height:1.5;opacity:.8;">
                        Isi bobot (%) kontribusi tiap asesmen (baris) terhadap Sub-CPMK (kolom). Total per baris tidak boleh melebihi bobot Asesmen.
                        Bila kuota sudah penuh, kolom Sub-CPMK yang masih kosong dikunci (readonly) — turunkan dulu bobot yang sudah terisi agar ada sisa kuota.
                        Kosongkan atau isi 0 untuk menghapus interaksi. Total per asesmen dihitung otomatis.
                    </p>

                    <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
                        <div style="flex:1 1 220px;min-width:200px;">
                            <label for="asesmen-search" style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">
                                Cari kode / nama tugas
                            </label>
                            <input
                                type="text"
                                id="asesmen-search"
                                wire:model.live.debounce.400ms="search"
                                placeholder="Cari kode atau nama tugas..."
                                style="width:100%;padding:8px 10px;border:1px solid rgba(128,128,128,.4);border-radius:8px;background:transparent;font-size:13px;"
                            />
                        </div>
                        <div style="flex:1 1 200px;min-width:180px;">
                            <label for="asesmen-filter-evaluasi" style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">
                                Filter komponen penilaian
                            </label>
                            <select
                                id="asesmen-filter-evaluasi"
                                wire:model.live="filterEvaluasiId"
                                style="width:100%;padding:8px 10px;border:1px solid rgba(128,128,128,.4);border-radius:8px;background:transparent;font-size:13px;"
                            >
                                <option value="">Semua komponen</option>
                                @foreach ($evaluasiOptions as $evaluasiId => $evaluasiNama)
                                    <option value="{{ $evaluasiId }}">{{ $evaluasiNama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div style="flex:1 1 180px;min-width:160px;">
                            <label for="asesmen-sort-by" style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">
                                Urutkan berdasarkan
                            </label>
                            <select
                                id="asesmen-sort-by"
                                wire:model.live="sortBy"
                                style="width:100%;padding:8px 10px;border:1px solid rgba(128,128,128,.4);border-radius:8px;background:transparent;font-size:13px;"
                            >
                                <option value="kode">Kode tugas</option>
                                <option value="evaluasi">Komponen penilaian</option>
                                <option value="total">Total persen penilaian</option>
                            </select>
                        </div>
                        <div style="flex:0 0 140px;min-width:120px;">
                            <label for="asesmen-sort-direction" style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">
                                Arah urutan
                            </label>
                            <select
                                id="asesmen-sort-direction"
                                wire:model.live="sortDirection"
                                style="width:100%;padding:8px 10px;border:1px solid rgba(128,128,128,.4);border-radius:8px;background:transparent;font-size:13px;"
                            >
                                <option value="asc">Naik (A–Z)</option>
                                <option value="desc">Turun (Z–A)</option>
                            </select>
                        </div>
                    </div>

                    @if ($asesmen->isEmpty())
                        <div style="padding:16px;text-align:center;font-size:13px;opacity:.7;">
                            Tidak ada asesmen yang sesuai dengan pencarian/filter saat ini.
                        </div>
                    @else
                        <div style="overflow-x:auto;" wire:key="matriks-subcpmk-asesmen"
                            x-data
                            x-init="
                                window.silogyRekapAsesmen = window.silogyRekapAsesmen || function (values, target, komponenId) {
                                    return {
                                        values: Object.assign({}, values || {}),
                                        target: Number(target) || 0,
                                        komponenId: komponenId,
                                        init() {
                                            const terapkanSnapshot = () => {
                                                const mentah = this.$el?.getAttribute('data-rekap-snapshot');
                                                if (! mentah) {
                                                    return;
                                                }
                                                try {
                                                    this.values = Object.assign({}, JSON.parse(mentah));
                                                } catch (e) {
                                                    return;
                                                }
                                            };

                                            terapkanSnapshot();
                                            this.$el.addEventListener('livewire:morph', terapkanSnapshot);
                                            if (window.Livewire?.hook) {
                                                Livewire.hook('commit', ({ succeed }) => {
                                                    succeed(() => queueMicrotask(terapkanSnapshot));
                                                });
                                            }
                                        },
                                        get total() {
                                            return Object.values(this.values).reduce((sum, nilai) => sum + (Number(nilai) || 0), 0);
                                        },
                                        get selisih() { return this.total - this.target; },
                                        get warna() {
                                            if (this.total <= 0) return '#9ca3af';
                                            if (Math.abs(this.selisih) <= 0.01) return '#16a34a';
                                            if (this.selisih > 0.01) return '#dc2626';
                                            return '#d97706';
                                        },
                                        get perluNorm() {
                                            return this.total > 0 && Math.abs(this.selisih) > 0.01;
                                        },
                                        get kuotaPenuh() {
                                            return (this.target - this.total) <= 0.01;
                                        },
                                        formatBobot(nilai) {
                                            const angka = Number(nilai) || 0;
                                            if (angka === 0) return '0';
                                            return String(Math.round(angka * 100) / 100).replace('.', ',');
                                        },
                                        nilaiSel(subId) {
                                            const nilai = this.values[subId];
                                            if (nilai === null || nilai === undefined || nilai === '') {
                                                return 0;
                                            }
                                            return Number(nilai) || 0;
                                        },
                                        maxUntuk(subId) {
                                            const maks = this.target - (this.total - this.nilaiSel(subId));
                                            return Math.max(0, Math.round(maks * 100) / 100);
                                        },
                                        terkunci(subId) {
                                            return this.nilaiSel(subId) <= 0 && this.kuotaPenuh;
                                        },
                                        setLokal(subId, mentah) {
                                            const teks = String(mentah ?? '').trim();
                                            if (teks === '' || Number.isNaN(Number(teks))) {
                                                this.values[subId] = null;
                                                return null;
                                            }
                                            let angka = Number(teks);
                                            const maks = this.maxUntuk(subId);
                                            if (angka > maks) {
                                                angka = maks;
                                            }
                                            if (angka <= 0) {
                                                this.values[subId] = null;
                                                return '';
                                            }
                                            this.values[subId] = angka;
                                            return String(angka);
                                        },
                                        simpan(subId, mentah, el) {
                                            this.setLokal(subId, mentah);
                                            const kirim = this.nilaiSel(subId) > 0 ? String(this.values[subId]) : '';
                                            if (el) {
                                                el.value = kirim;
                                            }
                                            this.$wire.updateBobot(this.komponenId, subId, kirim);
                                        },
                                    };
                                };
                            "
                        >
                            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                                <thead>
                                    <tr style="text-align:left;border-bottom:2px solid rgba(128,128,128,.35);">
                                        <th style="position:sticky;left:0;z-index:2;max-width:300px;padding:8px;background:rgba(128,128,128,.08);">Asesmen \ Sub-CPMK</th>
                                        @foreach ($subcpmks as $subcpmk)
                                            <th style="padding:8px;text-align:center;white-space:nowrap;">
                                                @include('filament.modules.kurikulum.partials.kode-keterangan-trigger', [
                                                    'jenis' => 'Sub-CPMK',
                                                    'kode' => $subcpmk->kode,
                                                    'deskripsi' => $subcpmk->deskripsi,
                                                ])
                                                <br>
                                                <span style="font-weight:400;opacity:.7;">
                                                    @if ($subcpmk->mkCpmk?->cpmk)
                                                        @include('filament.modules.kurikulum.partials.kode-keterangan-trigger', [
                                                            'jenis' => 'CPMK',
                                                            'kode' => $subcpmk->mkCpmk->cpmk->kode,
                                                            'deskripsi' => $subcpmk->mkCpmk->cpmk->deskripsi,
                                                        ])
                                                    @else
                                                        —
                                                    @endif
                                                </span>
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($asesmen as $komponen)
                                        @php
                                            $total = (float) ($totals[$komponen->id] ?? 0);
                                            $bobotAsesmen = (float) $komponen->bobot;
                                            $selisih = $total - $bobotAsesmen;
                                            $warnaBadge = match (true) {
                                                $total <= 0 => '#9ca3af',
                                                abs($selisih) <= 0.01 => '#16a34a',
                                                $selisih > 0.01 => '#dc2626',
                                                default => '#d97706',
                                            };
                                            $perluNormalisasi = $total > 0 && abs($selisih) > 0.01;
                                            $nilaiAwal = [];
                                            foreach ($subcpmks as $subAwal) {
                                                $kunciSel = $komponen->id.'/'.$subAwal->id;
                                                $nilaiAwal[$subAwal->id] = $bobots->has($kunciSel)
                                                    ? (float) $bobots[$kunciSel]
                                                    : null;
                                            }
                                            $nilaiAwalJson = json_encode($nilaiAwal, JSON_THROW_ON_ERROR);
                                            $totalTampil = rtrim(rtrim(number_format($total, 2, ',', '.'), '0'), ',');
                                            $bobotAsesmenTampil = rtrim(rtrim(number_format($bobotAsesmen, 2, ',', '.'), '0'), ',');
                                        @endphp
                                        <tr
                                            wire:key="baris-asesmen-{{ $komponen->id }}-{{ md5($nilaiAwalJson) }}"
                                            data-rekap-snapshot='@json($nilaiAwal)'
                                            data-rekap-total="{{ $total }}"
                                            style="border-bottom:1px solid rgba(128,128,128,.2);"
                                            x-data="silogyRekapAsesmen({{ $nilaiAwalJson }}, {{ $bobotAsesmen }}, '{{ $komponen->id }}')"
                                        >
                                            <td style="position:sticky;left:0;z-index:1;max-width:300px;padding:8px;background:rgba(128,128,128,.04);white-space:normal;overflow-wrap:break-word;">
                                                <span style="display:block;font-size:11px;font-weight:600;opacity:.75;">{{ $komponen->kode ?? '—' }}</span>
                                                <strong style="display:block;">{{ $komponen->nama }}</strong>
                                                <span style="display:block;font-size:11px;opacity:.7;">
                                                    {{ $komponen->evaluasi?->nama ?? '—' }}
                                                </span>
                                                <span
                                                    data-silogy="rekap-bobot-asesmen"
                                                    data-total="{{ $total }}"
                                                    style="display:inline-block;margin-top:4px;padding:1px 8px;border-radius:9999px;font-size:11px;font-weight:700;color:#fff;background:{{ $warnaBadge }};"
                                                    :style="'display:inline-block;margin-top:4px;padding:1px 8px;border-radius:9999px;font-size:11px;font-weight:700;color:#fff;background:' + warna"
                                                    :data-total="total"
                                                >
                                                    Σ <span x-text="formatBobot(total)" data-silogy="rekap-total-angka">{{ $totalTampil }}</span>%
                                                    /
                                                    {{ $bobotAsesmenTampil }}%
                                                </span>
                                                @if ($perluNormalisasi)
                                                    <div style="margin-top:4px;" x-show="perluNorm">
                                                        <x-filament::actions
                                                            :actions="[($this->normalisasiBobotAsesmenAction())(['komponenId' => $komponen->id])]"
                                                        />
                                                    </div>
                                                @endif
                                            </td>
                                            @foreach ($subcpmks as $subcpmk)
                                                @php
                                                    $nilaiBobotSel = $bobots[$komponen->id.'/'.$subcpmk->id] ?? '';
                                                    $nilaiNumSel = $nilaiBobotSel === '' ? 0.0 : (float) $nilaiBobotSel;
                                                    $sisaKuotaBaris = max(0.0, $bobotAsesmen - $total);
                                                    $terkunciSSR = $nilaiNumSel <= 0 && $sisaKuotaBaris <= 0.01;
                                                    $maxSelSSR = max(0.0, round($bobotAsesmen - ($total - $nilaiNumSel), 2));
                                                @endphp
                                                <td style="padding:6px;text-align:center;">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max="{{ $maxSelSSR }}"
                                                        step="0.1"
                                                        data-silogy="bobot-sel"
                                                        data-subcpmk="{{ $subcpmk->id }}"
                                                        @if ($terkunciSSR) data-terkunci="1" readonly @endif
                                                        :data-terkunci="terkunci('{{ $subcpmk->id }}') ? '1' : null"
                                                        :readonly="terkunci('{{ $subcpmk->id }}')"
                                                        :max="maxUntuk('{{ $subcpmk->id }}')"
                                                        :title="terkunci('{{ $subcpmk->id }}')
                                                            ? 'Kuota bobot asesmen sudah penuh — turunkan bobot Sub-CPMK lain terlebih dahulu'
                                                            : 'Maks. ' + maxUntuk('{{ $subcpmk->id }}') + '%'"
                                                        :style="terkunci('{{ $subcpmk->id }}')
                                                            ? 'width:74px;padding:4px 6px;border:1px solid rgba(128,128,128,.35);border-radius:6px;background:rgba(128,128,128,.12);text-align:center;cursor:not-allowed;opacity:.65;'
                                                            : 'width:74px;padding:4px 6px;border:1px solid rgba(128,128,128,.4);border-radius:6px;background:transparent;text-align:center;'"
                                                        style="width:74px;padding:4px 6px;border:1px solid rgba(128,128,128,.4);border-radius:6px;background:transparent;text-align:center;{{ $terkunciSSR ? 'cursor:not-allowed;opacity:.65;background:rgba(128,128,128,.12);' : '' }}"
                                                        value="{{ $nilaiBobotSel }}"
                                                        wire:key="bobot-input-{{ $komponen->id }}-{{ $subcpmk->id }}-{{ $nilaiBobotSel }}"
                                                        @input="(() => { const v = setLokal('{{ $subcpmk->id }}', $event.target.value); if (v !== null && String($event.target.value).trim() !== String(v)) { $event.target.value = v === '' ? '' : v; } })()"
                                                        @change="simpan('{{ $subcpmk->id }}', $event.target.value, $event.target)"
                                                    />
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            @endif
        </div>
    </div>
</x-filament-panels::page>

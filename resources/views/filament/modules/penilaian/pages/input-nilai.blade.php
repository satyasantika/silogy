<x-filament-panels::page>
    <x-filament::section icon="heroicon-o-academic-cap" heading="Pilih Kelas MK">
        <div style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px;">
            <div style="font-size:13px;line-height:1.7;">
                <div>
                    <span style="opacity:.7;">Mata kuliah terpilih (dari Penilaian):</span>
                    <strong>{{ $this->mkTerpilih?->nama ?? '—' }}</strong>
                </div>
                <div>
                    <span style="opacity:.7;">Semester:</span>
                    <strong>{{ $this->semesterTerpilih }}</strong>
                </div>
            </div>

            <a
                href="{{ \App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource::getUrl('index') }}"
                style="font-size:12px;font-weight:600;text-decoration:underline;white-space:nowrap;"
            >
                ← Pilih mata kuliah lain
            </a>
        </div>

        @if (! $this->mkTerpilih)
            <p style="font-size:13px;opacity:.75;">
                Anda belum memiliki kelas yang diampu pada semester ini.
            </p>
        @elseif (empty($this->kelasCards))
            <p style="font-size:13px;opacity:.75;">
                Anda tidak memiliki kelas untuk mata kuliah ini pada semester terpilih.
            </p>
        @else
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                @foreach ($this->kelasCards as $kelas)
                    @php
                        $aktif = $kelasMkId === $kelas['id'];
                        $sudahDinilai = $kelas['sudah_dinilai'];
                        $border = $aktif ? '#2563eb' : ($sudahDinilai ? '#86efac' : '#fcd34d');
                        $background = $aktif ? '#dbeafe' : ($sudahDinilai ? '#dcfce7' : '#fef3c7');
                        $color = $sudahDinilai ? '#166534' : '#92400e';
                    @endphp
                    <button
                        type="button"
                        wire:click="pilihKelas('{{ $kelas['id'] }}')"
                        wire:key="kelas-card-{{ $kelas['id'] }}"
                        style="cursor:pointer;text-align:left;min-width:150px;padding:10px 14px;border-radius:10px;
                            border:2px solid {{ $border }};background:{{ $background }};color:{{ $color }};"
                    >
                        <span style="display:block;font-weight:700;font-size:13px;">Kelas {{ $kelas['kode_kelas'] }}</span>
                        <span style="display:block;font-size:12px;margin-top:2px;">
                            {{ $kelas['jumlah_mahasiswa'] }} mhs
                            @if ($sudahDinilai)
                                · rata-rata {{ $kelas['rata_rata'] }}
                            @else
                                · Belum dinilai
                            @endif
                        </span>
                    </button>
                @endforeach
            </div>

            <div style="margin-top:12px;padding:10px 12px;border-radius:8px;background:rgba(128,128,128,.08);font-size:12px;">
                <strong>Seluruh kelas pada MK ini:</strong>
                {{ $this->ringkasanSeluruhKelas['jumlah_mahasiswa'] }} mahasiswa
                @if ($this->ringkasanSeluruhKelas['sudah_dinilai'])
                    · rata-rata {{ $this->ringkasanSeluruhKelas['rata_rata'] }}
                @else
                    · Belum dinilai
                @endif
            </div>
        @endif
    </x-filament::section>

    @if ($kelasMkId)
        @if ($penugasanBelumSelesai)
            <div style="margin-top:16px;">
            <x-filament::section
                icon="heroicon-o-clock"
                heading="Penugasan belum selesai"
            >
                Koordinator MK belum menyelesaikan penugasan kelas ini
                (komponen penilaian harus berbobot total 100% dan seluruhnya
                terpetakan ke Sub-CPMK). Penilaian akan terbuka setelahnya.
            </x-filament::section>
            </div>
        @elseif (count($columns) === 0 || count($rows) === 0)
            <div style="margin-top:16px;">
            <x-filament::section
                icon="heroicon-o-information-circle"
                heading="Data belum lengkap"
            >
                Belum ada mahasiswa terdaftar atau komponen penilaian pada kelas ini.
            </x-filament::section>
            </div>
        @else
            <div style="margin-top:16px;">
            <x-filament::section
                icon="heroicon-o-table-cells"
                heading="Penilaian"
                description="Kolom asesmen mengikuti komponen penilaian yang diset koordinator MK untuk kelas terpilih. Baris = mahasiswa, kolom = Sub-CPMK × asesmen. Isi nilai 0–100 lalu klik Simpan. Gunakan tombol Salin matriks / Tempel dari Excel di kanan atas untuk Excel."
            >
                <div style="overflow-x:auto;" wire:key="matriks-input-nilai-{{ $kelasMkId }}">
                    <table style="width:100%;min-width:max-content;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="text-align:left;border-bottom:2px solid rgba(128,128,128,.35);">
                                <th style="position:sticky;left:0;z-index:2;padding:8px 10px;min-width:180px;background:rgba(128,128,128,.08);">
                                    Mahasiswa
                                </th>
                                @foreach ($columns as $column)
                                    <th
                                        style="padding:8px 6px;text-align:center;white-space:nowrap;min-width:88px;"
                                        title="{{ $column['label'] }}"
                                    >
                                        <span style="display:block;font-weight:700;">{{ $column['asesmen'] }}</span>
                                        <span style="display:block;font-size:11px;font-weight:400;opacity:.75;margin-top:2px;">
                                            {{ $column['subcpmk'] }}
                                        </span>
                                        @if ($column['evaluasi'])
                                            <span style="display:block;font-size:10px;font-weight:400;opacity:.6;margin-top:1px;">
                                                {{ $column['evaluasi'] }}
                                            </span>
                                        @endif
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr
                                    wire:key="row-{{ $row['id'] }}"
                                    style="border-bottom:1px solid rgba(128,128,128,.2);"
                                >
                                    <td style="position:sticky;left:0;z-index:1;padding:8px 10px;background:rgba(128,128,128,.04);">
                                        <strong>{{ $row['nama'] }}</strong>
                                        <span style="display:block;font-size:11px;opacity:.7;margin-top:2px;">
                                            {{ $row['nim'] }}
                                        </span>
                                    </td>
                                    @foreach ($columns as $column)
                                        <td
                                            style="padding:6px;text-align:center;"
                                            wire:key="cell-{{ $row['id'] }}-{{ $column['id'] }}"
                                        >
                                            <input
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                wire:model="nilai.{{ $row['id'] }}.{{ $column['id'] }}"
                                                style="width:74px;padding:4px 6px;border:1px solid rgba(128,128,128,.4);border-radius:6px;background:transparent;text-align:center;"
                                                placeholder="—"
                                            />
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="margin-top:16px;display:flex;flex-wrap:wrap;align-items:center;gap:12px;">
                    <x-filament::button
                        wire:click="save"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        color="primary"
                        icon="heroicon-o-check"
                    >
                        <span wire:loading.remove wire:target="save">Simpan</span>
                        <span wire:loading wire:target="save">Menyimpan…</span>
                    </x-filament::button>

                    @if ($showKalkulasiBadge)
                        <x-filament::badge color="info">
                            Kalkulasi CPL dijalankan…
                        </x-filament::badge>
                    @endif
                </div>
            </x-filament::section>
            </div>
        @endif
    @endif
</x-filament-panels::page>

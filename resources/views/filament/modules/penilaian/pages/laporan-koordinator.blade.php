<x-filament-panels::page>
    <div
        data-silogy="banner-header-panel"
        data-silogy-laporan-koordinator-panel
        style="border-radius:14px;overflow:hidden;border:1px solid rgba(128,128,128,.2);background:var(--gray-50, #f9fafb);"
    >
        @include('filament.modules.mk.partials.mk-terpilih-banner-inner', [
            'catatan' => null,
            'sebagaiHeaderPanel' => true,
        ])

        <div style="padding:14px 16px 16px;">
            @if (! $mkTerpilih)
                <p style="font-size:13px;opacity:.75;">
                    Pilih mata kuliah dari halaman Mata Kuliah terlebih dahulu.
                </p>
            @else
                @if ($tampilkanFilterSemester)
                    <div style="margin-bottom:16px;max-width:420px;" data-silogy="langkah-semester">
                        <label for="semester-terpilih" style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:700;margin-bottom:6px;color:#0b3914;">
                            <span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:999px;background:#007000;color:#fff;font-size:11px;">1</span>
                            Pilih semester
                        </label>
                        <select
                            id="semester-terpilih"
                            wire:model.live="semesterTerpilihId"
                            style="width:100%;padding:8px 10px;border:1px solid rgba(128,128,128,.4);border-radius:8px;background:#fff;font-size:13px;"
                        >
                            @foreach ($semesterOptions as $semesterId => $semesterNama)
                                <option value="{{ $semesterId }}">{{ $semesterNama }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div data-silogy="langkah-kelas" class="silogy-langkah-batas" style="margin-bottom:16px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;font-size:13px;font-weight:700;color:#0b3914;">
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:999px;background:#007000;color:#fff;font-size:11px;">2</span>
                        Pilih kelas
                    </div>

                    @if (empty($this->kelasCards))
                        <p style="font-size:13px;opacity:.75;">
                            Belum ada kelas untuk mata kuliah ini pada semester terpilih.
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
                                    wire:key="kelas-koordinator-card-{{ $kelas['id'] }}"
                                    style="cursor:pointer;text-align:left;min-width:170px;padding:10px 14px;border-radius:10px;
                                        border:2px solid {{ $border }};background:{{ $background }};color:{{ $color }};"
                                >
                                    <span style="display:block;font-weight:700;font-size:13px;">Kelas {{ $kelas['kode_kelas'] }}</span>
                                    <span style="display:block;font-size:11px;margin-top:2px;opacity:.85;">
                                        Dosen: {{ $kelas['dosen_pengampu_nama'] }}
                                    </span>
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
                    @endif
                </div>

                @if ($kelasMkId)
                    @if ($penugasanBelumSelesai)
                        <div data-silogy="langkah-tab-laporan" class="silogy-langkah-batas">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:13px;font-weight:700;color:#0b3914;">
                                <span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:999px;background:#007000;color:#fff;font-size:11px;">3</span>
                                Pilih tab Laporan
                            </div>
                            <p style="font-size:13px;opacity:.8;margin:0;">
                                Komponen penilaian pada kelas ini belum berbobot total 100% dan/atau belum seluruhnya
                                terpetakan ke Sub-CPMK. Laporan akan tersedia setelah penugasan selesai.
                            </p>
                        </div>
                    @elseif (count($columns) === 0 || count($rows) === 0)
                        <div data-silogy="langkah-tab-laporan" class="silogy-langkah-batas">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:13px;font-weight:700;color:#0b3914;">
                                <span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:999px;background:#007000;color:#fff;font-size:11px;">3</span>
                                Pilih tab Laporan
                            </div>
                            <p style="font-size:13px;opacity:.8;margin:0;">
                                Belum ada mahasiswa terdaftar atau komponen penilaian pada kelas ini.
                            </p>
                        </div>
                    @else
                        <div data-silogy="langkah-tab-laporan" class="silogy-langkah-batas">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;font-size:13px;font-weight:700;color:#0b3914;">
                                <span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:999px;background:#007000;color:#fff;font-size:11px;">3</span>
                                Pilih tab Laporan
                            </div>
                            <div data-silogy="laporan-koordinator-data">
                                @include('filament.modules.penilaian.partials.laporan-kelas-tabs')
                            </div>
                        </div>
                    @endif
                @endif
            @endif
        </div>
    </div>

    @include('filament.modules.mk.partials.mk-pipeline-nav', ['stepKey' => 'laporan'])
</x-filament-panels::page>

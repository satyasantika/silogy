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
            @if (! $mkTerpilih)
                <p style="font-size:13px;opacity:.75;">
                    Pilih mata kuliah dari halaman Mata Kuliah terlebih dahulu.
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
                    </div>
                @endif

                <div style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;opacity:.55;margin-bottom:10px;">
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
            @endif
        </div>
    </div>

    @if ($mkTerpilih && $kelasMkId)
        @if ($penugasanBelumSelesai)
            <div style="margin-top:16px;">
                <x-filament::section icon="heroicon-o-clock" heading="Penugasan belum selesai">
                    Komponen penilaian pada kelas ini belum berbobot total 100% dan/atau belum seluruhnya
                    terpetakan ke Sub-CPMK. Laporan akan tersedia setelah penugasan selesai.
                </x-filament::section>
            </div>
        @elseif (count($columns) === 0 || count($rows) === 0)
            <div style="margin-top:16px;">
                <x-filament::section icon="heroicon-o-information-circle" heading="Data belum lengkap">
                    Belum ada mahasiswa terdaftar atau komponen penilaian pada kelas ini.
                </x-filament::section>
            </div>
        @else
            <div style="margin-top:16px;">
                @include('filament.modules.penilaian.partials.laporan-kelas-tabs')
            </div>
        @endif
    @endif

    @include('filament.modules.mk.partials.mk-pipeline-nav', ['stepKey' => 'laporan'])
</x-filament-panels::page>

<div style="margin-top:1.5rem;">
    <div style="border:1px solid rgba(128,128,128,.25);border-radius:14px;overflow:hidden;">
        <div style="padding:14px 16px;border-bottom:1px solid rgba(128,128,128,.2);display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;">
            <div>
                <div style="font-weight:700;font-size:15px;">Kontrak kelas</div>
                <div style="font-size:12px;opacity:.75;margin-top:2px;">
                    Tarik data kontrak hanya untuk semester kalender yang dipilih, memakai kode penawaran MK ini.
                </div>
            </div>
        </div>

        <div style="padding:14px 16px;display:flex;flex-wrap:wrap;gap:12px;align-items:end;">
            <div style="flex:1 1 260px;min-width:220px;">
                <label for="semester-kontrak-mk-unit" style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">
                    Semester
                </label>
                <select
                    id="semester-kontrak-mk-unit"
                    wire:model.live="semesterKontrakId"
                    style="width:100%;padding:8px 10px;border:1px solid rgba(128,128,128,.4);border-radius:8px;background:transparent;font-size:13px;"
                >
                    <option value="">— pilih semester —</option>
                    @foreach ($semesterOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div style="flex:0 0 auto;">
                <x-filament::button
                    color="primary"
                    icon="heroicon-o-arrow-down-on-square-stack"
                    wire:click="tarikKontrakKelas"
                    wire:loading.attr="disabled"
                    wire:target="tarikKontrakKelas"
                >
                    {{ $labelTombolTarik }}
                </x-filament::button>
            </div>
        </div>

        <div style="padding:0 16px 16px;">
            @if (blank($semesterKontrakId))
                <p style="font-size:13px;opacity:.7;">Pilih semester untuk melihat rekap kelas yang sudah ada pada penawaran ini.</p>
            @elseif ($rekapKelas === [])
                <p style="font-size:13px;opacity:.7;">
                    Belum ada kelas untuk semester ini. Gunakan tombol &ldquo;{{ $labelTombolTarik }}&rdquo; untuk mengimpor kontrak.
                </p>
            @else
                <div style="overflow-x:auto;border:1px solid rgba(128,128,128,.2);border-radius:12px;">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="background:rgba(128,128,128,.08);text-align:left;">
                                <th style="padding:10px 12px;">Kelas</th>
                                <th style="padding:10px 12px;">Dosen pengampu</th>
                                <th style="padding:10px 12px;text-align:center;">Mahasiswa</th>
                                <th style="padding:10px 12px;text-align:right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rekapKelas as $baris)
                                <tr style="border-top:1px solid rgba(128,128,128,.15);{{ $kelasDetailId === $baris['id'] ? 'background:rgba(37,99,235,.06);' : '' }}">
                                    <td style="padding:10px 12px;font-weight:600;">{{ $baris['kode_kelas'] }}</td>
                                    <td style="padding:10px 12px;">{{ $baris['dosen'] }}</td>
                                    <td style="padding:10px 12px;text-align:center;">{{ $baris['jumlah_mahasiswa'] }}</td>
                                    <td style="padding:10px 12px;text-align:right;">
                                        <x-filament::button
                                            size="sm"
                                            color="{{ $kelasDetailId === $baris['id'] ? 'primary' : 'gray' }}"
                                            icon="heroicon-o-eye"
                                            wire:click="pilihDetailKelas('{{ $baris['id'] }}')"
                                        >
                                            Detail
                                        </x-filament::button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($kelasDetail !== null)
            <div style="padding:0 16px 16px;" wire:key="detail-mahasiswa-{{ $kelasDetailId }}">
                <div style="border:1px solid rgba(37,99,235,.25);border-radius:12px;overflow:hidden;">
                    <div style="padding:12px 14px;background:rgba(37,99,235,.06);display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;">
                        <div>
                            <div style="font-weight:700;font-size:14px;">
                                Daftar mahasiswa — Kelas {{ $kelasDetail['kode_kelas'] }}
                            </div>
                            <div style="font-size:12px;opacity:.75;margin-top:2px;">
                                {{ $kelasDetail['dosen'] }} · {{ $kelasDetail['jumlah_mahasiswa'] }} mahasiswa
                            </div>
                        </div>
                        <x-filament::button
                            size="sm"
                            color="gray"
                            wire:click="pilihDetailKelas(null)"
                        >
                            Tutup
                        </x-filament::button>
                    </div>

                    @if ($detailMahasiswa === [])
                        <p style="padding:14px;font-size:13px;opacity:.7;">Kelas ini belum memiliki peserta.</p>
                    @else
                        <div style="overflow-x:auto;max-height:360px;overflow-y:auto;">
                            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                                <thead>
                                    <tr style="background:rgba(128,128,128,.08);text-align:left;position:sticky;top:0;">
                                        <th style="padding:8px 12px;width:48px;">No</th>
                                        <th style="padding:8px 12px;">NPM</th>
                                        <th style="padding:8px 12px;">Nama</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($detailMahasiswa as $index => $mahasiswa)
                                        <tr style="border-top:1px solid rgba(128,128,128,.12);">
                                            <td style="padding:8px 12px;opacity:.7;">{{ $index + 1 }}</td>
                                            <td style="padding:8px 12px;font-family:ui-monospace,monospace;">{{ $mahasiswa['nim'] }}</td>
                                            <td style="padding:8px 12px;">{{ $mahasiswa['nama'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

<x-filament-panels::page>
    <div
        data-silogy="validasi-nilai-panel"
        style="border-radius:14px;overflow:hidden;border:1px solid rgba(128,128,128,.2);background:var(--silogy-panel-bg, #f9fafb);"
    >
        <div style="padding:14px 16px 16px;">
            <div style="margin-bottom:16px;max-width:420px;" data-silogy="langkah-semester">
                <label for="semester-terpilih" style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;color:#0b3914;">
                    Semester
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

            @php $daftarKelas = $this->daftarKelas; @endphp

            @if (empty($daftarKelas))
                <p data-silogy="validasi-nilai-kosong" style="font-size:13px;opacity:.75;">
                    Belum ada kelas dalam cakupan Anda pada semester terpilih.
                </p>
            @else
                <div style="overflow-x:auto;" data-silogy="validasi-nilai-daftar">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="text-align:left;border-bottom:1px solid rgba(128,128,128,.3);">
                                <th style="padding:8px;">MK / Kelas</th>
                                <th style="padding:8px;">Dosen</th>
                                <th style="padding:8px;">Nilai</th>
                                <th style="padding:8px;">Status validasi</th>
                                <th style="padding:8px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($daftarKelas as $kelas)
                                @php
                                    $statusKelas = \App\Modules\Penilaian\Support\StatusValidasiNilai::from($kelas['status_validasi']);
                                    $nadaKelas = match ($statusKelas) {
                                        \App\Modules\Penilaian\Support\StatusValidasiNilai::Tervalidasi => 'success',
                                        \App\Modules\Penilaian\Support\StatusValidasiNilai::PerluUlang => 'warning',
                                        default => 'neutral',
                                    };
                                @endphp
                                <tr wire:key="validasi-kelas-{{ $kelas['id'] }}" style="border-bottom:1px solid rgba(128,128,128,.15);{{ $kelasMkId === $kelas['id'] ? 'background:rgba(37,99,235,.08);' : '' }}">
                                    <td style="padding:8px;">
                                        <div style="font-weight:600;">{{ $kelas['mk_nama'] }}</div>
                                        <div style="font-size:12px;opacity:.75;">{{ $kelas['mk_kode'] }} · Kelas {{ $kelas['kode_kelas'] }}</div>
                                    </td>
                                    <td style="padding:8px;">{{ $kelas['dosen'] }}</td>
                                    <td style="padding:8px;">
                                        {{ $kelas['nilai_terisi'] }}/{{ $kelas['nilai_total'] }}
                                        @if ($kelas['status_kelengkapan'] !== 'lengkap')
                                            <span class="silogy-badge silogy-tone-warning">Belum lengkap</span>
                                        @endif
                                    </td>
                                    <td style="padding:8px;">
                                        <span class="silogy-badge silogy-tone-{{ $nadaKelas }}">{{ $statusKelas->label() }}</span>
                                        @if ($kelas['divalidasi_at'])
                                            <div style="font-size:11px;opacity:.7;">{{ $kelas['divalidasi_at'] }}</div>
                                        @endif
                                    </td>
                                    <td style="padding:8px;text-align:right;">
                                        <x-filament::button size="sm" color="gray" wire:click="bukaKelas('{{ $kelas['id'] }}')">
                                            Lihat
                                        </x-filament::button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if ($kelasMkId)
        @php $identitas = $this->identitasMk; @endphp
        <div data-silogy="validasi-nilai-laporan" style="border-radius:14px;border:1px solid rgba(128,128,128,.2);padding:14px 16px 16px;">
            <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;">
                <div>
                    <div style="font-weight:700;font-size:15px;">{{ $identitas['nama'] }} ({{ $identitas['kode'] }})</div>
                    <div style="font-size:12px;opacity:.75;">{{ $identitas['semester'] }} · Dosen: {{ $identitas['dosen'] }}</div>
                </div>
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
                    @if ($this->alasanTidakBisaValidasi)
                        <span data-silogy="alasan-tidak-bisa-validasi" style="font-size:12px;opacity:.8;">
                            {{ $this->alasanTidakBisaValidasi }}
                        </span>
                    @endif
                    {{ $this->validasiAction }}
                    {{ $this->batalkanValidasiAction }}
                    <x-filament::button size="sm" color="gray" wire:click="tutupKelas">Tutup</x-filament::button>
                </div>
            </div>

            @if ($penugasanBelumSelesai)
                <p style="font-size:13px;opacity:.8;margin:0;">
                    Komponen penilaian pada kelas ini belum berbobot total 100% dan/atau belum seluruhnya
                    terpetakan ke Sub-CPMK. Laporan akan tersedia setelah penugasan selesai.
                </p>
            @elseif (count($columns) === 0 || count($rows) === 0)
                <p style="font-size:13px;opacity:.8;margin:0;">
                    Belum ada mahasiswa terdaftar atau komponen penilaian pada kelas ini.
                </p>
            @else
                <div data-silogy="laporan-validator-data">
                    @include('filament.modules.penilaian.partials.laporan-kelas-tabs')
                </div>
            @endif
        </div>
    @endif
</x-filament-panels::page>

@php
    $status = $this->status();
    $jalan = $status->jalan;
@endphp

<x-filament-panels::page>
    {{-- ── KEADAAN ── --}}
    <x-filament::section>
        <x-slot name="heading">Keadaan data simulasi</x-slot>

        @if (! $status->ada)
            <div style="display:flex;align-items:flex-start;gap:12px;">
                <x-filament::icon icon="heroicon-o-beaker" style="width:24px;height:24px;flex:0 0 auto;opacity:.5;" />
                <div style="display:grid;gap:4px;">
                    <p style="font-weight:700;">Belum ada data simulasi.</p>
                    <p style="font-size:13px;opacity:.75;line-height:1.6;">
                        Tekan <strong>Buat Simulasi</strong> untuk menyiapkan satu program studi contoh
                        lengkap dengan kurikulum, CPMK, kelas, dan nilai — supaya siapa pun bisa berlatih
                        memakai SILOGY tanpa menyentuh data yang sesungguhnya.
                    </p>
                </div>
            </div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:18px;">
                <div>
                    <p style="font-size:12px;opacity:.7;margin-bottom:2px;">Status</p>
                    <p style="font-weight:700;font-size:15px;">
                        @if ($status->gagal())
                            <span style="color:#c1121f;">Gagal di tengah jalan</span>
                        @elseif ($status->sedangBerjalan())
                            Sedang dibangun
                        @else
                            Aktif
                        @endif
                    </p>
                </div>
                <div>
                    <p style="font-size:12px;opacity:.7;margin-bottom:2px;">Dibuat</p>
                    <p style="font-weight:700;font-size:15px;">
                        {{ $jalan->mulai_pada?->translatedFormat('d F Y, H:i') ?? '—' }}
                    </p>
                </div>
                <div>
                    <p style="font-size:12px;opacity:.7;margin-bottom:2px;">Oleh</p>
                    <p style="font-weight:700;font-size:15px;">
                        {{ $jalan->dipicuOleh?->full_name ?? 'Baris perintah' }}
                    </p>
                </div>
                <div>
                    <p style="font-size:12px;opacity:.7;margin-bottom:2px;">Semester</p>
                    <p style="font-weight:700;font-size:15px;">
                        {{ $jalan->semester?->nama ?? '—' }}
                    </p>
                </div>
                <div>
                    <p style="font-size:12px;opacity:.7;margin-bottom:2px;">Mode latihan</p>
                    <p style="font-weight:700;font-size:15px;{{ $this->cobaPeranAktif() ? 'color:#b45309;' : '' }}">
                        {{ $this->cobaPeranDilarangInstans() ? 'Dikunci instans' : ($this->cobaPeranAktif() ? 'Terbuka' : 'Tertutup') }}
                    </p>
                </div>
            </div>

            @if ($status->gagal())
                <div style="margin-top:16px;padding:14px 16px;border-radius:10px;font-size:13px;line-height:1.6;background:rgba(193,18,31,.08);color:#c1121f;border:1px solid rgba(193,18,31,.25);">
                    Pembangunan berhenti sebelum selesai. Artefak yang terlanjur dibuat tetap tercatat,
                    jadi <strong>Hapus Simulasi</strong> masih bisa membersihkannya seluruhnya.
                    @if (! empty($jalan->peringatan['galat']))
                        <br><span style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;">{{ $jalan->peringatan['galat'] }}</span>
                    @endif
                </div>
            @endif
        @endif
    </x-filament::section>

    {{-- ── ISI ── --}}
    @if ($status->ada)
        <x-filament::section collapsible>
            <x-slot name="heading">Yang dimiliki simulasi ({{ number_format($status->totalArtefak(), 0, ',', '.') }} baris)</x-slot>
            <x-slot name="description">
                Hanya baris yang benar-benar dibuat oleh simulasi yang tercatat di sini — dan hanya
                baris inilah yang akan dihapus.
            </x-slot>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:2px 32px;">
                @foreach ($status->cacah as $label => $jumlah)
                    <div style="display:flex;align-items:baseline;justify-content:space-between;gap:12px;padding:5px 0;border-bottom:1px solid rgba(128,128,128,.18);">
                        <span style="font-size:13px;opacity:.75;">{{ $label }}</span>
                        <span style="font-weight:700;font-variant-numeric:tabular-nums;">{{ number_format($jumlah, 0, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>

            @if ($status->turunan !== [])
                <div style="margin-top:22px;">
                    <p style="font-weight:700;font-size:13px;margin-bottom:8px;">
                        Ikut terhapus otomatis bersama induknya
                    </p>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:2px 32px;">
                        @foreach ($status->turunan as $label => $jumlah)
                            <div style="display:flex;align-items:baseline;justify-content:space-between;gap:12px;padding:5px 0;border-bottom:1px solid rgba(128,128,128,.18);">
                                <span style="font-size:13px;opacity:.75;">{{ $label }}</span>
                                <span style="font-weight:700;font-variant-numeric:tabular-nums;">{{ number_format($jumlah, 0, ',', '.') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($status->takDikenal !== [])
                <div style="margin-top:22px;padding:14px 16px;border-radius:10px;font-size:13px;line-height:1.6;background:rgba(245,158,11,.1);color:#b45309;border:1px solid rgba(245,158,11,.3);">
                    <p style="font-weight:700;">Ada model di luar daftar izin yang ikut lahir:</p>
                    <ul style="margin-top:6px;padding-left:18px;list-style:disc;">
                        @foreach ($status->takDikenal as $kelas => $jumlah)
                            <li><span style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;">{{ $kelas }}</span> — {{ $jumlah }} baris</li>
                        @endforeach
                    </ul>
                    <p style="margin-top:8px;">Baris itu tidak tercatat sebagai milik simulasi, jadi tidak akan ikut terhapus.</p>
                </div>
            @endif
        </x-filament::section>

        {{-- ── AKUN ── --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Akun latihan</x-slot>
            <x-slot name="description">
                Semua memakai kata sandi <strong>{{ $this->sandiSimulasi() }}</strong>. Akun ini hanya
                ditugaskan ke unit simulasi, sehingga tidak bisa menyentuh data nyata.
            </x-slot>

            <div style="overflow-x:auto;">
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    <thead>
                        <tr style="text-align:left;border-bottom:1px solid rgba(128,128,128,.25);">
                            <th style="padding:7px 16px 7px 0;font-weight:700;">Username</th>
                            <th style="padding:7px 16px 7px 0;font-weight:700;">Peran</th>
                            <th style="padding:7px 0;font-weight:700;">Penugasan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->akun() as $username => $definisi)
                            <tr style="border-bottom:1px solid rgba(128,128,128,.14);">
                                <td style="padding:7px 16px 7px 0;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;">{{ $username }}</td>
                                <td style="padding:7px 16px 7px 0;opacity:.8;">{{ $definisi['peran'] }}</td>
                                <td style="padding:7px 0;opacity:.8;">{{ $definisi['jabatan'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($this->cobaPeranDilarangInstans())
                <p style="margin-top:16px;font-size:13px;opacity:.75;line-height:1.6;">
                    Mode latihan <strong>dikunci pada instans ini</strong> lewat
                    <span style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;">SIMULASI_IZINKAN_COBA_PERAN=false</span>.
                    Hanya administrator peladen yang dapat membukanya kembali.
                </p>
            @elseif ($this->cobaPeranAktif())
                <p style="margin-top:16px;font-size:13px;line-height:1.6;color:#b45309;">
                    <strong>Mode latihan terbuka.</strong> Siapa pun yang membuka halaman panduan
                    dapat menekan <em>Coba sebagai ‹peran›</em> dan langsung masuk tanpa kata sandi.
                    Tutup lewat tombol <strong>Tutup mode latihan</strong> di atas bila sesi latihan
                    sudah selesai.
                </p>
            @else
                <p style="margin-top:16px;font-size:13px;opacity:.75;line-height:1.6;">
                    Mode latihan sedang tertutup — pengunjung harus mengetik sendiri akun dan kata
                    sandi di atas. Tekan <strong>Buka mode latihan</strong> untuk menampilkan tombol
                    <em>Coba sebagai ‹peran›</em> di halaman panduan.
                </p>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>

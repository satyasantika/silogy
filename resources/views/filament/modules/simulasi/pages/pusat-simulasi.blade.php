@php
    $daftar = $this->daftar();
    $total = $this->totalArtefak();
    $terisiPeran = $this->peranTerisi();
    $jumlahPeran = $this->jumlahPeran();
    $kapasitas = \App\Modules\Simulasi\Support\PengaturanSimulasi::ambil('kapasitas');
    $pemegang = collect($terisiPeran)->flatten()->count();
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Ruang simulasi</x-slot>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:18px;">
            <div>
                <p style="font-size:12px;opacity:.7;margin-bottom:2px;">Mode latihan</p>
                <p style="font-weight:700;font-size:15px;{{ $this->cobaPeranAktif() ? 'color:#b45309;' : '' }}">
                    {{ $this->cobaPeranDilarangInstans() ? 'Dikunci instans' : ($this->cobaPeranAktif() ? 'Terbuka' : 'Tertutup') }}
                </p>
            </div>
            <div>
                <p style="font-size:12px;opacity:.7;margin-bottom:2px;">Ruang hidup</p>
                <p style="font-weight:700;font-size:15px;">{{ $daftar->count() }} / {{ $kapasitas }}</p>
            </div>
            <div>
                <p style="font-size:12px;opacity:.7;margin-bottom:2px;">Peran sedang dipegang</p>
                <p style="font-weight:700;font-size:15px;">{{ $pemegang }} / {{ $daftar->count() * $jumlahPeran }}</p>
            </div>
            <div>
                <p style="font-size:12px;opacity:.7;margin-bottom:2px;">Contoh terisi (di Panduan)</p>
                <p style="font-weight:700;font-size:15px;">{{ $this->contohTerisiStatus() }}</p>
            </div>
        </div>

        <p style="margin-top:16px;font-size:13px;opacity:.75;line-height:1.6;">
            Tiap <strong>ruang</strong> berisi satu program studi dengan satu kurikulum dan satu mata kuliah kosong,
            enam akun (satu per peran), dan sebuah <strong>token</strong> enam karakter. Bagikan token itu kepada peserta:
            mereka membuka <span style="font-family:ui-monospace,Menlo,monospace;">/ruang</span>, memasukkan token, lalu
            memilih satu peran. Satu peran hanya dipegang satu peserta; peran yang sudah terisi tidak bisa dipilih dan
            terbuka lagi bila pemegangnya keluar atau tak aktif selama {{ \App\Modules\Simulasi\Support\PengaturanSimulasi::ambil('sewa_menit') }} menit.
            <strong>Contoh terisi</strong> tersedia di halaman Panduan untuk semua peran (hanya-baca) dan tidak diurus di sini.
            Akun inti tidak dapat melihat data ruang, dan akun ruang tidak dapat melihat data inti maupun ruang lain.
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Ruang ({{ $daftar->count() }})</x-slot>

        @if ($daftar->isEmpty())
            <p style="font-size:13px;opacity:.75;line-height:1.6;">
                Belum ada ruang. Tekan <strong>Siapkan Ruang</strong> dan isi jumlah yang dibutuhkan
                (satu ruang untuk enam peserta).
            </p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    <thead>
                        <tr style="text-align:left;border-bottom:1px solid rgba(128,128,128,.25);">
                            <th style="padding:7px 16px 7px 0;">Token</th>
                            <th style="padding:7px 16px 7px 0;">Status</th>
                            <th style="padding:7px 16px 7px 0;">Peran terisi</th>
                            <th style="padding:7px 16px 7px 0;">Dibuat</th>
                            <th style="padding:7px 16px 7px 0;">Aktif terakhir</th>
                            <th style="padding:7px 16px 7px 0;text-align:right;">Baris</th>
                            <th style="padding:7px 0;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($daftar as $jalan)
                            @php $diisi = $terisiPeran[$jalan->getKey()] ?? []; @endphp
                            <tr style="border-bottom:1px solid rgba(128,128,128,.14);">
                                <td style="padding:7px 16px 7px 0;font-family:ui-monospace,Menlo,monospace;font-size:15px;font-weight:700;letter-spacing:.12em;">
                                    {{ $jalan->pin ?? '—' }}
                                </td>
                                <td style="padding:7px 16px 7px 0;">
                                    @if ($jalan->status === 'gagal')
                                        <span style="color:#c1121f;">Gagal</span>
                                    @elseif ($jalan->status === 'berjalan')
                                        Dibangun
                                    @else
                                        Siap
                                    @endif
                                </td>
                                <td style="padding:7px 16px 7px 0;">
                                    <span style="font-variant-numeric:tabular-nums;font-weight:600;">{{ count($diisi) }} / {{ $jumlahPeran }}</span>
                                    @foreach ($diisi as $slug)
                                        <span style="display:inline-flex;align-items:center;gap:4px;margin-left:6px;padding:1px 8px;border-radius:999px;background:rgba(128,128,128,.14);font-size:12px;">
                                            {{ \App\Modules\Panduan\Support\PeranPanduan::definisi($slug)['label'] ?? $slug }}
                                            <button type="button"
                                                    wire:click="lepasPeran('{{ $jalan->getKey() }}', '{{ $slug }}')"
                                                    wire:confirm="Lepaskan peran ini? Peserta yang sedang memegangnya akan terputus."
                                                    title="Lepas peran" aria-label="Lepas peran {{ $slug }}"
                                                    style="color:#c1121f;font-weight:700;line-height:1;">×</button>
                                        </span>
                                    @endforeach
                                </td>
                                <td style="padding:7px 16px 7px 0;opacity:.8;">{{ $jalan->mulai_pada?->translatedFormat('d M, H:i') ?? '—' }}</td>
                                <td style="padding:7px 16px 7px 0;opacity:.8;">{{ $jalan->terakhir_aktif_pada?->diffForHumans() ?? '—' }}</td>
                                <td style="padding:7px 16px 7px 0;text-align:right;font-variant-numeric:tabular-nums;">{{ number_format($total[$jalan->getKey()] ?? 0, 0, ',', '.') }}</td>
                                <td style="padding:7px 0;text-align:right;white-space:nowrap;">
                                    @if ($jalan->status === 'selesai')
                                        <button type="button"
                                                wire:click="gantiToken('{{ $jalan->getKey() }}')"
                                                wire:confirm="Ganti token ruang {{ $jalan->pin }}? Token lama tidak berlaku lagi untuk peserta baru; yang sudah masuk tidak terganggu."
                                                style="font-weight:600;margin-right:12px;">
                                            Ganti token
                                        </button>
                                    @endif
                                    <button type="button"
                                            wire:click="hapusSatu('{{ $jalan->getKey() }}')"
                                            wire:confirm="Hapus ruang {{ $jalan->pin }}? Semua peserta di dalamnya terputus. Tindakan ini tidak dapat dibatalkan."
                                            style="color:#c1121f;font-weight:600;">
                                        Hapus
                                    </button>
                                </td>
                            </tr>
                            @if ($jalan->status === 'gagal' && ! empty($jalan->peringatan['galat']))
                                <tr>
                                    <td colspan="7" style="padding:0 0 8px;font-family:ui-monospace,Menlo,monospace;font-size:12px;color:#c1121f;">
                                        {{ $jalan->peringatan['galat'] }}
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    {{-- Modal progres pembangunan ruang. Isinya hanya dirender selama ada ruang yang dipantau. --}}
    <x-filament::modal
        id="progres-sandbox"
        width="3xl"
        :close-by-clicking-away="false"
        x-on:modal-closed="if ($event.detail.id === 'progres-sandbox') $wire.tutupProgres()"
    >
        <x-slot name="heading">Menyiapkan ruang</x-slot>

        @if ($progresIds !== [] && ($p = $this->progres()) !== null)
            @include('filament.modules.simulasi.pages.progres-sandbox', ['p' => $p])
        @endif

        <x-slot name="footerActions">
            <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'progres-sandbox' })">
                Tutup
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</x-filament-panels::page>

@php
    $daftar = $this->daftar();
    $total = $this->totalArtefak();
    $siap = $daftar->filter(fn ($j) => $j->status === 'selesai' && $j->pengunjung === null)->count();
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Ruang latihan</x-slot>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:18px;">
            <div>
                <p style="font-size:12px;opacity:.7;margin-bottom:2px;">Mode latihan</p>
                <p style="font-weight:700;font-size:15px;{{ $this->cobaPeranAktif() ? 'color:#b45309;' : '' }}">
                    {{ $this->cobaPeranDilarangInstans() ? 'Dikunci instans' : ($this->cobaPeranAktif() ? 'Terbuka' : 'Tertutup') }}
                </p>
            </div>
            <div>
                <p style="font-size:12px;opacity:.7;margin-bottom:2px;">Sandbox hidup</p>
                <p style="font-weight:700;font-size:15px;">{{ $daftar->count() }} / {{ config('simulasi.maks_sandbox') }}</p>
            </div>
            <div>
                <p style="font-size:12px;opacity:.7;margin-bottom:2px;">Siap dipakai (kolam)</p>
                <p style="font-weight:700;font-size:15px;">{{ $siap }} / {{ config('simulasi.kolam_siap') }}</p>
            </div>
            <div>
                <p style="font-size:12px;opacity:.7;margin-bottom:2px;">Dihapus otomatis setelah</p>
                <p style="font-weight:700;font-size:15px;">{{ config('simulasi.umur_menit') }} menit tanpa aktivitas</p>
            </div>
        </div>

        <p style="margin-top:16px;font-size:13px;opacity:.75;line-height:1.6;">
            Setiap pengunjung mendapat satu sandbox berisi satu paket data utuh dengan 18 akun
            (6 peran × 3 tingkat). Akun inti tidak dapat melihat data sandbox, dan akun sandbox tidak dapat
            melihat data inti maupun sandbox lain. Menghapus sandbox tidak menyentuh data inti.
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Sandbox ({{ $daftar->count() }})</x-slot>

        @if ($daftar->isEmpty())
            <p style="font-size:13px;opacity:.75;line-height:1.6;">
                Belum ada sandbox. Tekan <strong>Siapkan Sandbox</strong>, atau biarkan penjadwal
                (<span style="font-family:ui-monospace,Menlo,monospace;">simulasi:kolam</span>) mengisi kolam.
            </p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    <thead>
                        <tr style="text-align:left;border-bottom:1px solid rgba(128,128,128,.25);">
                            <th style="padding:7px 16px 7px 0;">Kode</th>
                            <th style="padding:7px 16px 7px 0;">Status</th>
                            <th style="padding:7px 16px 7px 0;">Pemakai</th>
                            <th style="padding:7px 16px 7px 0;">Dibuat</th>
                            <th style="padding:7px 16px 7px 0;">Aktif terakhir</th>
                            <th style="padding:7px 16px 7px 0;text-align:right;">Baris</th>
                            <th style="padding:7px 0;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($daftar as $jalan)
                            <tr style="border-bottom:1px solid rgba(128,128,128,.14);">
                                <td style="padding:7px 16px 7px 0;font-family:ui-monospace,Menlo,monospace;font-size:12px;">{{ $jalan->kode() }}</td>
                                <td style="padding:7px 16px 7px 0;">
                                    @if ($jalan->status === 'gagal')
                                        <span style="color:#c1121f;">Gagal</span>
                                    @elseif ($jalan->status === 'berjalan')
                                        Dibangun
                                    @elseif ($jalan->pengunjung === null)
                                        Siap
                                    @else
                                        Dipakai
                                    @endif
                                </td>
                                <td style="padding:7px 16px 7px 0;opacity:.8;">{{ $jalan->pengunjung === null ? '—' : 'Pengunjung' }}</td>
                                <td style="padding:7px 16px 7px 0;opacity:.8;">{{ $jalan->mulai_pada?->translatedFormat('d M, H:i') ?? '—' }}</td>
                                <td style="padding:7px 16px 7px 0;opacity:.8;">{{ $jalan->terakhir_aktif_pada?->diffForHumans() ?? '—' }}</td>
                                <td style="padding:7px 16px 7px 0;text-align:right;font-variant-numeric:tabular-nums;">{{ number_format($total[$jalan->getKey()] ?? 0, 0, ',', '.') }}</td>
                                <td style="padding:7px 0;text-align:right;">
                                    <button type="button"
                                            wire:click="hapusSatu('{{ $jalan->getKey() }}')"
                                            wire:confirm="Hapus sandbox {{ $jalan->kode() }}? Tindakan ini tidak dapat dibatalkan."
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
</x-filament-panels::page>

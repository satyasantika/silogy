<x-filament-panels::page>
    @include('filament.modules.kurikulum.partials.kurikulum-terpilih-banner')

    @if (! $kurikulum)
        <x-filament::section icon="heroicon-o-exclamation-triangle" heading="Belum ada kurikulum terpilih">
            Pilih kurikulum dari halaman Kurikulum terlebih dahulu.
        </x-filament::section>
    @elseif ($cpls->isEmpty() || $boks->isEmpty())
        <x-filament::section icon="heroicon-o-information-circle" heading="Data belum lengkap">
            Matriks membutuhkan minimal satu CPL dan satu bahan kajian (BoK) pada kurikulum terpilih.
        </x-filament::section>
    @else
        <x-filament::section
            icon="heroicon-o-arrows-right-left"
            heading="Interaksi CPL ↔ BoK"
            description="Centang irisan untuk memetakan CPL (baris) ke bahan kajian (kolom)."
        >
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="text-align:left;border-bottom:2px solid rgba(128,128,128,.35);">
                            <th style="padding:8px;">CPL \ BoK</th>
                            @foreach ($boks as $bok)
                                <th style="padding:8px;text-align:center;" title="{{ $bok->nama }}">
                                    {{ $bok->kode }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cpls as $cpl)
                            <tr style="border-bottom:1px solid rgba(128,128,128,.2);">
                                <td style="padding:8px;white-space:nowrap;" title="{{ $cpl->deskripsi }}">
                                    <strong>{{ $cpl->kode }}</strong>
                                </td>
                                @foreach ($boks as $bok)
                                    @php
                                        $kunciSel = $cpl->id.'/'.$bok->id;
                                        $sudahTerpetakan = $terpetakan->has($kunciSel);
                                        $terkunci = $terkunciBobot->has($kunciSel);
                                    @endphp
                                    <td style="padding:8px;text-align:center;">
                                        <input
                                            type="checkbox"
                                            style="width:18px;height:18px;accent-color:#16a34a;{{ $terkunci ? 'cursor:not-allowed;opacity:.65;' : 'cursor:pointer;' }}"
                                            @if (! $terkunci)
                                                wire:click="toggle('{{ $cpl->id }}', '{{ $bok->id }}')"
                                            @endif
                                            @disabled($terkunci)
                                            @checked($sudahTerpetakan)
                                            @if ($terkunci)
                                                title="Hapus bobot pada interaksi CPL ↔ MK terlebih dahulu"
                                            @endif
                                        />
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-sm" style="margin-top:8px;opacity:.7;">
                Interaksi CPL–BoK yang sudah diberi bobot di matriks
                <strong>CPL ↔ MK</strong> tidak dapat dilepas centang dari halaman ini.
                Jika ingin membuang centang, hapus bobot pada interaksi CPL ↔ MK terlebih dahulu.
            </p>
        </x-filament::section>
    @endif
</x-filament-panels::page>

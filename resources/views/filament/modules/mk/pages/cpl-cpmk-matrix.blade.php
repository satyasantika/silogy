<x-filament-panels::page>
    @include('filament.modules.mk.partials.mk-terpilih-banner')

    @if (! $kurikulum)
        <x-filament::section icon="heroicon-o-exclamation-triangle" heading="Belum ada kurikulum terpilih">
            Pilih kurikulum lewat widget di dashboard atau filter pada halaman Mata Kuliah.
        </x-filament::section>
    @elseif (! $mkTerpilih)
        <x-filament::section icon="heroicon-o-exclamation-triangle" heading="Belum ada mata kuliah terpilih">
            Pilih mata kuliah dari halaman Mata Kuliah terlebih dahulu.
        </x-filament::section>
    @elseif ($cpmks->isEmpty())
        <x-filament::section icon="heroicon-o-information-circle" heading="Data belum lengkap">
            Matriks membutuhkan minimal satu CPMK pada mata kuliah terpilih.
        </x-filament::section>
    @elseif ($cpls->isEmpty())
        <x-filament::section icon="heroicon-o-information-circle" heading="Belum ada CPL terpetakan">
            Petakan CPL ke mata kuliah ini terlebih dahulu lewat interaksi CPL ↔ MK (beri bobot pada irisan).
        </x-filament::section>
    @else
        <x-filament::section
            icon="heroicon-o-arrows-right-left"
            heading="Interaksi CPL ↔ CPMK"
            description="Centang irisan untuk memetakan CPMK (baris) ke CPL (kolom). CPL–MK harus sudah dipetakan lewat interaksi CPL ↔ MK."
        >
            <div style="overflow-x:auto;" wire:key="matriks-cpl-cpmk">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="text-align:left;border-bottom:2px solid rgba(128,128,128,.35);">
                            <th style="padding:8px;">CPMK \ CPL</th>
                            @foreach ($cpls as $cpl)
                                <th style="padding:8px;text-align:center;white-space:nowrap;" title="{{ $cpl->deskripsi }}">
                                    {{ $cpl->kode }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cpmks as $cpmk)
                            <tr style="border-bottom:1px solid rgba(128,128,128,.2);">
                                <td style="padding:8px;white-space:nowrap;" title="{{ $cpmk->deskripsi }}">
                                    <strong>{{ $cpmk->kode }}</strong>
                                </td>
                                @foreach ($cpls as $cpl)
                                    @php
                                        $kunciSel = $cpmk->id.'/'.$cpl->id;
                                        $sudahTerpetakan = $terpetakan->has($kunciSel);
                                        $terkunci = $terkunciSubcpmk->has($kunciSel);
                                    @endphp
                                    <td style="padding:8px;text-align:center;">
                                        <input
                                            type="checkbox"
                                            style="width:18px;height:18px;accent-color:#16a34a;{{ $terkunci ? 'cursor:not-allowed;opacity:.65;' : 'cursor:pointer;' }}"
                                            @if (! $terkunci)
                                                wire:click="toggle('{{ $cpmk->id }}', '{{ $cpl->id }}')"
                                            @endif
                                            @disabled($terkunci)
                                            @checked($sudahTerpetakan)
                                            @if ($terkunci)
                                                title="Hapus Sub-CPMK terkait terlebih dahulu"
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
                Pemetaan CPL–CPMK yang sudah memiliki Sub-CPMK tidak dapat dilepas centang dari halaman ini.
            </p>
        </x-filament::section>
    @endif
</x-filament-panels::page>

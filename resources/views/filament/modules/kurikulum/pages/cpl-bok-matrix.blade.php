<x-filament-panels::page>
    @if (! $kurikulum)
        <x-filament::section icon="heroicon-o-exclamation-triangle" heading="Belum ada kurikulum terpilih">
            Pilih kurikulum lewat widget di dashboard atau filter pada halaman kurikulum.
        </x-filament::section>
    @elseif ($cpls->isEmpty() || $boks->isEmpty())
        <x-filament::section icon="heroicon-o-information-circle" heading="Data belum lengkap">
            Matriks membutuhkan minimal satu CPL dan satu bahan kajian (BoK) pada kurikulum
            <strong>{{ $kurikulum->nama }}</strong>.
        </x-filament::section>
    @else
        <x-filament::section
            icon="heroicon-o-arrows-right-left"
            heading="CPL ↔ BoK — {{ $kurikulum->nama }}"
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
                                    <td style="padding:8px;text-align:center;">
                                        <input
                                            type="checkbox"
                                            style="width:18px;height:18px;cursor:pointer;accent-color:#16a34a;"
                                            wire:click="toggle('{{ $cpl->id }}', '{{ $bok->id }}')"
                                            @checked($terpetakan->has($cpl->id.'/'.$bok->id))
                                        />
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-sm" style="margin-top:8px;opacity:.7;">
                Melepas centang akan menghapus pemetaan CPL–BoK beserta pemetaan MK yang menggantung padanya.
            </p>
        </x-filament::section>
    @endif
</x-filament-panels::page>

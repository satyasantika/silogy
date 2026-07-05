<x-filament-panels::page>
    @include('filament.modules.kurikulum.partials.kurikulum-terpilih-banner')

    @if (! $kurikulum)
        <x-filament::section icon="heroicon-o-exclamation-triangle" heading="Belum ada kurikulum terpilih">
            Pilih kurikulum dari halaman Kurikulum terlebih dahulu.
        </x-filament::section>
    @elseif ($mks->isEmpty() || $cplBoks->isEmpty())
        <x-filament::section icon="heroicon-o-information-circle" heading="Data belum lengkap">
            Matriks membutuhkan minimal satu mata kuliah dan satu pemetaan CPL–BoK
            (lihat menu Interaksi → CPL ↔ BoK) pada kurikulum terpilih.
        </x-filament::section>
    @else
        <x-filament::section
            icon="heroicon-o-arrows-right-left"
            heading="Interaksi CPL ↔ MK (bobot)"
            description="Isi bobot (%) kontribusi tiap MK (baris) terhadap CPL via BoK (kolom). Kosongkan atau isi 0 untuk menghapus. Total per MK dihitung otomatis."
        >
            <div style="overflow-x:auto;" wire:key="matriks-cpl-mk">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="text-align:left;border-bottom:2px solid rgba(128,128,128,.35);">
                            <th style="padding:8px;">MK \ CPL (via BoK)</th>
                            @foreach ($cplBoks as $cplBok)
                                <th style="padding:8px;text-align:center;white-space:nowrap;"
                                    title="{{ $cplBok->cpl?->deskripsi }}">
                                    {{ $cplBok->cpl?->kode }}<br>
                                    <span style="font-weight:400;opacity:.7;">{{ $cplBok->bok?->kode }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($mks as $mk)
                            @php($total = (float) ($totals[$mk->id] ?? 0))
                            <tr style="border-bottom:1px solid rgba(128,128,128,.2);">
                                <td style="padding:8px;white-space:nowrap;">
                                    <strong>{{ $mk->nama }}</strong>
                                    <span style="display:inline-block;margin-left:6px;padding:1px 8px;border-radius:9999px;font-size:11px;font-weight:700;color:#fff;background:{{ $total > 100 ? '#dc2626' : ($total > 0 ? '#16a34a' : '#9ca3af') }};">
                                        Σ {{ rtrim(rtrim(number_format($total, 2, ',', '.'), '0'), ',') }}%
                                    </span>
                                </td>
                                @foreach ($cplBoks as $cplBok)
                                    <td style="padding:6px;text-align:center;">
                                        <input
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            style="width:74px;padding:4px 6px;border:1px solid rgba(128,128,128,.4);border-radius:6px;background:transparent;text-align:center;"
                                            value="{{ $bobots[$mk->id.'/'.$cplBok->id] ?? '' }}"
                                            wire:change="updateBobot('{{ $mk->id }}', '{{ $cplBok->id }}', $event.target.value)"
                                        />
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>

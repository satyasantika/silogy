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
    @elseif ($tampilkanFilterSemester && $semesterOptions === [])
        <x-filament::section icon="heroicon-o-exclamation-triangle" heading="Belum ada semester">
            Tambahkan data semester terlebih dahulu agar filter operasional tersedia.
        </x-filament::section>
    @else
        @if ($tampilkanFilterSemester)
        <div style="margin-bottom:16px;max-width:420px;">
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
            <p style="margin-top:6px;font-size:12px;opacity:.75;">Semester diambil dari master semester.</p>
        </div>
        @endif

        @if ($asesmen->isEmpty() || $subcpmks->isEmpty())
        <x-filament::section icon="heroicon-o-information-circle" heading="Data belum lengkap">
            Matriks membutuhkan minimal satu asesmen dan satu Sub-CPMK pada semester terpilih.
        </x-filament::section>
    @else
        <x-filament::section
            icon="heroicon-o-arrows-right-left"
            heading="Interaksi Sub-CPMK ↔ Asesmen (bobot)"
            description="Isi bobot (%) kontribusi tiap asesmen (baris) terhadap Sub-CPMK (kolom). Kosongkan atau isi 0 untuk menghapus. Total per asesmen dihitung otomatis."
        >
            <div style="overflow-x:auto;" wire:key="matriks-subcpmk-asesmen">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="text-align:left;border-bottom:2px solid rgba(128,128,128,.35);">
                            <th style="padding:8px;">Asesmen \ Sub-CPMK</th>
                            @foreach ($subcpmks as $subcpmk)
                                <th style="padding:8px;text-align:center;white-space:nowrap;"
                                    title="{{ $subcpmk->deskripsi }}">
                                    {{ $subcpmk->kode }}<br>
                                    <span style="font-weight:400;opacity:.7;">{{ $subcpmk->mkCpmk?->cpmk?->kode }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($asesmen as $komponen)
                            @php($total = (float) ($totals[$komponen->id] ?? 0))
                            <tr style="border-bottom:1px solid rgba(128,128,128,.2);">
                                <td style="padding:8px;white-space:nowrap;">
                                    <strong>{{ $komponen->nama }}</strong>
                                    <span style="display:block;font-size:11px;opacity:.7;">
                                        {{ $komponen->evaluasi?->nama ?? '—' }}
                                        · Kelas {{ $komponen->kelasMk?->kode_kelas ?? '—' }}
                                    </span>
                                    <span style="display:inline-block;margin-top:4px;padding:1px 8px;border-radius:9999px;font-size:11px;font-weight:700;color:#fff;background:{{ $total > 100 ? '#dc2626' : ($total > 0 ? '#16a34a' : '#9ca3af') }};">
                                        Σ {{ rtrim(rtrim(number_format($total, 2, ',', '.'), '0'), ',') }}%
                                    </span>
                                </td>
                                @foreach ($subcpmks as $subcpmk)
                                    <td style="padding:6px;text-align:center;">
                                        <input
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            style="width:74px;padding:4px 6px;border:1px solid rgba(128,128,128,.4);border-radius:6px;background:transparent;text-align:center;"
                                            value="{{ $bobots[$komponen->id.'/'.$subcpmk->id] ?? '' }}"
                                            wire:change="updateBobot('{{ $komponen->id }}', '{{ $subcpmk->id }}', $event.target.value)"
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
    @endif
</x-filament-panels::page>

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

        @if (! $adaAsesmenSemua || $subcpmks->isEmpty())
        <x-filament::section icon="heroicon-o-information-circle" heading="Data belum lengkap">
            Matriks membutuhkan minimal satu asesmen dan satu Sub-CPMK pada semester terpilih.
        </x-filament::section>
    @else
        <x-filament::section
            icon="heroicon-o-arrows-right-left"
            heading="Interaksi Sub-CPMK ↔ Asesmen (bobot)"
            description="Isi bobot (%) kontribusi tiap asesmen (baris) terhadap Sub-CPMK (kolom). Kosongkan atau isi 0 untuk menghapus. Total per asesmen dihitung otomatis."
        >
            <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
                <div style="flex:1 1 220px;min-width:200px;">
                    <label for="asesmen-search" style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">
                        Cari kode / nama tugas
                    </label>
                    <input
                        type="text"
                        id="asesmen-search"
                        wire:model.live.debounce.400ms="search"
                        placeholder="Cari kode atau nama tugas..."
                        style="width:100%;padding:8px 10px;border:1px solid rgba(128,128,128,.4);border-radius:8px;background:transparent;font-size:13px;"
                    />
                </div>
                <div style="flex:1 1 200px;min-width:180px;">
                    <label for="asesmen-filter-evaluasi" style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">
                        Filter komponen penilaian
                    </label>
                    <select
                        id="asesmen-filter-evaluasi"
                        wire:model.live="filterEvaluasiId"
                        style="width:100%;padding:8px 10px;border:1px solid rgba(128,128,128,.4);border-radius:8px;background:transparent;font-size:13px;"
                    >
                        <option value="">Semua komponen</option>
                        @foreach ($evaluasiOptions as $evaluasiId => $evaluasiNama)
                            <option value="{{ $evaluasiId }}">{{ $evaluasiNama }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="flex:1 1 180px;min-width:160px;">
                    <label for="asesmen-sort-by" style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">
                        Urutkan berdasarkan
                    </label>
                    <select
                        id="asesmen-sort-by"
                        wire:model.live="sortBy"
                        style="width:100%;padding:8px 10px;border:1px solid rgba(128,128,128,.4);border-radius:8px;background:transparent;font-size:13px;"
                    >
                        <option value="kode">Kode tugas</option>
                        <option value="evaluasi">Komponen penilaian</option>
                        <option value="total">Total persen penilaian</option>
                    </select>
                </div>
                <div style="flex:0 0 140px;min-width:120px;">
                    <label for="asesmen-sort-direction" style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">
                        Arah urutan
                    </label>
                    <select
                        id="asesmen-sort-direction"
                        wire:model.live="sortDirection"
                        style="width:100%;padding:8px 10px;border:1px solid rgba(128,128,128,.4);border-radius:8px;background:transparent;font-size:13px;"
                    >
                        <option value="asc">Naik (A–Z)</option>
                        <option value="desc">Turun (Z–A)</option>
                    </select>
                </div>
            </div>

            @if ($asesmen->isEmpty())
                <div style="padding:16px;text-align:center;font-size:13px;opacity:.7;">
                    Tidak ada asesmen yang sesuai dengan pencarian/filter saat ini.
                </div>
            @else
                <div style="overflow-x:auto;" wire:key="matriks-subcpmk-asesmen">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="text-align:left;border-bottom:2px solid rgba(128,128,128,.35);">
                                <th style="position:sticky;left:0;z-index:2;max-width:300px;padding:8px;background:rgba(128,128,128,.08);">Asesmen \ Sub-CPMK</th>
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
                                    <td style="position:sticky;left:0;z-index:1;max-width:300px;padding:8px;background:rgba(128,128,128,.04);white-space:normal;overflow-wrap:break-word;">
                                        <span style="display:block;font-size:11px;font-weight:600;opacity:.75;">{{ $komponen->kode ?? '—' }}</span>
                                        <strong style="display:block;">{{ $komponen->nama }}</strong>
                                        <span style="display:block;font-size:11px;opacity:.7;">
                                            {{ $komponen->evaluasi?->nama ?? '—' }}
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
            @endif
        </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>

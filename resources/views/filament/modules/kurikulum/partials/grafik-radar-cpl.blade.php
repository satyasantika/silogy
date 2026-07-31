@if (empty($radarPerCpl))
    <p style="font-size:13px;opacity:.7;">
        Belum ada CPL yang dibebankan pada mata kuliah di program studi ini.
    </p>
@else
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:16px;">
        @foreach ($radarPerCpl as $cplData)
            <div wire:key="grafik-cpl-{{ $cplData['cpl_id'] }}" style="border:1px solid rgba(128,128,128,.2);border-radius:10px;padding:14px;">
                <div style="margin-bottom:8px;">
                    <strong style="font-size:14px;color:#1d4ed8;">{{ $cplData['cpl_kode'] }}</strong>
                    <p style="font-size:12px;opacity:.75;margin-top:2px;">{{ $cplData['cpl_deskripsi'] }}</p>
                </div>

                @if (! $cplData['ada_data'])
                    <div class="silogy-tone-warning" style="padding:10px 12px;border-radius:8px;font-size:12px;">
                        Grafik belum dapat ditampilkan karena mata kuliah pada CPL ini belum dinilai.
                    </div>
                @else
                    <div>
                        <canvas
                            wire:key="radar-cpl-{{ $cplData['cpl_id'] }}"
                            x-data
                            x-init="renderRadarSilogy($el, @js(['labels' => $cplData['labels'], 'data' => $cplData['data']]), '#2563eb')"
                        ></canvas>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif

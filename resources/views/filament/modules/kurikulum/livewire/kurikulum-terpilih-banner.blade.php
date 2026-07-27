<div>
    @if (! $kurikulum)
        <div style="padding:12px 14px;border-radius:8px;background:#fef3c7;border:1px solid #fcd34d;color:#92400e;font-size:13px;line-height:1.55;">
            <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;">
                <div>Belum ada kurikulum terpilih.</div>
                @if ($adaOpsi)
                    <div style="margin-inline-start:auto;">
                        {{ $this->pilihAction }}
                    </div>
                @endif
            </div>
        </div>
    @else
        <div style="padding:12px 14px;border-radius:8px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;font-size:13px;line-height:1.55;">
            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px;">
                <div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:6px;min-width:0;flex:1 1 auto;">
                    <span style="opacity:.88;">Kurikulum terpilih:</span>
                    <strong style="word-break:break-word;">{{ $kurikulum->nama }}</strong>
                </div>
                <div class="silogy-kurikulum-banner-ganti" style="margin-inline-start:auto;flex:0 0 auto;">
                    {{ $this->gantiAction }}
                </div>
            </div>
            @if ($hierarchy)
                <div style="margin-top:6px;opacity:.92;">{{ $hierarchy }}</div>
            @endif
        </div>
    @endif

    <x-filament-actions::modals />
</div>

@php
    use App\Modules\Kurikulum\Support\KurikulumTerpilih;
    use App\Modules\MK\Support\MkTerpilih;

    $gantiUrl = $gantiUrl ?? \App\Modules\MK\Filament\Resources\MataKuliahKoordinatorResource::getUrl('index');
    $mk = $mk ?? MkTerpilih::current();
    $kurikulum = $kurikulum ?? KurikulumTerpilih::current();
@endphp

@if (! $mk)
    <div class="silogy-tone-warning" style="padding:12px 14px;border-radius:8px;border:1px solid #fcd34d;font-size:13px;line-height:1.55;">
        <div>Belum ada mata kuliah terpilih.</div>
        <div style="margin-top:4px;">
            <a href="{{ $gantiUrl }}" style="font-weight:600;color:#b45309;text-decoration:underline;">Pilih dari halaman Mata Kuliah</a>
        </div>
    </div>
@else
    @php
        $kurikulum?->loadMissing('academicUnit');
        $mkLabel = MkTerpilih::label($mk, $kurikulum);
        $kurikulumLabel = $kurikulum?->nama ?? '—';
        $prodiLabel = $kurikulum?->academicUnit?->nama ?? '—';
    @endphp

    <div class="silogy-tone-info" style="padding:12px 14px;border-radius:8px;border:1px solid #bfdbfe;font-size:13px;line-height:1.5;">
        <div class="mk-terpilih-banner-grid">
            <div class="mk-terpilih-banner-col">
                <div class="mk-terpilih-banner-label">Mata Kuliah</div>
                <div class="mk-terpilih-banner-value">
                    {{ $mkLabel }}
                    <a href="{{ $gantiUrl }}" class="mk-terpilih-banner-ganti">Ganti</a>
                </div>
            </div>
            <div class="mk-terpilih-banner-col mk-terpilih-banner-col-center">
                <div class="mk-terpilih-banner-label">Kurikulum</div>
                <div class="mk-terpilih-banner-value">{{ $kurikulumLabel }}</div>
            </div>
            <div class="mk-terpilih-banner-col mk-terpilih-banner-col-end">
                <div class="mk-terpilih-banner-label">Program Studi</div>
                <div class="mk-terpilih-banner-value">{{ $prodiLabel }}</div>
            </div>
        </div>
    </div>

    @once
        <style>
            .mk-terpilih-banner-grid {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }

            .mk-terpilih-banner-label {
                font-size: 0.75rem;
                text-transform: uppercase;
                opacity: 0.65;
                letter-spacing: 0.04em;
            }

            .mk-terpilih-banner-value {
                font-weight: 600;
                margin-top: 2px;
            }

            .mk-terpilih-banner-ganti {
                margin-left: 0.375rem;
                font-size: 0.8125rem;
                font-weight: 600;
                color: #1d4ed8;
                text-decoration: underline;
            }

            @media (min-width: 768px) {
                .mk-terpilih-banner-grid {
                    flex-direction: row;
                    justify-content: space-between;
                    align-items: flex-start;
                    gap: 1rem;
                }

                .mk-terpilih-banner-col-center {
                    text-align: center;
                }

                .mk-terpilih-banner-col-end {
                    text-align: right;
                }
            }
        </style>
    @endonce
@endif

{{-- Nav back/next untuk halaman Page custom (bukan ListRecords Schema). --}}
@php
    $nav = \App\Modules\MK\Support\MkPipeline::navFor($stepKey);
    $prev = $nav['prev'] ?? null;
    $next = $nav['next'] ?? null;
@endphp

@if ($prev || $next)
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:16px;">
        <div>
            @if ($prev)
                <a
                    href="{{ $prev['url'] }}"
                    style="display:inline-flex;align-items:center;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;background:#f3f4f6;color:#374151;"
                >
                    « {{ $prev['label'] }}
                </a>
            @endif
        </div>
        <div>
            @if ($next)
                <a
                    href="{{ $next['url'] }}"
                    style="display:inline-flex;align-items:center;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;background:#2563eb;color:#fff;"
                >
                    {{ $next['label'] }} »
                </a>
            @endif
        </div>
    </div>
@endif

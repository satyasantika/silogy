{{-- Trigger kode CPL/BoK: klik membuka keterangan (teleport ke body agar tidak terpotong overflow matriks).
     Props: $jenis (CPL|BoK), $kode, $deskripsi (?), $nama (?), $meta (?) --}}
@php
    $jenis = $jenis ?? 'CPL';
    $kode = (string) ($kode ?? '—');
    $nama = filled($nama ?? null) ? trim((string) $nama) : null;
    $deskripsiMentah = $deskripsi ?? null;
    $deskripsi = filled($deskripsiMentah)
        ? trim(preg_replace('/\s+/u', ' ', strip_tags((string) $deskripsiMentah)) ?? '')
        : null;
    $meta = filled($meta ?? null) ? trim((string) $meta) : null;
    $adaIsi = filled($nama) || filled($deskripsi) || filled($meta);
@endphp

<span
    x-data="{ open: false }"
    data-silogy="kode-keterangan-trigger"
    data-jenis="{{ $jenis }}"
    style="position:relative;display:inline;"
>
    {{-- stopPropagation/preventDefault: di /komponen-penilaian seluruh card dibungkus
         <a recordUrl=edit>; tanpa ini klik kode ikut membuka form edit asesmen. --}}
    <button
        type="button"
        @click.stop.prevent="open = true"
        @mousedown.stop.prevent=""
        @keydown.enter.stop.prevent="open = true"
        @keydown.space.stop.prevent="open = true"
        onclick="event.stopPropagation(); event.preventDefault();"
        onmousedown="event.stopPropagation(); event.preventDefault();"
        title="Lihat keterangan {{ $jenis }}"
        aria-label="Lihat keterangan {{ $jenis }} {{ $kode }}"
        style="appearance:none;border:0;background:transparent;padding:0;margin:0;cursor:pointer;
            font:inherit;font-weight:inherit;color:inherit;line-height:inherit;
            text-decoration-line:underline;text-decoration-style:dotted;
            text-decoration-color:rgba(11,57,20,.45);text-underline-offset:3px;"
    >{{ $kode }}</button>

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            x-transition.opacity.duration.150ms
            @keydown.escape.window="open = false"
            role="dialog"
            aria-modal="true"
            aria-label="Keterangan {{ $jenis }} {{ $kode }}"
            data-silogy="kode-keterangan-dialog"
            style="position:fixed;inset:0;z-index:90;display:flex;align-items:flex-start;justify-content:center;
                padding:14vh 16px 24px;background:rgba(11,57,20,.22);"
            @click="open = false"
        >
            <div
                @click.stop
                style="width:min(100%,28rem);border-radius:14px;overflow:hidden;
                    border:1px solid rgba(11,57,20,.18);background:#fafaf9;
                    box-shadow:0 18px 40px -24px rgba(11,57,20,.55);"
            >
                <div style="padding:12px 14px;color:#fff;background:linear-gradient(120deg,#007000 0%,#009900 55%,#0b3914 100%);">
                    <div style="font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;opacity:.85;">
                        {{ $jenis }}
                    </div>
                    <div style="margin-top:2px;font-size:16px;font-weight:800;letter-spacing:-.01em;word-break:break-word;">
                        {{ $kode }}
                    </div>
                    @if ($nama)
                        <div style="margin-top:4px;font-size:12.5px;line-height:1.4;opacity:.92;">{{ $nama }}</div>
                    @endif
                </div>

                <div style="padding:12px 14px 14px;">
                    @if ($meta)
                        <div style="margin-bottom:8px;font-size:11px;line-height:1.4;opacity:.7;">{{ $meta }}</div>
                    @endif

                    @if (filled($deskripsi))
                        <p style="margin:0;font-size:13px;line-height:1.55;color:#14201a;">{{ $deskripsi }}</p>
                    @elseif (! $adaIsi)
                        <p style="margin:0;font-size:13px;line-height:1.55;opacity:.65;">Belum ada keterangan.</p>
                    @elseif (! filled($deskripsi) && filled($nama))
                        <p style="margin:0;font-size:12px;line-height:1.5;opacity:.6;">Tidak ada deskripsi tambahan.</p>
                    @endif

                    <div style="margin-top:12px;display:flex;justify-content:flex-end;">
                        <button
                            type="button"
                            @click="open = false"
                            style="padding:6px 12px;border-radius:8px;border:1px solid rgba(128,128,128,.35);
                                background:transparent;font-size:12px;font-weight:600;cursor:pointer;color:inherit;"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</span>

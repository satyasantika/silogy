{{-- Trigger kode CPL/BoK/CPMK/Sub-CPMK: panel keterangan muncul dekat tombol yang diklik
     (teleport ke body agar tidak terpotong overflow matriks).
     Props: $jenis (CPL|BoK|…), $kode, $deskripsi (?), $nama (?), $meta (?) --}}
@php
    /**
     * Amankan props dari kebocoran scope Livewire/Filament (mis. $meta berupa Closure).
     * Hanya terima skalar string/numerik untuk ditampilkan.
     */
    $silogyKeString = static function (mixed $nilai): ?string {
        if ($nilai === null || $nilai instanceof \Closure || is_object($nilai) || is_array($nilai)) {
            return null;
        }

        $teks = trim((string) $nilai);

        return $teks !== '' ? $teks : null;
    };

    $jenis = $silogyKeString($jenis ?? null) ?? 'CPL';
    $kode = $silogyKeString($kode ?? null) ?? '—';
    $nama = $silogyKeString($nama ?? null);
    $deskripsiMentah = $silogyKeString($deskripsi ?? null);
    $deskripsi = filled($deskripsiMentah)
        ? trim(preg_replace('/\s+/u', ' ', strip_tags($deskripsiMentah)) ?? '')
        : null;
    $deskripsi = filled($deskripsi) ? $deskripsi : null;
    $meta = $silogyKeString($meta ?? null);
    $adaIsi = filled($nama) || filled($deskripsi) || filled($meta);
@endphp

<span
    x-data="{
        open: false,
        panelStyle: 'visibility:hidden',
        buka(el) {
            this.open = true;
            this.panelStyle = 'visibility:hidden;position:fixed;top:0;left:0;z-index:91;width:min(28rem, calc(100vw - 24px))';
            this.$nextTick(() => {
                requestAnimationFrame(() => this.posisi(el));
            });
        },
        posisi(el) {
            const panel = this.$refs.panel;
            if (! el || ! panel) {
                return;
            }

            const rect = el.getBoundingClientRect();
            const gap = 8;
            const margin = 12;
            const vw = window.innerWidth;
            const vh = window.innerHeight;
            const pw = panel.offsetWidth || Math.min(448, vw - margin * 2);
            const ph = panel.offsetHeight || 160;

            let top = rect.bottom + gap;
            let left = rect.left;

            if (left + pw > vw - margin) {
                left = Math.max(margin, vw - pw - margin);
            }
            if (left < margin) {
                left = margin;
            }

            const muatDiBawah = top + ph <= vh - margin;
            const muatDiAtas = rect.top - gap - ph >= margin;

            if (! muatDiBawah && muatDiAtas) {
                top = rect.top - gap - ph;
            } else if (! muatDiBawah) {
                top = Math.max(margin, vh - ph - margin);
            }

            this.panelStyle = [
                'visibility:visible',
                'position:fixed',
                'top:' + top + 'px',
                'left:' + left + 'px',
                'z-index:91',
                'width:min(28rem, calc(100vw - 24px))',
            ].join(';');
        },
        tutup() {
            this.open = false;
            this.panelStyle = 'visibility:hidden';
        },
    }"
    data-silogy="kode-keterangan-trigger"
    data-jenis="{{ $jenis }}"
    style="position:relative;display:inline;"
>
    {{-- stopPropagation/preventDefault: di /komponen-penilaian seluruh card dibungkus
         <a recordUrl=edit>; tanpa ini klik kode ikut membuka form edit asesmen. --}}
    <button
        type="button"
        x-ref="trigger"
        @click.stop.prevent="buka($event.currentTarget)"
        @mousedown.stop.prevent=""
        @keydown.enter.stop.prevent="buka($event.currentTarget)"
        @keydown.space.stop.prevent="buka($event.currentTarget)"
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
            x-transition.opacity.duration.120ms
            @keydown.escape.window="tutup()"
            @scroll.window.passive="open && tutup()"
            @resize.window.passive="open && tutup()"
            role="presentation"
            data-silogy="kode-keterangan-backdrop"
            style="position:fixed;inset:0;z-index:90;background:rgba(11,57,20,.12);"
            @click="tutup()"
        >
            <div
                x-ref="panel"
                role="dialog"
                aria-modal="true"
                aria-label="Keterangan {{ $jenis }} {{ $kode }}"
                data-silogy="kode-keterangan-dialog"
                @click.stop
                :style="panelStyle"
            >
                {{-- Kartu solid terpisah dari :style posisi, agar latar tidak hilang diganti Alpine. --}}
                <div style="border-radius:14px;overflow:hidden;border:1px solid #c5d4c8;
                    background:#ffffff;box-shadow:0 12px 32px -8px rgba(11,57,20,.35),0 0 0 1px rgba(11,57,20,.06);">
                    <div style="padding:12px 14px;color:#ffffff;background:linear-gradient(120deg,#007000 0%,#009900 55%,#0b3914 100%);">
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

                    <div style="padding:12px 14px 14px;background:#ffffff;color:#14201a;">
                        @if ($meta)
                            <div style="margin-bottom:8px;font-size:11px;line-height:1.4;color:#4b5563;">{{ $meta }}</div>
                        @endif

                        @if (filled($deskripsi))
                            <p style="margin:0;font-size:13px;line-height:1.55;color:#14201a;">{{ $deskripsi }}</p>
                        @elseif (! $adaIsi)
                            <p style="margin:0;font-size:13px;line-height:1.55;color:#6b7280;">Belum ada keterangan.</p>
                        @elseif (! filled($deskripsi) && filled($nama))
                            <p style="margin:0;font-size:12px;line-height:1.5;color:#6b7280;">Tidak ada deskripsi tambahan.</p>
                        @endif

                        <div style="margin-top:12px;display:flex;justify-content:flex-end;">
                            <button
                                type="button"
                                @click="tutup()"
                                style="padding:6px 12px;border-radius:8px;border:1px solid #d1d5db;
                                    background:#f3f4f6;font-size:12px;font-weight:600;cursor:pointer;color:#14201a;"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</span>

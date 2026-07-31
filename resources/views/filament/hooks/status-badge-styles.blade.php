<style>
    /*
     * Badge/banner status (nilai huruf, pemetaan, peringatan) — bg tembus
     * pandang + teks solid, keduanya beda per mode supaya kontras terjaga
     * baik di halaman terang maupun gelap.
     */
    .silogy-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 999px;
        font-weight: 700;
        font-size: 12px;
    }

    .silogy-tone-success {
        background: rgba(34, 197, 94, .12);
        color: #166534;
    }

    .dark .silogy-tone-success {
        background: rgba(34, 197, 94, .18);
        color: #4ade80;
    }

    .silogy-tone-info {
        background: rgba(59, 130, 246, .12);
        color: #1d4ed8;
    }

    .dark .silogy-tone-info {
        background: rgba(59, 130, 246, .18);
        color: #60a5fa;
    }

    .silogy-tone-warning {
        background: rgba(245, 158, 11, .12);
        color: #92400e;
    }

    .dark .silogy-tone-warning {
        background: rgba(245, 158, 11, .18);
        color: #fbbf24;
    }

    .silogy-tone-danger {
        background: rgba(239, 68, 68, .12);
        color: #b91c1c;
    }

    .dark .silogy-tone-danger {
        background: rgba(239, 68, 68, .18);
        color: #f87171;
    }

    .silogy-tone-indigo {
        background: rgba(99, 102, 241, .12);
        color: #3730a3;
    }

    .dark .silogy-tone-indigo {
        background: rgba(99, 102, 241, .18);
        color: #a5b4fc;
    }

    .silogy-tone-neutral {
        background: rgba(107, 114, 128, .15);
        color: #6b7280;
    }

    .dark .silogy-tone-neutral {
        background: rgba(156, 163, 175, .18);
        color: #d1d5db;
    }

    /* Kartu ringkasan (mis. "Target Kelulusan CPL") */
    .silogy-stat-card {
        border: 1px solid rgba(0, 153, 0, .3);
        border-radius: 10px;
        padding: 8px 16px;
        background: #ffffff;
        color: #212529;
    }

    .dark .silogy-stat-card {
        background: #18181b;
        color: #f8f9fa;
        border-color: rgba(34, 197, 94, .35);
    }

    /*
     * Kolom Dikontrak (/mks): pasangan metrik kontrak — angka tabular
     * menonjol, label unit kecil. Hijau institusi untuk kelas, emas redup
     * untuk mahasiswa (bukan pill primary generik).
     */
    .silogy-dikontrak {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: stretch;
        gap: 6px;
    }

    .silogy-dikontrak-chip {
        display: inline-flex;
        flex-direction: column;
        justify-content: center;
        min-width: 3.25rem;
        padding: 4px 9px 5px;
        border-radius: 8px;
        border: 1px solid transparent;
        line-height: 1.1;
        text-align: start;
    }

    .silogy-dikontrak-n {
        font-size: 14px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.02em;
    }

    .silogy-dikontrak-l {
        margin-top: 2px;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        opacity: 0.72;
    }

    .silogy-dikontrak-kelas {
        background: color-mix(in srgb, #0b3914 8%, #ffffff);
        border-color: color-mix(in srgb, #0b3914 18%, transparent);
        color: #0b3914;
    }

    .silogy-dikontrak-mhs {
        background: color-mix(in srgb, #c9a227 14%, #ffffff);
        border-color: color-mix(in srgb, #a8841a 22%, transparent);
        color: #6b5310;
    }

    .dark .silogy-dikontrak-kelas {
        background: rgba(34, 197, 94, 0.12);
        border-color: rgba(74, 222, 128, 0.28);
        color: #86efac;
    }

    .dark .silogy-dikontrak-mhs {
        background: rgba(255, 215, 0, 0.1);
        border-color: rgba(255, 215, 0, 0.22);
        color: #fde68a;
    }
</style>

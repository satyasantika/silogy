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
</style>

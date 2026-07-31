<style>
    /*
     * Brand chrome: sidebar & identitas/tombol keluar fixed hijau tua di kedua
     * mode. Top navbar & halaman utama ikut toggle dark mode (lihat blok
     * masing-masing di bawah untuk varian .dark-nya).
     */

    /* ── SIDEBAR: Hijau Tua Gelap, teks putih ── */
    .fi-sidebar,
    .fi-sidebar-header {
        background-color: #0b3914 !important;
    }

    .fi-sidebar-item-label,
    .fi-sidebar-database-notifications-btn-label,
    .fi-sidebar-group-label {
        color: #f8f9fa !important;
    }

    .fi-sidebar-item-btn > .fi-icon,
    .fi-sidebar-group-btn .fi-icon,
    .fi-sidebar-group-dropdown-trigger-btn .fi-icon,
    .fi-sidebar-database-notifications-btn > .fi-icon {
        color: rgba(248, 249, 250, .65) !important;
    }

    .fi-sidebar-item-grouped-border-part-not-first,
    .fi-sidebar-item-grouped-border-part-not-last {
        background-color: rgba(248, 249, 250, .2) !important;
    }

    .fi-sidebar-item-grouped-border-part {
        background-color: rgba(248, 249, 250, .35) !important;
    }

    .fi-sidebar-item-has-url > .fi-sidebar-item-btn:hover,
    .fi-sidebar-database-notifications-btn:hover {
        background-color: rgba(248, 249, 250, .08) !important;
    }

    /* Menu aktif: Kuning Emas, teks hitam */
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background-color: #ffd700 !important;
    }

    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-icon,
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-sidebar-item-label {
        color: #000000 !important;
    }

    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-grouped-border-part {
        background-color: #000000 !important;
    }

    /* Tombol Keluar di footer sidebar: chip kontras di atas hijau tua */
    .silogy-sidebar-user-actions .fi-icon-btn {
        background-color: rgba(248, 249, 250, .14) !important;
        color: #ffffff !important;
        border-radius: 8px;
    }

    .silogy-sidebar-user-actions .fi-icon-btn:hover {
        background-color: #ef4444 !important;
        color: #ffffff !important;
    }

    /* Logo di sidebar-header (muncul di mobile) tetap kontras di atas hijau tua */
    .fi-sidebar-header .silogy-logo-text {
        color: #ffffff !important;
    }

    .fi-sidebar-header .silogy-logo-sub {
        color: rgba(248, 249, 250, .75) !important;
    }

    /* Identitas pengguna di footer sidebar: kuning emas, konsisten kedua mode */
    .silogy-sidebar-user-name,
    .silogy-sidebar-user-peran,
    .silogy-sidebar-user-unit {
        color: #ffd700 !important;
    }

    /* ── TOP NAVBAR: putih (terang) / abu gelap (dark), border hijau tetap ── */
    .fi-topbar {
        background-color: #ffffff !important;
        border-bottom: 3px solid #009900 !important;
        box-shadow: none !important;
    }

    .fi-topbar-item-label {
        color: #212529 !important;
    }

    .fi-topbar-item-btn > .fi-icon {
        color: #495057 !important;
    }

    .fi-topbar-item.fi-active .fi-topbar-item-label,
    .fi-topbar-item.fi-active .fi-topbar-item-btn > .fi-icon {
        color: #009900 !important;
    }

    .dark .fi-topbar {
        background-color: #18181b !important;
        border-bottom-color: #009900 !important;
    }

    .dark .fi-topbar-item-label {
        color: #f8f9fa !important;
    }

    .dark .fi-topbar-item-btn > .fi-icon {
        color: #9ca3af !important;
    }

    .dark .fi-topbar-item.fi-active .fi-topbar-item-label,
    .dark .fi-topbar-item.fi-active .fi-topbar-item-btn > .fi-icon {
        color: #4dcc4d !important;
    }

    /* ── HALAMAN UTAMA: latar terang Charcoal / gelap default Filament ──
       !important + selector .dark asli (bukan :where() bawaan Tailwind,
       yang spesifisitasnya 0 dan bisa kalah oleh rule terang ini). */
    .fi-body {
        background-color: #f8f9fa !important;
    }

    .fi-body .fi-main {
        color: #212529 !important;
        /* Kunci gutter seperti mobile — jangan naik di md/lg (px-6/px-8) */
        padding-inline: 1rem !important;
    }

    .dark .fi-body {
        background-color: #030712 !important;
    }

    .dark .fi-body .fi-main {
        color: #ffffff !important;
    }
</style>

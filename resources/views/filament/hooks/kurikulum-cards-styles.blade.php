<style>
    /* Kartu /kurikulums: Kerjakan di kiri, ikon Ubah di kanan */
    .silogy-kurikulum-cards .fi-ta-record .fi-ta-actions {
        display: flex !important;
        width: 100%;
        justify-content: space-between;
        align-items: center;
    }

    .silogy-kurikulum-cards .fi-ta-record .fi-ta-actions .silogy-kurikulum-edit-action {
        margin-inline-start: auto;
    }

    /* Status Kerjakan vs Sedang dikerjakan — pola sama dengan mata kuliah koordinator */
    .silogy-kurikulum-cards .silogy-kurikulum-card-sedang-dikerjakan {
        box-shadow: inset 0 0 0 2px #86efac;
        background-color: color-mix(in srgb, #dcfce7 35%, transparent);
    }

    .silogy-kurikulum-cards .silogy-kurikulum-aksi-sedang-dikerjakan {
        opacity: 1 !important;
    }

    .silogy-kurikulum-cards .silogy-kurikulum-aksi-kerjakan {
        font-weight: 600;
    }

    /* Kartu /mata-kuliah-koordinator: aksi Kerjakan full-width di kiri */
    .silogy-mk-koordinator-cards .fi-ta-record .fi-ta-actions {
        display: flex !important;
        width: 100%;
        justify-content: flex-start;
        align-items: center;
    }

    .silogy-mk-koordinator-cards .silogy-mk-card-sedang-dikerjakan {
        box-shadow: inset 0 0 0 2px #86efac;
        background-color: color-mix(in srgb, #dcfce7 35%, transparent);
    }

    .silogy-mk-koordinator-cards .silogy-mk-aksi-sedang-dikerjakan {
        opacity: 1 !important;
    }

    .silogy-mk-koordinator-cards .silogy-mk-aksi-kerjakan {
        font-weight: 600;
    }

    /*
     * /subcpmk & /komponen-penilaian: filter semester kiri, cari kanan.
     *
     * Catatan: Table::extraAttributes() menempel di akar .fi-ta, BUKAN di
     * .fi-ta-ctn — selector harus .fi-ta.silogy-mk-semester-toolbar …
     */
    .fi-ta.silogy-mk-semester-toolbar .fi-ta-header-ctn {
        display: flex;
        flex-wrap: wrap;
        align-items: stretch;
        border-bottom: 1px solid rgb(229 231 235);
    }

    .fi-ta.silogy-mk-semester-toolbar.fi-ta-dark .fi-ta-header-ctn,
    .dark .fi-ta.silogy-mk-semester-toolbar .fi-ta-header-ctn {
        border-bottom-color: rgb(255 255 255 / 0.1);
    }

    .fi-ta.silogy-mk-semester-toolbar .fi-ta-header {
        flex: 1 0 100%;
    }

    /* Semester kiri, pencarian kanan — lebar select mengikuti teks opsi */
    .fi-ta.silogy-mk-semester-toolbar .fi-ta-filters-above-content-ctn {
        order: 1;
        flex: 0 1 auto;
        width: max-content;
        max-width: min(100%, 32rem);
        display: flex !important;
        align-items: center;
        border-bottom: none !important;
        padding-block: 0.75rem !important;
        padding-inline: 1.5rem 0.75rem !important;
        box-sizing: border-box;
    }

    .fi-ta.silogy-mk-semester-toolbar .fi-ta-header-toolbar {
        order: 2;
        flex: 1 1 0;
        min-width: 12rem;
        margin-block: 0 !important;
        border-bottom: none !important;
        justify-content: flex-end !important;
    }

    .fi-ta.silogy-mk-semester-toolbar .fi-ta-filters {
        width: max-content;
        max-width: 100%;
        padding: 0 !important;
        border: none !important;
        background: transparent !important;
        box-shadow: none !important;
        gap: 0 !important;
        display: block !important;
    }

    .fi-ta.silogy-mk-semester-toolbar .fi-ta-filters-header {
        display: none !important;
    }

    .fi-ta.silogy-mk-semester-toolbar .fi-ta-filters .fi-fo-field-wrp,
    .fi-ta.silogy-mk-semester-toolbar .fi-ta-filters .fi-sc-component,
    .fi-ta.silogy-mk-semester-toolbar .fi-ta-filters .fi-fo-select-wrp {
        margin: 0;
        width: max-content;
        max-width: 100%;
    }

    .fi-ta.silogy-mk-semester-toolbar .fi-ta-filters .fi-input-wrp {
        width: max-content !important;
        max-width: 100%;
    }

    .fi-ta.silogy-mk-semester-toolbar .fi-ta-filters .fi-select-input,
    .fi-ta.silogy-mk-semester-toolbar .fi-ta-filters select {
        width: max-content !important;
        min-width: 10rem;
        max-width: 100%;
        field-sizing: content;
        text-overflow: clip;
        white-space: nowrap;
    }
</style>

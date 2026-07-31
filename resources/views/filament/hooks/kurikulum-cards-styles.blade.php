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
</style>

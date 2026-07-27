{{-- Modal konfirmasi Keluar: dua paket tombol (sekunder | primer), rata tengah. --}}
<style>
    .silogy-keluar-modal .fi-modal-footer {
        justify-content: center;
    }

    .silogy-keluar-modal .fi-modal-footer-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        align-items: center;
        gap: 0.75rem 1rem;
        width: 100%;
    }

    /* Non-mobile: dua paket berdampingan, masing-masing utuh (fi-btn-group). */
    @media (min-width: 640px) {
        .silogy-keluar-modal .fi-modal-footer-actions {
            flex-wrap: nowrap;
            width: auto;
            margin-inline: auto;
        }
    }

    /* Mobile: paket bertumpuk vertikal, tetap rata tengah. */
    @media (max-width: 639px) {
        .silogy-keluar-modal .fi-modal-footer-actions {
            flex-direction: column;
        }
    }
</style>

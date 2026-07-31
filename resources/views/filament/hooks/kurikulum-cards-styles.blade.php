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

    /*
     * /peserta-kelas (pola /subcpmk):
     * 1) banner + KPI full-width di card
     * 2) semester + Tarik data bergabung kiri
     * 3) pencarian kanan
     *
     * display:contents pada .fi-ta-header membuat anak (deskripsi & aksi)
     * menjadi sibling flex dari filter/toolbar.
     */
    .fi-ta.silogy-peserta-kelas .fi-ta-header-ctn {
        align-items: center;
        column-gap: 0.5rem;
        row-gap: 0;
    }

    .fi-ta.silogy-peserta-kelas .fi-ta-header {
        display: contents;
    }

    .fi-ta.silogy-peserta-kelas .fi-ta-header > div:not(.fi-ta-actions) {
        order: 0;
        flex: 1 0 100%;
        width: 100%;
        padding-block: 1rem 0.25rem;
        padding-inline: 1.5rem;
        box-sizing: border-box;
    }

    .fi-ta.silogy-peserta-kelas .fi-ta-header-description {
        margin: 0 !important;
        color: inherit !important;
    }

    .fi-ta.silogy-peserta-kelas .fi-ta-filters-above-content-ctn {
        order: 1;
        padding-inline-end: 0.35rem !important;
    }

    .fi-ta.silogy-peserta-kelas .fi-ta-header .fi-ta-actions {
        order: 2;
        flex: 0 0 auto;
        align-self: center;
        padding-block: 0.75rem;
        padding-inline-end: 0.75rem;
    }

    .fi-ta.silogy-peserta-kelas .fi-ta-header-toolbar {
        order: 3;
        flex: 1 1 0;
        min-width: 12rem;
        justify-content: flex-end !important;
    }

    .fi-ta.silogy-peserta-kelas .fi-ta-filter-indicators {
        display: none !important;
    }

    .silogy-peserta-deskripsi {
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
    }

    /*
     * Bento KPI peserta kelas — dual-pane ledger (semester | semua).
     * Hijau institusi + aksen emas; bukan kartu generik ungu/krem.
     */
    .silogy-peserta-kpi {
        margin: 0;
    }

    .silogy-peserta-kpi__bento {
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        gap: 0;
        border-radius: 14px;
        overflow: hidden;
        border: 1px solid rgba(11, 57, 20, 0.18);
        background:
            linear-gradient(135deg, rgba(11, 57, 20, 0.045) 0%, rgba(255, 215, 0, 0.06) 52%, rgba(247, 250, 247, 0.9) 100%);
        box-shadow: 0 1px 0 rgba(255, 255, 255, 0.65) inset;
    }

    .silogy-peserta-kpi__bento--solo {
        grid-template-columns: 1fr;
    }

    .dark .silogy-peserta-kpi__bento {
        border-color: rgba(255, 215, 0, 0.22);
        background:
            linear-gradient(135deg, rgba(11, 57, 20, 0.55) 0%, rgba(24, 24, 27, 0.92) 55%, rgba(255, 215, 0, 0.08) 100%);
        box-shadow: none;
    }

    .silogy-peserta-kpi__pane {
        padding: 1rem 1.15rem 1.1rem;
        min-width: 0;
    }

    .silogy-peserta-kpi__rule {
        width: 2px;
        margin-block: 0.85rem;
        border-radius: 999px;
        background: linear-gradient(
            180deg,
            rgba(255, 215, 0, 0.15) 0%,
            #ffd700 45%,
            rgba(255, 215, 0, 0.15) 100%
        );
    }

    .silogy-peserta-kpi__eyebrow {
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #0b3914;
        opacity: 0.72;
    }

    .dark .silogy-peserta-kpi__eyebrow {
        color: #ffd700;
        opacity: 0.9;
    }

    .silogy-peserta-kpi__caption {
        margin-top: 0.2rem;
        font-size: 0.8125rem;
        line-height: 1.35;
        color: #14201a;
        opacity: 0.78;
        max-width: 22rem;
    }

    .dark .silogy-peserta-kpi__caption {
        color: #f8f9fa;
        opacity: 0.7;
    }

    .silogy-peserta-kpi__tiles {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.65rem;
        margin-top: 0.85rem;
    }

    .silogy-peserta-kpi__tile {
        padding: 0.7rem 0.8rem;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.72);
        border: 1px solid rgba(11, 57, 20, 0.1);
    }

    .dark .silogy-peserta-kpi__tile {
        background: rgba(0, 0, 0, 0.28);
        border-color: rgba(255, 255, 255, 0.08);
    }

    .silogy-peserta-kpi__label {
        display: block;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        opacity: 0.58;
        color: #14201a;
    }

    .dark .silogy-peserta-kpi__label {
        color: #f8f9fa;
        opacity: 0.55;
    }

    .silogy-peserta-kpi__value {
        display: block;
        margin-top: 0.15rem;
        font-size: 1.625rem;
        font-weight: 800;
        line-height: 1.1;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.02em;
        color: #0b3914;
    }

    .silogy-peserta-kpi__value--gold {
        color: #8a6d00;
    }

    .dark .silogy-peserta-kpi__value {
        color: #f8f9fa;
    }

    .dark .silogy-peserta-kpi__value--gold {
        color: #ffd700;
    }

    @media (max-width: 767px) {
        .silogy-peserta-kpi__bento {
            grid-template-columns: 1fr;
        }

        .silogy-peserta-kpi__rule {
            width: auto;
            height: 2px;
            margin-block: 0;
            margin-inline: 1.15rem;
        }

        .fi-ta.silogy-peserta-kelas .fi-ta-header > div:not(.fi-ta-actions) {
            padding-inline: 1rem;
        }

        .fi-ta.silogy-peserta-kelas .fi-ta-filters-above-content-ctn,
        .fi-ta.silogy-peserta-kelas .fi-ta-header .fi-ta-actions,
        .fi-ta.silogy-peserta-kelas .fi-ta-header-toolbar {
            width: 100%;
            max-width: 100%;
            padding-inline: 1rem !important;
        }

        .fi-ta.silogy-peserta-kelas .fi-ta-header-toolbar {
            justify-content: flex-start !important;
        }
    }

    @media (prefers-reduced-motion: no-preference) {
        .silogy-peserta-kpi__bento {
            animation: silogy-peserta-kpi-in 420ms cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        .silogy-peserta-kpi__tile {
            transition: transform 160ms ease, border-color 160ms ease;
        }

        .silogy-peserta-kpi__tile:hover {
            transform: translateY(-1px);
            border-color: rgba(11, 57, 20, 0.28);
        }

        .dark .silogy-peserta-kpi__tile:hover {
            border-color: rgba(255, 215, 0, 0.35);
        }
    }

    @keyframes silogy-peserta-kpi-in {
        from {
            opacity: 0;
            transform: translateY(6px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /*
     * /penilaian (Pengampu MK):
     * - KPI bento full-width di atas toolbar
     * - semester + Tarik data kiri, pencarian kanan
     * - card 1 kolom per unit penawaran (prodi) + tabel kelas
     */
    .fi-ta.silogy-penilaian-dosen .fi-ta-header-ctn {
        align-items: center;
        column-gap: 0.5rem;
        row-gap: 0;
    }

    .fi-ta.silogy-penilaian-dosen .fi-ta-header {
        display: contents;
    }

    .fi-ta.silogy-penilaian-dosen .fi-ta-header > div:not(.fi-ta-actions) {
        order: 0;
        flex: 1 0 100%;
        width: 100%;
        padding-block: 1rem 0.35rem;
        padding-inline: 1.5rem;
        box-sizing: border-box;
    }

    .fi-ta.silogy-penilaian-dosen .fi-ta-header-description {
        margin: 0 !important;
        color: inherit !important;
    }

    .fi-ta.silogy-penilaian-dosen .fi-ta-filters-above-content-ctn {
        order: 1;
        padding-inline-end: 0.35rem !important;
    }

    .fi-ta.silogy-penilaian-dosen .fi-ta-header .fi-ta-actions {
        order: 2;
        flex: 0 0 auto;
        align-self: center;
        padding-block: 0.75rem;
        padding-inline-end: 0.75rem;
    }

    .fi-ta.silogy-penilaian-dosen .fi-ta-header-toolbar {
        order: 3;
        flex: 1 1 0;
        min-width: 12rem;
        justify-content: flex-end !important;
    }

    .fi-ta.silogy-penilaian-dosen .fi-ta-filter-indicators {
        display: none !important;
    }

    /* KPI bento — ledger pengampu + donut progress (bukan kartu generik) */
    .silogy-penilaian-kpi {
        margin: 0;
    }

    .silogy-penilaian-kpi__bento {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) auto minmax(0, 1fr);
        gap: 0;
        border-radius: 14px;
        overflow: hidden;
        border: 1px solid rgba(11, 57, 20, 0.18);
        background:
            linear-gradient(135deg, rgba(11, 57, 20, 0.045) 0%, rgba(255, 215, 0, 0.06) 52%, rgba(247, 250, 247, 0.9) 100%);
        box-shadow: 0 1px 0 rgba(255, 255, 255, 0.65) inset;
    }

    .dark .silogy-penilaian-kpi__bento {
        border-color: rgba(255, 215, 0, 0.22);
        background:
            linear-gradient(135deg, rgba(11, 57, 20, 0.55) 0%, rgba(24, 24, 27, 0.92) 55%, rgba(255, 215, 0, 0.08) 100%);
        box-shadow: none;
    }

    .silogy-penilaian-kpi__pane {
        padding: 1rem 1.15rem 1.1rem;
        min-width: 0;
    }

    .silogy-penilaian-kpi__rule {
        width: 2px;
        margin-block: 0.85rem;
        border-radius: 999px;
        background: linear-gradient(
            180deg,
            rgba(255, 215, 0, 0.15) 0%,
            #ffd700 45%,
            rgba(255, 215, 0, 0.15) 100%
        );
    }

    .silogy-penilaian-kpi__eyebrow {
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #0b3914;
        opacity: 0.72;
    }

    .dark .silogy-penilaian-kpi__eyebrow {
        color: #ffd700;
        opacity: 0.9;
    }

    .silogy-penilaian-kpi__caption {
        margin-top: 0.2rem;
        font-size: 0.8125rem;
        line-height: 1.35;
        color: #14201a;
        opacity: 0.78;
    }

    .dark .silogy-penilaian-kpi__caption {
        color: #f8f9fa;
        opacity: 0.7;
    }

    .silogy-penilaian-kpi__tiles {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.65rem;
        margin-top: 0.85rem;
    }

    .silogy-penilaian-kpi__tile {
        padding: 0.7rem 0.8rem;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.72);
        border: 1px solid rgba(11, 57, 20, 0.1);
    }

    .dark .silogy-penilaian-kpi__tile {
        background: rgba(0, 0, 0, 0.28);
        border-color: rgba(255, 255, 255, 0.08);
    }

    .silogy-penilaian-kpi__label {
        display: block;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        opacity: 0.58;
        color: #14201a;
    }

    .dark .silogy-penilaian-kpi__label {
        color: #f8f9fa;
        opacity: 0.55;
    }

    .silogy-penilaian-kpi__value {
        display: block;
        margin-top: 0.15rem;
        font-size: 1.625rem;
        font-weight: 800;
        line-height: 1.1;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.02em;
        color: #0b3914;
    }

    .silogy-penilaian-kpi__value--gold {
        color: #8a6d00;
    }

    .dark .silogy-penilaian-kpi__value {
        color: #f8f9fa;
    }

    .dark .silogy-penilaian-kpi__value--gold {
        color: #ffd700;
    }

    .silogy-penilaian-kpi__hint {
        margin: 0.75rem 0 0;
        font-size: 0.75rem;
        line-height: 1.4;
        color: #92400e;
    }

    .dark .silogy-penilaian-kpi__hint {
        color: #fde68a;
    }

    .silogy-penilaian-kpi__progress {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-top: 0.85rem;
    }

    .silogy-penilaian-kpi__donut {
        --pct: 0;
        position: relative;
        flex: 0 0 5.5rem;
        width: 5.5rem;
        height: 5.5rem;
    }

    .silogy-penilaian-kpi__donut-ring {
        position: absolute;
        inset: 0;
        border-radius: 50%;
        background: conic-gradient(
            #0b3914 0 calc(var(--pct) * 1%),
            rgba(11, 57, 20, 0.12) calc(var(--pct) * 1%) 100%
        );
        -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 0.7rem), #000 calc(100% - 0.68rem));
        mask: radial-gradient(farthest-side, transparent calc(100% - 0.7rem), #000 calc(100% - 0.68rem));
    }

    .dark .silogy-penilaian-kpi__donut-ring {
        background: conic-gradient(
            #ffd700 0 calc(var(--pct) * 1%),
            rgba(255, 215, 0, 0.14) calc(var(--pct) * 1%) 100%
        );
    }

    .silogy-penilaian-kpi__donut-hole {
        position: absolute;
        inset: 0.85rem;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.88);
        border: 1px solid rgba(11, 57, 20, 0.08);
    }

    .dark .silogy-penilaian-kpi__donut-hole {
        background: rgba(0, 0, 0, 0.35);
        border-color: rgba(255, 255, 255, 0.08);
    }

    .silogy-penilaian-kpi__donut-value {
        font-size: 1.05rem;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.02em;
        color: #0b3914;
        line-height: 1;
    }

    .dark .silogy-penilaian-kpi__donut-value {
        color: #ffd700;
    }

    .silogy-penilaian-kpi__progress-meta {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        min-width: 0;
    }

    .silogy-penilaian-kpi__progress-title {
        font-size: 0.875rem;
        font-weight: 600;
        line-height: 1.35;
        color: #14201a;
    }

    .dark .silogy-penilaian-kpi__progress-title {
        color: #f8f9fa;
    }

    .silogy-penilaian-kpi__progress-sub {
        font-size: 0.75rem;
        color: #6b7280;
        font-variant-numeric: tabular-nums;
    }

    .dark .silogy-penilaian-kpi__progress-sub {
        color: rgb(161 161 170);
    }

    @media (max-width: 767px) {
        .silogy-penilaian-kpi__bento {
            grid-template-columns: 1fr;
        }

        .silogy-penilaian-kpi__rule {
            width: auto;
            height: 2px;
            margin-block: 0;
            margin-inline: 1.15rem;
        }

        .silogy-penilaian-kpi__tiles {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .fi-ta.silogy-penilaian-dosen .fi-ta-header > div:not(.fi-ta-actions) {
            padding-inline: 1rem;
        }
    }

    @media (prefers-reduced-motion: no-preference) {
        .silogy-penilaian-kpi__bento {
            animation: silogy-penilaian-kpi-in 420ms cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        .silogy-penilaian-kpi__tile {
            transition: transform 160ms ease, border-color 160ms ease;
        }

        .silogy-penilaian-kpi__tile:hover {
            transform: translateY(-1px);
            border-color: rgba(11, 57, 20, 0.28);
        }

        .dark .silogy-penilaian-kpi__tile:hover {
            border-color: rgba(255, 215, 0, 0.35);
        }
    }

    @keyframes silogy-penilaian-kpi-in {
        from {
            opacity: 0;
            transform: translateY(6px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .fi-ta.silogy-penilaian-dosen .fi-ta-content-grid {
        grid-template-columns: minmax(0, 1fr) !important;
        gap: 0.85rem;
    }

    .fi-ta.silogy-penilaian-dosen .fi-ta-content-grid .fi-ta-record-content-ctn {
        padding-inline: 1.1rem;
        padding-block: 1rem 1.05rem;
        box-sizing: border-box;
        gap: 0.75rem;
        border-left: 3px solid #0b3914;
    }

    .dark .fi-ta.silogy-penilaian-dosen .fi-ta-content-grid .fi-ta-record-content-ctn {
        border-left-color: #ffd700;
    }

    .fi-ta.silogy-penilaian-dosen .fi-ta-content-grid .fi-ta-record-content,
    .fi-ta.silogy-penilaian-dosen .fi-ta-content-grid .fi-ta-col {
        width: 100%;
        min-width: 0;
    }

    .silogy-penilaian-prodi__judul {
        display: flex;
        align-items: baseline;
        gap: 0.5rem;
        width: 100%;
        min-width: 0;
    }

    .silogy-penilaian-prodi__nama {
        font-weight: 700;
        font-size: 1rem;
        line-height: 1.3;
        letter-spacing: -0.01em;
        color: #0b3914;
        min-width: 0;
    }

    .dark .silogy-penilaian-prodi__nama {
        color: #ffd700;
    }

    .silogy-penilaian-prodi__empty {
        margin-top: 0.25rem;
        font-size: 0.8125rem;
        color: #6b7280;
    }

    .silogy-penilaian-prodi__table-wrap {
        width: 100%;
        min-width: 0;
        overflow-x: auto;
        border-radius: 8px;
        border: 1px solid rgb(229 231 235);
        background: rgb(250 250 249);
    }

    .dark .silogy-penilaian-prodi__table-wrap {
        border-color: rgb(255 255 255 / 0.12);
        background: rgb(255 255 255 / 0.03);
    }

    .silogy-penilaian-prodi__table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8125rem;
        line-height: 1.35;
    }

    .silogy-penilaian-prodi__table thead th {
        text-align: left;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #6b7280;
        padding: 0.55rem 0.75rem;
        border-bottom: 1px solid rgb(229 231 235);
        white-space: nowrap;
        background: rgb(255 255 255 / 0.65);
    }

    .dark .silogy-penilaian-prodi__table thead th {
        color: rgb(161 161 170);
        border-bottom-color: rgb(255 255 255 / 0.1);
        background: rgb(0 0 0 / 0.15);
    }

    .silogy-penilaian-prodi__td {
        padding: 0.6rem 0.75rem;
        border-bottom: 1px solid rgb(243 244 246);
        vertical-align: middle;
    }

    .silogy-penilaian-prodi__row:last-child .silogy-penilaian-prodi__td {
        border-bottom: none;
    }

    .dark .silogy-penilaian-prodi__td {
        border-bottom-color: rgb(255 255 255 / 0.06);
    }

    .silogy-penilaian-prodi__td--kode {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-weight: 600;
        font-size: 0.75rem;
        white-space: nowrap;
        width: 1%;
    }

    .silogy-penilaian-prodi__td--kelas,
    .silogy-penilaian-prodi__td--mhs {
        white-space: nowrap;
        width: 1%;
        font-variant-numeric: tabular-nums;
        font-weight: 600;
    }

    .silogy-penilaian-prodi__td--status {
        min-width: 11rem;
        max-width: 18rem;
    }

    .silogy-penilaian-prodi__td--aksi {
        white-space: nowrap;
        width: 1%;
        text-align: right;
    }

    .silogy-penilaian-prodi__link-kode,
    .silogy-penilaian-prodi__link-nama {
        color: inherit;
        text-decoration: none;
    }

    .silogy-penilaian-prodi__link-kode:hover,
    .silogy-penilaian-prodi__link-nama:hover {
        color: #0b3914;
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .dark .silogy-penilaian-prodi__link-kode:hover,
    .dark .silogy-penilaian-prodi__link-nama:hover {
        color: #ffd700;
    }

    .silogy-penilaian-prodi__status {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1.25;
        text-decoration: none;
    }

    .silogy-penilaian-prodi__status--pending {
        padding: 0.2rem 0.55rem;
        border-radius: 6px;
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fcd34d;
        white-space: nowrap;
        transition: background 120ms ease, border-color 120ms ease;
    }

    .silogy-penilaian-prodi__status--pending:hover {
        background: #fde68a;
        border-color: #f59e0b;
    }

    .silogy-penilaian-prodi__status--wait {
        display: inline;
        font-weight: 500;
        font-size: 0.75rem;
        line-height: 1.35;
        color: #6b7280;
        white-space: normal;
    }

    .dark .silogy-penilaian-prodi__status--wait {
        color: rgb(161 161 170);
    }

    .silogy-penilaian-prodi__status--ok {
        color: #166534;
        white-space: nowrap;
    }

    .silogy-penilaian-prodi__rata {
        font-variant-numeric: tabular-nums;
        font-weight: 700;
    }

    .silogy-penilaian-prodi__laporan,
    .silogy-penilaian-prodi__aksi {
        padding: 0.15rem 0.5rem;
        border-radius: 6px;
        font-weight: 700;
        font-size: 0.6875rem;
        letter-spacing: 0.02em;
        text-decoration: none;
        transition: background 120ms ease, transform 120ms ease;
    }

    .silogy-penilaian-prodi__laporan {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #86efac;
    }

    .silogy-penilaian-prodi__laporan:hover {
        background: #bbf7d0;
        transform: translateY(-1px);
    }

    .silogy-penilaian-prodi__aksi {
        display: inline-flex;
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
    }

    .silogy-penilaian-prodi__aksi:hover {
        background: #dbeafe;
        transform: translateY(-1px);
    }

    .silogy-penilaian-prodi__aksi-disabled {
        font-size: 0.75rem;
        color: #9ca3af;
    }

    .silogy-penilaian-prodi__laporan:focus-visible,
    .silogy-penilaian-prodi__aksi:focus-visible,
    .silogy-penilaian-prodi__status--pending:focus-visible,
    .silogy-penilaian-prodi__link-kode:focus-visible,
    .silogy-penilaian-prodi__link-nama:focus-visible {
        outline: 2px solid #0b3914;
        outline-offset: 2px;
    }

    .dark .silogy-penilaian-prodi__status--pending {
        background: rgba(146, 64, 14, 0.35);
        color: #fde68a;
        border-color: rgba(252, 211, 77, 0.35);
    }

    .dark .silogy-penilaian-prodi__status--ok {
        color: #bbf7d0;
    }

    .dark .silogy-penilaian-prodi__laporan {
        background: rgba(22, 101, 52, 0.35);
        color: #bbf7d0;
        border-color: rgba(134, 239, 172, 0.35);
    }

    .dark .silogy-penilaian-prodi__laporan:hover {
        background: rgba(22, 101, 52, 0.55);
    }

    .dark .silogy-penilaian-prodi__aksi {
        background: rgba(59, 130, 246, 0.18);
        color: #93c5fd;
        border-color: rgba(147, 197, 253, 0.35);
    }

    .dark .silogy-penilaian-prodi__aksi:hover {
        background: rgba(59, 130, 246, 0.3);
    }

    @media (prefers-reduced-motion: reduce) {
        .silogy-penilaian-prodi__status--pending,
        .silogy-penilaian-prodi__laporan,
        .silogy-penilaian-prodi__aksi {
            transition: none;
        }

        .silogy-penilaian-prodi__laporan:hover,
        .silogy-penilaian-prodi__aksi:hover {
            transform: none;
        }
    }

</style>

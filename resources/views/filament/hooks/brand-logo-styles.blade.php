{{-- Gaya brand logo panel — meniru logo-mark / logo-sub di landing & login. --}}
<style>
    .fi-logo {
        align-items: center;
        gap: .75rem;
        height: auto !important;
        font-size: 1.05rem;
        line-height: 1.15;
        letter-spacing: -.02em;
    }

    .fi-logo .silogy-logo-mark {
        position: relative;
        flex-shrink: 0;
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 10px;
        overflow: hidden;
        display: grid;
        place-items: center;
        background: transparent;
    }

    .fi-logo .silogy-logo-mark img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
    }

    .fi-logo .silogy-logo-text {
        display: flex;
        flex-direction: column;
        min-width: 0;
        font-weight: 800;
    }

    .fi-logo .silogy-logo-sub {
        display: block;
        margin-top: 2px;
        font-size: .6rem;
        font-weight: 600;
        line-height: 1.2;
        letter-spacing: .015em;
        color: rgb(107 114 128); /* gray-500 */
        white-space: normal;
    }

    .dark .fi-logo .silogy-logo-sub {
        color: rgb(156 163 175); /* gray-400 */
    }
</style>

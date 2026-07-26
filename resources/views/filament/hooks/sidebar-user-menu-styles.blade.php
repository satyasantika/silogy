{{--
    Identitas user bawaan Filament di topbar disembunyikan saat sidebar
    desktop terbuka (info user sudah ada di footer sidebar). Tampil kembali
    pada mobile (< lg) atau saat sidebar diminimize.
--}}
<style>
    @media (min-width: 1024px) {
        .fi-body:has(.fi-main-sidebar.fi-sidebar-open) .fi-topbar-end > .fi-user-menu {
            display: none;
        }
    }
</style>

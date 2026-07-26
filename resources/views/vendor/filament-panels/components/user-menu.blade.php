{{--
    Override total dari vendor/filament/filament/resources/views/components/user-menu.blade.php.
    Dropdown Profile+Logout bawaan Filament diganti sepenuhnya oleh
    komponen App\Modules\Auth\Livewire\PeranUnitMenu (avatar/Nama/Peran/
    Unit + tombol Keluar, lihat AdminPanelProvider & trait
    HasGantiPeranUnitAction). Render hook USER_MENU_BEFORE/_AFTER WAJIB
    dipertahankan supaya RoleSwitcher (tidak disentuh sama sekali) tetap
    tampil persis di posisi yang sama seperti sebelumnya.
--}}
{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_BEFORE) }}

@livewire('silogy.peran-unit-menu')

{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_AFTER) }}

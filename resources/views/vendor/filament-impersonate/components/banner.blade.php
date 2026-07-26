{{--
    Override total dari
    vendor/stechstudio/filament-impersonate/resources/views/components/banner.blade.php.
    Banner teks "Anda sedang berpura-pura menjadi X" + tombol "Tinggalkan"
    bawaan paket dihapus total — fungsinya sudah dipindah ke menu pengguna
    (PeranUnitMenu) dan halaman PilihPeranUnit. Diganti outline animasi
    warna-warni di sekeliling halaman sebagai indikator impersonate.
    Kondisi tampil dipertahankan PERSIS sama seperti file asli.
--}}
@php
    $impersonatorGuard = app('impersonate')->getImpersonatorGuardUsingName();
    $currentPanelGuard = \Filament\Facades\Filament::getAuthGuard();
    $shouldShowBorder = app('impersonate')->isImpersonating()
        && $currentPanelGuard
        && $impersonatorGuard === $currentPanelGuard;
@endphp

@if ($shouldShowBorder)
    <style>
        @keyframes silogy-impersonate-border {
            0% { border-color: #f87171; }
            25% { border-color: #fbbf24; }
            50% { border-color: #34d399; }
            75% { border-color: #60a5fa; }
            100% { border-color: #f87171; }
        }

        #silogy-impersonate-outline {
            position: fixed;
            inset: 0;
            border: 4px solid #f87171;
            pointer-events: none;
            z-index: 9999;
            animation: silogy-impersonate-border 4s linear infinite;
        }
    </style>

    <div id="silogy-impersonate-outline"></div>
@endif

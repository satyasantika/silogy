<div>
    @include(\App\Modules\Kurikulum\Filament\Support\BannerKurikulumDikerjakan::VIEW, [
        'kurikulum' => $kurikulum,
        'hierarki' => $hierarki,
        'catatan' => $catatan,
        'peringatan' => $peringatan,
        'arahan' => $arahan,
        'aksi' => $kurikulum ? $this->gantiAction : ($adaOpsi ? $this->pilihAction : null),
    ])

    <x-filament-actions::modals />
</div>

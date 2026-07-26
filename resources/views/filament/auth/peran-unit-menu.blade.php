<div style="display:grid;grid-template-columns:1fr auto;gap:12px;align-items:center;padding:8px 12px;min-width:260px;">
    <div style="display:flex;gap:10px;align-items:center;">
        <x-filament-panels::avatar.user size="md" :user="$user" loading="lazy" />

        <div>
            <p style="font-weight:600;font-size:.875rem;">{{ $user ? filament()->getUserName($user) : '' }}</p>

            @if (count($roles) === 0)
                <p class="fi-color-danger fi-text-color-600" style="font-size:.75rem;font-weight:600;">
                    Belum ada peran/unit. Hubungi admin.
                </p>
            @else
                <p style="font-size:.75rem;opacity:.75;">
                    Peran: {{ $peranAktif }}
                    @if ($bisaGanti)
                        {{ $this->gantiPeranUnitAction() }}
                    @endif
                </p>
                <p style="font-size:.75rem;opacity:.75;">
                    Unit: {{ $unitAktifId ? \App\Modules\Institusi\Models\AcademicUnit::find($unitAktifId)?->nama : '—' }}
                    @if ($bisaGanti)
                        {{ $this->gantiPeranUnitAction() }}
                    @endif
                </p>
            @endif
        </div>
    </div>

    <div style="display:flex;gap:4px;">
        @if ($isImpersonating)
            <x-filament::icon-button
                icon="heroicon-o-arrow-uturn-left"
                color="warning"
                tooltip="Tinggalkan impersonate"
                wire:click="leaveImpersonate"
            />
        @endif

        <form action="{{ filament()->getLogoutUrl() }}" method="post">
            @csrf
            <x-filament::icon-button
                icon="heroicon-o-arrow-left-end-on-rectangle"
                color="danger"
                tooltip="Keluar"
                tag="button"
                type="submit"
            />
        </form>
    </div>
</div>

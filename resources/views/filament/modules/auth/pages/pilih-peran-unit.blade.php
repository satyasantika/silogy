<x-filament-panels::page>
    <style>
        .silogy-peran-identitas {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.15rem;
            margin-bottom: 1.25rem;
            border-radius: .9rem;
            border: 1px solid rgba(148, 163, 184, .28);
            background: linear-gradient(135deg, rgba(30, 58, 95, .06), rgba(22, 163, 74, .05));
        }

        .dark .silogy-peran-identitas {
            border-color: rgba(148, 163, 184, .18);
            background: linear-gradient(135deg, rgba(30, 58, 95, .28), rgba(22, 163, 74, .12));
        }

        .silogy-peran-identitas-meta {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: .2rem;
        }

        .silogy-peran-identitas-nama {
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.25;
        }

        .silogy-peran-identitas-sub,
        .silogy-peran-identitas-chip {
            font-size: .8125rem;
            opacity: .78;
        }

        .silogy-peran-identitas-chips {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem .75rem;
            margin-top: .35rem;
        }

        .silogy-peran-identitas-chip strong {
            font-weight: 700;
            opacity: 1;
        }
    </style>

    <x-filament::section>
        <x-slot name="heading">
            Pilih peran dan unit Anda
        </x-slot>

        @if ($user ?? null)
            <div class="silogy-peran-identitas">
                <x-filament-panels::avatar.user size="lg" :user="$user" loading="lazy" />

                <div class="silogy-peran-identitas-meta">
                    <div class="silogy-peran-identitas-nama">
                        {{ filament()->getUserName($user) }}
                    </div>
                    <div class="silogy-peran-identitas-sub">
                        @if (filled($user->username))
                            {{ '@'.$user->username }}
                        @endif
                        @if (filled($user->email))
                            @if (filled($user->username)) · @endif{{ $user->email }}
                        @endif
                    </div>
                    <div class="silogy-peran-identitas-chips">
                        <span class="silogy-peran-identitas-chip">
                            Peran saat ini:
                            <strong>{{ filled($peranAktif) ? $peranAktif : 'Belum dipilih' }}</strong>
                        </span>
                        <span class="silogy-peran-identitas-chip">
                            Unit saat ini:
                            <strong>{{ filled($unitAktifNama) ? $unitAktifNama : 'Belum dipilih' }}</strong>
                        </span>
                    </div>
                </div>
            </div>
        @endif

        <p style="font-size:13px;opacity:.75;margin-bottom:16px;">
            Pilih peran yang ingin digunakan sekarang — menu dan hak akses akan
            mengikuti peran ini. Anda bisa menggantinya kapan saja lewat menu
            identitas di footer sidebar atau sudut kanan atas.
        </p>

        <form wire:submit="submit">
            {{ $this->form }}

            <div style="margin-top:20px;display:flex;gap:8px;flex-wrap:wrap;">
                <x-filament::button type="submit" icon="heroicon-o-arrow-right">
                    Lanjutkan
                </x-filament::button>

                @if ($this->isImpersonating())
                    <x-filament::button color="warning" wire:click="leaveImpersonate" type="button" icon="heroicon-o-arrow-uturn-left">
                        Tinggalkan impersonate
                    </x-filament::button>
                @endif
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>

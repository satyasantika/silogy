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

        .silogy-peran-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(148px, 1fr));
            gap: 1rem;
        }

        .silogy-peran-card {
            aspect-ratio: 1 / 1;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: .75rem;
            padding: 1rem .75rem;
            border-radius: 1rem;
            border: 1px solid rgba(148, 163, 184, .28);
            background: rgba(255, 255, 255, .72);
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
            cursor: pointer;
            text-align: center;
            transition: transform .15s ease, border-color .15s ease, box-shadow .15s ease, background .15s ease;
            --card-accent: #64748b;
        }

        .dark .silogy-peran-card {
            background: rgba(15, 23, 42, .45);
            border-color: rgba(148, 163, 184, .18);
        }

        .silogy-peran-card:hover {
            transform: translateY(-2px);
            border-color: color-mix(in srgb, var(--card-accent) 55%, transparent);
            box-shadow: 0 8px 20px rgba(15, 23, 42, .08);
            background: color-mix(in srgb, var(--card-accent) 8%, white);
        }

        .dark .silogy-peran-card:hover {
            background: color-mix(in srgb, var(--card-accent) 18%, rgb(15 23 42));
        }

        .silogy-peran-card:focus-visible {
            outline: 2px solid var(--card-accent);
            outline-offset: 2px;
        }

        .silogy-peran-card[data-color="danger"] { --card-accent: #ef4444; }
        .silogy-peran-card[data-color="warning"] { --card-accent: #f59e0b; }
        .silogy-peran-card[data-color="info"] { --card-accent: #0ea5e9; }
        .silogy-peran-card[data-color="success"] { --card-accent: #22c55e; }
        .silogy-peran-card[data-color="primary"] { --card-accent: #3b82f6; }
        .silogy-peran-card[data-color="gray"] { --card-accent: #64748b; }

        .silogy-peran-card-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 3.25rem;
            height: 3.25rem;
            border-radius: .9rem;
            color: var(--card-accent);
            background: color-mix(in srgb, var(--card-accent) 14%, transparent);
            flex-shrink: 0;
        }

        .silogy-peran-card-icon svg {
            width: 1.75rem;
            height: 1.75rem;
        }

        .silogy-peran-card-label {
            font-size: .875rem;
            font-weight: 700;
            line-height: 1.3;
            color: inherit;
            word-break: break-word;
        }

        .silogy-peran-card-hint {
            font-size: .75rem;
            opacity: .65;
            line-height: 1.25;
        }

        .silogy-peran-step-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .silogy-peran-step-title {
            font-size: .9375rem;
            font-weight: 700;
        }

        .silogy-peran-step-sub {
            font-size: .8125rem;
            opacity: .72;
            margin-top: .15rem;
        }

        .silogy-peran-actions {
            margin-top: 1.25rem;
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
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

        @if (blank($this->selectedRole))
            <p style="font-size:13px;opacity:.75;margin-bottom:16px;">
                Klik kartu peran yang ingin digunakan sekarang — menu dan hak akses
                akan mengikuti peran ini. Anda bisa menggantinya kapan saja lewat
                menu identitas di footer sidebar atau sudut kanan atas.
            </p>

            <div class="silogy-peran-cards" role="list">
                @foreach ($roles as $role)
                    <button
                        type="button"
                        class="silogy-peran-card"
                        data-color="{{ $role['color'] }}"
                        wire:click="pilihPeran({{ \Illuminate\Support\Js::from($role['name']) }})"
                        wire:key="peran-{{ $role['name'] }}"
                        role="listitem"
                    >
                        <span class="silogy-peran-card-icon" aria-hidden="true">
                            <x-filament::icon :icon="$role['icon']" />
                        </span>
                        <span class="silogy-peran-card-label">{{ $role['name'] }}</span>
                    </button>
                @endforeach
            </div>
        @else
            <div class="silogy-peran-step-bar">
                <div>
                    <div class="silogy-peran-step-title">Pilih unit akademik</div>
                    <div class="silogy-peran-step-sub">
                        Peran: <strong>{{ $this->selectedRole }}</strong> — klik kartu unit untuk melanjutkan.
                    </div>
                </div>
                <x-filament::button color="gray" size="sm" wire:click="kembaliKePeran" icon="heroicon-o-arrow-left">
                    Ganti peran
                </x-filament::button>
            </div>

            <div class="silogy-peran-cards" role="list">
                @foreach ($this->unitOptions as $unit)
                    <button
                        type="button"
                        class="silogy-peran-card"
                        data-color="info"
                        wire:click="pilihUnit({{ \Illuminate\Support\Js::from($unit['id']) }})"
                        wire:key="unit-{{ $unit['id'] }}"
                        role="listitem"
                    >
                        <span class="silogy-peran-card-icon" aria-hidden="true">
                            <x-filament::icon :icon="$unitIcon" />
                        </span>
                        <span class="silogy-peran-card-label">{{ $unit['nama'] }}</span>
                        <span class="silogy-peran-card-hint">{{ str_replace('_', ' ', $unit['type']) }}</span>
                    </button>
                @endforeach
            </div>
        @endif

        @if ($this->isImpersonating())
            <div class="silogy-peran-actions">
                <x-filament::button color="warning" wire:click="leaveImpersonate" type="button" icon="heroicon-o-arrow-uturn-left">
                    Tinggalkan impersonate
                </x-filament::button>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>

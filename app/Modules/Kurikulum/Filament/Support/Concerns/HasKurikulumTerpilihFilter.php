<?php

namespace App\Modules\Kurikulum\Filament\Support\Concerns;

use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filter "Kurikulum" yang selalu tampil di atas tabel (Profil, CPL, BoK,
 * MK, Penawaran MK, Kelas MK). Pilihan tersimpan di session sehingga
 * konsisten antarhalaman; default mengikuti KurikulumTerpilih.
 */
trait HasKurikulumTerpilihFilter
{
    /**
     * @param  callable(Builder<Model>, Kurikulum): Builder<Model>  $applyScope
     */
    protected static function kurikulumTerpilihFilter(callable $applyScope): SelectFilter
    {
        return SelectFilter::make('kurikulum_terpilih')
            ->label('Kurikulum')
            ->options(fn (): array => KurikulumTerpilih::options())
            ->default(fn (): ?string => KurikulumTerpilih::currentId())
            ->selectablePlaceholder(false)
            ->searchable()
            ->query(function (Builder $query, array $data) use ($applyScope): Builder {
                $id = $data['value'] ?? null;

                if (blank($id)) {
                    return $query;
                }

                // Sinkronkan pilihan ke session agar halaman lain mengikuti.
                KurikulumTerpilih::set($id);

                $kurikulum = Kurikulum::query()->with('academicUnit')->find($id);

                if (! $kurikulum) {
                    return $query;
                }

                return $applyScope($query, $kurikulum);
            })
            ->indicateUsing(function (array $data): ?string {
                $id = $data['value'] ?? null;

                if (blank($id)) {
                    return null;
                }

                $kurikulum = Kurikulum::query()->with('academicUnit')->find($id);

                return $kurikulum ? 'Kurikulum: '.KurikulumTerpilih::label($kurikulum) : null;
            });
    }
}

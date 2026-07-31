<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Modules\Audit\Models\Activity;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Ringkasan aktivitas terbaru untuk dashboard Super Admin — versi ringkas
 * dari App\Modules\Audit\Filament\Resources\ActivityLogResource (halaman
 * penuh untuk telusur/filter tetap di sana).
 */
class AktivitasTerbaruWidget extends TableWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        // Ikuti role aktif switcher (hasRole), bukan kepemilikan role.
        return $user instanceof User && $user->hasRole('Super Admin');
    }

    public function getTableHeading(): string|Htmlable|null
    {
        return 'Aktivitas Terbaru';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Activity::query()->latest()->limit(8))
            ->paginated(false)
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i'),
                TextColumn::make('description')
                    ->label('Deskripsi')
                    ->wrap(),
                TextColumn::make('causer.full_name')
                    ->label('Pelaku')
                    ->placeholder('Sistem'),
                TextColumn::make('subject_type')
                    ->label('Subjek')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—'),
            ]);
    }
}

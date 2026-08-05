<?php

namespace App\Modules\Penilaian\Support;

use Filament\Forms\Components\Select;

/**
 * Opsi pembulatan pada modal konfirmasi aksi normalisasi bobot.
 * Default: satuan (0 desimal).
 */
final class NormalisasiBobotDesimal
{
    public const DEFAULT = 0;

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return [
            0 => 'Satuan (tanpa desimal) — contoh: 33',
            1 => '1 desimal — contoh: 33,3',
            2 => '2 desimal — contoh: 33,33',
        ];
    }

    public static function field(): Select
    {
        return Select::make('desimal')
            ->label('Pembulatan hasil')
            ->options(self::options())
            ->default(self::DEFAULT)
            ->required()
            ->native(false)
            ->selectablePlaceholder(false)
            ->helperText('Hasil normalisasi proporsional akan dibulatkan sesuai pilihan ini. Default: satuan.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function dariData(array $data): int
    {
        return max(0, min(2, (int) ($data['desimal'] ?? self::DEFAULT)));
    }

    public static function ringkas(int $desimal): string
    {
        return match ($desimal) {
            0 => 'satuan (tanpa desimal)',
            1 => '1 desimal',
            2 => '2 desimal',
            default => self::ringkas(self::DEFAULT),
        };
    }
}

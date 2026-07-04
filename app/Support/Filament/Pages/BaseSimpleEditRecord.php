<?php

namespace App\Support\Filament\Pages;

use Filament\Schemas\Schema;

/**
 * Halaman edit minimal: hanya form data + aksi Simpan/Kembali,
 * tanpa relation manager atau elemen tambahan di bawah form.
 */
abstract class BaseSimpleEditRecord extends BaseEditRecord
{
    /**
     * @return array<class-string>
     */
    public function getRelationManagers(): array
    {
        return [];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }
}

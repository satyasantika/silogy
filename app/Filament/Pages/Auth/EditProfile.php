<?php

namespace App\Filament\Pages\Auth;

use App\Modules\Auth\Filament\Actions\GantiPasswordAction;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

/**
 * Halaman profil SILOGY: gelar, nama, email + tombol ganti kata sandi
 * (bukan isian password inline).
 */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('prefix')
                    ->label('Gelar depan')
                    ->maxLength(30),
                $this->getNameFormComponent(),
                TextInput::make('suffix')
                    ->label('Gelar belakang')
                    ->maxLength(50),
                $this->getEmailFormComponent(),
                // Tetap minta kata sandi saat ini bila email diubah.
                $this->getCurrentPasswordFormComponent(),
            ]);
    }

    protected function getNameFormComponent(): Component
    {
        return TextInput::make('full_name')
            ->label('Nama lengkap')
            ->required()
            ->maxLength(150)
            ->autofocus();
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            $this->gantiPasswordAction(),
            $this->getCancelFormAction(),
        ];
    }

    public function gantiPasswordAction(): Action
    {
        return GantiPasswordAction::make()
            ->color('gray')
            ->outlined();
    }
}

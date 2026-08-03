<?php

namespace App\Filament\Widgets;

use App\Modules\Auth\Filament\Actions\KeluarAction;
use App\Modules\Auth\Filament\Concerns\HasGantiPeranUnitAction;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\AccountWidget;

/**
 * Kartu "Selamat Datang" — menggantikan Filament\Widgets\AccountWidget
 * bawaan supaya bisa menampilkan unit yang ditugaskan dan meng-host modal
 * "Ganti peran & unit" (AccountWidget bawaan tidak implements HasActions,
 * jadi tidak bisa menampung Action/modal).
 */
class WelcomeWidget extends AccountWidget implements HasActions, HasSchemas
{
    use HasGantiPeranUnitAction;
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected string $view = 'filament.widgets.welcome-widget';

    public function keluarAction(): Action
    {
        return KeluarAction::make();
    }
}

<?php

namespace App\Modules\Kurikulum\Filament\Resources\KurikulumResource\RelationManagers;

use App\Support\Filament\Concerns\ForcesFullPageRender;
use Filament\Resources\RelationManagers\RelationManager;

abstract class BaseKurikulumRelationManager extends RelationManager
{
    use ForcesFullPageRender;
}

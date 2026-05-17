<?php

namespace App\Modules\Kurikulum\States;

use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Transitions\KurikulumTransition;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class KurikulumState extends State
{
    /**
     * Daftar state class yang boleh dituju dari state ini (§4.1 System Design).
     *
     * @return array<int, class-string<KurikulumState>>
     */
    abstract public function transitionTargets(): array;

    /**
     * @param  class-string<KurikulumState>|null  $toStateClass
     */
    abstract public function canTransition(?string $toStateClass = null): bool;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(DraftState::class)
            ->registerState([
                DraftState::class,
                ProfilLulusanState::class,
                CplState::class,
                BokState::class,
                MkState::class,
                SetdosenmkState::class,
                AktifState::class,
            ])
            ->allowTransitions([
                [DraftState::class, ProfilLulusanState::class, KurikulumTransition::class],
                [DraftState::class, CplState::class, KurikulumTransition::class],
                [ProfilLulusanState::class, CplState::class, KurikulumTransition::class],
                [CplState::class, BokState::class, KurikulumTransition::class],
                [BokState::class, MkState::class, KurikulumTransition::class],
                [MkState::class, SetdosenmkState::class, KurikulumTransition::class],
                [SetdosenmkState::class, AktifState::class, KurikulumTransition::class],
            ]);
    }

    protected function kurikulum(): Kurikulum
    {
        /** @var Kurikulum $model */
        $model = $this->getModel();

        return $model;
    }
}

<?php

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Tests\TestCase;

uses(TestCase::class);

it('mengonfigurasi EditAction sebagai icon-only Ubah dengan pensil outlined abu-abu', function () {
    $action = EditAction::make();

    expect($action->isIconButton())->toBeTrue()
        ->and($action->getLabel())->toBe('Ubah')
        ->and($action->getTooltip())->toBe('Ubah')
        ->and($action->getIcon())->toBe(Heroicon::OutlinedPencilSquare)
        ->and($action->getColor())->toBe('gray');
});

it('mengonfigurasi DeleteAction sebagai icon-only Hapus dengan trash outlined danger', function () {
    $action = DeleteAction::make();

    expect($action->isIconButton())->toBeTrue()
        ->and($action->getLabel())->toBe('Hapus')
        ->and($action->getTooltip())->toBe('Hapus')
        ->and($action->getIcon())->toBe(Heroicon::OutlinedTrash)
        ->and($action->getColor())->toBe('danger');
});

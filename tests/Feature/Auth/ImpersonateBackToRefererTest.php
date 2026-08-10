<?php

use App\Models\User;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

/**
 * Reproduksi HTTP sungguhan (bukan Livewire::test()) — sengaja, karena
 * Livewire::test() tidak mensimulasikan request HTTP asli dengan header
 * Referer seperti fetch() browser yang sesungguhnya. ->backTo() dulu
 * memakai url()->full(), yang di dalam siklus request POST /livewire/update
 * mengembalikan URL endpoint AJAX itu sendiri — bukan URL halaman edit user
 * yang sedang dibuka browser. Test ini membuktikan session('impersonate.back_to')
 * berisi URL halaman (dari header Referer), bukan /livewire/update.
 */
it('mengimpersonate dari halaman edit user menyimpan URL halaman (referer), bukan endpoint livewire/update', function () {
    $superAdmin = User::query()->where('username', 'superadmin')->firstOrFail();
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();

    $this->actingAs($superAdmin);

    $editUrl = route('filament.admin.resources.users.edit', ['record' => $timkur->id]);

    $page = $this->get($editUrl);
    $page->assertOk();

    $html = $page->getContent();

    preg_match_all('/wire:snapshot="(.*?)"(?=\s+wire:effects)/s', $html, $all);
    expect($all[1])->not->toBeEmpty();

    $snapshotRaw = null;
    foreach ($all[1] as $candidateRaw) {
        $candidate = htmlspecialchars_decode($candidateRaw);
        $decoded = json_decode($candidate, true);
        $name = $decoded['memo']['name'] ?? '';
        if (str_contains($name, 'users.pages.edit-user') || str_contains($name, 'edit-user')) {
            $snapshotRaw = $candidate;
            break;
        }
    }
    expect($snapshotRaw)->not->toBeNull();

    $csrfToken = session()->token();

    $payload = [
        '_token' => $csrfToken,
        'components' => [
            [
                'snapshot' => $snapshotRaw,
                'updates' => [],
                'calls' => [
                    ['path' => '', 'method' => 'mountAction', 'params' => ['impersonate']],
                ],
            ],
        ],
    ];

    $this->withHeaders([
        'X-Livewire' => '',
        'X-CSRF-TOKEN' => $csrfToken,
        'Referer' => $editUrl,
    ])->postJson('/livewire/update', $payload);

    $backTo = session('impersonate.back_to');

    expect($backTo)->not->toBeNull()
        ->and($backTo)->not->toContain('/livewire/update')
        ->and($backTo)->toBe($editUrl);
});

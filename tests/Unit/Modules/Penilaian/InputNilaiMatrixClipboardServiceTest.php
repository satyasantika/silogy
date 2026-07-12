<?php

use App\Modules\Penilaian\Services\InputNilaiMatrixClipboardService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

it('exports matrix as tsv with header row', function () {
    $rows = [
        ['id' => 'kmm-1', 'nim' => '12345', 'nama' => 'Budi'],
    ];
    $columns = [
        ['id' => 'skp-1', 'label' => 'UTS / SUB-01'],
    ];
    $nilai = [
        'kmm-1' => ['skp-1' => '85.5'],
    ];

    $tsv = app(InputNilaiMatrixClipboardService::class)->exportTsv($rows, $columns, $nilai);

    expect($tsv)->toBe("NIM\tNama\tUTS / SUB-01\n12345\tBudi\t85.5");
});

it('applies pasted tsv by nim and column header', function () {
    $rows = [
        ['id' => 'kmm-1', 'nim' => '12345', 'nama' => 'Budi'],
        ['id' => 'kmm-2', 'nim' => '67890', 'nama' => 'Ani'],
    ];
    $columns = [
        ['id' => 'skp-1', 'label' => 'UTS / SUB-01'],
        ['id' => 'skp-2', 'label' => 'UAS / SUB-01'],
    ];
    $currentNilai = [
        'kmm-1' => ['skp-1' => null, 'skp-2' => null],
        'kmm-2' => ['skp-1' => null, 'skp-2' => null],
    ];

    $paste = "NIM\tNama\tUTS / SUB-01\tUAS / SUB-01\n67890\tAni\t88\t92\n12345\tBudi\t75,5\t";

    $result = app(InputNilaiMatrixClipboardService::class)->applyPaste(
        $paste,
        $rows,
        $columns,
        $currentNilai,
    );

    expect($result['applied_cells'])->toBe(4)
        ->and($result['matched_rows'])->toBe(2)
        ->and($result['nilai']['kmm-1']['skp-1'])->toBe('75.5')
        ->and($result['nilai']['kmm-1']['skp-2'])->toBeNull()
        ->and($result['nilai']['kmm-2']['skp-1'])->toBe('88')
        ->and($result['nilai']['kmm-2']['skp-2'])->toBe('92');
});

it('rejects paste without data rows', function () {
    $service = app(InputNilaiMatrixClipboardService::class);

    expect(fn () => $service->applyPaste(
        "NIM\tNama\tUTS / SUB-01",
        [],
        [['id' => 'skp-1', 'label' => 'UTS / SUB-01']],
        [],
    ))->toThrow(ValidationException::class);
});

it('mencocokkan kolom berlabel sama (asesmen yang sama, sub-cpmk berbeda) secara berurutan', function () {
    $rows = [
        ['id' => 'kmm-1', 'nim' => '12345', 'nama' => 'Budi'],
    ];
    $columns = [
        ['id' => 'skp-1', 'label' => 'Asesmen01'],
        ['id' => 'skp-2', 'label' => 'Asesmen01'],
    ];
    $currentNilai = [
        'kmm-1' => ['skp-1' => null, 'skp-2' => null],
    ];

    $paste = "NIM\tNama\tAsesmen01\tAsesmen01\n12345\tBudi\t80\t90";

    $result = app(InputNilaiMatrixClipboardService::class)->applyPaste(
        $paste,
        $rows,
        $columns,
        $currentNilai,
    );

    expect($result['nilai']['kmm-1']['skp-1'])->toBe('80')
        ->and($result['nilai']['kmm-1']['skp-2'])->toBe('90');
});

it('reports invalid numeric values without applying cell', function () {
    $rows = [
        ['id' => 'kmm-1', 'nim' => '12345', 'nama' => 'Budi'],
    ];
    $columns = [
        ['id' => 'skp-1', 'label' => 'UTS / SUB-01'],
    ];
    $currentNilai = [
        'kmm-1' => ['skp-1' => '70'],
    ];

    $paste = "NIM\tNama\tUTS / SUB-01\n12345\tBudi\t150";

    $result = app(InputNilaiMatrixClipboardService::class)->applyPaste(
        $paste,
        $rows,
        $columns,
        $currentNilai,
    );

    expect($result['nilai']['kmm-1']['skp-1'])->toBe('70')
        ->and($result['errors'])->not->toBeEmpty();
});

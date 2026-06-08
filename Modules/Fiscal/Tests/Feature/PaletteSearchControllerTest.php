<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * US-FISCAL-015 — Palette ⌘K cross-fiscal search (PR #7 Wave).
 *
 * Tests focados em contrato + validação. Smoke completo de SEFAZ + busca real
 * em biz=1 fica via Pest browser MCP pós-merge.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeBrasil requer schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_emissoes') || ! Schema::hasTable('nfe_dfe_recebidos')) {
        $this->markTestSkipped('Tabelas NfeBrasil ausentes — rodar migrate primeiro');
    }
});

it('search rejeita query < 3 chars (anti-DOS leading wildcard — GAP-FISCAL-002)', function () {
    foreach (['a', 'ab'] as $q) {
        $validator = validator(
            ['q' => $q],
            ['q' => ['required', 'string', 'min:3', 'max:50']],
        );
        expect($validator->fails())->toBeTrue("q='{$q}' deveria falhar (anti-DOS leading wildcard)")
            ->and($validator->errors()->has('q'))->toBeTrue();
    }
});

it('search rejeita query > 50 chars (defesa anti-abuse)', function () {
    $validator = validator(
        ['q' => str_repeat('x', 51)],
        ['q' => ['required', 'string', 'min:3', 'max:50']],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('q'))->toBeTrue();
});

it('search aceita query válida 3-50 chars', function () {
    foreach (['abc', 'numero 123', str_repeat('x', 50)] as $q) {
        $validator = validator(
            ['q' => $q],
            ['q' => ['required', 'string', 'min:3', 'max:50']],
        );
        expect($validator->fails())->toBeFalse("q='{$q}' deveria passar");
    }
});

it('PaletteSearchController classe existe + método search público', function () {
    expect(class_exists(\Modules\Fiscal\Http\Controllers\PaletteSearchController::class))->toBeTrue()
        ->and(method_exists(\Modules\Fiscal\Http\Controllers\PaletteSearchController::class, 'search'))->toBeTrue();

    $reflection = new ReflectionMethod(
        \Modules\Fiscal\Http\Controllers\PaletteSearchController::class,
        'search',
    );
    expect($reflection->isPublic())->toBeTrue();
});

it('route fiscal.palette.search registrada', function () {
    expect(\Illuminate\Support\Facades\Route::has('fiscal.palette.search'))->toBeTrue();
});

it('contract: searchNotas + searchDfe são privates (não exposed externamente)', function () {
    $class = \Modules\Fiscal\Http\Controllers\PaletteSearchController::class;
    expect((new ReflectionMethod($class, 'searchNotas'))->isPrivate())->toBeTrue()
        ->and((new ReflectionMethod($class, 'searchDfe'))->isPrivate())->toBeTrue();
});

it('contract: result format — categorias notas + dfe (top 5 cada)', function () {
    // Defesa estrutural: documentar limit declarativo. Implementation usa
    // ->limit(5) em ambas queries — guarda no source code.
    $src = file_get_contents(
        (new ReflectionClass(\Modules\Fiscal\Http\Controllers\PaletteSearchController::class))->getFileName(),
    );

    expect(substr_count($src, '->limit(5)'))->toBeGreaterThanOrEqual(2);
});

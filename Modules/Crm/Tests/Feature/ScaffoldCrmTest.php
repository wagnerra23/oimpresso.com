<?php

declare(strict_types=1);

use Nwidart\Modules\Facades\Module;

uses(Tests\TestCase::class);

/**
 * Smoke scaffold pra Modules/Crm — D2.b canônico (3/3 checks).
 *
 * Garante que:
 *   1. nWidart enxerga o módulo registrado (Module::find)
 *   2. CrmServiceProvider existe e é carregável
 *   3. Rotas nomeadas principais existem (Route::has) — follow-ups, leads
 *   4. Controllers críticos existem (class_exists) — Install + Lead + Schedule + Dashboard
 *   5. LeadAssignmentService extraído (Wave Massive — D4.a thin service)
 *
 * Tests biz=1 (Wagner WR2) conforme ADR 0101 — nunca biz=4 (cliente ROTA LIVRE).
 *
 * @see memory/decisions/0024-instalacao-1-clique-modulos.md
 * @see memory/decisions/0011-alinhamento-padrao-jana.md
 * @see Modules/Crm/Tests/Feature/SmokeRoutesTest.php (complementa este — não duplica)
 */

it('cenario 1: modulo Crm esta registrado em nWidart', function () {
    $module = Module::find('Crm');
    expect($module)->not->toBeNull('Modules/Crm deveria estar registrado em nWidart');
    expect($module->getName())->toBe('Crm');
});

it('cenario 2: CrmServiceProvider existe e e carregavel', function () {
    expect(class_exists(\Modules\Crm\Providers\CrmServiceProvider::class))
        ->toBeTrue('ServiceProvider canonico (per ADR 0011 padrao Jana/Repair) deveria existir');
});

it('cenario 3: rota nomeada follow-ups.index existe (Schedule resource)', function () {
    expect(\Route::has('follow-ups.index'))
        ->toBeTrue('Rota follow-ups.index deveria existir per Routes/web.php (Route::resource follow-ups)');
});

it('cenario 4: rota nomeada leads.index existe (Lead resource)', function () {
    expect(\Route::has('leads.index'))
        ->toBeTrue('Rota leads.index deveria existir per Routes/web.php (Route::resource leads)');
});

it('cenario 5: Controllers criticos existem', function () {
    expect(class_exists(\Modules\Crm\Http\Controllers\InstallController::class))->toBeTrue();
    expect(class_exists(\Modules\Crm\Http\Controllers\LeadController::class))->toBeTrue();
    expect(class_exists(\Modules\Crm\Http\Controllers\ScheduleController::class))->toBeTrue();
    expect(class_exists(\Modules\Crm\Http\Controllers\CrmDashboardController::class))->toBeTrue();
    expect(class_exists(\Modules\Crm\Http\Controllers\DataController::class))->toBeTrue();
});

it('cenario 6: LeadAssignmentService extraido (D4.a thin service Wave Massive)', function () {
    expect(class_exists(\Modules\Crm\Services\LeadAssignmentService::class))
        ->toBeTrue('LeadAssignmentService deveria existir (extracao thin de LeadController per ADR 0011 padrao Jana)');
});

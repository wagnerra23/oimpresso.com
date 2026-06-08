<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Essentials\Entities\KnowledgeBase;
use Modules\Essentials\Entities\Reminder;
use Modules\Essentials\Http\Controllers\EssentialsHolidayController;
use Modules\Essentials\Http\Controllers\KnowledgeBaseController;
use Modules\Essentials\Http\Controllers\ReminderController;

uses(\Modules\Essentials\Tests\Feature\EssentialsTestCase::class);

/**
 * Wave D Blade T1 Migration — smoke test das 3 telas migradas Blade → Inertia:
 *   - /essentials/reminder (Reminders/Index)
 *   - /hrm/holiday (Holidays/Index)
 *   - /essentials/knowledge-base (Knowledge/Index)
 *
 * Cobertura (6 cenários):
 *   1. Rota /essentials/reminder retorna 200 + component Inertia correto
 *   2. Rota /hrm/holiday retorna 200 + component Inertia correto
 *   3. Rota /essentials/knowledge-base retorna 200 + component Inertia correto
 *   4. ReminderController.index: Reminder Entity usa HasBusinessScope (multi-tenant Tier 0)
 *   5. EssentialsHolidayController.index: declara Inertia::defer em 'holidays' (perf)
 *   6. KnowledgeBaseController.index: KnowledgeBase Entity usa HasBusinessScope + Reminder também
 *
 * SQLite skip: usa estrutura DB real (UltimatePOS legacy migrations + triggers).
 *
 * Tests biz=1 (ADR 0101) — NUNCA biz=4 ROTA LIVRE.
 *
 * @see memory/requisitos/Essentials/RUNBOOK-blade-t1-migration-wave-d.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0104-processo-mwart-canonico-unico-caminho.md
 */

// ------------------------------------------------------------------
// Helper local — checa se driver atual é SQLite (incompatível com legacy schema)
// ------------------------------------------------------------------

function essentialsBladeT1IsSqlite(): bool
{
    $default = config('database.default');
    return $default === 'sqlite'
        || config("database.connections.{$default}.driver") === 'sqlite';
}

// ------------------------------------------------------------------
// 1-3) Smoke route + component Inertia (skip se SQLite)
// ------------------------------------------------------------------

it('Wave D — GET /essentials/reminder retorna Inertia component Essentials/Reminders/Index', function () {
    if (essentialsBladeT1IsSqlite()) {
        $this->markTestSkipped('SQLite incompatível com schema UltimatePOS legacy (triggers MySQL).');
    }

    $this->actAsAdmin();
    $response = $this->inertiaGet('/essentials/reminder');
    $this->assertInertiaComponent($response, 'Essentials/Reminders/Index');
});

it('Wave D — GET /hrm/holiday retorna Inertia component Essentials/Holidays/Index', function () {
    if (essentialsBladeT1IsSqlite()) {
        $this->markTestSkipped('SQLite incompatível com schema UltimatePOS legacy (triggers MySQL).');
    }

    $this->actAsAdmin();
    $response = $this->inertiaGet('/hrm/holiday');
    $this->assertInertiaComponent($response, 'Essentials/Holidays/Index');
    $response->assertJsonPath('props.can_manage', fn ($v) => is_bool($v));
});

it('Wave D — GET /essentials/knowledge-base retorna Inertia component Essentials/Knowledge/Index', function () {
    if (essentialsBladeT1IsSqlite()) {
        $this->markTestSkipped('SQLite incompatível com schema UltimatePOS legacy (triggers MySQL).');
    }

    $this->actAsAdmin();
    $response = $this->inertiaGet('/essentials/knowledge-base');
    $this->assertInertiaComponent($response, 'Essentials/Knowledge/Index');
});

// ------------------------------------------------------------------
// 4-6) Multi-tenant Tier 0 + estrutura Controllers
// ------------------------------------------------------------------

it('Wave D — Reminder Entity tem HasBusinessScope (multi-tenant Tier 0 ADR 0093)', function () {
    $traits = class_uses_recursive(Reminder::class);
    expect($traits)->toContain(\App\Concerns\HasBusinessScope::class);
});

it('Wave D — KnowledgeBase Entity tem HasBusinessScope (multi-tenant Tier 0 ADR 0093)', function () {
    $traits = class_uses_recursive(KnowledgeBase::class);
    expect($traits)->toContain(\App\Concerns\HasBusinessScope::class);
});

it('Wave D — 3 Controllers Inertia declaram render do component esperado (source-level)', function () {
    $reminderSrc = file_get_contents((new \ReflectionClass(ReminderController::class))->getFileName());
    $holidaySrc  = file_get_contents((new \ReflectionClass(EssentialsHolidayController::class))->getFileName());
    $knowledgeSrc = file_get_contents((new \ReflectionClass(KnowledgeBaseController::class))->getFileName());

    expect($reminderSrc)->toContain("Inertia::render('Essentials/Reminders/Index'");
    expect($holidaySrc)->toContain("Inertia::render('Essentials/Holidays/Index'");
    expect($knowledgeSrc)->toContain("Inertia::render('Essentials/Knowledge/Index'");

    // Defer pattern aplicado em props caras (RUNBOOK-inertia-defer-pattern.md)
    expect($holidaySrc)->toContain('Inertia::defer(');
    expect($knowledgeSrc)->toContain('Inertia::defer(');

    // Reminder não usa defer (lista local cronológica simples, volume baixo) — OK.
});

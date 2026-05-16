<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SRS\Entities\DocRequirement;
use Modules\SRS\Entities\DocSource;

uses(Tests\TestCase::class);

/**
 * Testa isolamento multi-tenant COLUMN-level dos Models SRS (docs_*).
 *
 * NOTA importante: Modules/SRS NÃO usa BusinessScope global (verificado nas Entities
 * DocSource/DocRequirement/DocEvidence/DocPage — sem boot() com global scope).
 * As tabelas docs_sources e docs_requirements TÊM coluna business_id (verificado
 * em Database/Migrations/2026_04_22_000001 e 2026_04_22_000003).
 *
 * Portanto este teste valida isolamento COLUMN-level — toda query de produção
 * DEVE incluir where('business_id', ...) explícito. Se algum Controller esquecer,
 * vazamento cross-tenant acontece. Adicionar BusinessScope global ao SRS é
 * proposta P0 separada (não no escopo deste PR Pest-only).
 *
 * ADR 0093: multi-tenant Tier 0 IRREVOGÁVEL.
 * ADR 0101: tests usam biz=1 (Wagner WR2) e biz=99 (fictício) — NUNCA biz=4 (ROTA LIVRE prod).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

const BIZ_WAGNER = 1;
const BIZ_FICTICIO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema docs_* requer MySQL UltimatePOS — rodar Pest local com MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('docs_sources') || ! Schema::hasTable('docs_requirements')) {
        $this->markTestSkipped('Tabelas docs_* ausentes — rode `php artisan module:migrate SRS` primeiro');
    }
});

// ------------------------------------------------------------------
// DocSource — isolamento column-level via where('business_id', ...)
// ------------------------------------------------------------------

it('DocSource biz=1 NÃO aparece em query filtrada por biz=99 (isolamento column-level)', function () {
    $source = DocSource::create([
        'business_id'   => BIZ_WAGNER,
        'type'          => 'text',
        'title'         => 'SRS Teste Isolamento DocSource',
        'module_target' => 'TesteFicticio',
        'body_text'     => 'conteúdo arbitrário pra teste',
    ]);

    // Consulta com filter explícito biz=99 — NÃO deve aparecer
    $resultado = DocSource::where('business_id', BIZ_FICTICIO)
        ->where('id', $source->id)
        ->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    DocSource::where('title', 'SRS Teste Isolamento DocSource')->delete();
});

it('DocSource biz=1 aparece em query filtrada por biz=1', function () {
    $source = DocSource::create([
        'business_id'   => BIZ_WAGNER,
        'type'          => 'text',
        'title'         => 'SRS Teste Visibilidade DocSource',
        'module_target' => 'TesteFicticio',
        'body_text'     => 'visível pro próprio business',
    ]);

    $resultado = DocSource::where('business_id', BIZ_WAGNER)
        ->where('id', $source->id)
        ->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->title)->toBe('SRS Teste Visibilidade DocSource');
})->afterEach(function () {
    DocSource::where('title', 'SRS Teste Visibilidade DocSource')->delete();
});

// ------------------------------------------------------------------
// DocRequirement — isolamento column-level
// ------------------------------------------------------------------

it('DocRequirement biz=1 NÃO aparece em query filtrada por biz=99', function () {
    $req = DocRequirement::create([
        'business_id'   => BIZ_WAGNER,
        'module_target' => 'TesteFicticio',
        'external_id'   => 'US-SRS-TEST-99991',
        'kind'          => 'user_story',
        'title'         => 'SRS Teste Isolamento Requirement',
        'status'        => 'draft',
    ]);

    $resultado = DocRequirement::where('business_id', BIZ_FICTICIO)
        ->where('id', $req->id)
        ->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    DocRequirement::where('external_id', 'US-SRS-TEST-99991')->delete();
});

it('DocRequirement biz=1 aparece em query filtrada por biz=1', function () {
    $req = DocRequirement::create([
        'business_id'   => BIZ_WAGNER,
        'module_target' => 'TesteFicticio',
        'external_id'   => 'US-SRS-TEST-99992',
        'kind'          => 'user_story',
        'title'         => 'SRS Teste Visibilidade Requirement',
        'status'        => 'draft',
    ]);

    $resultado = DocRequirement::where('business_id', BIZ_WAGNER)
        ->where('id', $req->id)
        ->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->external_id)->toBe('US-SRS-TEST-99992');
})->afterEach(function () {
    DocRequirement::where('external_id', 'US-SRS-TEST-99992')->delete();
});

// ------------------------------------------------------------------
// Cross-tenant — biz=1 e biz=99 coexistem sem vazamento
// ------------------------------------------------------------------

it('DocSource biz=1 e biz=99 são isolados em queries simultâneas', function () {
    $sourceBiz1 = DocSource::create([
        'business_id'   => BIZ_WAGNER,
        'type'          => 'text',
        'title'         => 'SRS Cross-Tenant Biz1',
        'module_target' => 'TesteFicticio',
    ]);

    $sourceBiz99 = DocSource::create([
        'business_id'   => BIZ_FICTICIO,
        'type'          => 'text',
        'title'         => 'SRS Cross-Tenant Biz99',
        'module_target' => 'TesteFicticio',
    ]);

    // Query biz=1 só enxerga biz=1
    $resBiz1 = DocSource::where('business_id', BIZ_WAGNER)
        ->whereIn('id', [$sourceBiz1->id, $sourceBiz99->id])
        ->get();
    expect($resBiz1)->toHaveCount(1);
    expect($resBiz1->first()->business_id)->toBe(BIZ_WAGNER);

    // Query biz=99 só enxerga biz=99
    $resBiz99 = DocSource::where('business_id', BIZ_FICTICIO)
        ->whereIn('id', [$sourceBiz1->id, $sourceBiz99->id])
        ->get();
    expect($resBiz99)->toHaveCount(1);
    expect($resBiz99->first()->business_id)->toBe(BIZ_FICTICIO);
})->afterEach(function () {
    DocSource::whereIn('title', ['SRS Cross-Tenant Biz1', 'SRS Cross-Tenant Biz99'])->delete();
});

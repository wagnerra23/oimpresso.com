<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Services\RecipeBomService;
use Modules\Manufacturing\Services\ProductionService;

uses(Tests\TestCase::class);

/**
 * Wave 23 F2 — RecipeBomService como contrato público reusável (consumível Sells).
 *
 * Tests valida arquitetura de reuse:
 *   - RecipeBomService é resolvable do container (Sells pode `app(RecipeBomService::class)`)
 *   - Métodos públicos respeitam contrato multi-tenant Tier 0 (recebem business_id explícito)
 *   - listForDropdown retorna formato consumível por outros módulos (Sells autocomplete)
 *   - calculateUnitCost é puro (não muta estado) — seguro pra Sells calcular orçamento
 *
 * Por que matters: Sells precisa cotar produto manufaturado sem reimplementar BOM lookup.
 * RecipeBomService já expõe contratos certos — este test PROTEGE backward compat.
 *
 * @see Modules\Manufacturing\Services\RecipeBomService
 * @see ADR 0093 multi-tenant Tier 0
 */

function w23MfgNeedsMysql(): bool
{
    return DB::connection()->getDriverName() === 'sqlite';
}

test('classe RecipeBomService existe (Reflection puro)', function () {
    expect(class_exists(RecipeBomService::class))->toBeTrue();
});

test('classe ProductionService existe (Reflection puro)', function () {
    expect(class_exists(ProductionService::class))->toBeTrue();
});

test('RecipeBomService é resolvable do container (Sells pode injetar)', function () {
    if (w23MfgNeedsMysql()) {
        $this->markTestSkipped('Container injection requer DB válido em alguns providers.');
    }
    $svc = app(RecipeBomService::class);
    expect($svc)->toBeInstanceOf(RecipeBomService::class);
});

test('ProductionService é resolvable do container (Sells pode invocar produção)', function () {
    if (w23MfgNeedsMysql()) {
        $this->markTestSkipped('Container injection requer DB válido em alguns providers.');
    }
    $svc = app(ProductionService::class);
    expect($svc)->toBeInstanceOf(ProductionService::class);
});

test('RecipeBomService::resolveBom assinatura explicita businessId (multi-tenant Tier 0)', function () {
    $ref = new ReflectionMethod(RecipeBomService::class, 'resolveBom');
    $params = $ref->getParameters();

    expect($params)->toHaveCount(2);
    expect($params[0]->getName())->toBe('recipeId');
    expect($params[1]->getName())->toBe('businessId');
    expect((string) $params[1]->getType())->toBe('int');
});

test('RecipeBomService::listForDropdown assinatura explicita businessId (consumível Sells autocomplete)', function () {
    $ref = new ReflectionMethod(RecipeBomService::class, 'listForDropdown');
    $params = $ref->getParameters();

    expect($params)->toHaveCount(2);
    expect($params[0]->getName())->toBe('businessId');
    expect((string) $params[0]->getType())->toBe('int');
});

test('RecipeBomService::calculateUnitCost é método público (Sells pode cotar)', function () {
    $ref = new ReflectionMethod(RecipeBomService::class, 'calculateUnitCost');
    expect($ref->isPublic())->toBeTrue();
    expect($ref->getReturnType()?->getName())->toBe('float');
});

test('RecipeBomService::calculateCost retorna float (compatível com Transaction subtotal)', function () {
    $ref = new ReflectionMethod(RecipeBomService::class, 'calculateCost');
    expect($ref->isPublic())->toBeTrue();
    expect($ref->getReturnType()?->getName())->toBe('float');
});

// ⚠️ Este caso afirmava `Schema::hasColumn('mfg_recipes', 'business_id')`, e afirmava o
// CONTRÁRIO da arquitetura do módulo. Nunca reprovou porque nunca executou: a guarda
// `w23MfgNeedsMysql()` o pulava em sqlite, e sqlite era a única lane que via este arquivo
// (§5 2026-08-02 "registro não é execução" · LC-13 "skip sai exit 0"). O primeiro run da
// lane `manufacturing-pest.yml` sob MySQL o derrubou — `Failed asserting that false is true`.
//
// `mfg_recipes` NÃO tem `business_id`, e isso é DESENHO, não buraco: a migration
// `2019_07_15_114403_create_mfg_recipes_table` cria `product_id` + `variation_id`, e o
// isolamento de tenant vem do JOIN com `products.business_id` — a ÚNICA barreira do módulo
// ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)), a mesma
// que o `MultiTenantIsolationTest` defende e que o cabeçalho do `modules-pest.yml` registra.
//
// Então o assert passa a exigir a coluna que REALMENTE carrega o vínculo de tenant. Trocar
// por `business_id` de novo faria o teste pedir uma coluna cuja existência quebraria o
// desenho — e ele voltaria a reprovar, agora com razão.
test('schema mfg_recipes presente (reuse depende de tabela canônica)', function () {
    if (w23MfgNeedsMysql() || ! Schema::hasTable('mfg_recipes')) {
        $this->markTestSkipped('Tabela mfg_recipes ausente em ambiente atual.');
    }

    // `product_id` é o elo com `products`, de onde sai o `business_id` do tenant.
    expect(Schema::hasColumn('mfg_recipes', 'product_id'))->toBeTrue();

    // E o contrário também é contrato: coluna direta de tenant aqui significaria uma
    // SEGUNDA fonte de verdade de negócio, divergindo do JOIN na primeira gravação.
    expect(Schema::hasColumn('mfg_recipes', 'business_id'))->toBeFalse();
});

test('Wave14 LgpdSecurity + Wave17 OtelInstrumentation tests existem (cobertura saturação)', function () {
    expect(file_exists(__DIR__ . '/Wave14LgpdSecurityTest.php'))->toBeTrue();
    expect(file_exists(__DIR__ . '/Wave17OtelInstrumentationTest.php'))->toBeTrue();
    expect(file_exists(__DIR__ . '/Wave18ProductionJourneyTest.php'))->toBeTrue();
});

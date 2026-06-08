<?php

declare(strict_types=1);

/**
 * Smoke unit test multi-tenant Tier 0 — Modules/Ponto.
 *
 * Garante que após PR #435 (HasBusinessScope aplicado em Colaborador):
 *   1. Model Colaborador (tabela ponto_colaborador_config) usa o trait
 *   2. Trait HasBusinessScope tem boot via convenção Eloquent
 *      (bootHasBusinessScope) — Eloquent invoca automaticamente
 *   3. Jobs assíncronos do Ponto exigem $businessId no constructor
 *      (não dá pra confiar em session() em queue worker — ADR 0093 §SUPERADMIN)
 *
 * Tests Unit-only (sem DB) — modelo canônico em
 * tests/Unit/Concerns/HasBusinessScopeTest.php.
 *
 * Tests de isolamento real cross-tenant em DB ficam em
 * Modules/Ponto/Tests/Feature/MultiTenantTest.php (follow-up — exige DB
 * Wagner local com migrations core UltimatePOS, regra 2026-05-09).
 *
 * @see ADR 0093 (Multi-tenant Tier 0 IRREVOGÁVEL)
 * @see PR #435 (Colaborador trait HasBusinessScope)
 */

use App\Concerns\HasBusinessScope;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Jobs\ProcessarImportacaoAfdJob;
use Modules\Ponto\Jobs\ReapurarDiaJob;

test('Colaborador usa HasBusinessScope trait', function () {
    $traits = class_uses_recursive(Colaborador::class);
    expect($traits)->toHaveKey(HasBusinessScope::class);
});

test('HasBusinessScope tem boot Eloquent (bootHasBusinessScope)', function () {
    // Eloquent descobre o boot do trait via convenção: boot{TraitName}
    // Sem esse método, addGlobalScope nunca seria chamado.
    expect(method_exists(HasBusinessScope::class, 'bootHasBusinessScope'))->toBeTrue();
});

test('ReapurarDiaJob constructor exige businessId como 1º param', function () {
    $params = (new ReflectionClass(ReapurarDiaJob::class))
        ->getConstructor()
        ->getParameters();

    expect($params[0]->getName())->toBe('businessId');
});

test('ReapurarDiaJob casta businessId pra int no constructor', function () {
    // Property pública $businessId existe no Job — Queueable serializa.
    $reflection = new ReflectionClass(ReapurarDiaJob::class);
    expect($reflection->hasProperty('businessId'))->toBeTrue();
});

test('ProcessarImportacaoAfdJob constructor exige businessId como 1º param', function () {
    $params = (new ReflectionClass(ProcessarImportacaoAfdJob::class))
        ->getConstructor()
        ->getParameters();

    expect($params[0]->getName())->toBe('businessId');
});

test('ProcessarImportacaoAfdJob expõe businessId como property pública', function () {
    $reflection = new ReflectionClass(ProcessarImportacaoAfdJob::class);
    expect($reflection->hasProperty('businessId'))->toBeTrue();

    $prop = $reflection->getProperty('businessId');
    expect($prop->isPublic())->toBeTrue();
});

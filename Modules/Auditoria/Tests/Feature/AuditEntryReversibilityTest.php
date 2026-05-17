<?php

declare(strict_types=1);

use Modules\Auditoria\Services\RevertService;

uses(Tests\TestCase::class);

/**
 * Wave 23 — Reversibility Test (D2 Pest — gap +4 sub-dim).
 *
 * Cobre TODAS as 5 categorias UNREVERTIBLE da whitelist (ADR 0127 §3) +
 * cenários positivos (entries fora da whitelist). Unit-level (não toca DB
 * Eloquent — usa reflection/registry).
 *
 * Tier 0 (ADR 0093 + ADR 0127 §3 + ADR 0101):
 *  - PROIBIDO modificar `RevertService::unrevertibleRegistry()` sem ADR
 *  - Cada classe bloqueada é compliance/legal real (Portaria 671/2021,
 *    CONFAZ SINIEF 07/2005, Asaas API contract, NFSe prefeitura, payment
 *    consistency)
 *  - biz=99 quando precisar de tenant fictício (NUNCA biz=cliente)
 *
 * @see Modules/Auditoria/Services/RevertService.php
 * @see memory/decisions/0127-modulo-auditoria-ui-undo.md
 */

beforeEach(function () {
    $this->service = new RevertService();
});

it('whitelist UNREVERTIBLE tem exatamente 5 categorias (ADR 0127 §3)', function () {
    $registry = $this->service->unrevertibleRegistry();
    expect($registry)->toHaveCount(5);
});

it('whitelist contém Marcacao (Portaria MTP 671/2021 append-only)', function () {
    $registry = $this->service->unrevertibleRegistry();
    expect($registry)->toHaveKey(\Modules\PontoWr2\Models\Marcacao::class);

    $rule = $registry[\Modules\PontoWr2\Models\Marcacao::class];
    expect($rule['condition'])->toBeNull(); // sempre bloqueado
    expect($rule['reason'])->toContain('Portaria MTP 671/2021');
    expect($rule['reason'])->toContain('append-only');
});

it('whitelist contém NfeTransaction com condition cstat ∈ {100,101,135}', function () {
    $registry = $this->service->unrevertibleRegistry();
    expect($registry)->toHaveKey(\Modules\NfeBrasil\Models\NfeTransaction::class);

    $rule = $registry[\Modules\NfeBrasil\Models\NfeTransaction::class];
    expect($rule['condition'])->toBeCallable();

    // cstat 100 (autorizada) — bloqueado
    $fake = (object) ['cstat' => 100];
    expect(($rule['condition'])($fake))->toBeTrue();

    // cstat 101 (cancelada SEFAZ) — bloqueado
    $fake = (object) ['cstat' => 101];
    expect(($rule['condition'])($fake))->toBeTrue();

    // cstat 135 (homologada) — bloqueado
    $fake = (object) ['cstat' => 135];
    expect(($rule['condition'])($fake))->toBeTrue();

    // cstat 999 (rejeitada) — permitido reverter (não SEFAZ-firme)
    $fake = (object) ['cstat' => 999];
    expect(($rule['condition'])($fake))->toBeFalse();

    // sem cstat — permitido (rascunho)
    $fake = (object) [];
    expect(($rule['condition'])($fake))->toBeFalse();
});

it('whitelist contém TituloBaixa com condition origem=asaas-paid', function () {
    $registry = $this->service->unrevertibleRegistry();
    expect($registry)->toHaveKey(\Modules\Financeiro\Models\TituloBaixa::class);

    $rule = $registry[\Modules\Financeiro\Models\TituloBaixa::class];
    expect($rule['reason'])->toContain('Asaas');

    $fake = (object) ['origem' => 'asaas-paid'];
    expect(($rule['condition'])($fake))->toBeTrue();

    $fake = (object) ['origem' => 'manual'];
    expect(($rule['condition'])($fake))->toBeFalse();
});

it('whitelist contém OS com condition nfse_emitida=true', function () {
    $registry = $this->service->unrevertibleRegistry();
    expect($registry)->toHaveKey(\Modules\Repair\Models\OS::class);

    $rule = $registry[\Modules\Repair\Models\OS::class];
    expect($rule['reason'])->toContain('NFSe');

    $fake = (object) ['nfse_emitida' => true];
    expect(($rule['condition'])($fake))->toBeTrue();

    $fake = (object) ['nfse_emitida' => false];
    expect(($rule['condition'])($fake))->toBeFalse();
});

it('whitelist contém Transaction com condition payment posterior (closure 2-arg)', function () {
    $registry = $this->service->unrevertibleRegistry();
    expect($registry)->toHaveKey(\App\Transaction::class);

    $rule = $registry[\App\Transaction::class];
    expect($rule['reason'])->toContain('pagamento');
    expect($rule['condition'])->toBeCallable();

    // closure deve aceitar 2 args ($model, ?Activity $logEntry)
    $reflection = new \ReflectionFunction($rule['condition']);
    expect($reflection->getNumberOfParameters())->toBe(2);

    // logEntry null → não pode determinar → returns false (permitido)
    $fake = (object) [];
    expect(($rule['condition'])($fake, null))->toBeFalse();
});

it('whitelist NÃO contém entries de classes não-compliance (Contact, Product)', function () {
    $registry = $this->service->unrevertibleRegistry();
    expect($registry)->not->toHaveKey(\App\Contact::class);
    expect($registry)->not->toHaveKey(\App\Product::class);
});

it('reasons das 5 categorias têm comprimento mínimo (mensagem útil)', function () {
    $registry = $this->service->unrevertibleRegistry();
    foreach ($registry as $class => $rule) {
        expect(strlen($rule['reason']))
            ->toBeGreaterThan(40, "Reason muito curta pra {$class}");
    }
});

it('cada rule tem chaves canônicas reason + condition (estrutura estável)', function () {
    $registry = $this->service->unrevertibleRegistry();
    foreach ($registry as $class => $rule) {
        expect($rule)->toHaveKeys(['reason', 'condition'], "Rule {$class} sem chaves canônicas");
        expect($rule['reason'])->toBeString();
    }
});

it('RevertService expõe método canRevert + revert (API pública)', function () {
    expect(method_exists(RevertService::class, 'canRevert'))->toBeTrue();
    expect(method_exists(RevertService::class, 'revert'))->toBeTrue();
    expect(method_exists(RevertService::class, 'unrevertibleRegistry'))->toBeTrue();
});

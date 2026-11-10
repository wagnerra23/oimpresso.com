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

it('toda chave da whitelist é classe que existe (instanceof de classe inexistente é trava inerte)', function () {
    foreach (array_keys($this->service->unrevertibleRegistry()) as $classe) {
        expect(class_exists($classe))->toBeTrue("Chave {$classe} não existe");
    }
});

it('whitelist contém Marcacao (Portaria MTP 671/2021 append-only)', function () {
    $registry = $this->service->unrevertibleRegistry();
    expect($registry)->toHaveKey(\Modules\Ponto\Entities\Marcacao::class);

    $rule = $registry[\Modules\Ponto\Entities\Marcacao::class];
    expect($rule['condition'])->toBeNull(); // sempre bloqueado
    expect($rule['reason'])->toContain('Portaria MTP 671/2021');
    expect($rule['reason'])->toContain('append-only');
});

it('documentos fiscais e baixa financeira bloqueiam sempre, sem condição', function () {
    $registry = $this->service->unrevertibleRegistry();

    foreach ([
        \Modules\NfeBrasil\Models\NfeEmissao::class,
        \Modules\NfeBrasil\Models\NfeEvento::class,
        \Modules\NfeBrasil\Models\NfeInutilizacao::class,
        \Modules\NFSe\Models\NfseEmissao::class,
        \Modules\NfeBrasil\Models\NfseEmissao::class,
        \Modules\Financeiro\Models\TituloBaixa::class,
    ] as $classe) {
        expect($registry)->toHaveKey($classe);
        expect($registry[$classe]['condition'])->toBeNull();
    }
    expect($registry[\Modules\Financeiro\Models\TituloBaixa::class]['reason'])->toContain('Asaas');
});

it('whitelist contém JobSheet com condição de NFSe (efeito provado no RevertServiceTest)', function () {
    $registry = $this->service->unrevertibleRegistry();
    expect($registry)->toHaveKey(\Modules\Repair\Entities\JobSheet::class);

    $rule = $registry[\Modules\Repair\Entities\JobSheet::class];
    expect($rule['reason'])->toContain('NFSe');
    expect($rule['condition'])->toBeCallable();
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

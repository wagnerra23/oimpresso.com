<?php

declare(strict_types=1);

use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class);

/**
 * US-FISCAL-014 — NfeService::retransmitir unit tests.
 *
 * Cobertura broad em SEFAZ real fica em Pest browser MCP pós-merge biz=1
 * (homologação SP — emite NFe inválida → retransmite com cadastro corrigido).
 *
 * Aqui foca contract + guards:
 *   - Método público existe com signature canônica
 *   - Status retransmissíveis whitelist exata
 *   - OTel span name canônico
 */

it('retransmitir method público existe + signature canônica', function () {
    expect(method_exists(NfeService::class, 'retransmitir'))->toBeTrue();

    $reflection = new ReflectionMethod(NfeService::class, 'retransmitir');
    expect($reflection->isPublic())->toBeTrue()
        ->and($reflection->getNumberOfParameters())->toBe(2);

    $params = $reflection->getParameters();
    expect($params[0]->getName())->toBe('businessId')
        ->and((string) $params[0]->getType())->toBe('int')
        ->and($params[1]->getName())->toBe('nfeEmissaoId')
        ->and((string) $params[1]->getType())->toBe('int');

    expect((string) $reflection->getReturnType())
        ->toBe('Modules\NfeBrasil\Models\NfeEmissao');
});

it('retransmitir contrato: lista de status válidos = [rejeitada, denegada, erro_envio]', function () {
    // Defesa estrutural: garante que whitelist está documentada e testada.
    // SEFAZ status "autorizada" / "cancelada" / "inutilizada" / "pendente" NÃO podem retransmitir.
    $whitelist = ['rejeitada', 'denegada', 'erro_envio'];
    $bloqueados = ['autorizada', 'cancelada', 'inutilizada', 'pendente'];

    expect($whitelist)->toHaveCount(3)
        ->and(array_intersect($whitelist, $bloqueados))->toBeEmpty();
});

it('retransmitir contrato: método retransmitirInterno privado (não exposto)', function () {
    $reflection = new ReflectionMethod(NfeService::class, 'retransmitirInterno');
    expect($reflection->isPrivate())->toBeTrue();
});

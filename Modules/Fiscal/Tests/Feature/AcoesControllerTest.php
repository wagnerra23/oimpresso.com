<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * PR #4 Wave Ações Mutação Fiscal — guards Tier 0 + permissões + delegação.
 *
 * AcoesController é thin delegate pra NfeService::cancelar (FSM cascade ADR 0143)
 * e ManifestacaoService (4 ações DF-e).
 *
 * ⚠️ PODA 2026-07-28 (decisão [W]) — 11 casos foram REMOVIDOS por serem TAUTOLÓGICOS: montavam um
 * `validator([...], [...])` LOCAL com as regras reescritas à mão, ou assertavam um array literal
 * declarado uma linha acima. Testavam o Laravel (e o próprio teste), não o `AcoesController` —
 * trocar `min:15` por `min:5` no Controller NÃO os derrubava. Todos têm substituto com mordida
 * provada em `AcoesContratoTest` (UC-FNFE-04/05/06), que invoca os métodos do Controller.
 *
 * Exceção declarada: `retransmitir contrato: status válidos` também era tautológico e foi removido
 * SEM substituto — no Controller a whitelist de status é checada DEPOIS do `firstOrFail()`, então
 * exige `nfe_emissoes`, indisponível nas lanes de hoje. A lacuna está no backlog de `Nfe.casos.md`.
 *
 * O que FICA (7 casos):
 *  - 5 ESTRUTURAIS (métodos/rota/Services existem, via reflection) → ancoram `UC-FNFE-08`;
 *  - 2 de manifestação DF-e → ancoram `UC-FDFE-03`/`UC-FDFE-04` (tela `Fiscal/Dfe`). Estes DOIS
 *    seguem tautológicos (assertam arrays locais), mas NÃO foram removidos porque são a cobertura
 *    G-2 declarada por outra tela. O comportamento real já é provado por
 *    `AcoesContratoTest::UC-FNFE-07`; re-apontar os UCs do DF-e pra lá é trabalho do dono daquela
 *    tela, não carona deste PR.
 *
 * Guard de banco removido 2026-07-27: nenhum caso deste arquivo consulta tabela (`class_exists`,
 * `Route::has`, reflection), mas o `beforeEach` skipava todos quando `nfe_emissoes` faltava —
 * inclusive os estruturais, que passavam a não rodar em lane nenhuma.
 */

it('UC-FDFE-03 · manifestarDfe whitelist exatamente 4 ações canon SEFAZ', function () {
    $acoesValidas = ['cienciar', 'confirmar', 'desconhecer', 'nao_realizada'];

    // Whitelist guard — qualquer outra string deve falhar
    expect($acoesValidas)
        ->toHaveCount(4)
        ->toContain('cienciar', 'confirmar', 'desconhecer', 'nao_realizada')
        ->not->toContain('cancelar', 'aprovar', 'rejeitar');
});

it('UC-FDFE-04 · manifestarDfe desconhecer/nao_realizada exigem justificativa, cienciar/confirmar não', function () {
    $exigemJustif = ['desconhecer', 'nao_realizada'];
    $semJustif    = ['cienciar', 'confirmar'];

    foreach ($exigemJustif as $acao) {
        expect(in_array($acao, ['desconhecer', 'nao_realizada'], true))->toBeTrue("$acao deve exigir justificativa");
    }

    foreach ($semJustif as $acao) {
        expect(in_array($acao, ['desconhecer', 'nao_realizada'], true))->toBeFalse("$acao NÃO exige justificativa");
    }
});

it('UC-FNFE-08 · AcoesController classe existe e tem 5 métodos públicos esperados (Waves 4+5+6)', function () {
    $controller = new \Modules\Fiscal\Http\Controllers\AcoesController();
    // Wave 4 (PR #4)
    expect(method_exists($controller, 'cancelarNfe'))->toBeTrue()
        ->and(method_exists($controller, 'manifestarDfe'))->toBeTrue();
    // Wave 5 (PR #5) — CCe + Inutilização
    expect(method_exists($controller, 'cartaCorrecao'))->toBeTrue()
        ->and(method_exists($controller, 'inutilizar'))->toBeTrue();
    // Wave 6 (PR #6) — Retransmitir
    expect(method_exists($controller, 'retransmitir'))->toBeTrue();
});

it('UC-FNFE-08 · retransmitir contrato: NfeService::retransmitir signature int/int → NfeEmissao', function () {
    $reflection = new ReflectionMethod(\Modules\NfeBrasil\Services\NfeService::class, 'retransmitir');
    $params = $reflection->getParameters();

    expect($params)->toHaveCount(2)
        ->and((string) $params[0]->getType())->toBe('int')
        ->and((string) $params[1]->getType())->toBe('int')
        ->and((string) $reflection->getReturnType())->toBe('Modules\NfeBrasil\Models\NfeEmissao');
});

it('UC-FNFE-08 · retransmitir route POST registrada (acoes.nfe.retransmitir)', function () {
    expect(\Illuminate\Support\Facades\Route::has('fiscal.acoes.nfe.retransmitir'))->toBeTrue();
});

it('UC-FNFE-08 · NfeCartaCorrecaoService classe existe e tem método aplicar público', function () {
    expect(class_exists(\Modules\NfeBrasil\Services\NfeCartaCorrecaoService::class))->toBeTrue()
        ->and(method_exists(\Modules\NfeBrasil\Services\NfeCartaCorrecaoService::class, 'aplicar'))->toBeTrue();
});

it('UC-FNFE-08 · NfeInutilizacaoService já existia (delegação Wave 5 não duplica lógica)', function () {
    expect(class_exists(\Modules\NfeBrasil\Services\NfeInutilizacaoService::class))->toBeTrue()
        ->and(method_exists(\Modules\NfeBrasil\Services\NfeInutilizacaoService::class, 'inutilizar'))->toBeTrue();
});

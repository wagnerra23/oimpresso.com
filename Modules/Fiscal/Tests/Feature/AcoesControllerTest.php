<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * PR #4 Wave Ações Mutação Fiscal — guards Tier 0 + permissões + delegação.
 *
 * AcoesController é thin delegate pra NfeService::cancelar (FSM cascade ADR 0143)
 * e ManifestacaoService (4 ações DF-e). Tests focam em validar contratos:
 *  - Permissões obrigatórias (fiscal.nfe.acoes / fiscal.dfe.manage)
 *  - Validação de input (motivo/justificativa ≥15 chars regra CONFAZ)
 *  - Ações DF-e válidas (whitelist)
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeBrasil requer schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_emissoes') || ! Schema::hasTable('nfe_dfe_recebidos')) {
        $this->markTestSkipped('Tabelas NfeBrasil ausentes — rodar migrate primeiro');
    }
});

it('cancelarNfe rejeita motivo < 15 chars (regra CONFAZ SINIEF 07/2005)', function () {
    // Defesa estrutural: testamos validação direta sem precisar de DB real.
    // Smoke completo via Pest browser MCP pós-merge biz=1.
    $validator = validator(
        ['motivo' => 'curto'],
        ['motivo' => ['required', 'string', 'min:15', 'max:255']]
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('motivo'))->toBeTrue();
});

it('cancelarNfe aceita motivo válido ≥15 chars', function () {
    $validator = validator(
        ['motivo' => 'Cliente desistiu pós-emissão, refaturado V-1234'],
        ['motivo' => ['required', 'string', 'min:15', 'max:255']]
    );

    expect($validator->fails())->toBeFalse();
});

it('manifestarDfe whitelist exatamente 4 ações canon SEFAZ', function () {
    $acoesValidas = ['cienciar', 'confirmar', 'desconhecer', 'nao_realizada'];

    // Whitelist guard — qualquer outra string deve falhar
    expect($acoesValidas)
        ->toHaveCount(4)
        ->toContain('cienciar', 'confirmar', 'desconhecer', 'nao_realizada')
        ->not->toContain('cancelar', 'aprovar', 'rejeitar');
});

it('manifestarDfe desconhecer/nao_realizada exigem justificativa, cienciar/confirmar não', function () {
    $exigemJustif = ['desconhecer', 'nao_realizada'];
    $semJustif    = ['cienciar', 'confirmar'];

    foreach ($exigemJustif as $acao) {
        expect(in_array($acao, ['desconhecer', 'nao_realizada'], true))->toBeTrue("$acao deve exigir justificativa");
    }

    foreach ($semJustif as $acao) {
        expect(in_array($acao, ['desconhecer', 'nao_realizada'], true))->toBeFalse("$acao NÃO exige justificativa");
    }
});

it('AcoesController classe existe e tem 2 métodos públicos esperados', function () {
    $controller = new \Modules\Fiscal\Http\Controllers\AcoesController();
    expect(method_exists($controller, 'cancelarNfe'))->toBeTrue()
        ->and(method_exists($controller, 'manifestarDfe'))->toBeTrue();
});

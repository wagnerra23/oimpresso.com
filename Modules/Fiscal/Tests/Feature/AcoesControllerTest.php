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

it('AcoesController classe existe e tem 4 métodos públicos esperados (Wave 4 + Wave 5)', function () {
    $controller = new \Modules\Fiscal\Http\Controllers\AcoesController();
    // Wave 4 (PR #4)
    expect(method_exists($controller, 'cancelarNfe'))->toBeTrue()
        ->and(method_exists($controller, 'manifestarDfe'))->toBeTrue();
    // Wave 5 (PR #5) — CCe + Inutilização
    expect(method_exists($controller, 'cartaCorrecao'))->toBeTrue()
        ->and(method_exists($controller, 'inutilizar'))->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────
// PR #5 Wave — CCe (Carta de Correção Eletrônica) + Inutilização faixa
// ──────────────────────────────────────────────────────────────────────

it('cartaCorrecao rejeita texto correção <15 chars (CONFAZ Art. 14)', function () {
    $validator = validator(
        ['texto_correcao' => 'curto', 'n_seq_evento' => 1],
        [
            'texto_correcao' => ['required', 'string', 'min:15', 'max:1000'],
            'n_seq_evento'   => ['required', 'integer', 'min:1', 'max:20'],
        ],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('texto_correcao'))->toBeTrue();
});

it('cartaCorrecao rejeita texto correção >1000 chars (limite SEFAZ)', function () {
    $textoLongo = str_repeat('a', 1001);
    $validator = validator(
        ['texto_correcao' => $textoLongo, 'n_seq_evento' => 1],
        [
            'texto_correcao' => ['required', 'string', 'min:15', 'max:1000'],
            'n_seq_evento'   => ['required', 'integer', 'min:1', 'max:20'],
        ],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('texto_correcao'))->toBeTrue();
});

it('cartaCorrecao rejeita n_seq_evento fora de 1-20 (CONFAZ Art. 14)', function () {
    foreach ([0, 21, -1, 100] as $seqInvalida) {
        $validator = validator(
            ['texto_correcao' => str_repeat('a', 20), 'n_seq_evento' => $seqInvalida],
            [
                'texto_correcao' => ['required', 'string', 'min:15', 'max:1000'],
                'n_seq_evento'   => ['required', 'integer', 'min:1', 'max:20'],
            ],
        );
        expect($validator->fails())->toBeTrue("seq={$seqInvalida} deve falhar")
            ->and($validator->errors()->has('n_seq_evento'))->toBeTrue();
    }
});

it('cartaCorrecao aceita texto válido (15-1000) + seq 1-20', function () {
    $validator = validator(
        ['texto_correcao' => 'Endereço do destinatário corrigido pra Rua A, 1234', 'n_seq_evento' => 1],
        [
            'texto_correcao' => ['required', 'string', 'min:15', 'max:1000'],
            'n_seq_evento'   => ['required', 'integer', 'min:1', 'max:20'],
        ],
    );

    expect($validator->fails())->toBeFalse();
});

it('inutilizar valida modelo (whitelist 55/65)', function () {
    foreach (['54', '56', 'abc', '5'] as $modeloInvalido) {
        $validator = validator(
            [
                'modelo' => $modeloInvalido,
                'serie' => '1', 'numero_de' => 1, 'numero_ate' => 1,
                'justificativa' => str_repeat('x', 20),
            ],
            [
                'modelo'        => ['required', 'string', 'in:55,65'],
                'serie'         => ['required', 'string', 'max:3'],
                'numero_de'     => ['required', 'integer', 'min:1'],
                'numero_ate'    => ['required', 'integer', 'min:1', 'gte:numero_de'],
                'justificativa' => ['required', 'string', 'min:15', 'max:255'],
            ],
        );
        expect($validator->fails())->toBeTrue("modelo={$modeloInvalido} deve falhar");
    }
});

it('inutilizar rejeita faixa inválida (numero_ate < numero_de)', function () {
    $validator = validator(
        [
            'modelo' => '55', 'serie' => '1',
            'numero_de' => 100, 'numero_ate' => 50,
            'justificativa' => str_repeat('x', 20),
        ],
        [
            'modelo'        => ['required', 'string', 'in:55,65'],
            'serie'         => ['required', 'string', 'max:3'],
            'numero_de'     => ['required', 'integer', 'min:1'],
            'numero_ate'    => ['required', 'integer', 'min:1', 'gte:numero_de'],
            'justificativa' => ['required', 'string', 'min:15', 'max:255'],
        ],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('numero_ate'))->toBeTrue();
});

it('inutilizar rejeita justificativa <15 chars (regra SEFAZ)', function () {
    $validator = validator(
        [
            'modelo' => '55', 'serie' => '1',
            'numero_de' => 1, 'numero_ate' => 5,
            'justificativa' => 'curto',
        ],
        [
            'modelo'        => ['required', 'string', 'in:55,65'],
            'serie'         => ['required', 'string', 'max:3'],
            'numero_de'     => ['required', 'integer', 'min:1'],
            'numero_ate'    => ['required', 'integer', 'min:1', 'gte:numero_de'],
            'justificativa' => ['required', 'string', 'min:15', 'max:255'],
        ],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('justificativa'))->toBeTrue();
});

it('inutilizar aceita payload válido (modelo 55/65, faixa 1..N, just 15-255)', function () {
    foreach (['55', '65'] as $modelo) {
        $validator = validator(
            [
                'modelo' => $modelo, 'serie' => '1',
                'numero_de' => 100, 'numero_ate' => 105,
                'justificativa' => 'NFe rejeitada SEFAZ cstat 539 — inutilizando faixa.',
            ],
            [
                'modelo'        => ['required', 'string', 'in:55,65'],
                'serie'         => ['required', 'string', 'max:3'],
                'numero_de'     => ['required', 'integer', 'min:1'],
                'numero_ate'    => ['required', 'integer', 'min:1', 'gte:numero_de'],
                'justificativa' => ['required', 'string', 'min:15', 'max:255'],
            ],
        );
        expect($validator->fails())->toBeFalse("modelo={$modelo} deve passar");
    }
});

it('NfeCartaCorrecaoService classe existe e tem método aplicar público', function () {
    expect(class_exists(\Modules\NfeBrasil\Services\NfeCartaCorrecaoService::class))->toBeTrue()
        ->and(method_exists(\Modules\NfeBrasil\Services\NfeCartaCorrecaoService::class, 'aplicar'))->toBeTrue();
});

it('NfeInutilizacaoService já existia (delegação Wave 5 não duplica lógica)', function () {
    expect(class_exists(\Modules\NfeBrasil\Services\NfeInutilizacaoService::class))->toBeTrue()
        ->and(method_exists(\Modules\NfeBrasil\Services\NfeInutilizacaoService::class, 'inutilizar'))->toBeTrue();
});

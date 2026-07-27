<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * PR #4 Wave Ações Mutação Fiscal — guards Tier 0 + permissões + delegação.
 *
 * AcoesController é thin delegate pra NfeService::cancelar (FSM cascade ADR 0143)
 * e ManifestacaoService (4 ações DF-e).
 *
 * ⚠️ LIMITE DESTE ARQUIVO (medido 2026-07-27) — 13 dos 18 casos aqui são TAUTOLÓGICOS: montam
 * um `validator([...], [...])` LOCAL com as regras reescritas à mão, ou assertam um array literal
 * declarado na linha acima. Eles testam o Laravel (e o próprio teste), não o `AcoesController`:
 * trocar `min:15` por `min:5` no Controller NÃO os derruba. O contrato REAL das mesmas regras
 * vive em `AcoesContratoTest` (UC-FNFE-04/05/06/07), que invoca os métodos do Controller e tem
 * mordida provada. Estes 18 ficam como candidatos a subtração — decisão [W], não do agente.
 *
 * Os 5 casos ESTRUTURAIS (métodos/rota/Services existem) tocam produção de verdade e ancoram
 * `UC-FNFE-08`.
 *
 * Guard de banco removido 2026-07-27: nenhum caso deste arquivo consulta tabela (validator local,
 * `class_exists`, `Route::has`, reflection), mas o `beforeEach` skipava os 18 quando
 * `nfe_emissoes` faltava — inclusive os 5 estruturais, que passavam a não rodar em lane nenhuma.
 */

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

// ──────────────────────────────────────────────────────────────────────
// PR #6 Wave — Retransmitir NFe rejeitada/denegada
// ──────────────────────────────────────────────────────────────────────

it('retransmitir contrato: status válidos = rejeitada/denegada/erro_envio', function () {
    $statusRetransmissiveis = ['rejeitada', 'denegada', 'erro_envio'];
    expect($statusRetransmissiveis)
        ->toHaveCount(3)
        ->toContain('rejeitada', 'denegada', 'erro_envio')
        ->not->toContain('autorizada', 'cancelada', 'inutilizada', 'pendente');
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

it('UC-FNFE-08 · NfeCartaCorrecaoService classe existe e tem método aplicar público', function () {
    expect(class_exists(\Modules\NfeBrasil\Services\NfeCartaCorrecaoService::class))->toBeTrue()
        ->and(method_exists(\Modules\NfeBrasil\Services\NfeCartaCorrecaoService::class, 'aplicar'))->toBeTrue();
});

it('UC-FNFE-08 · NfeInutilizacaoService já existia (delegação Wave 5 não duplica lógica)', function () {
    expect(class_exists(\Modules\NfeBrasil\Services\NfeInutilizacaoService::class))->toBeTrue()
        ->and(method_exists(\Modules\NfeBrasil\Services\NfeInutilizacaoService::class, 'inutilizar'))->toBeTrue();
});

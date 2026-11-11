<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Models\CaixaMovimento;
use Modules\Financeiro\Models\TituloBaixa;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Baixas e movimentos de caixa são livros: corrige-se lançando outro registro, nunca
 * editando (auditoria Financeiro 2026-11-05, achado D10).
 *
 * Os dois Models só bloqueavam `$model->delete()`. `Model::where(...)->update(...)`,
 * `->delete()` e `$model->save()` passavam direto, em silêncio. Hoje nenhum código de
 * produção faz isso; este teste trava que continue assim.
 *
 * `truncate()` fica de fora: no MySQL ele fecha a transação do teste e, no vermelho,
 * apagaria o banco compartilhado da lane. `DB::table(...)` também fica de fora: é a
 * saída deliberada de limpeza e correção por comando.
 *
 * Tenant 98 (ADR 0358), nunca biz=4 nem biz=1.
 * ⚠️ Skip fora do MySQL: leia as assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Schema real do Financeiro (MySQL).');
    }
    if (! Schema::hasTable('fin_titulo_baixas') || ! Schema::hasTable('fin_caixa_movimentos')) {
        $this->markTestSkipped('Tabelas do Financeiro ausentes.');
    }

    $this->biz = (int) $this->seededTenant()->id;
    $this->uid = (int) $this->usuarioComPermissoes([])->id;
    session(['user.business_id' => $this->biz, 'business.id' => $this->biz]);

    $hoje = now();
    $titulo = DB::table('fin_titulos')->insertGetId([
        'business_id' => $this->biz, 'numero' => 'APO-'.bin2hex(random_bytes(4)), 'status' => 'aberto',
        'tipo' => 'receber', 'cliente_descricao' => 'Append-only', 'valor_total' => 100, 'valor_aberto' => 90,
        'moeda' => 'BRL', 'emissao' => $hoje->toDateString(), 'vencimento' => $hoje->toDateString(),
        'competencia_mes' => $hoje->format('Y-m'), 'origem' => 'manual', 'created_by' => $this->uid,
        'created_at' => $hoje, 'updated_at' => $hoje,
    ]);

    $this->ids = [
        TituloBaixa::class => (int) DB::table('fin_titulo_baixas')->insertGetId([
            'business_id' => $this->biz, 'titulo_id' => $titulo, 'valor_baixa' => 10,
            'data_baixa' => $hoje->toDateString(), 'meio_pagamento' => 'pix',
            'idempotency_key' => 'apo-'.bin2hex(random_bytes(6)), 'created_by' => $this->uid, 'created_at' => $hoje,
        ]),
        CaixaMovimento::class => (int) DB::table('fin_caixa_movimentos')->insertGetId([
            'business_id' => $this->biz, 'tipo' => 'entrada', 'valor' => 10, 'saldo_apos' => 10,
            'data' => $hoje->toDateString(), 'descricao' => 'Append-only', 'created_by' => $this->uid,
            'created_at' => $hoje,
        ]),
    ];
    $this->colunas = [
        TituloBaixa::class    => ['fin_titulo_baixas', 'valor_baixa'],
        CaixaMovimento::class => ['fin_caixa_movimentos', 'valor'],
    ];
});

dataset('livros', [
    'baixa'           => [TituloBaixa::class],
    'movimento caixa' => [CaixaMovimento::class],
]);

function apoValor($test, string $model): float
{
    [$tabela, $coluna] = $test->colunas[$model];

    return (float) DB::table($tabela)->where('id', $test->ids[$model])->value($coluna);
}

it('recusa update pela query e o valor continua igual', function (string $model) {
    $coluna = $this->colunas[$model][1];

    expect(fn () => $model::whereKey($this->ids[$model])->update([$coluna => 999]))
        ->toThrow(DomainException::class);

    expect(apoValor($this, $model))->toBe(10.0);
})->with('livros');

it('recusa delete e forceDelete pela query e o registro continua lá', function (string $model) {
    expect(fn () => $model::whereKey($this->ids[$model])->delete())->toThrow(DomainException::class);
    expect(fn () => $model::whereKey($this->ids[$model])->forceDelete())->toThrow(DomainException::class);

    expect(apoValor($this, $model))->toBe(10.0);
})->with('livros');

it('recusa increment e decrement pela query', function (string $model) {
    $coluna = $this->colunas[$model][1];

    expect(fn () => $model::whereKey($this->ids[$model])->increment($coluna, 5))->toThrow(DomainException::class);
    expect(fn () => $model::whereKey($this->ids[$model])->decrement($coluna, 5))->toThrow(DomainException::class);

    expect(apoValor($this, $model))->toBe(10.0);
})->with('livros');

it('recusa alterar um registro já gravado pelo próprio model', function (string $model) {
    $coluna = $this->colunas[$model][1];
    $registro = $model::findOrFail($this->ids[$model]);
    $registro->{$coluna} = 999;

    expect(fn () => $registro->save())->toThrow(DomainException::class);

    expect(apoValor($this, $model))->toBe(10.0);
})->with('livros');

it('continua aceitando lançamento novo e leitura', function () {
    $titulo = (int) DB::table('fin_titulo_baixas')->where('id', $this->ids[TituloBaixa::class])->value('titulo_id');

    $estorno = TituloBaixa::create([
        'business_id' => $this->biz, 'titulo_id' => $titulo, 'valor_baixa' => -10,
        'data_baixa' => now()->toDateString(), 'meio_pagamento' => 'pix',
        'idempotency_key' => 'apo-est-'.bin2hex(random_bytes(6)),
        'estorno_de_id' => $this->ids[TituloBaixa::class], 'created_by' => $this->uid,
    ]);
    $movimento = CaixaMovimento::create([
        'business_id' => $this->biz, 'tipo' => 'saida', 'valor' => 10,
        'data' => now()->toDateString(), 'descricao' => 'Estorno', 'created_by' => $this->uid,
    ]);

    expect($estorno->exists)->toBeTrue()
        ->and($movimento->exists)->toBeTrue()
        ->and((float) TituloBaixa::where('titulo_id', $titulo)->sum('valor_baixa'))->toBe(0.0);
});

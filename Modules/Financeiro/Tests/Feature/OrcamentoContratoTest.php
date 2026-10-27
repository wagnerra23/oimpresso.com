<?php

declare(strict_types=1);
// Cobre US-FIN-076 (memory/requisitos/Financeiro/SPEC.md) — orçado × realizado por conta, no mês.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Financeiro\Services\CentroCustoFiltro;
use Modules\Financeiro\Services\OrcamentoService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Regra mestre de VALOR, dois caminhos: (1) cada número bate com a conta à mão sobre a fixture;
 * (2) A + B + "sem centro" fecha o "todos", no orçado e no realizado.
 *
 * Fixture (tenant 98, NUNCA biz=4), mês isolado 2031-06, contas novas R (receita) e D (despesa):
 *   Orçado: R geral 10.000 · D geral 3.000 · D no centro A 2.000
 *   Títulos: R 12.000 sem rateio · D1 4.000 rateado A 2.500 / B 1.500 · D2 1.000 sem rateio
 *   Baixa: D1 1.000 em 10/06 → caixa do centro A = 1.000 × 2.500/4.000 = 625
 */
const ORC_MES = '2031-06';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: fin_orcamentos usa coluna gerada do MySQL.');
    }
    if (! Schema::hasTable('fin_orcamentos')) {
        $this->markTestSkipped('fin_orcamentos ausente — rode migrate.');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->user = $this->usuarioComPermissoes(['financeiro.relatorios.view']);
    $this->svc = app(OrcamentoService::class);
    $u = (int) $this->user->id;

    $plano = fn (int $biz, string $tipo) => (int) DB::table('fin_planos_conta')->insertGetId(['business_id' => $biz,
        'codigo' => 'O'.uniqid(), 'nome' => 'ZZ orc '.$tipo, 'tipo' => $tipo, 'nivel' => 4,
        'natureza' => $tipo === 'receita' ? 'credito' : 'debito', 'aceita_lancamento' => true, 'protegido' => false,
        'ativo' => true, 'created_at' => now(), 'updated_at' => now()]);
    $centro = fn (string $nome) => (int) DB::table('fin_centros_custo')->insertGetId(['business_id' => $this->b98,
        'nome' => $nome, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()]);
    $titulo = fn (int $plano, string $tipo, string $valor) => (int) DB::table('fin_titulos')->insertGetId(['business_id' => $this->b98,
        'numero' => 'ORC'.substr(uniqid(), -9), 'tipo' => $tipo, 'status' => 'aberto', 'cliente_descricao' => 'fixture orçamento',
        'valor_total' => $valor, 'valor_aberto' => $valor, 'moeda' => 'BRL', 'emissao' => ORC_MES.'-02', 'vencimento' => ORC_MES.'-20',
        'competencia_mes' => ORC_MES, 'origem' => 'manual', 'plano_conta_id' => $plano, 'created_by' => $u,
        'created_at' => now(), 'updated_at' => now()]);
    $rateio = fn (int $t, int $c, string $pct, string $v) => DB::table('fin_titulo_rateios')->insert(['business_id' => $this->b98,
        'titulo_id' => $t, 'centro_custo_id' => $c, 'percentual' => $pct, 'valor' => $v, 'created_at' => now(), 'updated_at' => now()]);

    $this->R = $plano($this->b98, 'receita');
    $this->D = $plano($this->b98, 'despesa');
    $this->P99 = $plano($this->b99, 'despesa');
    $this->A = $centro('ZZ orc A');
    $this->B = $centro('ZZ orc B');

    $this->svc->definir($this->b98, ORC_MES, $this->R, null, '10000,00');
    $this->svc->definir($this->b98, ORC_MES, $this->D, null, '3000');
    $this->svc->definir($this->b98, ORC_MES, $this->D, $this->A, '2000.00');

    $titulo($this->R, 'receber', '12000.0000');
    $d1 = $titulo($this->D, 'pagar', '4000.0000');
    $titulo($this->D, 'pagar', '1000.0000');
    $rateio($d1, $this->A, '62.5000', '2500.0000');
    $rateio($d1, $this->B, '37.5000', '1500.0000');
    DB::table('fin_titulo_baixas')->insert(['business_id' => $this->b98, 'titulo_id' => $d1, 'valor_baixa' => '1000.0000',
        'juros' => 0, 'multa' => 0, 'desconto' => 0, 'data_baixa' => ORC_MES.'-10', 'meio_pagamento' => 'pix',
        'idempotency_key' => (string) Str::uuid(), 'created_by' => $u, 'created_at' => now()]);
});

/** Linha da conta no comparativo (ou zeros se a conta não aparece). */
function orcLinha(array $cmp, int $conta): array
{
    foreach ($cmp['linhas'] as $l) {
        if ($l['plano_conta_id'] === $conta) {
            return $l;
        }
    }

    return ['orcado' => 0.0, 'realizado' => 0.0, 'desvio' => 0.0, 'favoravel' => true];
}

it('compara orçado e realizado em competência, com desvio e sinal por tipo de conta', function () {
    $cmp = $this->svc->comparar($this->b98, ORC_MES);
    $r = orcLinha($cmp, $this->R);
    $d = orcLinha($cmp, $this->D);

    expect([$r['orcado'], $r['realizado'], $r['desvio'], $r['favoravel']])->toBe([10000.0, 12000.0, 2000.0, true])
        ->and([$d['orcado'], $d['realizado'], $d['desvio'], $d['favoravel']])->toBe([5000.0, 5000.0, 0.0, true])
        ->and($r['desvio_pct'])->toBe(20.0);
});

it('filtra por centro e A + B + sem centro fecha o total da conta', function () {
    $a = orcLinha($this->svc->comparar($this->b98, ORC_MES, $this->A), $this->D);
    $b = orcLinha($this->svc->comparar($this->b98, ORC_MES, $this->B), $this->D);
    $sem = orcLinha($this->svc->comparar($this->b98, ORC_MES, CentroCustoFiltro::SEM_CENTRO), $this->D);
    $todos = orcLinha($this->svc->comparar($this->b98, ORC_MES), $this->D);

    expect([$a['orcado'], $a['realizado'], $a['favoravel']])->toBe([2000.0, 2500.0, false])
        ->and([$b['orcado'], $b['realizado']])->toBe([0.0, 1500.0])
        ->and([$sem['orcado'], $sem['realizado']])->toBe([3000.0, 1000.0])
        ->and($a['orcado'] + $b['orcado'] + $sem['orcado'])->toBe($todos['orcado'])
        ->and($a['realizado'] + $b['realizado'] + $sem['realizado'])->toBe($todos['realizado']);
});

it('em caixa, conta as baixas do mês na proporção do centro', function () {
    $todos = orcLinha($this->svc->comparar($this->b98, ORC_MES, null, 'caixa'), $this->D);
    $a = orcLinha($this->svc->comparar($this->b98, ORC_MES, $this->A, 'caixa'), $this->D);

    expect($todos['realizado'])->toBe(1000.0)->and($a['realizado'])->toBe(625.0);
});

it('regrava o mesmo orçado sem duplicar e zero apaga a linha', function () {
    $this->svc->definir($this->b98, ORC_MES, $this->D, $this->A, '2500');
    $this->svc->definir($this->b98, ORC_MES, $this->D, null, '3500');

    $linhas = fn () => DB::table('fin_orcamentos')->where('business_id', $this->b98)->where('plano_conta_id', $this->D)->count();
    expect($linhas())->toBe(2)
        ->and(orcLinha($this->svc->comparar($this->b98, ORC_MES), $this->D)['orcado'])->toBe(6000.0);

    $this->svc->definir($this->b98, ORC_MES, $this->D, $this->A, '0');
    expect($linhas())->toBe(1);
});

it('recusa conta de outra empresa, valor negativo e mês inválido', function (string $caso) {
    match ($caso) {
        'conta 99' => $this->svc->definir($this->b98, ORC_MES, $this->P99, null, '100'),
        'negativo' => $this->svc->definir($this->b98, ORC_MES, $this->D, null, '-1'),
        'mês' => $this->svc->definir($this->b98, '2031-13', $this->D, null, '100'),
    };
})->with(['conta 99', 'negativo', 'mês'])->throws(ValidationException::class);

it('a empresa 99 não enxerga o orçamento nem o realizado da 98', function () {
    $cmp = $this->svc->comparar($this->b99, ORC_MES);

    expect(collect($cmp['linhas'])->pluck('plano_conta_id')->intersect([$this->R, $this->D])->all())->toBe([]);
});

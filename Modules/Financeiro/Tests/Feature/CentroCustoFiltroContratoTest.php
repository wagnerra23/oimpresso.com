<?php

declare(strict_types=1);
// Cobre US-FIN-075 (memory/requisitos/Financeiro/SPEC.md) — DRE e fluxo realizado filtram por centro de custo.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Services\CentroCustoFiltro;
use Modules\Financeiro\Services\DreService;
use Modules\Financeiro\Services\FluxoRealizadoService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Regra mestre de VALOR, dois caminhos: (1) cada filtro bate com a conta à mão sobre a fixture;
 * (2) A + B + "sem centro" fecha o "todos" — o mesmo dinheiro somado por outra rota.
 *
 * Fixture (tenant 98, NUNCA biz=4), mês de competência isolado 2031-05:
 *   T1 = 1000,00 rateado A 600,00 / B 400,00   · baixa de 250,00 hoje
 *   T2 =  500,00 sem rateio                    · baixa de 500,00 hoje
 *   T3 =  300,00 rateado A 100%
 * Esperado no título: A = 900,00 · B = 400,00 · sem = 500,00 · todos = 1800,00.
 * Esperado na baixa: A = 250 × 600/1000 = 150,00 · B = 100,00.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: fin_titulos depende de schema MySQL.');
    }
    if (! Schema::hasTable('fin_titulo_rateios')) {
        $this->markTestSkipped('fin_titulo_rateios ausente — rode migrate.');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->user = $this->usuarioComPermissoes(['financeiro.relatorios.view']);
    $u = (int) $this->user->id;

    $centro = fn (int $biz, string $nome) => (int) DB::table('fin_centros_custo')->insertGetId(['business_id' => $biz,
        'nome' => $nome, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()]);
    $titulo = fn (int $biz, string $valor) => (int) DB::table('fin_titulos')->insertGetId(['business_id' => $biz,
        'numero' => 'CCF'.substr(uniqid(), -9), 'tipo' => 'pagar', 'status' => 'aberto', 'cliente_descricao' => 'fixture filtro',
        'valor_total' => $valor, 'valor_aberto' => $valor, 'moeda' => 'BRL', 'emissao' => '2031-05-02', 'vencimento' => '2031-05-20',
        'competencia_mes' => '2031-05', 'origem' => 'manual', 'created_by' => $u, 'created_at' => now(), 'updated_at' => now()]);
    $rateio = fn (int $biz, int $t, int $c, string $pct, string $valor) => DB::table('fin_titulo_rateios')->insert(['business_id' => $biz,
        'titulo_id' => $t, 'centro_custo_id' => $c, 'percentual' => $pct, 'valor' => $valor, 'created_at' => now(), 'updated_at' => now()]);
    $baixa = fn (int $biz, int $t, string $valor) => DB::table('fin_titulo_baixas')->insert(['business_id' => $biz, 'titulo_id' => $t,
        'valor_baixa' => $valor, 'juros' => 0, 'multa' => 0, 'desconto' => 0, 'data_baixa' => now()->toDateString(),
        'meio_pagamento' => 'pix', 'idempotency_key' => (string) Str::uuid(), 'created_by' => $u, 'created_at' => now()]);

    $this->A = $centro($this->b98, 'ZZ filtro A');
    $this->B = $centro($this->b98, 'ZZ filtro B');
    $t1 = $titulo($this->b98, '1000.0000');
    $t2 = $titulo($this->b98, '500.0000');
    $t3 = $titulo($this->b98, '300.0000');
    $rateio($this->b98, $t1, $this->A, '60.0000', '600.0000');
    $rateio($this->b98, $t1, $this->B, '40.0000', '400.0000');
    $rateio($this->b98, $t3, $this->A, '100.0000', '300.0000');
    $baixa($this->b98, $t1, '250.0000');
    $baixa($this->b98, $t2, '500.0000');

    // Empresa 99 com rateio no centro de mesmo id lógico não pode vazar para a 98.
    $c99 = $centro($this->b99, 'ZZ filtro 99');
    $t99 = $titulo($this->b99, '7777.0000');
    $rateio($this->b99, $t99, $c99, '100.0000', '7777.0000');
    $this->c99 = $c99;
});

/** Soma, em centavos, dos títulos de 2031-05 da empresa sob o filtro — a mesma chamada que a DRE faz. */
function ccfSomaTitulos(int $biz, ?int $centro): int
{
    $q = Titulo::withoutGlobalScopes() // SUPERADMIN: teste sem sessão; o filtro de empresa é explícito abaixo
        ->where('fin_titulos.business_id', $biz)
        ->where('fin_titulos.competencia_mes', '2031-05')
        ->where('fin_titulos.status', '!=', 'cancelado');
    $valor = CentroCustoFiltro::aplicarNoTitulo($q, $biz, $centro);
    $total = (string) $q->selectRaw('COALESCE(SUM('.$valor->getValue(DB::connection()->getQueryGrammar()).'), 0) AS t')->value('t');

    return (int) round(((float) $total) * 100);
}

it('filtra os títulos por centro e a soma dos filtros fecha o total', function () {
    $todos = ccfSomaTitulos($this->b98, null);
    $a = ccfSomaTitulos($this->b98, $this->A);
    $b = ccfSomaTitulos($this->b98, $this->B);
    $sem = ccfSomaTitulos($this->b98, CentroCustoFiltro::SEM_CENTRO);

    expect($a)->toBe(90000)
        ->and($b)->toBe(40000)
        ->and($a + $b + $sem)->toBe($todos);
});

it('centro de outra empresa não traz nada para a 98', function () {
    expect(ccfSomaTitulos($this->b98, $this->c99))->toBe(0);
});

it('fluxo realizado conta a baixa na proporção da fatia do centro', function () {
    $svc = app(FluxoRealizadoService::class);
    $saidas = fn (?int $c) => (int) round(collect($svc->buscar($this->b98, 1, $c)['meses'])->sum('saidas') * 100);

    $a = $saidas($this->A);
    $b = $saidas($this->B);
    $sem = $saidas(CentroCustoFiltro::SEM_CENTRO);
    $todos = $saidas(null);

    expect($a)->toBe(15000)
        ->and($b)->toBe(10000)
        ->and($a + $b + $sem)->toBe($todos)
        ->and($svc->buscar($this->b98, 1, $this->A)['meta']['centro_custo_id'])->toBe($this->A);
});

it('a DRE recebe o centro e devolve no meta', function () {
    $shape = app(DreService::class)->montar($this->b98, 'mes', '2031-05', $this->A);

    expect($shape['meta']['centro_custo_id'])->toBe($this->A);
});

it('lê o filtro da query string', function (mixed $entrada, ?int $esperado) {
    expect(CentroCustoFiltro::daQuery($entrada))->toBe($esperado);
})->with([
    'ausente' => [null, null],
    'vazio' => ['', null],
    'todos' => ['todos', null],
    'sem' => ['sem', 0],
    'zero' => ['0', 0],
    'id' => ['12', 12],
    'lixo' => ['12abc', null],
    'negativo' => ['-3', null],
]);

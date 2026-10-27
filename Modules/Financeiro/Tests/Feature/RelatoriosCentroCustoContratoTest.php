<?php

declare(strict_types=1);
// Cobre UC-DRE-09 (Dre/Index.casos.md) e UC-FLX-40 (Fluxo/Index.casos.md) — US-FIN-075.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * O seletor de centro das telas: a query `?centro_custo=` chega ao serviço pelo controller e a
 * lista de centros vem só da empresa da sessão. A conta em si está em CentroCustoFiltroContratoTest.
 * Tenant 98 contra o 99. NUNCA biz=4.
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
    $this->user = $this->usuarioComPermissoes(['financeiro.relatorios.view', 'financeiro.dashboard.view']);
    $centro = fn (int $biz, string $nome) => (int) DB::table('fin_centros_custo')->insertGetId(['business_id' => $biz,
        'nome' => $nome, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()]);
    $this->A = $centro($this->b98, 'ZZ rel A');
    $this->C99 = $centro($this->b99, 'ZZ rel 99');
});

it('UC-DRE-09 a DRE recebe o centro da query e lista só os centros da empresa', function () {
    $this->actingAs($this->user)->get('/financeiro/dre?anchor=2031-09&centro_custo='.$this->A)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Financeiro/Dre/Index')
            ->where('meta.centro_custo_id', $this->A)
            ->where('centro_custo', $this->A)
            ->where('centros_custo', fn ($cs) => collect($cs)->pluck('id')->contains($this->A)
                && ! collect($cs)->pluck('id')->contains($this->C99)));

    $this->actingAs($this->user)->get('/financeiro/dre?anchor=2031-09&centro_custo=sem')
        ->assertInertia(fn (AssertableInertia $p) => $p->where('meta.centro_custo_id', 0));
});

it('UC-FLX-40 o realizado conta a baixa na proporção da fatia do centro escolhido', function () {
    // Título de 1.000,00 com 250,00 no centro A; baixa de 400,00 hoje → A = 400 × 250/1000 = 100,00.
    $t = (int) DB::table('fin_titulos')->insertGetId(['business_id' => $this->b98, 'numero' => 'REL'.substr(uniqid(), -9),
        'tipo' => 'pagar', 'status' => 'parcial', 'cliente_descricao' => 'fixture relatório', 'valor_total' => '1000.0000',
        'valor_aberto' => '600.0000', 'moeda' => 'BRL', 'emissao' => now()->toDateString(), 'vencimento' => now()->toDateString(),
        'competencia_mes' => now()->format('Y-m'), 'origem' => 'manual', 'created_by' => $this->user->id,
        'created_at' => now(), 'updated_at' => now()]);
    DB::table('fin_titulo_rateios')->insert(['business_id' => $this->b98, 'titulo_id' => $t, 'centro_custo_id' => $this->A,
        'percentual' => '25.0000', 'valor' => '250.0000', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('fin_titulo_baixas')->insert(['business_id' => $this->b98, 'titulo_id' => $t, 'valor_baixa' => '400.0000',
        'juros' => 0, 'multa' => 0, 'desconto' => 0, 'data_baixa' => now()->toDateString(), 'meio_pagamento' => 'pix',
        'idempotency_key' => (string) Str::uuid(), 'created_by' => $this->user->id, 'created_at' => now()]);

    $this->actingAs($this->user)->get('/financeiro/fluxo?tab=realizado&meses=1&centro_custo='.$this->A)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Financeiro/Fluxo/Index')
            ->where('realizado.meta.centro_custo_id', $this->A)
            ->where('realizado.totais.saidas', 100.0)
            ->where('centro_custo', $this->A));
});

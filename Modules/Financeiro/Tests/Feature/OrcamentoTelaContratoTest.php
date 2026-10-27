<?php

declare(strict_types=1);
// Cobre UC-ORC-01..04 (resources/js/Pages/Financeiro/Orcamento/Index.casos.md · US-FIN-076).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * A tela pelo HTTP real. A conta é do OrcamentoService (OrcamentoContratoTest prova os dois caminhos);
 * aqui o contrato é: a tela traz as contas, filtra por centro e regime, e grava pela empresa da sessão.
 * Tenant 98 contra o 99, mês isolado 2031-08. NUNCA biz=4.
 */
const ORT_MES = '2031-08';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: fin_orcamentos usa coluna gerada do MySQL.');
    }
    if (! Schema::hasTable('fin_orcamentos')) {
        $this->markTestSkipped('fin_orcamentos ausente — rode migrate.');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->user = $this->usuarioComPermissoes(['financeiro.relatorios.view', 'financeiro.lancamentos.create']);

    $plano = fn (int $biz, string $tipo, string $nome) => (int) DB::table('fin_planos_conta')->insertGetId(['business_id' => $biz,
        'codigo' => 'T'.uniqid(), 'nome' => $nome, 'tipo' => $tipo, 'nivel' => 4,
        'natureza' => $tipo === 'receita' ? 'credito' : 'debito', 'aceita_lancamento' => true, 'protegido' => false,
        'ativo' => true, 'created_at' => now(), 'updated_at' => now()]);
    $this->D = $plano($this->b98, 'despesa', 'ZZ tela despesa');
    $this->Vazia = $plano($this->b98, 'despesa', 'ZZ tela sem movimento');
    $this->P99 = $plano($this->b99, 'despesa', 'ZZ tela da 99');
    $this->A = (int) DB::table('fin_centros_custo')->insertGetId(['business_id' => $this->b98, 'nome' => 'ZZ tela A',
        'ativo' => true, 'created_at' => now(), 'updated_at' => now()]);

    $t = (int) DB::table('fin_titulos')->insertGetId(['business_id' => $this->b98, 'numero' => 'ORT'.substr(uniqid(), -9),
        'tipo' => 'pagar', 'status' => 'aberto', 'cliente_descricao' => 'fixture tela orçamento', 'valor_total' => '800.0000',
        'valor_aberto' => '800.0000', 'moeda' => 'BRL', 'emissao' => ORT_MES.'-02', 'vencimento' => ORT_MES.'-20',
        'competencia_mes' => ORT_MES, 'origem' => 'manual', 'plano_conta_id' => $this->D, 'created_by' => $this->user->id,
        'created_at' => now(), 'updated_at' => now()]);
    DB::table('fin_titulo_rateios')->insert(['business_id' => $this->b98, 'titulo_id' => $t, 'centro_custo_id' => $this->A,
        'percentual' => '25.0000', 'valor' => '200.0000', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('fin_titulo_baixas')->insert(['business_id' => $this->b98, 'titulo_id' => $t, 'valor_baixa' => '400.0000',
        'juros' => 0, 'multa' => 0, 'desconto' => 0, 'data_baixa' => ORT_MES.'-15', 'meio_pagamento' => 'pix',
        'idempotency_key' => (string) Str::uuid(), 'created_by' => $this->user->id, 'created_at' => now()]);
});

/** Linha da conta no payload da tela. */
function ortLinha(array $linhas, int $conta): ?array
{
    return collect($linhas)->firstWhere('plano_conta_id', $conta);
}

function ortTela(object $t, string $query = ''): array
{
    $linhas = null;
    $t->actingAs($t->user)->get('/financeiro/orcamento?mes='.ORT_MES.$query)
        ->assertOk()
        ->assertInertia(function (AssertableInertia $p) use (&$linhas) {
            $p->component('Financeiro/Orcamento/Index')->where('mes', ORT_MES);
            $linhas = $p->toArray()['props']['linhas'];
        });

    return $linhas;
}

it('UC-ORC-01 traz toda conta de resultado, com zero quando não tem movimento, e o desvio da que tem', function () {
    $this->actingAs($this->user)->post('/financeiro/orcamento', ['mes' => ORT_MES, 'plano_conta_id' => $this->D, 'valor' => '500,00'])
        ->assertSessionHasNoErrors();
    $linhas = ortTela($this);

    $d = ortLinha($linhas, $this->D);
    expect([$d['orcado'], $d['realizado'], $d['desvio'], $d['favoravel']])->toBe([500.0, 800.0, 300.0, false])
        ->and(ortLinha($linhas, $this->Vazia))->toMatchArray(['orcado' => 0.0, 'realizado' => 0.0])
        ->and(ortLinha($linhas, $this->P99))->toBeNull();
});

it('UC-ORC-02 grava o orçado pelo centro escolhido e zero apaga', function () {
    $post = fn (string $valor) => $this->actingAs($this->user)->post('/financeiro/orcamento',
        ['mes' => ORT_MES, 'plano_conta_id' => $this->D, 'centro_custo_id' => $this->A, 'valor' => $valor]);

    $post('150,50')->assertSessionHasNoErrors();
    expect(DB::table('fin_orcamentos')->where('business_id', $this->b98)->where('plano_conta_id', $this->D)
        ->where('centro_custo_id', $this->A)->value('valor'))->toBe('150.5000');

    $post('0')->assertSessionHasNoErrors();
    expect(DB::table('fin_orcamentos')->where('plano_conta_id', $this->D)->count())->toBe(0);
});

it('UC-ORC-03 filtra o realizado pela fatia do centro e troca para as baixas em caixa', function () {
    expect(ortLinha(ortTela($this, '&centro_custo='.$this->A), $this->D)['realizado'])->toBe(200.0)
        ->and(ortLinha(ortTela($this, '&centro_custo=sem'), $this->D)['realizado'])->toBe(0.0)
        ->and(ortLinha(ortTela($this, '&regime=caixa'), $this->D)['realizado'])->toBe(400.0)
        // Caixa no centro A: 400 × 200/800 = 100.
        ->and(ortLinha(ortTela($this, '&regime=caixa&centro_custo='.$this->A), $this->D)['realizado'])->toBe(100.0);
});

it('UC-ORC-04 recusa orçado em conta da empresa 99', function () {
    $this->actingAs($this->user)->post('/financeiro/orcamento', ['mes' => ORT_MES, 'plano_conta_id' => $this->P99, 'valor' => '100'])
        ->assertSessionHasErrors('plano_conta_id');

    expect(DB::table('fin_orcamentos')->where('plano_conta_id', $this->P99)->count())->toBe(0);
});

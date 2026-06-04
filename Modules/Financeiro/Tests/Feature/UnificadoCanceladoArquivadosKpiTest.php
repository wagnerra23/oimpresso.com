<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * 2026-06-04 — GUARD comportamento do bug do CANCELADO + filtro Arquivados +
 * KPIs seguindo o campo de data escolhido (Visão Unificada Financeiro).
 *
 * Contexto: na migração WR Comercial, títulos INATIVO viravam "Recebido" e
 * somavam em dobro nos cards (2.683 títulos / R$ [redacted Tier 0]M backfillados). O fix
 * (UnificadoController: kpisCore exclui baixas de cancelados + filtro Arquivados
 * + cards seguem data_campo) NÃO tinha teste guardando — qualquer mexida futura
 * nas KPIs reabria o buraco sem o CI piscar. Estes 5 casos travam isso.
 *
 * Cobre:
 *  (C1) baixa de título CANCELADO não soma em kpis.recebido (pareia com a lista)
 *  (A1) filtro Arquivados: default esconde cancelado e mostra ativo
 *  (A2) filtro Arquivados=1: mostra SÓ cancelado (ativo some)
 *  (D1) data_campo recalcula os cards (a_receber segue vencimento × emissão)
 *  (S1) shapeTitulo expõe os campos de paridade WR (Fase 1+2)
 *
 * biz=1 dogfooding (ADR 0101 — nunca biz=cliente). Janela 2099-03 isola dos
 * dados semeados → asserções absolutas. try/finally limpa o lixo do teste.
 * Skip gracioso quando DB greenfield / sem conta / module gate bloqueia.
 */

// Janela futura sem dados semeados → totais determinísticos e isolados.
const UNI_INI = '2099-03-01';
const UNI_FIM = '2099-03-31';
const UNI_DIA = '2099-03-15';
const UNI_COMP = '2099-03';

function uniBootstrap(): array
{
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }
    if (! $business) {
        test()->markTestSkipped('Sem business no banco.');
    }
    $user = User::where('business_id', $business->id)->first();
    if (! $user) {
        test()->markTestSkipped('Sem user no business.');
    }
    Permission::firstOrCreate(['name' => 'financeiro.dashboard.view', 'guard_name' => 'web']);
    if (! $user->hasPermissionTo('financeiro.dashboard.view')) {
        $user->givePermissionTo('financeiro.dashboard.view');
    }
    session([
        'user.business_id' => $business->id,
        'user.id'          => $user->id,
        'business.id'      => $business->id,
        'business.name'    => $business->name,
        'business'         => ['id' => $business->id, 'name' => $business->name, 'currency_symbol' => 'R$'],
        'is_admin'         => true,
    ]);

    return [$business, $user];
}

function uniConta(int $businessId): ?ContaBancaria
{
    return ContaBancaria::where('business_id', $businessId)->orderBy('id')->first();
}

function uniTitulo(int $businessId, int $userId, array $overrides = []): Titulo
{
    return Titulo::create(array_merge([
        'business_id'       => $businessId,
        'numero'            => 'UNI-'.bin2hex(random_bytes(5)),
        'tipo'              => 'receber',
        'status'            => 'aberto',
        'cliente_descricao' => 'GUARD cancelado/arquivados',
        'valor_total'       => 100.0,
        'valor_aberto'      => 100.0,
        'moeda'             => 'BRL',
        'emissao'           => UNI_DIA,
        'vencimento'        => UNI_DIA,
        'competencia_mes'   => UNI_COMP,
        'origem'            => 'manual',
        'created_by'        => $userId,
    ], $overrides));
}

function uniBaixa(Titulo $titulo, ContaBancaria $conta, float $valor, string $data, int $userId): TituloBaixa
{
    return TituloBaixa::create([
        'business_id'       => $titulo->business_id,
        'titulo_id'         => $titulo->id,
        'conta_bancaria_id' => $conta->id,
        'valor_baixa'       => $valor,
        'data_baixa'        => $data,
        'meio_pagamento'    => 'pix',
        'idempotency_key'   => (string) Str::uuid(),
        'created_by'        => $userId,
    ]);
}

function uniGet(User $user, string $qs = '')
{
    $response = test()->actingAs($user)->get('/financeiro/unificado'.$qs);
    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    return $response;
}

// ─────────────────────────────────────────────────────────────────────────
// C1 — baixa de título CANCELADO não soma em Recebido (o bug central).
// ─────────────────────────────────────────────────────────────────────────
it('C1: baixa de título cancelado NÃO soma em kpis.recebido', function () {
    [$business, $user] = uniBootstrap();
    $conta = uniConta($business->id);
    if (! $conta) {
        test()->markTestSkipped('Sem conta bancária no business.');
    }

    $valido = uniTitulo($business->id, $user->id, [
        'status' => 'quitado', 'valor_total' => 123.45, 'valor_aberto' => 0.0,
    ]);
    $cancelado = uniTitulo($business->id, $user->id, [
        'status' => 'cancelado', 'valor_total' => 999.99, 'valor_aberto' => 0.0,
    ]);
    uniBaixa($valido, $conta, 123.45, UNI_DIA, $user->id);
    uniBaixa($cancelado, $conta, 999.99, UNI_DIA, $user->id);

    try {
        $qs = '?data_campo=pagamento&data_inicio='.UNI_INI.'&data_fim='.UNI_FIM;
        uniGet($user, $qs)->assertInertia(function (AssertableInertia $page) {
            $kpis = $page->toArray()['props']['kpis'];
            // Só a baixa do título VÁLIDO entra: 123.45 (o cancelado 999.99 fica fora).
            expect((float) $kpis['recebido']['valor'])->toEqualWithDelta(123.45, 0.01);
            expect((int) $kpis['recebido']['qtd'])->toBe(1);
        });
    } finally {
        TituloBaixa::where('titulo_id', $valido->id)->forceDelete();
        TituloBaixa::where('titulo_id', $cancelado->id)->forceDelete();
        $valido->forceDelete();
        $cancelado->forceDelete();
    }
});

// ─────────────────────────────────────────────────────────────────────────
// A1/A2 — filtro Arquivados (cancelado escondido por padrão; só ele com flag).
// ─────────────────────────────────────────────────────────────────────────
it('A1: lista default esconde cancelado e mostra ativo', function () {
    [$business, $user] = uniBootstrap();
    $cancelado = uniTitulo($business->id, $user->id, ['status' => 'cancelado']);
    $ativo = uniTitulo($business->id, $user->id, ['status' => 'aberto']);

    try {
        $qs = '?data_inicio='.UNI_INI.'&data_fim='.UNI_FIM;
        uniGet($user, $qs)->assertInertia(function (AssertableInertia $page) use ($cancelado, $ativo) {
            $ids = collect($page->toArray()['props']['lancamentos'])->pluck('id');
            expect($ids)->not->toContain($cancelado->id);
            expect($ids)->toContain($ativo->id);
        });
    } finally {
        $cancelado->forceDelete();
        $ativo->forceDelete();
    }
});

it('A2: ?arquivados=1 mostra SÓ cancelado (ativo some)', function () {
    [$business, $user] = uniBootstrap();
    $cancelado = uniTitulo($business->id, $user->id, ['status' => 'cancelado']);
    $ativo = uniTitulo($business->id, $user->id, ['status' => 'aberto']);

    try {
        $qs = '?arquivados=1&data_inicio='.UNI_INI.'&data_fim='.UNI_FIM;
        uniGet($user, $qs)->assertInertia(function (AssertableInertia $page) use ($cancelado, $ativo) {
            $ids = collect($page->toArray()['props']['lancamentos'])->pluck('id');
            expect($ids)->toContain($cancelado->id);
            expect($ids)->not->toContain($ativo->id);
        });
    } finally {
        $cancelado->forceDelete();
        $ativo->forceDelete();
    }
});

// ─────────────────────────────────────────────────────────────────────────
// D1 — os cards seguem o campo de data escolhido (Wagner: "tem que acompanhar
// o campo de data selecionado"). a_receber muda entre vencimento × emissão.
// ─────────────────────────────────────────────────────────────────────────
it('D1: kpis.a_receber segue o data_campo (vencimento × emissao)', function () {
    [$business, $user] = uniBootstrap();
    // vencimento em 2099-03 mas emissão em 2099-09 (mês diferente de propósito).
    $titulo = uniTitulo($business->id, $user->id, [
        'status' => 'aberto', 'valor_total' => 200.0, 'valor_aberto' => 200.0,
        'vencimento' => UNI_DIA, 'emissao' => '2099-09-20', 'competencia_mes' => UNI_COMP,
    ]);

    try {
        $win = '&data_inicio='.UNI_INI.'&data_fim='.UNI_FIM;

        // Por VENCIMENTO (2099-03): conta no a_receber.
        uniGet($user, '?data_campo=vencimento'.$win)->assertInertia(function (AssertableInertia $page) {
            $kpis = $page->toArray()['props']['kpis'];
            expect((int) $kpis['a_receber']['qtd'])->toBe(1);
            expect((float) $kpis['a_receber']['valor'])->toEqualWithDelta(200.0, 0.01);
        });

        // Por EMISSÃO (a emissão é 2099-09): NÃO conta na janela 2099-03.
        uniGet($user, '?data_campo=emissao'.$win)->assertInertia(function (AssertableInertia $page) {
            $kpis = $page->toArray()['props']['kpis'];
            expect((int) $kpis['a_receber']['qtd'])->toBe(0);
            expect((float) $kpis['a_receber']['valor'])->toEqualWithDelta(0.0, 0.01);
        });
    } finally {
        $titulo->forceDelete();
    }
});

// ─────────────────────────────────────────────────────────────────────────
// S1 — shapeTitulo expõe os campos de paridade WR (Fase 1 + Fase 2).
// ─────────────────────────────────────────────────────────────────────────
it('S1: shapeTitulo expõe os campos de paridade WR', function () {
    [$business, $user] = uniBootstrap();
    $conta = uniConta($business->id);
    if (! $conta) {
        test()->markTestSkipped('Sem conta bancária no business.');
    }

    $numero = 'WR-'.bin2hex(random_bytes(4));
    $titulo = uniTitulo($business->id, $user->id, [
        'numero' => $numero, 'status' => 'quitado',
        'valor_total' => 300.0, 'valor_aberto' => 0.0,
        'parcela_numero' => 2, 'parcela_total' => 4,
        'metadata' => [
            'delphi_condicaopagto' => '30/60/90',
            'delphi_desconto'      => 10.5,
            'delphi_juros'         => 3.25,
            'delphi_codpedido'     => 'PED-7831',
        ],
    ]);
    uniBaixa($titulo, $conta, 300.0, UNI_DIA, $user->id);

    try {
        $qs = '?data_inicio='.UNI_INI.'&data_fim='.UNI_FIM;
        uniGet($user, $qs)->assertInertia(function (AssertableInertia $page) use ($titulo, $numero) {
            $row = collect($page->toArray()['props']['lancamentos'])->firstWhere('id', $titulo->id);
            expect($row)->not->toBeNull('título não apareceu na lista (janela/paginação)');

            // Chaves de paridade WR presentes no shape.
            foreach ([
                'numero', 'parcela', 'pedido', 'condicao_pagamento', 'desconto', 'juros',
                'documento', 'emissao', 'competencia_mes', 'vencimento', 'data_pagamento', 'valor_aberto',
            ] as $chave) {
                expect($row)->toHaveKey($chave);
            }

            // Valores derivados corretos.
            expect($row['numero'])->toBe($numero);
            expect($row['parcela'])->toBe('2/4');
            expect($row['pedido'])->toBe('PED-7831');
            expect($row['condicao_pagamento'])->toBe('30/60/90');
            expect((float) $row['desconto'])->toEqualWithDelta(10.5, 0.01);
            expect((float) $row['juros'])->toEqualWithDelta(3.25, 0.01);
            expect($row['competencia_mes'])->toBe(UNI_COMP);
            expect($row['data_pagamento'])->toBe(UNI_DIA);
        });
    } finally {
        TituloBaixa::where('titulo_id', $titulo->id)->forceDelete();
        $titulo->forceDelete();
    }
});

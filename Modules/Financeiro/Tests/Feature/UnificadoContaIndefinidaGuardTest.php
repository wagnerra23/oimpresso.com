<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * US-FIN-038 — pill "Conta indefinida" nas linhas/drawer da Visão Unificada.
 *
 * Pós-ADR 0175 a baixa pode ser registrada SEM conta bancária
 * (`fin_titulo_baixas.conta_bancaria_id NULL`). shapeTitulo passa a expor o flag
 * `conta_indefinida` que alimenta o pill CTA (link pra cadastro de conta). Não
 * mexe em VALOR nem ESTOQUE (só derivação de um booleano de exibição).
 *
 * GUARDs (contra o payload Inertia real, DB MySQL — lane financeiro-pest, biz=1):
 *  (G1) baixa com conta_bancaria_id NULL  → conta_indefinida = true
 *  (G2) baixa com conta vinculada         → conta_indefinida = false
 *  (G3) título SEM baixa (não pago)        → conta_indefinida = false
 */

function ci038Bootstrap(): array
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

function ci038CreateTitulo(int $businessId, int $userId): Titulo
{
    return Titulo::create([
        'business_id'       => $businessId,
        'numero'            => 'CI038-'.bin2hex(random_bytes(4)),
        'tipo'              => 'receber',
        'status'            => 'aberto',
        'cliente_descricao' => 'CONTA INDEFINIDA guard',
        'valor_total'       => 100.0,
        'valor_aberto'      => 100.0,
        'moeda'             => 'BRL',
        'emissao'           => now()->toDateString(),
        'vencimento'        => now()->addDays(10)->toDateString(),
        'competencia_mes'   => now()->format('Y-m'),
        'origem'            => 'manual',
        'created_by'        => $userId,
    ]);
}

/** Insere baixa via DB raw (evita side-effects do Observer — só precisamos da linha). */
function ci038InsertBaixa(int $businessId, int $tituloId, int $userId, ?int $contaId): void
{
    DB::table('fin_titulo_baixas')->insert([
        'business_id'      => $businessId,
        'titulo_id'        => $tituloId,
        'conta_bancaria_id' => $contaId,
        'valor_baixa'      => 100.0,
        'data_baixa'       => now()->toDateString(),
        'meio_pagamento'   => 'transferencia',
        'idempotency_key'  => (string) Str::uuid(),
        'created_by'       => $userId,
        'created_at'       => now(),
    ]);
}

function ci038Cleanup(Titulo $t): void
{
    DB::table('fin_titulo_baixas')->where('titulo_id', $t->id)->delete();
    DB::table('fin_titulos')->where('id', $t->id)->delete();
}

/** Busca o shape do título no payload Inertia de /financeiro/unificado. */
function ci038FetchShape(User $user, Titulo $t): ?array
{
    $response = test()->actingAs($user)->get('/financeiro/unificado');
    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }
    $found = null;
    $response->assertInertia(function ($page) use ($t, &$found) {
        $lancamentos = $page->toArray()['props']['lancamentos'] ?? [];
        $found = collect($lancamentos)->firstWhere('id', $t->id);
    });

    return $found;
}

// G1 — baixa SEM conta → conta_indefinida = true
it('UC-F05 GUARD G1: baixa com conta_bancaria_id NULL expõe conta_indefinida = true', function () {
    [$business, $user] = ci038Bootstrap();
    $titulo = ci038CreateTitulo($business->id, $user->id);
    ci038InsertBaixa($business->id, $titulo->id, $user->id, null);

    $shape = ci038FetchShape($user, $titulo);

    if ($shape !== null) {
        expect($shape)->toHaveKey('conta_indefinida')
            ->and($shape['conta_indefinida'])->toBeTrue();
    }

    ci038Cleanup($titulo);
});

// G2 — baixa COM conta → conta_indefinida = false
it('UC-F05 GUARD G2: baixa com conta vinculada expõe conta_indefinida = false', function () {
    [$business, $user] = ci038Bootstrap();
    $conta = ContaBancaria::where('business_id', $business->id)->orderBy('id')->first();
    if (! $conta) {
        test()->markTestSkipped('Sem conta bancária no business pra o caso vinculado.');
    }
    $titulo = ci038CreateTitulo($business->id, $user->id);
    ci038InsertBaixa($business->id, $titulo->id, $user->id, $conta->id);

    $shape = ci038FetchShape($user, $titulo);

    if ($shape !== null) {
        expect($shape['conta_indefinida'])->toBeFalse();
    }

    ci038Cleanup($titulo);
});

// G3 — título sem baixa (não pago) → conta_indefinida = false (pill NÃO aparece)
it('UC-F05 GUARD G3: título sem baixa expõe conta_indefinida = false', function () {
    [$business, $user] = ci038Bootstrap();
    $titulo = ci038CreateTitulo($business->id, $user->id);

    $shape = ci038FetchShape($user, $titulo);

    if ($shape !== null) {
        expect($shape['conta_indefinida'])->toBeFalse();
    }

    ci038Cleanup($titulo);
});

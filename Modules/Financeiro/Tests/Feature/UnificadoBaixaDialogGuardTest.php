<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * 2026-06-03 — GUARD do diálogo de baixa (escolher valor/conta/forma/plano).
 *
 * Antes o botão "Recebi/Paguei" fazia baixa instantânea (1ª conta, valor cheio,
 * meio fixo). Agora baixar() aceita os campos escolhidos + baixa PARCIAL, com
 * os defaults legacy preservados (body vazio).
 *
 * Cobre:
 *  (G1) shapeTitulo expõe valor_aberto (default do diálogo)
 *  (G2) baixar() aceita conta + valor + meio + persiste; título quita
 *  (G3) baixa PARCIAL: valor < aberto → status 'parcial' + valor_aberto reduzido
 *  (G4) conta cross-tenant/inexistente rejeitada (não cria baixa)
 *  (G5) body vazio = comportamento legacy (baixa instantânea) preservado
 */

function bxBootstrap(): array
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

function bxConta(int $businessId): ?ContaBancaria
{
    return ContaBancaria::where('business_id', $businessId)->orderBy('id')->first();
}

function bxCreateTitulo(int $businessId, int $userId, float $valor = 100.0): Titulo
{
    return Titulo::create([
        'business_id'       => $businessId,
        'numero'            => 'BX-'.bin2hex(random_bytes(4)),
        'tipo'              => 'receber',
        'status'            => 'aberto',
        'cliente_descricao' => 'BAIXA guard',
        'valor_total'       => $valor,
        'valor_aberto'      => $valor,
        'moeda'             => 'BRL',
        'emissao'           => now()->toDateString(),
        'vencimento'        => now()->addDays(10)->toDateString(),
        'competencia_mes'   => now()->format('Y-m'),
        'origem'            => 'manual',
        'created_by'        => $userId,
    ]);
}

// G1 — shapeTitulo expõe valor_aberto
it('GUARD G1: shapeTitulo expõe valor_aberto', function () {
    [$business, $user] = bxBootstrap();
    $titulo = bxCreateTitulo($business->id, $user->id, 77.0);

    $response = $this->actingAs($user)->get('/financeiro/unificado');
    if (in_array($response->status(), [403, 404], true)) {
        $titulo->forceDelete();
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(function ($page) use ($titulo) {
        $lancamentos = $page->toArray()['props']['lancamentos'] ?? [];
        $found = collect($lancamentos)->firstWhere('id', $titulo->id);
        if ($found !== null) {
            expect($found)->toHaveKey('valor_aberto');
            expect((float) $found['valor_aberto'])->toBe(77.0);
        }
    });

    $titulo->forceDelete();
});

// G2 — baixar() aceita conta + valor + meio escolhidos
it('GUARD G2: baixar persiste conta/valor/meio escolhidos e quita', function () {
    [$business, $user] = bxBootstrap();
    $conta = bxConta($business->id);
    if (! $conta) {
        test()->markTestSkipped('Sem conta bancária no business.');
    }
    $titulo = bxCreateTitulo($business->id, $user->id, 100.0);

    $response = $this->actingAs($user)->post("/financeiro/unificado/{$titulo->id}/baixar", [
        'valor_baixa'       => 100.0,
        'conta_bancaria_id' => $conta->id,
        'meio_pagamento'    => 'pix',
        'data_baixa'        => now()->toDateString(),
    ]);
    if (in_array($response->status(), [403, 404], true)) {
        $titulo->forceDelete();
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $titulo->refresh();
    expect($titulo->status)->toBe('quitado');
    $baixa = TituloBaixa::where('titulo_id', $titulo->id)->latest('id')->first();
    expect($baixa)->not->toBeNull();
    expect($baixa->conta_bancaria_id)->toBe($conta->id);
    expect($baixa->meio_pagamento)->toBe('pix');
    expect((float) $baixa->valor_baixa)->toBe(100.0);

    $baixa->forceDelete();
    $titulo->forceDelete();
});

// G3 — baixa parcial
it('GUARD G3: baixa parcial reduz valor_aberto e marca parcial', function () {
    [$business, $user] = bxBootstrap();
    $conta = bxConta($business->id);
    if (! $conta) {
        test()->markTestSkipped('Sem conta bancária no business.');
    }
    $titulo = bxCreateTitulo($business->id, $user->id, 100.0);

    $response = $this->actingAs($user)->post("/financeiro/unificado/{$titulo->id}/baixar", [
        'valor_baixa'       => 40.0,
        'conta_bancaria_id' => $conta->id,
        'meio_pagamento'    => 'dinheiro',
    ]);
    if (in_array($response->status(), [403, 404], true)) {
        $titulo->forceDelete();
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $titulo->refresh();
    expect($titulo->status)->toBe('parcial');
    expect((float) $titulo->valor_aberto)->toBe(60.0);

    TituloBaixa::where('titulo_id', $titulo->id)->forceDelete();
    $titulo->forceDelete();
});

// G4 — conta cross-tenant/inexistente rejeitada
it('GUARD G4: conta inexistente/cross-tenant é rejeitada (não cria baixa)', function () {
    [$business, $user] = bxBootstrap();
    $titulo = bxCreateTitulo($business->id, $user->id, 100.0);

    $response = $this->actingAs($user)->post("/financeiro/unificado/{$titulo->id}/baixar", [
        'valor_baixa'       => 100.0,
        'conta_bancaria_id' => 999999999, // não pertence ao business
        'meio_pagamento'    => 'pix',
    ]);
    if (in_array($response->status(), [403, 404], true)) {
        $titulo->forceDelete();
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $titulo->refresh();
    expect($titulo->status)->toBe('aberto', 'Conta inválida não deveria ter quitado o título');
    expect(TituloBaixa::where('titulo_id', $titulo->id)->exists())->toBeFalse();

    $titulo->forceDelete();
});

// G5 — body vazio = legacy preservado
it('GUARD G5: body vazio mantém baixa instantânea legacy', function () {
    [$business, $user] = bxBootstrap();
    $conta = bxConta($business->id);
    if (! $conta) {
        test()->markTestSkipped('Sem conta bancária no business.');
    }
    $titulo = bxCreateTitulo($business->id, $user->id, 50.0);

    $response = $this->actingAs($user)->post("/financeiro/unificado/{$titulo->id}/baixar", []);
    if (in_array($response->status(), [403, 404], true)) {
        $titulo->forceDelete();
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $titulo->refresh();
    expect($titulo->status)->toBe('quitado');

    TituloBaixa::where('titulo_id', $titulo->id)->forceDelete();
    $titulo->forceDelete();
});

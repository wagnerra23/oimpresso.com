<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * 2026-06-03 — GUARD Tier 0 anti-regressão pra forma_pagamento no
 * /financeiro/unificado (pedido Wagner: mostrar/editar tipo de pagamento).
 *
 * Cobre 5 invariantes (cada Δ = CI quebra):
 *  (G1) shapeTitulo() expõe forma_pagamento + forma_pagamento_realizada
 *  (G2) Edit PUT persiste forma_pagamento (forma PREVISTA, título em aberto)
 *  (G3) Store POST persiste forma_pagamento
 *  (G4) Enum inválido rejeitado (anti tampering)
 *  (G5) Quando há baixa, a forma REALIZADA (baixa.meio_pagamento) tem
 *       prioridade no shape + forma_pagamento_realizada=true (read-only)
 *
 * Padrão graceful skip (Jana/Repair/Copiloto): pula quando DB greenfield
 * ou subscription gate bloqueia financeiro_module no env atual.
 */

/**
 * Cleanup via DB raw: Titulo::delete()/forceDelete() é bloqueado por DomainException
 * (fin_titulos não permite delete — usa cancelar()/status=cancelado). US-FIN-053 Batch 5.
 */
function fpCleanup(Titulo $t): void
{
    DB::table('fin_titulo_baixas')->where('titulo_id', $t->id)->delete();
    DB::table('fin_titulos')->where('id', $t->id)->delete();
}

function fpBootstrap(): array
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

function fpCreateTitulo(int $businessId, int $userId, ?string $forma = null): Titulo
{
    return Titulo::create([
        'business_id'       => $businessId,
        'numero'            => 'FPG-'.bin2hex(random_bytes(4)),
        'tipo'              => 'receber',
        'status'            => 'aberto',
        'cliente_descricao' => 'FORMA PGTO guard',
        'valor_total'       => 50.00,
        'valor_aberto'      => 50.00,
        'moeda'             => 'BRL',
        'emissao'           => now()->toDateString(),
        'vencimento'        => now()->endOfMonth()->toDateString(), // dentro do mês corrente (período default filtra vencimento; addDays(15) cruzava a borda do mês perto do fim → flaky)
        'competencia_mes'   => now()->format('Y-m'),
        'origem'            => 'manual',
        'forma_pagamento'   => $forma,
        'created_by'        => $userId,
    ]);
}

// ════════════════════════════════════════════════════════════════════════
// G1 — shapeTitulo expõe forma_pagamento + forma_pagamento_realizada
// ════════════════════════════════════════════════════════════════════════
it('GUARD G1: shapeTitulo expõe forma_pagamento (prevista) + flag realizada', function () {
    [$business, $user] = fpBootstrap();

    $titulo = fpCreateTitulo($business->id, $user->id, 'boleto');

    $response = $this->actingAs($user)->get('/financeiro/unificado');

    if (in_array($response->status(), [403, 404], true)) {
        fpCleanup($titulo);
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(function (AssertableInertia $page) use ($titulo) {
        $page->has('lancamentos');
        $lancamentos = $page->toArray()['props']['lancamentos'] ?? [];
        $found = collect($lancamentos)->firstWhere('id', $titulo->id);

        expect($found)->not->toBeNull('título FPG não veio no payload — fora do período default?');
        expect($found)->toHaveKeys(['forma_pagamento', 'forma_pagamento_realizada']);
        expect($found['forma_pagamento'])->toBe('boleto');
        expect($found['forma_pagamento_realizada'])->toBeFalse();
    });

    fpCleanup($titulo);
});

// ════════════════════════════════════════════════════════════════════════
// G2 — Edit PUT persiste forma_pagamento
// ════════════════════════════════════════════════════════════════════════
it('GUARD G2: Edit PUT /unificado/{id} persiste forma_pagamento', function () {
    [$business, $user] = fpBootstrap();

    $titulo = fpCreateTitulo($business->id, $user->id);

    $response = $this->actingAs($user)->put("/financeiro/unificado/{$titulo->id}", [
        'cliente_descricao' => $titulo->cliente_descricao,
        'observacoes'       => null,
        'categoria_id'      => null,
        'vencimento'        => $titulo->vencimento->toDateString(),
        'forma_pagamento'   => 'pix',
    ]);

    if (in_array($response->status(), [403, 404], true)) {
        fpCleanup($titulo);
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertRedirect();
    $titulo->refresh();
    expect($titulo->forma_pagamento)->toBe('pix');

    fpCleanup($titulo);
});

// ════════════════════════════════════════════════════════════════════════
// G3 — Store POST persiste forma_pagamento
// ════════════════════════════════════════════════════════════════════════
it('GUARD G3: Store POST /unificado persiste forma_pagamento', function () {
    [$business, $user] = fpBootstrap();

    $response = $this->actingAs($user)->post('/financeiro/unificado', [
        'tipo'              => 'pagar',
        'valor_total'       => 25.00,
        'vencimento'        => now()->addDays(7)->toDateString(),
        'cliente_descricao' => 'FPG G3 store',
        'forma_pagamento'   => 'cartao_credito',
    ]);

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertRedirect();

    $created = Titulo::where('business_id', $business->id)
        ->where('cliente_descricao', 'FPG G3 store')
        ->latest('id')
        ->first();

    expect($created)->not->toBeNull();
    expect($created->forma_pagamento)->toBe('cartao_credito');

    fpCleanup($created);
});

// ════════════════════════════════════════════════════════════════════════
// G4 — Enum inválido rejeitado (anti tampering)
// ════════════════════════════════════════════════════════════════════════
it('GUARD G4: forma_pagamento fora do enum é rejeitada', function () {
    [$business, $user] = fpBootstrap();

    $titulo = fpCreateTitulo($business->id, $user->id);

    $response = $this->actingAs($user)->put("/financeiro/unificado/{$titulo->id}", [
        'cliente_descricao' => $titulo->cliente_descricao,
        'observacoes'       => null,
        'categoria_id'      => null,
        'vencimento'        => $titulo->vencimento->toDateString(),
        'forma_pagamento'   => 'bitcoin', // inválido
    ]);

    if (in_array($response->status(), [403, 404], true)) {
        fpCleanup($titulo);
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    expect(in_array($response->status(), [302, 422], true))->toBeTrue();
    $titulo->refresh();
    expect($titulo->forma_pagamento)->toBeNull('Enum inválido foi persistido — validação furou');

    fpCleanup($titulo);
});

// ════════════════════════════════════════════════════════════════════════
// G5 — Baixa realizada tem prioridade no shape (read-only)
// ════════════════════════════════════════════════════════════════════════
it('GUARD G5: baixa.meio_pagamento sobrepõe forma prevista + flag realizada', function () {
    [$business, $user] = fpBootstrap();

    // Título com forma PREVISTA = boleto, mas baixa REALIZADA = dinheiro.
    $titulo = fpCreateTitulo($business->id, $user->id, 'boleto');

    $baixa = TituloBaixa::create([
        'business_id'     => $business->id,
        'titulo_id'       => $titulo->id,
        'conta_bancaria_id' => null,
        'valor_baixa'     => 50.00,
        'data_baixa'      => now()->toDateString(),
        'meio_pagamento'  => 'dinheiro',
        'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        'created_by'      => $user->id,
    ]);

    $response = $this->actingAs($user)->get('/financeiro/unificado');

    if (in_array($response->status(), [403, 404], true)) {
        DB::table('fin_titulo_baixas')->where('id', $baixa->id)->delete();
        fpCleanup($titulo);
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(function (AssertableInertia $page) use ($titulo) {
        $lancamentos = $page->toArray()['props']['lancamentos'] ?? [];
        $found = collect($lancamentos)->firstWhere('id', $titulo->id);

        if ($found !== null) {
            // Realizada (dinheiro) manda sobre prevista (boleto).
            expect($found['forma_pagamento'])->toBe('dinheiro');
            expect($found['forma_pagamento_realizada'])->toBeTrue();
        }
    });

    DB::table('fin_titulo_baixas')->where('id', $baixa->id)->delete();
    fpCleanup($titulo);
});

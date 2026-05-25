<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Modules\Financeiro\Models\PlanoConta;
use Modules\Financeiro\Models\Titulo;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * PR C (2026-05-25) — GUARD Tier 0 anti-regressão pra plano_conta_id no
 * /financeiro/unificado (Ondas 24+25 — US-FIN-021 completa).
 *
 * G1 da auditoria pós Ondas 24/25:
 *   "Charter `/unificado` v8 não tem Pest GUARD para campo plano_conta_id
 *    no Edit/Create. US-FIN-027 prevê GUARD genérico mas falta caso específico."
 *
 * Cobre 7 invariantes anti-regressão (cada Δ = CI quebra):
 *  (G1) shape Inertia expõe planosConta como prop
 *  (G2) shapeTitulo() expõe plano_conta_id + plano_conta_codigo + plano_conta_nome
 *  (G3) eager-load planoConta preserva (não vira N+1 silencioso)
 *  (G4) Edit aceita plano_conta_id no payload e persiste
 *  (G5) Edit revalida coerência tipo↔plano (assertPlanoCoerente)
 *  (G6) Store aceita plano_conta_id e persiste
 *  (G7) PlanoConta cross-tenant rejeitado em ambos (Update + Store)
 *
 * Padrão graceful skip Jana/Repair/Copiloto: pula quando DB greenfield
 * ou subscription gate bloqueia financeiro_module no env atual.
 */

function guardBootstrap(): array
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

function guardCreatePlano(int $businessId, string $codigo, string $tipo): PlanoConta
{
    return PlanoConta::firstOrCreate(
        ['business_id' => $businessId, 'codigo' => $codigo],
        [
            'nome'              => "GUARD {$codigo} {$tipo}",
            'tipo'              => $tipo,
            'nivel'             => 4,
            'natureza'          => in_array($tipo, ['receita', 'passivo', 'patrimonio'], true) ? 'credito' : 'debito',
            'aceita_lancamento' => true,
            'protegido'         => false,
            'ativo'             => true,
        ]
    );
}

function guardCreateTitulo(int $businessId, int $userId, string $tipo, ?int $planoId = null): Titulo
{
    return Titulo::create([
        'business_id'       => $businessId,
        'numero'            => 'GUARD-'.bin2hex(random_bytes(4)),
        'tipo'              => $tipo,
        'status'            => 'aberto',
        'cliente_descricao' => 'GUARD test',
        'valor_total'       => 50.00,
        'valor_aberto'      => 50.00,
        'moeda'             => 'BRL',
        'emissao'           => now()->toDateString(),
        'vencimento'        => now()->addDays(15)->toDateString(),
        'competencia_mes'   => now()->format('Y-m'),
        'origem'            => 'manual',
        'plano_conta_id'    => $planoId,
        'created_by'        => $userId,
    ]);
}

// ════════════════════════════════════════════════════════════════════════
// G1 — shape Inertia expõe planosConta como prop (não pode sumir)
// ════════════════════════════════════════════════════════════════════════
it('GUARD G1: Inertia prop planosConta exposta na rota /unificado', function () {
    $user = guardBootstrap()[1];

    $response = $this->actingAs($user)->get('/financeiro/unificado');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Financeiro/Unificado/Index')
        ->has('planosConta')
    );
});

// ════════════════════════════════════════════════════════════════════════
// G2 — shapeTitulo expõe plano_conta_id + plano_conta_codigo + plano_conta_nome
// ════════════════════════════════════════════════════════════════════════
it('GUARD G2: shapeTitulo expõe 3 campos plano_conta_* em cada lançamento', function () {
    [$business, $user] = guardBootstrap();

    $plano = guardCreatePlano($business->id, '3.1.01.G2', 'receita');
    $titulo = guardCreateTitulo($business->id, $user->id, 'receber', $plano->id);

    $response = $this->actingAs($user)->get('/financeiro/unificado');

    if (in_array($response->status(), [403, 404], true)) {
        $titulo->forceDelete();
        $plano->forceDelete();
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(function (AssertableInertia $page) use ($titulo) {
        $page->has('lancamentos');
        $lancamentos = $page->toArray()['props']['lancamentos'] ?? [];
        $found = collect($lancamentos)->firstWhere('id', $titulo->id);

        expect($found)->not->toBeNull('título GUARD não veio no payload — pode ser que esteja fora do período default');
        expect($found)->toHaveKeys(['plano_conta_id', 'plano_conta_codigo', 'plano_conta_nome']);
        expect($found['plano_conta_id'])->toBe($titulo->plano_conta_id);
    });

    $titulo->forceDelete();
    $plano->forceDelete();
});

// ════════════════════════════════════════════════════════════════════════
// G3 — Eager-load planoConta preserva (anti N+1 silencioso)
// ════════════════════════════════════════════════════════════════════════
it('GUARD G3: relation planoConta é eager-loaded (sem N+1 ao iterar)', function () {
    [$business, $user] = guardBootstrap();

    $plano = guardCreatePlano($business->id, '3.1.01.G3', 'receita');
    $titulos = collect(range(1, 3))->map(fn () => guardCreateTitulo($business->id, $user->id, 'receber', $plano->id));

    // Hit a rota e conta queries de planoConta — eager deve resultar em 1 query, não N
    DB::enableQueryLog();
    $response = $this->actingAs($user)->get('/financeiro/unificado');
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $titulos->each->forceDelete();
    $plano->forceDelete();

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $planoQueries = collect($queries)->filter(fn ($q) => str_contains($q['query'], 'fin_planos_conta'));
    // Eager-load = 1 query com whereIn(...). Sem eager = N queries (uma por titulo).
    // Tolerância: aceita até 2 queries (1 da index + 1 do controller; lista de filtros).
    expect($planoQueries->count())->toBeLessThanOrEqual(2, "Eager-load quebrou — N+1 em planoConta (queries={$planoQueries->count()})");
});

// ════════════════════════════════════════════════════════════════════════
// G4 — Edit aceita plano_conta_id e persiste
// ════════════════════════════════════════════════════════════════════════
it('GUARD G4: Edit PUT /unificado/{id} persiste plano_conta_id', function () {
    [$business, $user] = guardBootstrap();

    $plano = guardCreatePlano($business->id, '3.1.01.G4', 'receita');
    $titulo = guardCreateTitulo($business->id, $user->id, 'receber');

    $response = $this->actingAs($user)->put("/financeiro/unificado/{$titulo->id}", [
        'cliente_descricao' => $titulo->cliente_descricao,
        'observacoes'       => null,
        'categoria_id'      => null,
        'plano_conta_id'    => $plano->id,
        'vencimento'        => $titulo->vencimento->toDateString(),
    ]);

    if (in_array($response->status(), [403, 404], true)) {
        $titulo->forceDelete();
        $plano->forceDelete();
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertRedirect();
    $titulo->refresh();
    expect($titulo->plano_conta_id)->toBe($plano->id);

    $titulo->forceDelete();
    $plano->forceDelete();
});

// ════════════════════════════════════════════════════════════════════════
// G5 — Edit assertPlanoCoerente rejeita tipo incompatível
// ════════════════════════════════════════════════════════════════════════
it('GUARD G5: Edit rejeita plano de tipo incompatível com título (receber + despesa)', function () {
    [$business, $user] = guardBootstrap();

    $planoDespesa = guardCreatePlano($business->id, '5.1.99.G5', 'despesa');
    $titulo = guardCreateTitulo($business->id, $user->id, 'receber');

    $response = $this->actingAs($user)->put("/financeiro/unificado/{$titulo->id}", [
        'cliente_descricao' => $titulo->cliente_descricao,
        'observacoes'       => null,
        'categoria_id'      => null,
        'plano_conta_id'    => $planoDespesa->id,
        'vencimento'        => $titulo->vencimento->toDateString(),
    ]);

    if (in_array($response->status(), [403, 404], true)) {
        $titulo->forceDelete();
        $planoDespesa->forceDelete();
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    expect($response->status())->toBe(422);
    $titulo->refresh();
    expect($titulo->plano_conta_id)->toBeNull();

    $titulo->forceDelete();
    $planoDespesa->forceDelete();
});

// ════════════════════════════════════════════════════════════════════════
// G6 — Store aceita plano_conta_id e persiste
// ════════════════════════════════════════════════════════════════════════
it('GUARD G6: Store POST /unificado persiste plano_conta_id no novo título', function () {
    [$business, $user] = guardBootstrap();

    $plano = guardCreatePlano($business->id, '5.1.99.G6', 'despesa');

    $response = $this->actingAs($user)->post('/financeiro/unificado', [
        'tipo'              => 'pagar',
        'valor_total'       => 25.00,
        'vencimento'        => now()->addDays(7)->toDateString(),
        'cliente_descricao' => 'GUARD G6 store',
        'plano_conta_id'    => $plano->id,
    ]);

    if (in_array($response->status(), [403, 404], true)) {
        $plano->forceDelete();
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertRedirect();

    $created = Titulo::where('business_id', $business->id)
        ->where('cliente_descricao', 'GUARD G6 store')
        ->latest('id')
        ->first();

    expect($created)->not->toBeNull();
    expect($created->plano_conta_id)->toBe($plano->id);

    $created->forceDelete();
    $plano->forceDelete();
});

// ════════════════════════════════════════════════════════════════════════
// G7 — Plano de outro business é rejeitado em ambos (Update + Store)
// ════════════════════════════════════════════════════════════════════════
it('GUARD G7: PlanoConta cross-tenant rejeitado em Update + Store', function () {
    [$business, $user] = guardBootstrap();

    // Cria business B ficcional.
    $otherBizId = (int) (DB::table('business')->max('id') ?? 0) + 88888;
    DB::table('business')->insert([
        'id'         => $otherBizId,
        'name'       => 'GUARD-G7-OTHER-BIZ',
        'currency_id' => 1,
        'start_date' => now()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $planoCrossTenant = guardCreatePlano($otherBizId, '3.1.01.XT7', 'receita');
    $titulo = guardCreateTitulo($business->id, $user->id, 'receber');

    // Update — deve rejeitar (302 com errors OU 422)
    $resp1 = $this->actingAs($user)->put("/financeiro/unificado/{$titulo->id}", [
        'cliente_descricao' => $titulo->cliente_descricao,
        'observacoes'       => null,
        'categoria_id'      => null,
        'plano_conta_id'    => $planoCrossTenant->id,
        'vencimento'        => $titulo->vencimento->toDateString(),
    ]);

    // Store — deve rejeitar idem
    $resp2 = $this->actingAs($user)->post('/financeiro/unificado', [
        'tipo'              => 'receber',
        'valor_total'       => 30.00,
        'vencimento'        => now()->addDays(7)->toDateString(),
        'cliente_descricao' => 'GUARD G7 store cross-tenant',
        'plano_conta_id'    => $planoCrossTenant->id,
    ]);

    // Cleanup antes do skip-check
    $titulo->refresh();
    expect($titulo->plano_conta_id)->toBeNull('Update aceitou plano cross-tenant — VIOLAÇÃO TIER 0');

    $created = Titulo::where('business_id', $business->id)
        ->where('cliente_descricao', 'GUARD G7 store cross-tenant')
        ->exists();
    expect($created)->toBeFalse('Store aceitou plano cross-tenant — VIOLAÇÃO TIER 0');

    // Cleanup
    $titulo->forceDelete();
    $planoCrossTenant->forceDelete();
    DB::table('business')->where('id', $otherBizId)->delete();

    if (in_array($resp1->status(), [403, 404], true) || in_array($resp2->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    expect(in_array($resp1->status(), [302, 422], true))->toBeTrue();
    expect(in_array($resp2->status(), [302, 422], true))->toBeTrue();
});

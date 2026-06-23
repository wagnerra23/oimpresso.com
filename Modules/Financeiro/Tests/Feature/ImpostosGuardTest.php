<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Modules\Financeiro\Models\Titulo;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Impostos & obrigações (PACOTE-FINANCEIRO-F2 PR-2 · 2026-06-10).
 *
 * GUARDs:
 *  (I1) GET /financeiro/impostos responde 200 com shape Inertia (kpis/guias/calendario/sem_nf)
 *  (I2) "Lançar a pagar" cria título payable P-NNNNN com metadata.guia da competência
 *  (I3) lançamento é IDEMPOTENTE — re-POST não duplica (metadata.guia única por business)
 *  (I4) Tier 0 — guia lançada em outro business não vaza (ADR 0093)
 *  (I5) competência inválida é rejeitada (422)
 *
 * Skip gracioso quando DB greenfield ou module gate bloqueia (padrão GUARDs Financeiro).
 * Limpeza via DB raw (fin_titulos bloqueia delete por DomainException).
 */

function impostosBootstrap(): User
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

    return $user;
}

function impostosCleanup(int ...$tituloIds): void
{
    if (empty($tituloIds)) {
        return;
    }
    DB::table('fin_titulo_baixas')->whereIn('titulo_id', $tituloIds)->delete();
    DB::table('fin_titulos')->whereIn('id', $tituloIds)->delete();
}

function impostosGet(User $user)
{
    $response = test()->actingAs($user)->get('/financeiro/impostos');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    return $response;
}

// Garante receita recebida na competência (senão lancar() devolve "nada a lançar").
function impostosSeedReceita(int $businessId, int $userId, string $comp): array
{
    $dia = $comp.'-05';
    $titulo = Titulo::create([
        'business_id'       => $businessId,
        'numero'            => 'IMP-'.bin2hex(random_bytes(5)),
        'tipo'              => 'receber',
        'status'            => 'quitado',
        'cliente_descricao' => 'GUARD impostos — receita',
        'valor_total'       => 1000.0,
        'valor_aberto'      => 0.0,
        'moeda'             => 'BRL',
        'emissao'           => $dia,
        'vencimento'        => $dia,
        'competencia_mes'   => $comp,
        'origem'            => 'manual',
        'created_by'        => $userId,
    ]);
    // Baixa imutável: sem updated_at; meio_pagamento NOT NULL; idempotency_key UNIQUE/biz.
    $baixaId = (int) DB::table('fin_titulo_baixas')->insertGetId([
        'business_id'     => $businessId,
        'titulo_id'       => $titulo->id,
        'valor_baixa'     => 1000.0,
        'juros'           => 0,
        'multa'           => 0,
        'desconto'        => 0,
        'data_baixa'      => $dia,
        'meio_pagamento'  => 'pix',
        'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        'created_by'      => $userId,
        'created_at'      => now(),
    ]);

    return [$titulo->id, $baixaId];
}

it('UC-IMP-01 · GUARD I1: GET /financeiro/impostos responde com o shape da tela', function () {
    $user = impostosBootstrap();

    impostosGet($user)->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Financeiro/Impostos/Index')
        ->has('kpis.a_recolher.valor')
        ->has('kpis.pct_com_nf')
        ->has('guias')
        ->has('calendario')
        ->has('sem_nf')
        ->has('receita_recebida')
    );
});

it('UC-IMP-02 · UC-IMP-03 · GUARD I2+I3: lançar a pagar cria título payable idempotente (metadata.guia)', function () {
    $user = impostosBootstrap();
    $bizId = (int) $user->business_id;
    $comp = now()->format('Y-m');
    [$receitaId] = impostosSeedReceita($bizId, $user->id, $comp);

    $criados = [];
    try {
        $r1 = test()->actingAs($user)->post('/financeiro/impostos/lancar', ['competencia' => $comp]);
        if (in_array($r1->status(), [403, 404], true)) {
            test()->markTestSkipped('Module gate bloqueia neste env.');
        }
        $r1->assertRedirect();

        $guia = Titulo::where('business_id', $bizId)
            ->where('tipo', 'pagar')
            ->where('metadata->guia', "das-{$comp}")
            ->first();
        expect($guia)->not->toBeNull();
        $criados[] = $guia->id;
        expect($guia->numero)->toMatch('/^P-\d{5}$/');
        expect((float) $guia->valor_total)->toBeGreaterThan(0.0);
        expect($guia->status)->toBe('aberto');

        // I3 — idempotência: re-POST não duplica.
        test()->actingAs($user)->post('/financeiro/impostos/lancar', ['competencia' => $comp])->assertRedirect();
        $qtd = Titulo::where('business_id', $bizId)
            ->where('tipo', 'pagar')
            ->where('metadata->guia', "das-{$comp}")
            ->count();
        expect($qtd)->toBe(1);
    } finally {
        impostosCleanup(...array_merge([$receitaId], $criados));
    }
});

it('UC-IMP-06 · GUARD I4: Tier 0 — guia de outro business não vaza na tela (ADR 0093)', function () {
    $user = impostosBootstrap();

    $outroBiz = Business::where('id', '!=', $user->business_id)->first();
    if (! $outroBiz) {
        test()->markTestSkipped('Só 1 business no banco — cross-tenant não testável aqui.');
    }

    $alheio = Titulo::create([
        'business_id'       => (int) $outroBiz->id,
        'numero'            => 'IMP-'.bin2hex(random_bytes(5)),
        'tipo'              => 'pagar',
        'status'            => 'aberto',
        'cliente_descricao' => 'FGTS · folha (GUARD cross-tenant)',
        'valor_total'       => 412.8,
        'valor_aberto'      => 412.8,
        'moeda'             => 'BRL',
        'emissao'           => now()->toDateString(),
        'vencimento'        => now()->addDays(5)->toDateString(),
        'competencia_mes'   => now()->format('Y-m'),
        'origem'            => 'manual',
        'created_by'        => $user->id,
    ]);

    try {
        impostosGet($user)->assertInertia(function (AssertableInertia $page) use ($alheio) {
            $guias = collect($page->toArray()['props']['guias'] ?? []);
            expect($guias->pluck('lanc'))->not->toContain($alheio->numero);
        });
    } finally {
        impostosCleanup($alheio->id);
    }
});

it('GUARD I5: competência inválida é rejeitada', function () {
    $user = impostosBootstrap();

    $r = test()->actingAs($user)->post('/financeiro/impostos/lancar', ['competencia' => '2026-13-01']);
    if (in_array($r->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }
    $r->assertSessionHasErrors('competencia');
});

<?php

declare(strict_types=1);
// @covers-us US-FIN-062

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Modules\Financeiro\Models\Titulo;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Impostos & obrigações — CONTRATO (MV batch 2026-07-06, piloto Módulo Vivo).
 *
 * Complementa ImpostosGuardTest.php (I1-I5) fechando os UCs que lá tinham só
 * cobertura de shape (I1) ou eram manuais (UC-IMP-04/05). Toda asserção deriva
 * do charter/US-FIN-062 (não do código): valores são recomputados de forma
 * independente do Controller pra que um bug no Controller QUEBRE o teste.
 *
 *  (C1) UC-IMP-08 — kpis.a_recolher.valor == soma das guias abertas (calendario)
 *  (C2) UC-IMP-09 — valor recalculado server-side; client não injeta valor/venc
 *  (C3) UC-IMP-10 — costura NF↔título: sem_nf/pct_com_nf derivados de metadata
 *  (C4) UC-IMP-11 — guia quitada sai de a_recolher e do calendario
 *
 * Padrão dos GUARDs Financeiro: skip gracioso (greenfield/module gate) + limpeza
 * via DB raw (fin_titulos bloqueia hard delete por DomainException). biz=1 (ADR 0101).
 */

function impContratoBootstrap(): User
{
    // Tenant canônico via trait WithSeededTenant (biz=1, skip acionável se seed ausente) —
    // NUNCA resolução crua de tenant em teste novo (catraca foundation-ratchet n_business_first).
    try {
        $business = test()->seededTenant();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
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

function impContratoCleanup(int ...$tituloIds): void
{
    if (empty($tituloIds)) {
        return;
    }
    DB::table('fin_titulo_baixas')->whereIn('titulo_id', $tituloIds)->delete();
    DB::table('fin_titulos')->whereIn('id', $tituloIds)->delete();
}

function impContratoGet(User $user)
{
    // Lane backend do financeiro-pest não builda o JS → ensure_pages_exist dá
    // falso-negativo mesmo com Index.tsx no repo (mesmo motivo do I1). Desligamos
    // só a checagem de existência de arquivo; component()+props seguem validando.
    config(['inertia.testing.ensure_pages_exist' => false]);

    $response = test()->actingAs($user)->get('/financeiro/impostos');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    return $response;
}

/**
 * Seed de 1 recebível quitado com baixa no dia informado (gera receita recebida
 * na competência → DAS estimado > 0). $comp = 'YYYY-MM'. Devolve [tituloId, baixaId].
 */
function impContratoSeedRecebido(int $businessId, int $userId, string $comp, float $valor = 1000.0): array
{
    $dia = $comp.'-05';
    $titulo = Titulo::create([
        'business_id'       => $businessId,
        'numero'            => 'IMPC-'.bin2hex(random_bytes(5)),
        'tipo'              => 'receber',
        'status'            => 'quitado',
        'cliente_descricao' => 'CONTRATO impostos — recebido',
        'valor_total'       => $valor,
        'valor_aberto'      => 0.0,
        'moeda'             => 'BRL',
        'emissao'           => $dia,
        'vencimento'        => $dia,
        'competencia_mes'   => $comp,
        'origem'            => 'manual',
        'created_by'        => $userId,
    ]);
    $baixaId = (int) DB::table('fin_titulo_baixas')->insertGetId([
        'business_id'     => $businessId,
        'titulo_id'       => $titulo->id,
        'valor_baixa'     => $valor,
        'juros'           => 0,
        'multa'           => 0,
        'desconto'        => 0,
        'data_baixa'      => $dia,
        'meio_pagamento'  => 'pix',
        'idempotency_key' => (string) Str::uuid(),
        'created_by'      => $userId,
        'created_at'      => now(),
    ]);

    return [$titulo->id, $baixaId];
}

// ── C1 · UC-IMP-08 ──────────────────────────────────────────────────────────
it('UC-IMP-08 · C1: kpis.a_recolher.valor é a soma das guias abertas (não só shape)', function () {
    $user = impContratoBootstrap();
    $bizId = (int) $user->business_id;
    $comp = now()->format('Y-m');

    // Receita recebida garante que o DAS estimado (guia aberta) exista no mês.
    [$recId, $baixaId] = impContratoSeedRecebido($bizId, $user->id, $comp, 1000.0);

    try {
        impContratoGet($user)->assertInertia(function (AssertableInertia $page) {
            $props = $page->toArray()['props'];
            $abertas = collect($props['calendario'] ?? []);

            // O KPI é DERIVADO das abertas — recompomos aqui pra pegar drift do Controller.
            $somaAbertas = round((float) $abertas->sum('valor'), 2);
            $kpiValor = round((float) data_get($props, 'kpis.a_recolher.valor'), 2);
            $kpiQtd = (int) data_get($props, 'kpis.a_recolher.qtd');

            expect($abertas->count())->toBeGreaterThan(0); // DAS estimado presente
            expect($kpiValor)->toEqualWithDelta($somaAbertas, 0.01);
            expect($kpiQtd)->toBe($abertas->count());

            // Nenhuma guia paga pode entrar no calendário (invariante do KPI só-abertas).
            expect($abertas->pluck('status'))->not->toContain('paga');
        });
    } finally {
        impContratoCleanup($recId, $baixaId);
    }
});

// ── C2 · UC-IMP-09 ──────────────────────────────────────────────────────────
it('UC-IMP-09 · C2: valor é recalculado server-side — client não injeta valor/vencimento', function () {
    $user = impContratoBootstrap();
    $bizId = (int) $user->business_id;
    $comp = now()->format('Y-m');

    [$recId, $baixaId] = impContratoSeedRecebido($bizId, $user->id, $comp, 1000.0);
    $criados = [];

    try {
        // Client tenta injetar valor absurdo + vencimento + status. Controller deve ignorar.
        $r = test()->actingAs($user)->post('/financeiro/impostos/lancar', [
            'competencia' => $comp,
            'valor'       => 999999.99,
            'vencimento'  => '2099-01-01',
            'status'      => 'quitado',
        ]);

        if (in_array($r->status(), [403, 404], true)) {
            test()->markTestSkipped('Module gate bloqueia neste env.');
        }
        $r->assertRedirect();

        $guia = Titulo::where('business_id', $bizId)
            ->where('tipo', 'pagar')
            ->where('metadata->guia', "das-{$comp}")
            ->first();
        expect($guia)->not->toBeNull();
        $criados[] = $guia->id;

        // Valor = 6% de R$ 1000,00 = R$ 60,00 (recalculado), NUNCA 999999.99.
        expect((float) $guia->valor_total)->toEqualWithDelta(60.0, 0.5);
        expect((float) $guia->valor_total)->toBeLessThan(1000.0);

        // Vencimento = dia 20 do mês seguinte à competência, não o do payload.
        $vencEsperado = \Carbon\Carbon::createFromFormat('Y-m', $comp)
            ->startOfMonth()->addMonthNoOverflow()->setDay(20)->toDateString();
        expect(substr((string) $guia->vencimento, 0, 10))->toBe($vencEsperado);

        // Status inicial é sempre 'aberto', nunca o 'quitado' injetado.
        expect($guia->status)->toBe('aberto');
    } finally {
        impContratoCleanup(...array_merge([$recId, $baixaId], $criados));
    }
});

// ── C3 · UC-IMP-10 ──────────────────────────────────────────────────────────
it('UC-IMP-10 · C3: costura NF↔título — sem_nf e pct_com_nf derivam de metadata.nfe', function () {
    $user = impContratoBootstrap();
    $bizId = (int) $user->business_id;
    $venc = now()->setDay(15)->toDateString(); // dentro do mês corrente
    $criados = [];

    try {
        // Recebível SEM NF vinculada.
        $semNf = Titulo::create([
            'business_id'       => $bizId,
            'numero'            => 'IMPC-'.bin2hex(random_bytes(5)),
            'tipo'              => 'receber',
            'status'            => 'aberto',
            'cliente_descricao' => 'CONTRATO NF — sem nota',
            'valor_total'       => 500.0,
            'valor_aberto'      => 500.0,
            'moeda'             => 'BRL',
            'emissao'           => $venc,
            'vencimento'        => $venc,
            'competencia_mes'   => now()->format('Y-m'),
            'origem'            => 'manual',
            'created_by'        => $user->id,
        ]);
        $criados[] = $semNf->id;

        // Recebível COM NF vinculada (metadata.nfe_numero).
        $comNf = Titulo::create([
            'business_id'       => $bizId,
            'numero'            => 'IMPC-'.bin2hex(random_bytes(5)),
            'tipo'              => 'receber',
            'status'            => 'aberto',
            'cliente_descricao' => 'CONTRATO NF — com nota',
            'valor_total'       => 700.0,
            'valor_aberto'      => 700.0,
            'moeda'             => 'BRL',
            'emissao'           => $venc,
            'vencimento'        => $venc,
            'competencia_mes'   => now()->format('Y-m'),
            'origem'            => 'manual',
            'metadata'          => ['nfe_numero' => '12345'],
            'created_by'        => $user->id,
        ]);
        $criados[] = $comNf->id;

        impContratoGet($user)->assertInertia(function (AssertableInertia $page) use ($semNf, $comNf) {
            $props = $page->toArray()['props'];
            $semNfProps = collect($props['sem_nf'] ?? []);

            // O sem-NF entra no painel; o com-NF não.
            expect($semNfProps->pluck('numero'))->toContain($semNf->numero);
            expect($semNfProps->pluck('numero'))->not->toContain($comNf->numero);

            // pct_com_nf está entre 0 e 100 e reflete que NEM tudo tem NF (< 100).
            $pct = (int) data_get($props, 'kpis.pct_com_nf');
            expect($pct)->toBeGreaterThanOrEqual(0)->toBeLessThan(100);

            // sem_nf_qtd conta ao menos o nosso título sem NF.
            expect((int) data_get($props, 'kpis.sem_nf_qtd'))->toBeGreaterThanOrEqual(1);
        });
    } finally {
        impContratoCleanup(...$criados);
    }
});

// ── C4 · UC-IMP-11 ──────────────────────────────────────────────────────────
it('UC-IMP-11 · C4: guia quitada aparece como paga mas sai de a_recolher e do calendario', function () {
    $user = impContratoBootstrap();
    $bizId = (int) $user->business_id;
    $criados = [];

    try {
        // Guia (título payable com descritivo de guia) já QUITADA, vencida no mês.
        $guiaPaga = Titulo::create([
            'business_id'       => $bizId,
            'numero'            => 'P-'.random_int(90000, 99999),
            'tipo'              => 'pagar',
            'status'            => 'quitado',
            'cliente_descricao' => 'DAS · Simples Nacional (CONTRATO quitado)',
            'valor_total'       => 123.45,
            'valor_aberto'      => 0.0,
            'moeda'             => 'BRL',
            'emissao'           => now()->subDays(10)->toDateString(),
            'vencimento'        => now()->subDays(3)->toDateString(),
            'competencia_mes'   => now()->subMonthNoOverflow()->format('Y-m'),
            'origem'            => 'manual',
            'created_by'        => $user->id,
        ]);
        $criados[] = $guiaPaga->id;

        impContratoGet($user)->assertInertia(function (AssertableInertia $page) use ($guiaPaga) {
            $props = $page->toArray()['props'];

            $guias = collect($props['guias'] ?? []);
            $calendario = collect($props['calendario'] ?? []);

            // Aparece na tabela de guias com status 'paga'.
            $linha = $guias->firstWhere('lanc', $guiaPaga->numero);
            expect($linha)->not->toBeNull();
            expect($linha['status'])->toBe('paga');

            // NÃO entra no calendário (só abertas).
            expect($calendario->pluck('lanc'))->not->toContain($guiaPaga->numero);

            // NÃO soma no a_recolher: nenhuma linha do calendário é a guia paga.
            expect($calendario->firstWhere('lanc', $guiaPaga->numero))->toBeNull();
        });
    } finally {
        impContratoCleanup(...$criados);
    }
});

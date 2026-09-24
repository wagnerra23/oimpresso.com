<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

uses(Tests\TestCase::class);

/**
 * Plano de Contas — guard da tela /financeiro/plano-contas (somente leitura).
 *
 * Cobre os UC do contrato ao lado do .tsx
 * (`resources/js/Pages/Financeiro/PlanoContas/Index.casos.md`):
 *
 *   UC-FPC-01  shape + ordenação por `codigo` (a ordenação É a hierarquia)
 *   UC-FPC-02  Tier 0 — conta de outro business nunca aparece [ADR 0093]
 *   UC-FPC-03  conta `ativo = false` fica fora
 *   UC-FPC-04  `stats` conta exatamente as linhas listadas
 *
 * Âncora: `memory/requisitos/Financeiro/SPEC.md` R-FIN-009 ("47 contas do plano
 * padrão Receita Federal são seedadas com business_id correto"), que declarava
 * `_lacuna_` em "Testado em:" e pedia cobertura. Este arquivo fecha o eixo de
 * LEITURA. O eixo de SEED (o `SeedPlanoContasPadrao` disparar no `BusinessCreated`
 * e proteger 1.1.01.001 / 3.1.01.001) segue SEM cobertura — está no backlog do
 * casos.md, declarado, não vendido como coberto aqui.
 *
 * Sem `@covers-us`: não existe US nem `CU-FIN-*` de plano de contas no SPEC/SDD.
 * Ancorar num alheio seria âncora falsa (mesma postura do `CaixaControllerTest`).
 *
 * Tenant: `$this->seededTenant()` — biz=98 fictício ([ADR 0358]); biz=4 (ROTA
 * LIVRE, cliente real) é proibido sem exceção. Os inserts usam `DB::table` cru
 * de propósito: o Model tem global scope + auto-fill de `business_id`, e semear
 * o tenant B pelo Eloquent silenciosamente reescreveria o business errado —
 * o teste passaria sem nunca ter exercitado o cruzamento.
 */

/**
 * Marca única por execução.
 *
 * `fin_planos_conta` tem `unique(business_id, codigo)` e a base do CT 100 **não é
 * limpa entre runs** (é clone de prod persistente — proibicoes §Ambiente). Código
 * fixo passaria no primeiro run e bateria na constraint no segundo, e a falha
 * pareceria regressão do Controller. O afterEach abaixo ainda apaga o que semeamos.
 */
function planoContaMarca(): string
{
    static $marca = null;

    return $marca ??= strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

/** Cria uma conta do plano direto no banco, sem passar pelo Model (ver docblock). */
function planoContaSeed(int $businessId, string $codigo, string $nome, array $over = []): int
{
    return (int) DB::table('fin_planos_conta')->insertGetId(array_merge([
        'business_id' => $businessId,
        'codigo' => $codigo,
        'nome' => $nome,
        'tipo' => 'ativo',
        'nivel' => substr_count($codigo, '.') + 1,
        'parent_id' => null,
        'natureza' => 'debito',
        'aceita_lancamento' => true,
        'protegido' => false,
        'ativo' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ], $over));
}

/** Usuário do tenant canônico, com a sessão que o Controller lê. */
function planoContaAtor(): array
{
    $business = test()->seededTenant();

    $user = User::where('business_id', $business->id)->first();
    if (! $user) {
        test()->markTestSkipped("Sem user no business {$business->id} — seed mínimo não rodou.");
    }

    if (! DB::getSchemaBuilder()->hasTable('fin_planos_conta')) {
        test()->markTestSkipped('Tabela fin_planos_conta ausente (migration do Financeiro não rodou).');
    }

    // Desde o #7766 (2026-09-23) /financeiro/plano-contas exige financeiro.dashboard.view.
    // O ator é o 1º usuário do tenant 98, que no seed do CI NÃO tem o papel Admin#98 —
    // sem conceder aqui, os 4 UCs recebem 403 em vez de medir a tela.
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'financeiro.dashboard.view', 'guard_name' => 'web']);
    if (! $user->hasPermissionTo('financeiro.dashboard.view')) {
        $user->givePermissionTo('financeiro.dashboard.view');
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return [$user, $business];
}

// Apaga só o que ESTE run semeou (a marca é única por execução). Sem isso a base
// persistente do CT 100 acumula lixo de teste dentro do espelho de dado real.
afterEach(function () {
    if (DB::getSchemaBuilder()->hasTable('fin_planos_conta')) {
        DB::table('fin_planos_conta')->where('codigo', 'like', '9.9.%'.planoContaMarca())->delete();
    }
});

it('UC-FPC-01 · lista o plano com o shape que a tela consome, ordenado por codigo', function () {
    [$user, $business] = planoContaAtor();

    // Inseridos FORA de ordem de propósito: se o Controller perder o orderBy,
    // a asserção de ordenação cai — e não por acaso do id auto-incremento.
    $marca = planoContaMarca();
    planoContaSeed($business->id, "9.9.02.{$marca}", 'Conta Z');
    planoContaSeed($business->id, "9.9.01.{$marca}", 'Conta A');

    $r = $this->actingAs($user)
        ->withSession(['user.business_id' => $business->id])
        ->get('/financeiro/plano-contas');

    $r->assertStatus(200);
    $r->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Financeiro/PlanoContas/Index')
        ->has('planos')
        ->has('stats')
        ->where('planos', function ($planos) use ($marca) {
            $lista = collect($planos);

            // shape: os campos que a tabela renderiza
            $meus = $lista->filter(fn ($p) => str_contains((string) data_get($p, 'codigo'), $marca));
            expect($meus)->toHaveCount(2);
            foreach (['id', 'codigo', 'nome', 'tipo', 'nivel', 'natureza', 'aceita_lancamento', 'protegido'] as $campo) {
                expect(data_get($meus->first(), $campo))->not->toBeNull("campo `{$campo}` ausente no payload");
            }

            // ordenação por codigo — é o que produz a hierarquia visual
            $codigos = $lista->pluck('codigo')->map(fn ($c) => (string) $c)->values()->all();
            $ordenados = $codigos;
            sort($ordenados, SORT_STRING);
            expect($codigos)->toBe($ordenados);

            return true;
        })
    );
});

it('UC-FPC-02 · Tier 0 — conta de outro negocio nunca aparece', function () {
    [$user, $business] = planoContaAtor();

    $outro = Business::where('id', '!=', $business->id)->first();
    if (! $outro) {
        $this->markTestSkipped('Precisa 2+ businesses no banco pro cruzamento Tier 0.');
    }

    $codigoVazado = '9.9.99.'.planoContaMarca();
    planoContaSeed($outro->id, $codigoVazado, 'Conta do outro negocio');

    // Âncora no tenant A. Sem ela a lista de A vem VAZIA no CI (a fixture não
    // dispara o `BusinessCreated`, logo o `SeedPlanoContasPadrao` nunca rodou e
    // não há as 47 entries) — e "a conta de B não aparece" ficaria verde numa
    // lista vazia, provando nada. Foi o controle positivo abaixo que pegou isso.
    $codigoAncora = '9.9.98.'.planoContaMarca();
    planoContaSeed($business->id, $codigoAncora, 'Conta do proprio negocio');

    $r = $this->actingAs($user)
        ->withSession(['user.business_id' => $business->id])
        ->get('/financeiro/plano-contas');

    $r->assertStatus(200);
    $r->assertInertia(fn (AssertableInertia $page) => $page
        ->where('planos', function ($planos) use ($codigoVazado, $codigoAncora) {
            $codigos = collect($planos)->pluck('codigo');

            // nenhuma conta do outro business, nem pelo codigo nem pelo escopo
            expect($codigos->contains($codigoVazado))->toBeFalse(
                'conta do outro business apareceu na lista — vazamento cross-tenant (ADR 0093)'
            );

            // CONTROLE POSITIVO: a conta do PRÓPRIO tenant entrou. Sem isto o
            // assert acima ficaria verde numa lista vazia, provando nada.
            expect($codigos->contains($codigoAncora))->toBeTrue(
                'a conta do próprio negócio não apareceu — a lista está vazia e o assert de cima não prova nada'
            );

            return true;
        })
    );

    // o vazamento também não pode entrar pelo KPI
    $r->assertInertia(function (AssertableInertia $page) use ($business) {
        $stats = (array) ($page->toArray()['props']['stats'] ?? []);

        $doTenant = DB::table('fin_planos_conta')
            ->where('business_id', $business->id)
            ->where('ativo', true)
            ->whereNull('deleted_at')
            ->count();

        expect((int) ($stats['total'] ?? -1))->toBe(
            $doTenant,
            'o KPI total nao corresponde ao que existe no tenant — ou vazou, ou perdeu linha'
        );

        return true;
    });
});

it('UC-FPC-03 · conta inativa fica fora da lista', function () {
    [$user, $business] = planoContaAtor();

    $codigoInativo = '9.9.03.I'.planoContaMarca();
    $codigoAtivo = '9.9.03.A'.planoContaMarca();
    planoContaSeed($business->id, $codigoInativo, 'Conta desativada', ['ativo' => false]);
    planoContaSeed($business->id, $codigoAtivo, 'Conta em uso');

    $r = $this->actingAs($user)
        ->withSession(['user.business_id' => $business->id])
        ->get('/financeiro/plano-contas');

    $r->assertStatus(200);
    $r->assertInertia(fn (AssertableInertia $page) => $page
        ->where('planos', function ($planos) use ($codigoInativo, $codigoAtivo) {
            $codigos = collect($planos)->pluck('codigo');

            expect($codigos->contains($codigoInativo))->toBeFalse('conta inativa vazou pra lista');
            // controle positivo: a ativa irmã ENTROU — sem isso o assert acima
            // ficaria verde numa lista vazia
            expect($codigos->contains($codigoAtivo))->toBeTrue('a conta ativa de controle não apareceu');

            return true;
        })
    );
});

it('UC-FPC-04 · o KPI conta exatamente as linhas listadas', function () {
    [$user, $business] = planoContaAtor();

    planoContaSeed($business->id, '9.9.04.R'.planoContaMarca(), 'Receita de teste', ['tipo' => 'receita', 'natureza' => 'credito']);
    planoContaSeed($business->id, '9.9.04.D'.planoContaMarca(), 'Despesa de teste', ['tipo' => 'despesa']);

    $r = $this->actingAs($user)
        ->withSession(['user.business_id' => $business->id])
        ->get('/financeiro/plano-contas');

    $r->assertStatus(200);
    $r->assertInertia(function (AssertableInertia $page) {
        $planos = collect($page->toArray()['props']['planos'] ?? []);
        $stats = (array) ($page->toArray()['props']['stats'] ?? []);

        expect($stats['total'] ?? null)->toBe($planos->count(), 'o KPI total nao bate com as linhas listadas');

        $porTipo = array_sum(array_map(
            fn ($k) => (int) ($stats[$k] ?? 0),
            ['receita', 'despesa', 'ativo', 'passivo', 'patrimonio', 'custo']
        ));
        expect($porTipo)->toBeLessThanOrEqual((int) $stats['total'], 'a soma por tipo excede o total');

        // controle positivo: os dois tipos que semeamos aparecem no strip
        expect((int) ($stats['receita'] ?? 0))->toBeGreaterThan(0);
        expect((int) ($stats['despesa'] ?? 0))->toBeGreaterThan(0);

        return true;
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// FIN-6b — "Lanç. mês" e "Saldo mês" (DreService::movimentoMesPorConta).
// REGRA MESTRE de valor (memory/proibicoes.md): o número é provado por DOIS caminhos
// independentes — (1) valores esperados escritos à mão a partir da fixture e (2) o
// balancete, cálculo que JÁ existia no DreService e tem a mesma base de competência.
//
// `fin_titulos` não aceita DELETE (regra de domínio), e a base do CT 100 persiste entre
// runs: por isso cada caso roda dentro de transação desfeita no fim.
// ─────────────────────────────────────────────────────────────────────────────

/** Título mínimo direto no banco (mesmo idioma do ConciliacaoAuditReabrirTest). */
function planoContaTitulo(int $businessId, int $userId, ?int $planoContaId, string $tipo, float $valor, array $over = []): int
{
    return (int) DB::table('fin_titulos')->insertGetId(array_merge([
        'business_id'     => $businessId,
        'numero'          => 'FIN6B-'.uniqid(),
        'tipo'            => $tipo,
        'status'          => 'aberto',
        'valor_total'     => $valor,
        'valor_aberto'    => $valor,
        'moeda'           => 'BRL',
        'emissao'         => now()->toDateString(),
        'vencimento'      => now()->toDateString(),
        'competencia_mes' => now()->format('Y-m'),
        'plano_conta_id'  => $planoContaId,
        'origem'          => 'manual',
        'origem_id'       => random_int(700000, 799999),
        'created_by'      => $userId,
        'created_at'      => now(),
        'updated_at'      => now(),
    ], $over));
}

/** Pede SÓ a prop deferida `movimento` (partial reload), como o navegador faz no 2º request. */
function planoContaMovimento($test): array
{
    $manifestPath = public_path('build-inertia/manifest.json');
    $version = file_exists($manifestPath) ? md5_file($manifestPath) : '1';

    // Headers POR REQUISIÇÃO, nunca withHeaders() — ver ConciliacaoResumoTituloTest::fcrResumo.
    $resp = $test->get('/financeiro/plano-contas', [
        'X-Inertia'                   => 'true',
        'X-Inertia-Version'           => $version,
        'X-Inertia-Partial-Component' => 'Financeiro/PlanoContas/Index',
        'X-Inertia-Partial-Data'      => 'movimento',
        'X-Requested-With'            => 'XMLHttpRequest',
        'Accept'                      => 'text/html',
    ]);
    $resp->assertOk();
    $mov = $resp->json('props.movimento');
    expect($mov)->toBeArray();

    return $mov;
}

/**
 * Árvore da fixture: PAI (não-folha) ⊃ F1, F2. Títulos do mês corrente:
 *   F1: receber 100 + receber 50           → 2 lanç.,  +150
 *       receber 999 CANCELADO               → fora
 *       receber 40 do MÊS PASSADO           → fora
 *       pagar 500 de OUTRO NEGÓCIO apontando pra F1 → fora (Tier 0)
 *   F2: pagar 30                            → 1 lanç.,  −30
 *   PAI: receber 7 lançado DIRETO no pai    → entra no pai (o balancete deixaria de fora)
 *   PAI total: 4 lanç., 150 − 30 + 7 = +127
 */
function planoContaFixtureMovimento(int $businessId, int $userId): array
{
    // Código do PAI só com dígitos, de propósito: é o formato das raízes do plano BR ("1".."5"),
    // e chave de array PHP numérica vira INT — o que derrubava o cálculo com TypeError em
    // `str_starts_with` (medido no balancete de produção, 2026-09-23). Com "9.9.X" o defeito passava.
    $raiz = '9'.random_int(1000000, 9999999);
    $pai = planoContaSeed($businessId, $raiz, 'FIN6B Pai', ['aceita_lancamento' => false, 'tipo' => 'receita', 'natureza' => 'credito']);
    $f1 = planoContaSeed($businessId, "{$raiz}.01", 'FIN6B Filha 1', ['tipo' => 'receita', 'natureza' => 'credito']);
    $f2 = planoContaSeed($businessId, "{$raiz}.02", 'FIN6B Filha 2', ['tipo' => 'despesa', 'natureza' => 'debito']);

    planoContaTitulo($businessId, $userId, $f1, 'receber', 100.00);
    planoContaTitulo($businessId, $userId, $f1, 'receber', 50.00);
    planoContaTitulo($businessId, $userId, $f1, 'receber', 999.00, ['status' => 'cancelado']);
    planoContaTitulo($businessId, $userId, $f1, 'receber', 40.00, ['competencia_mes' => now()->subMonthNoOverflow()->format('Y-m')]);
    planoContaTitulo($businessId, $userId, $f2, 'pagar', 30.00);
    planoContaTitulo($businessId, $userId, $pai, 'receber', 7.00);

    $outro = Business::where('id', '!=', $businessId)->first();
    if ($outro) {
        planoContaTitulo($outro->id, $userId, $f1, 'pagar', 500.00);
    }

    return ['pai' => $pai, 'f1' => $f1, 'f2' => $f2, 'outro' => (bool) $outro];
}

it('UC-FPC-05 · movimento do mês — conta lançamentos, saldo com sinal e soma tudo no pai', function () {
    [$user, $business] = planoContaAtor();
    if (! DB::getSchemaBuilder()->hasColumn('fin_titulos', 'plano_conta_id')) {
        $this->markTestSkipped('fin_titulos sem plano_conta_id nesta base.');
    }

    DB::beginTransaction();
    try {
        $ids = planoContaFixtureMovimento($business->id, $user->id);

        $this->actingAs($user)->withSession(['user.business_id' => $business->id]);
        $mov = planoContaMovimento($this);

        expect($mov['mes'])->toBe(now()->format('Y-m'));
        $contas = $mov['contas'];

        // CAMINHO 1 — valores esperados escritos à mão a partir da fixture (docblock acima).
        expect($contas[$ids['f1']] ?? null)->toEqual(['lancamentos' => 2, 'saldo' => 150.0]);
        expect($contas[$ids['f2']] ?? null)->toEqual(['lancamentos' => 1, 'saldo' => -30.0]);
        expect($contas[$ids['pai']] ?? null)->toEqual(['lancamentos' => 4, 'saldo' => 127.0]);
    } finally {
        DB::rollBack();
    }
});

it('UC-FPC-06 · movimento do mês — Tier 0: título de outro negócio não entra, nem apontando pra conta daqui', function () {
    [$user, $business] = planoContaAtor();
    if (! DB::getSchemaBuilder()->hasColumn('fin_titulos', 'plano_conta_id')) {
        $this->markTestSkipped('fin_titulos sem plano_conta_id nesta base.');
    }

    DB::beginTransaction();
    try {
        $ids = planoContaFixtureMovimento($business->id, $user->id);
        if (! $ids['outro']) {
            $this->markTestSkipped('Precisa 2+ businesses pro cruzamento Tier 0.');
        }

        $contas = app(\Modules\Financeiro\Services\DreService::class)
            ->movimentoMesPorConta($business->id)['contas'];

        // O pagar de 500 do outro negócio aponta pra F1. Se o filtro de business caísse,
        // F1 viraria 3 lançamentos e −350 — e o pai, 5 e −373.
        expect($contas[$ids['f1']]['lancamentos'] ?? null)->toBe(2);
        expect((float) ($contas[$ids['f1']]['saldo'] ?? 0))->toBe(150.0);
    } finally {
        DB::rollBack();
    }
});

it('UC-FPC-07 · movimento do mês — CAMINHO 2: bate com o balancete nas folhas', function () {
    [$user, $business] = planoContaAtor();
    if (! DB::getSchemaBuilder()->hasColumn('fin_titulos', 'plano_conta_id')) {
        $this->markTestSkipped('fin_titulos sem plano_conta_id nesta base.');
    }

    DB::beginTransaction();
    try {
        $ids = planoContaFixtureMovimento($business->id, $user->id);
        $dre = app(\Modules\Financeiro\Services\DreService::class);

        $movimento = $dre->movimentoMesPorConta($business->id)['contas'];
        $balancete = collect($dre->montarBalancete($business->id, 'mes')['linhas'])->keyBy('codigo');
        $codigos = DB::table('fin_planos_conta')->whereIn('id', [$ids['f1'], $ids['f2']])->pluck('codigo', 'id');

        // O balancete é o cálculo que JÁ existia, com a mesma base (competência, sem cancelado).
        // Ele não tem sinal nem contagem, então a comparação é pelo MÓDULO nas folhas — onde os
        // dois têm de dar o mesmo dinheiro. (No pai eles divergem de propósito: o balancete não
        // soma título lançado direto na conta pai; ver docblock do movimentoMesPorConta.)
        foreach ([$ids['f1'], $ids['f2']] as $id) {
            expect($balancete->has($codigos[$id]))->toBeTrue();
            $doBalancete = (float) $balancete[$codigos[$id]]['saldo'];
            expect(abs((float) $movimento[$id]['saldo']))->toBe(round(abs($doBalancete), 2));
        }
    } finally {
        DB::rollBack();
    }
});

<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato da tela /governance/qualidade-ia — resources/js/Pages/governance/QualidadeIa.casos.md (UC-GQIA-*).
 *
 * ── DE ONDE OS CASOS VÊM ─────────────────────────────────────────────────────
 * Do `QualidadeIa.charter.md`, do `RUNBOOK-qualidade-ia.md` (§1 alvos, §4 controller, §6
 * pegadinhas) e da ADR 0049/0050 (gates e métricas). O controller entrou só pra confirmar
 * comportamento (lápide §5 2026-06-05). Os casos de UI (veredito de gate no card, travessão,
 * partial reload, vazio, gabarito, runs recentes) estão em
 * `tests/js/governance-qualidade-ia.test.tsx`.
 *
 * ── POR QUE APAGA AS LINHAS DO TENANT NO SETUP ───────────────────────────────
 * A janela da tela é relativa a hoje (`ultimosDias`), então não dá pra isolar por data antiga
 * como o contrato de Custos faz. No CT 100 a base persiste: o setup apaga as métricas do
 * tenant 98 DENTRO da transação (DatabaseTransactions faz rollback) e semeia as suas.
 *
 * ── PREDIÇÃO DECLARADA (não medida — Pest não roda local) ─────────────────────
 * UC-GQIA-04 deve sair VERMELHO: `MemoriaMetrica::scopeUltimosDias` já ordena por
 * `apurado_em DESC` e o controller acrescenta `orderBy('apurado_em')` ASC; o DESC vence,
 * os pontos chegam do mais novo pro mais antigo e `end($pontos)` pega a leitura MAIS
 * ANTIGA. É hipótese até a lane rodar.
 *
 * TENANT: 98 (ADR 0358). NUNCA biz=4, NUNCA biz=1.
 *
 * ⚠️ D-GATE (AuthServiceProvider `Gate::before`, ADR 0415): `jana.mcp.usage.all` é permissão de
 * plataforma — o papel Admin NÃO a herda. Os usuários daqui nascem sem role.
 */

const GQIA_PERMISSAO = 'jana.mcp.usage.all';
const GQIA_COMPONENTE = 'governance/QualidadeIa';
const GQIA_TABELA = 'jana_memoria_metricas';

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql'
        || ! Schema::hasTable(GQIA_TABELA)
        || ! Schema::hasTable('jana_memoria_gabarito')) {
        $this->markTestSkipped('Requer schema MySQL com jana_memoria_metricas/jana_memoria_gabarito.');
    }

    $this->biz = $this->seededTenant();

    config(['constants.administrator_usernames' => 'ninguem-gqia-'.uniqid()]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate(GQIA_PERMISSAO, 'web');

    // Rollback pela transação — ver docblock.
    DB::table(GQIA_TABELA)->where('business_id', $this->biz->id)->delete();
});

afterEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function gqiaUsuario(int $businessId, bool $comPermissao): User
{
    $user = User::factory()->create([
        'business_id' => $businessId,
        'username' => 'gqia_contrato_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
    $user->syncRoles([]);
    if ($comPermissao) {
        $user->givePermissionTo(GQIA_PERMISSAO);
    }
    $user->forgetCachedPermissions();

    return $user;
}

function gqiaMetrica(int $businessId, string $data, float $recall): void
{
    DB::table(GQIA_TABELA)->insert([
        'apurado_em' => $data,
        'business_id' => $businessId,
        'recall_at_3' => $recall,
        'precision_at_3' => 0.700,
        'mrr' => 0.750,
        'latencia_p95_ms' => 900,
        'tokens_medio_interacao' => 1200,
        'memory_bloat_ratio' => 0.700,
        'taxa_contradicoes_pct' => 0.50,
        'cross_tenant_violations' => 0,
        'total_interacoes_dia' => 10,
        'total_memorias_ativas' => 50,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function gqiaGet(User $user, int $businessId, array $query = [])
{
    return test()
        ->actingAs($user)
        ->withSession([
            'user.business_id' => $businessId,
            'user' => ['business_id' => $businessId, 'id' => $user->id],
        ])
        ->get('/governance/qualidade-ia'.($query ? '?'.http_build_query($query) : ''));
}

function gqiaPagina(User $user, int $businessId, array $query = []): array
{
    $res = gqiaGet($user, $businessId, $query);
    expect($res->status())->toBe(200);

    return (array) $res->viewData('page');
}

it('UC-GQIA-01 [T0]: sem jana.mcp.usage.all a tela responde 403; com ela, abre governance/QualidadeIa', function () {
    $sem = gqiaUsuario((int) $this->biz->id, false);
    expect(gqiaGet($sem, (int) $this->biz->id)->status())->toBe(403);

    // Controle: sem este par, o 403 poderia vir de outra trava da pilha de middleware.
    $com = gqiaUsuario((int) $this->biz->id, true);
    expect(gqiaPagina($com, (int) $this->biz->id)['component'] ?? null)->toBe(GQIA_COMPONENTE);
});

it('UC-GQIA-02: a janela fica entre 7 e 90 dias, e o padrão é 30', function () {
    $user = gqiaUsuario((int) $this->biz->id, true);
    $biz = (int) $this->biz->id;

    expect(gqiaPagina($user, $biz)['props']['filtros']['dias'])->toBe(30)
        ->and(gqiaPagina($user, $biz, ['dias' => 1])['props']['filtros']['dias'])->toBe(7)
        ->and(gqiaPagina($user, $biz, ['dias' => 999])['props']['filtros']['dias'])->toBe(90)
        ->and(gqiaPagina($user, $biz, ['dias' => 60])['props']['filtros']['dias'])->toBe(60);
});

it('UC-GQIA-03: os gates chegam com os alvos canônicos da ADR 0049/0050', function () {
    $user = gqiaUsuario((int) $this->biz->id, true);
    $gates = gqiaPagina($user, (int) $this->biz->id)['props']['gates'];

    // Alvos do RUNBOOK §1 (contrato), não lidos do controller.
    $esperado = [
        'recall_at_3' => ['>=', 0.80],
        'precision_at_3' => ['>=', 0.60],
        'mrr' => ['>=', 0.70],
        'latencia_p95_ms' => ['<=', 2000],
        'tokens_medio' => ['<=', 3000],
        'memory_bloat' => ['>=', 0.60],
        'taxa_contradicoes_pct' => ['<=', 2.0],
        'cross_tenant_violations' => ['==', 0],
        'faithfulness' => ['>=', 0.85],
    ];

    foreach ($esperado as $chave => [$op, $alvo]) {
        expect($gates)->toHaveKey($chave);
        expect($gates[$chave]['op'])->toBe($op);
        expect((float) $gates[$chave]['alvo'])->toBe((float) $alvo);
    }
});

it('UC-GQIA-04: o KPI do business é a leitura MAIS RECENTE da janela', function () {
    $user = gqiaUsuario((int) $this->biz->id, true);
    $biz = (int) $this->biz->id;

    // Valores distintos por dia — só a leitura de ontem tem recall 0.930.
    gqiaMetrica($biz, now()->subDays(3)->toDateString(), 0.810);
    gqiaMetrica($biz, now()->subDays(2)->toDateString(), 0.620);
    gqiaMetrica($biz, now()->subDays(1)->toDateString(), 0.930);

    $props = gqiaPagina($user, $biz)['props'];
    $kpi = collect($props['kpis'])->firstWhere('business_id', $biz);

    expect($kpi)->not->toBeNull();   // anti-vácuo: sem a linha o teste não diz nada
    expect($kpi['apurado_em'])->toBe(now()->subDays(1)->toDateString());
    expect((float) $kpi['recall_at_3'])->toBe(0.93);
});

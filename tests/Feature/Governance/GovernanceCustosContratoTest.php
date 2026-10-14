<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class);

/**
 * Contrato da tela /governance/custos — resources/js/Pages/governance/Custos.casos.md (UC-GCUS-*).
 *
 * ── DE ONDE OS CASOS VÊM ─────────────────────────────────────────────────────
 * Do `Custos.charter.md`, do `RUNBOOK-custos.md` e da US-COPI-070 (SPEC do Jana). O
 * controller e o `CustosService` entraram só pra confirmar comportamento (lápide §5
 * 2026-06-05 — teste derivado do código é tautológico).
 *
 * ── POR QUE O ANO 2001 ───────────────────────────────────────────────────────
 * No CT 100 a base persiste entre runs e o tenant 98 pode já ter consumo de IA no mês
 * corrente. Toda fixture nasce com `created_at` em jan/2001 e toda requisição pede o range
 * custom desse mês — o número esperado fica sob controle do teste, não do histórico da base.
 *
 * ── SEM VALOR MONETÁRIO ──────────────────────────────────────────────────────
 * Os asserts são sobre tokens, mensagens, contagem de dias e presença de props. O cálculo
 * de custo em si é coberto por `Modules/Governance/Tests/Feature/CustosControllerTest.php`.
 *
 * TENANT: 98 (ADR 0358), adversário cross-tenant 99. NUNCA biz=4, NUNCA biz=1.
 *
 * ⚠️ D-GATE (AuthServiceProvider `Gate::before`): a role `Admin#{biz}` passa por qualquer
 * `can:`. Os usuários daqui nascem SEM role — o 403 medido é o do não-admin.
 */

const GCUS_PERMISSAO = 'jana.admin.custos.view';
const GCUS_COMPONENTE = 'governance/Custos';
const GCUS_RANGE = ['preset' => 'custom', 'de' => '2001-01-01', 'ate' => '2001-01-31'];

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql'
        || ! Schema::hasTable('jana_conversas')
        || ! Schema::hasTable('jana_mensagens')) {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS com jana_conversas/jana_mensagens.');
    }

    $this->biz = $this->seededTenant();

    // Cache do Spatie sobrevive ao rollback da transação (medido no CustosVazamentoTest).
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate(GCUS_PERMISSAO, 'web');
});

afterEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function gcusUsuario(int $businessId, bool $comPermissao): User
{
    $user = User::factory()->create([
        'business_id' => $businessId,
        'username' => 'gcus_contrato_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
    $user->syncRoles([]);
    if ($comPermissao) {
        $user->givePermissionTo(GCUS_PERMISSAO);
    }
    $user->forgetCachedPermissions();

    return $user;
}

/** Cria uma conversa com N mensagens no instante dado. Devolve os tokens totais gravados. */
function gcusConsumo(int $businessId, int $userId, string $quando, array $mensagens): int
{
    $conversaId = DB::table('jana_conversas')->insertGetId([
        'business_id' => $businessId,
        'user_id' => $userId,
        'titulo' => '__test_gcus__'.uniqid(),
        'status' => 'ativa',
        'iniciada_em' => $quando,
        'created_at' => $quando,
        'updated_at' => $quando,
    ]);

    $total = 0;
    foreach ($mensagens as [$in, $out]) {
        DB::table('jana_mensagens')->insert([
            'conversa_id' => $conversaId,
            'role' => 'user',
            'content' => 'fixture contrato custos',
            'tokens_in' => $in,
            'tokens_out' => $out,
            'created_at' => $quando,
        ]);
        $total += $in + $out;
    }

    return $total;
}

function gcusGet(User $user, int $businessId, array $query = [], array $headers = [])
{
    test()->flushHeaders();

    return test()
        ->actingAs($user)
        ->withSession([
            'user.business_id' => $businessId,
            'user' => ['business_id' => $businessId, 'id' => $user->id],
        ])
        ->withHeaders($headers)
        ->get('/governance/custos'.($query ? '?'.http_build_query($query) : ''));
}

/** Página Inertia do 1º render (root view). */
function gcusPagina(User $user, int $businessId, array $query = []): array
{
    $res = gcusGet($user, $businessId, $query);
    expect($res->status())->toBe(200);

    return (array) $res->viewData('page');
}

it('UC-GCUS-01 [T0]: o consumo de outro business não entra nos números da tela, nem pedindo pela query', function () {
    $outro = $this->seededSupportClientTenant();
    $user = gcusUsuario((int) $this->biz->id, true);

    $meus = gcusConsumo((int) $this->biz->id, $user->id, '2001-01-15 10:00:00', [[1200, 300], [800, 200]]);
    gcusConsumo((int) $outro->id, $user->id, '2001-01-15 10:00:00', [[50000, 50000]]);

    // `business_id` na query é ignorado: o tenant vem da sessão (ADR 0093).
    $props = gcusPagina($user, (int) $this->biz->id, GCUS_RANGE + ['business_id' => $outro->id])['props'];

    expect($props['kpis']['tokens'])->toBe($meus)
        ->and($props['kpis']['mensagens'])->toBe(2);
});

it('UC-GCUS-02: sem jana.admin.custos.view a tela responde 403; com ela, abre governance/Custos', function () {
    $sem = gcusUsuario((int) $this->biz->id, false);
    expect(gcusGet($sem, (int) $this->biz->id)->status())->toBe(403);

    $com = gcusUsuario((int) $this->biz->id, true);
    $pagina = gcusPagina($com, (int) $this->biz->id);
    expect($pagina['component'] ?? null)->toBe(GCUS_COMPONENTE);
});

it('UC-GCUS-03: KPIs, série diária e tabela por usuário chegam no 1º render, sem defer', function () {
    $user = gcusUsuario((int) $this->biz->id, true);
    $pagina = gcusPagina($user, (int) $this->biz->id, GCUS_RANGE);

    foreach (['kpis', 'por_usuario', 'serie_diaria'] as $prop) {
        expect(array_key_exists($prop, $pagina['props']))->toBeTrue("prop {$prop} ausente do 1º render");
    }
    expect($pagina['props']['kpis'])->toHaveCount(4);

    $deferidas = collect($pagina['deferredProps'] ?? [])->flatten()->all();
    expect(array_intersect(['kpis', 'por_usuario', 'serie_diaria'], $deferidas))->toBe([]);
});

it('UC-GCUS-04: preset fora da lista cai no mês atual, igual a pedir mes_atual', function () {
    $user = gcusUsuario((int) $this->biz->id, true);

    $invalido = gcusPagina($user, (int) $this->biz->id, ['preset' => 'qualquer_coisa'])['props'];
    $mesAtual = gcusPagina($user, (int) $this->biz->id, ['preset' => 'mes_atual'])['props'];

    expect($invalido['filters']['preset'])->toBe('mes_atual')
        ->and($invalido['periodo'])->toBe($mesAtual['periodo']);
});

it('UC-GCUS-05: a troca de filtro é partial reload que não retrafega o pricing', function () {
    $user = gcusUsuario((int) $this->biz->id, true);
    $versao = data_get(gcusPagina($user, (int) $this->biz->id), 'version');

    $parcial = gcusGet($user, (int) $this->biz->id, GCUS_RANGE, [
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Component' => GCUS_COMPONENTE,
        'X-Inertia-Partial-Data' => 'kpis,por_usuario,serie_diaria,periodo,filters',
    ]);

    expect($parcial->status())->toBe(200);
    $props = (array) data_get($parcial->json(), 'props', []);
    expect(array_key_exists('pricing', $props))->toBeFalse()
        ->and(array_key_exists('kpis', $props))->toBeTrue();
});

it('UC-GCUS-06: o range custom De/Até define o período — fora dele não conta, e a série tem um ponto por dia', function () {
    $user = gcusUsuario((int) $this->biz->id, true);

    $dentro = gcusConsumo((int) $this->biz->id, $user->id, '2001-01-20 09:00:00', [[400, 100]]);
    gcusConsumo((int) $this->biz->id, $user->id, '2001-02-10 09:00:00', [[9000, 9000]]);

    $props = gcusPagina($user, (int) $this->biz->id, GCUS_RANGE)['props'];

    expect($props['kpis']['tokens'])->toBe($dentro)
        ->and($props['serie_diaria'])->toHaveCount(31)
        ->and($props['filters']['preset'])->toBe('custom');
});

it('UC-GCUS-07: a soma da tabela por usuário bate com os KPIs do período', function () {
    $a = gcusUsuario((int) $this->biz->id, true);
    $b = gcusUsuario((int) $this->biz->id, false);

    gcusConsumo((int) $this->biz->id, $a->id, '2001-01-05 08:00:00', [[100, 50], [200, 50]]);
    gcusConsumo((int) $this->biz->id, $b->id, '2001-01-06 08:00:00', [[300, 300]]);

    $props = gcusPagina($a, (int) $this->biz->id, GCUS_RANGE)['props'];
    $linhas = collect($props['por_usuario']);

    expect($linhas)->toHaveCount(2)
        ->and($linhas->sum('mensagens'))->toBe($props['kpis']['mensagens'])
        ->and($linhas->sum('tokens'))->toBe($props['kpis']['tokens'])
        ->and($props['kpis']['usuarios_ativos'])->toBe(2);
});

it('UC-GCUS-08: a URL antiga /ia/admin/custos redireciona 301 preservando o filtro', function () {
    $user = gcusUsuario((int) $this->biz->id, true);

    $res = $this->actingAs($user)->get('/ia/admin/custos?preset=90d');

    expect($res->status())->toBe(301)
        ->and((string) $res->headers->get('Location'))->toEndWith('/governance/custos?preset=90d');
});

it('UC-GCUS-09: abrir e filtrar a tela não grava nada', function () {
    $user = gcusUsuario((int) $this->biz->id, true);
    gcusConsumo((int) $this->biz->id, $user->id, '2001-01-10 08:00:00', [[10, 10]]);

    $antes = [DB::table('jana_conversas')->count(), DB::table('jana_mensagens')->count()];

    gcusPagina($user, (int) $this->biz->id, GCUS_RANGE);
    gcusPagina($user, (int) $this->biz->id, ['preset' => '90d']);

    expect([DB::table('jana_conversas')->count(), DB::table('jana_mensagens')->count()])->toBe($antes);
});

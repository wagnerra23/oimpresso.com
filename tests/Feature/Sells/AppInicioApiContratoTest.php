<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use App\User;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use Tests\Contract\AutosaveContractRunner;

/**
 * API de Início do app das lojas (oimpresso-app) — GET /api/app/inicio, só leitura.
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §6 — cada bloco respeita a permissão
 * da tela web e vem null sem ela; meta do dia = meta MENSAL de faturamento da Jana ÷ dias úteis
 * (D11, [W]). NÃO derivado do controller.
 *
 * Tier 0 (ADR 0093): a meta de OUTRO business nunca vira a meta do dia (controle positivo no par).
 */
uses(DatabaseTransactions::class);

function appIniMeta(int $businessId, float $alvo): void
{
    $metaId = DB::table('jana_metas')->insertGetId([
        'business_id' => $businessId, 'slug' => 'faturamento_app_' . uniqid(), 'nome' => 'Faturamento',
        'unidade' => 'R$', 'tipo_agregacao' => 'soma', 'ativo' => 1, 'origem' => 'manual',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('jana_meta_periodos')->insert([
        'meta_id' => $metaId, 'tipo_periodo' => 'mes',
        'data_ini' => Carbon::today()->startOfMonth()->toDateString(),
        'data_fim' => Carbon::today()->endOfMonth()->toDateString(),
        'valor_alvo' => $alvo, 'trajetoria' => 'linear', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function appIniDiasUteis(): int
{
    $n = 0;
    for ($d = Carbon::today()->startOfMonth(); $d->lte(Carbon::today()->endOfMonth()); $d->addDay()) {
        if (! $d->isWeekend()) {
            $n++;
        }
    }

    return $n;
}

beforeEach(function () {
    // O guard `api` do Passport precisa das chaves mesmo com Passport::actingAs; a lane não as gera.
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['transactions', 'jana_metas', 'jana_meta_periodos'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }

    $ctx = AutosaveContractRunner::setupSellsContext($this);
    $this->biz = $ctx['business'];
    $this->user = $ctx['user'];
    $this->venda = (int) $ctx['transactionId'];
    $this->outroBiz = \App\Business::where('id', '!=', $this->biz->id)->first();
    if (! $this->outroBiz) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }

    DB::table('transactions')->where('id', $this->venda)->update([
        'status' => 'final', 'sub_type' => null, 'final_total' => 248.00, 'transaction_date' => now(),
    ]);
});

it('sem token responde 401', function () {
    $this->getJson('/api/app/inicio')->assertStatus(401);
});

it('sem permissões: só usuário e empresa; os blocos protegidos vêm null', function () {
    $semPerm = \App\User::query()->whereKey(
        DB::table('users')->insertGetId([
            'first_name' => 'APP Ini', 'username' => 'app_ini_' . uniqid(), 'password' => 'x',
            'business_id' => $this->biz->id, 'created_at' => now(), 'updated_at' => now(),
        ])
    )->firstOrFail();
    Passport::actingAs($semPerm, [], 'api');

    $r = $this->getJson('/api/app/inicio')->assertOk();
    expect($r->json('empresa'))->toBe((string) $this->biz->name);
    expect($r->json('faturado_hoje'))->toBeNull();
    expect($r->json('meta_dia'))->toBeNull();
    expect($r->json('kpis.pedidos_ativos'))->toBeNull();
    expect($r->json('kpis.estoque_baixo'))->toBeNull();
    expect($r->json('financeiro'))->toBeNull();
    expect($r->json('proximas_tarefas'))->toBeArray();
});

it('com dashboard.data: faturado de hoje inclui a venda final de hoje', function () {
    foreach (['dashboard.data', 'access_all_locations'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        $this->user->givePermissionTo($p);
    }
    Passport::actingAs($this->user, [], 'api');

    $r = $this->getJson('/api/app/inicio')->assertOk();
    expect($r->json('faturado_hoje'))->toHaveKeys(['valor', 'ontem', 'variacao_pct']);
    expect((float) $r->json('faturado_hoje.valor'))->toBeGreaterThanOrEqual(248.0);
});

it('meta do dia = meta mensal de faturamento ÷ dias úteis; meta de OUTRO business não conta', function () {
    Permission::firstOrCreate(['name' => 'dashboard.data', 'guard_name' => 'web']);
    $this->user->givePermissionTo('dashboard.data');
    DB::table('jana_metas')->where('business_id', $this->biz->id)->update(['ativo' => 0]);
    Passport::actingAs($this->user, [], 'api');

    appIniMeta((int) $this->outroBiz->id, 9000.00);
    expect($this->getJson('/api/app/inicio')->json('meta_dia'))->toBeNull();

    appIniMeta((int) $this->biz->id, 2200.00);
    $r = $this->getJson('/api/app/inicio')->assertOk();
    expect((float) $r->json('meta_dia.valor'))->toBe(round(2200 / appIniDiasUteis(), 2));
    expect($r->json('meta_dia.derivada'))->toBeTrue();
});

/** Usuário do business sem permissão nenhuma; com cadastro de colaborador do ponto se pedido. */
function appIniUsuario(int $businessId, bool $colaborador): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'APP Perfil', 'username' => 'app_perfil_' . uniqid(), 'password' => 'x',
        'business_id' => $businessId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    if ($colaborador) {
        DB::table('ponto_colaborador_config')->insert([
            'business_id' => $businessId, 'user_id' => $id, 'matricula' => 'APP-' . uniqid(),
            'controla_ponto' => true, 'admissao' => '2020-01-01', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return User::findOrFail($id);
}

/** Sem Essentials no plano: a aba Tarefas não depende do pacote da lane. */
function appIniSemEssentials(): void
{
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')->andReturn(false);
    app()->instance(ModuleUtil::class, $mu);
}

it('D6: colaborador do ponto sem acesso ao ERP abre no Ponto e só tem Ponto e Mais', function () {
    if (! Schema::hasTable('ponto_colaborador_config')) {
        $this->markTestSkipped('Schema ausente (ponto_colaborador_config).');
    }
    appIniSemEssentials();
    Passport::actingAs(appIniUsuario((int) $this->biz->id, true), [], 'api');

    $r = $this->getJson('/api/app/inicio')->assertOk();
    expect($r->json('perfil'))->toBe('colaborador');
    expect($r->json('abre_em'))->toBe('ponto');
    expect($r->json('areas'))->toBe(['ponto', 'mais']);
});

it('D6: quem vê vendas é perfil erp, abre no Início e tem Pedidos, Produção e Orçamentos (sem Ponto, que não é colaborador)', function () {
    appIniSemEssentials();
    $u = appIniUsuario((int) $this->biz->id, false);
    Permission::firstOrCreate(['name' => 'direct_sell.view', 'guard_name' => 'web']);
    $u->givePermissionTo('direct_sell.view');
    Passport::actingAs($u, [], 'api');

    $r = $this->getJson('/api/app/inicio')->assertOk();
    expect($r->json('perfil'))->toBe('erp');
    expect($r->json('abre_em'))->toBe('inicio');
    // `relatorios` vem junto: o bloco produção dos Relatórios segue a regra de vendas (§10.3).
    expect($r->json('areas'))->toBe(['inicio', 'pedidos', 'producao', 'orcamentos', 'relatorios', 'mais']);
});

it('com stock_report.view: estoque_baixo é um número (antes dava 500 — count() num Builder)', function () {
    foreach (['stock_report.view', 'access_all_locations'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        $this->user->givePermissionTo($p);
    }
    Passport::actingAs($this->user, [], 'api');

    $r = $this->getJson('/api/app/inicio')->assertOk();
    expect($r->json('kpis.estoque_baixo'))->toBeInt();
    expect($r->json('kpis.estoque_baixo'))->toBeGreaterThanOrEqual(0);

    // Caminho 2: as mesmas linhas contadas depois de trazidas (o agrupamento não pode colapsar a contagem).
    $linhas = app(ProductUtil::class)->getProductAlert($this->biz->id, 'all')->get()->count();
    expect($r->json('kpis.estoque_baixo'))->toBe($linhas);
});

<?php

declare(strict_types=1);

use App\User;
use App\Utils\ModuleUtil;
use App\Utils\TransactionUtil;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;

/**
 * API do Dashboard do app das lojas (tela 35) — GET /api/app/dashboard, só leitura.
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §10.4 (formato do oimpresso-app#42;
 * regras de acesso combinadas com a sessão do app). NÃO derivado do controller.
 *
 * Dupla prova: faturamento contra TransactionUtil::getSellTotals (o painel web) e a receber contra
 * UnificadoService::kpis (a tela 06 e o Início); pedidos e vencido conferidos por DELTA contra a
 * mudança feita à mão no teste.
 *
 * Tier 0 (ADR 0093): título vencido do business 2 não soma no 98 (controle positivo em par).
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['transactions', 'fin_titulos', 'jana_metas', 'jana_meta_periodos'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }

    $this->tenant = $this->seededTenant();
    $this->outro = \App\Business::where('id', '!=', $this->tenant->id)->orderBy('id')->first();
    if (! $this->outro) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }
    foreach (['dashboard.data', 'direct_sell.view', 'financeiro.access', 'access_all_locations'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
});

function appDashPlano(bool $comFinanceiro): void
{
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($biz, $perm) => $comFinanceiro && $perm === 'financeiro_module');
    app()->instance(ModuleUtil::class, $mu);
}

/** @param list<string> $perms */
function appDashUsuario(int $businessId, array $perms): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'APP Dash', 'username' => 'app_dash_' . uniqid(), 'password' => 'x',
        'business_id' => $businessId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $u = User::findOrFail($id);
    foreach ($perms as $p) {
        $u->givePermissionTo($p);
    }

    return $u;
}

it('sem token 401; sem dashboard.data 403 (mesmo vendo vendas) e a área não entra no Início', function () {
    $this->getJson('/api/app/dashboard')->assertStatus(401);
    appDashPlano(false);
    Passport::actingAs(appDashUsuario((int) $this->tenant->id, ['direct_sell.view']), [], 'api');
    $this->getJson('/api/app/dashboard')->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
    expect($this->getJson('/api/app/inicio')->assertOk()->json('areas'))->not->toContain('dashboard');

    Passport::actingAs(appDashUsuario((int) $this->tenant->id, ['dashboard.data']), [], 'api');
    expect($this->getJson('/api/app/inicio')->assertOk()->json('areas'))->toContain('dashboard');
});

it('só dashboard.data: faturamento bate com o painel; pedidos, produção e financeiro vêm null', function () {
    appDashPlano(false);
    $biz = (int) $this->tenant->id;
    $eu = appDashUsuario($biz, ['dashboard.data', 'access_all_locations']);
    Passport::actingAs($eu, [], 'api');
    $venda = DB::table('transactions')->where('business_id', $biz)->where('type', 'sell')->value('id');
    if ($venda) {
        DB::table('transactions')->where('id', $venda)->update(['status' => 'final', 'final_total' => 4321.00, 'transaction_date' => now()]);
    }

    $r = $this->getJson('/api/app/dashboard')->assertOk()->json();

    $hoje = CarbonImmutable::today();
    $tu = app(TransactionUtil::class);
    $locais = $eu->permitted_locations($biz);
    $painel30 = (float) $tu->getSellTotals($biz, $hoje->subDays(29)->toDateString(), $hoje->toDateString(), null, null, $locais)['total_sell_inc_tax'];
    $painelHoje = (float) $tu->getSellTotals($biz, $hoje->toDateString(), $hoje->toDateString(), null, null, $locais)['total_sell_inc_tax'];
    expect((float) $r['faturamento_30d']['valor'])->toBe(round($painel30, 2));
    expect($r['faturamento_30d']['serie_semanal'])->toHaveCount(7);
    expect((float) end($r['faturamento_30d']['serie_semanal']))->toBe(round($painelHoje, 2));
    if ($venda) {
        expect((float) $r['faturamento_30d']['valor'])->toBeGreaterThanOrEqual(4321.0);
    }

    expect($r['kpis'])->toBe([
        'pedidos_ativos' => null, 'pedidos_novos' => null, 'producao_em_curso' => null, 'a_receber' => null, 'vencido' => null,
    ]);
    expect($r['pedidos_por_dia'])->toBeNull();
    expect($r['producao_concluida'])->toBeNull();
});

it('com vendas: pedidos_por_dia tem 14 dias e uma venda movida para hoje soma 1 em pedidos_novos', function () {
    appDashPlano(false);
    $biz = (int) $this->tenant->id;
    Passport::actingAs(appDashUsuario($biz, ['dashboard.data', 'direct_sell.view', 'access_all_locations']), [], 'api');
    $venda = DB::table('transactions')->where('business_id', $biz)->where('type', 'sell')
        ->whereNull('sub_type')->whereDate('transaction_date', '!=', now()->toDateString())->value('id');
    if (! $venda) {
        $this->markTestSkipped('Seed sem venda fora de hoje no tenant.');
    }
    DB::table('transactions')->where('id', $venda)->update(['status' => 'final', 'transaction_date' => now()->subYears(5)]);

    $antes = $this->getJson('/api/app/dashboard')->assertOk()->json();
    DB::table('transactions')->where('id', $venda)->update(['transaction_date' => now()]);
    $depois = $this->getJson('/api/app/dashboard')->assertOk()->json();

    expect($depois['pedidos_por_dia'])->toHaveCount(14);
    expect(end($depois['pedidos_por_dia'])['data'])->toBe(now()->toDateString());
    expect($depois['kpis']['pedidos_novos'] - $antes['kpis']['pedidos_novos'])->toBe(1);
    expect($depois['kpis']['pedidos_novos'])->toBe(end($depois['pedidos_por_dia'])['total']);
    expect($depois['kpis']['producao_em_curso'])->toBeInt();
    expect($depois['producao_concluida']['concluidas'])->toBeLessThanOrEqual($depois['producao_concluida']['total']);
});

it('com Financeiro: a_receber = UnificadoService::kpis e vencido soma só o vencido a receber do business', function () {
    appDashPlano(true);
    $biz = (int) $this->tenant->id;
    $eu = appDashUsuario($biz, ['dashboard.data', 'financeiro.access']);
    Passport::actingAs($eu, [], 'api');
    $titulo = fn (int $b, string $tipo, string $venc) => DB::table('fin_titulos')->insert([
        'business_id' => $b, 'numero' => 'APP-' . strtoupper(Str::random(8)), 'tipo' => $tipo, 'status' => 'aberto',
        'valor_total' => 30.00, 'valor_aberto' => 30.00, 'emissao' => $venc, 'vencimento' => $venc,
        'competencia_mes' => substr($venc, 0, 7), 'origem' => 'manual', 'created_by' => $eu->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $antes = $this->getJson('/api/app/dashboard')->assertOk()->json('kpis');
    $ontem = now()->subDay()->toDateString();
    $titulo($biz, 'receber', $ontem);                          // vencido a receber: +30
    $titulo($biz, 'pagar', $ontem);                            // a pagar vencido: não entra
    $titulo((int) $this->outro->id, 'receber', $ontem);        // outro business: não entra
    $depois = $this->getJson('/api/app/dashboard')->assertOk()->json('kpis');

    expect(round((float) $depois['vencido'] - (float) $antes['vencido'], 2))->toBe(30.0);
    expect(round((float) $depois['a_receber'] - (float) $antes['a_receber'], 2))->toBe(30.0);
    $k = app(\Modules\Financeiro\Services\UnificadoService::class)->kpis($biz);
    expect((float) $depois['a_receber'])->toBe((float) $k['total_receber']);
});

it('meta_mes: a meta mensal de faturamento da Jana e o realizado do mês pelo painel; sem meta, null', function () {
    appDashPlano(false);
    $biz = (int) $this->tenant->id;
    $eu = appDashUsuario($biz, ['dashboard.data', 'access_all_locations']);
    Passport::actingAs($eu, [], 'api');
    DB::table('jana_metas')->where('business_id', $biz)->update(['ativo' => 0]);
    expect($this->getJson('/api/app/dashboard')->assertOk()->json('meta_mes'))->toBeNull();

    $metaId = DB::table('jana_metas')->insertGetId([
        'business_id' => $biz, 'slug' => 'faturamento_app_' . uniqid(), 'nome' => 'Faturamento',
        'unidade' => 'R$', 'tipo_agregacao' => 'soma', 'ativo' => 1, 'origem' => 'manual',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('jana_meta_periodos')->insert([
        'meta_id' => $metaId, 'tipo_periodo' => 'mes',
        'data_ini' => now()->startOfMonth()->toDateString(), 'data_fim' => now()->endOfMonth()->toDateString(),
        'valor_alvo' => 10000.00, 'trajetoria' => 'linear', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $r = $this->getJson('/api/app/dashboard')->assertOk()->json('meta_mes');
    $mes = (float) app(TransactionUtil::class)->getSellTotals($biz, now()->startOfMonth()->toDateString(), now()->toDateString(), null, null, $eu->permitted_locations($biz))['total_sell_inc_tax'];

    expect((float) $r['valor'])->toBe(10000.0);
    expect($r['realizado_pct'])->toBe((int) round($mes / 10000 * 100));
});

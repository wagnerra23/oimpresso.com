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
use Tests\Contract\AutosaveContractRunner;
use Tests\Support\EstoqueFixture;

/**
 * API de Relatórios do app das lojas (tela 13) — GET /api/app/relatorios, só leitura.
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §10.3 (formato do oimpresso-app#41,
 * com kpis/dre `null` sem Financeiro — combinado com a sessão do app). NÃO derivado do controller.
 *
 * Números conferidos por DELTA (antes × depois de semear) contra a conta feita à mão aqui; a série
 * de vendas de hoje também contra TransactionUtil::getSellTotals (o número do painel web).
 *
 * Tier 0 (ADR 0093): títulos e vendas do business 2 nunca somam no 98 (controle positivo em par).
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['fin_titulos', 'transactions', 'contacts'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }

    $this->tenant = $this->seededTenant();
    $this->outro = \App\Business::where('id', '!=', $this->tenant->id)->orderBy('id')->first();
    if (! $this->outro) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }
    foreach (['financeiro.access', 'dashboard.data', 'stock_report.view', 'access_all_locations'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
});

function appRelPlano(bool $comFinanceiro): void
{
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($biz, $perm) => $comFinanceiro && $perm === 'financeiro_module');
    app()->instance(ModuleUtil::class, $mu);
}

/** @param list<string> $perms */
function appRelUsuario(int $businessId, array $perms): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'APP Rel', 'username' => 'app_rel_' . uniqid(), 'password' => 'x',
        'business_id' => $businessId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $u = User::findOrFail($id);
    foreach ($perms as $p) {
        $u->givePermissionTo($p);
    }

    return $u;
}

function appRelTitulo(int $biz, int $criador, string $tipo, string $status, float $valor, string $competencia, ?int $categoria = null): void
{
    DB::table('fin_titulos')->insert([
        'business_id' => $biz, 'numero' => 'APP-' . strtoupper(Str::random(8)), 'tipo' => $tipo, 'status' => $status,
        'valor_total' => $valor, 'valor_aberto' => $valor, 'emissao' => $competencia . '-01', 'vencimento' => $competencia . '-01',
        'competencia_mes' => $competencia, 'origem' => 'manual', 'created_by' => $criador, 'categoria_id' => $categoria,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('sem token 401; sem nenhum bloco 403', function () {
    $this->getJson('/api/app/relatorios')->assertStatus(401);
    appRelPlano(true);
    Passport::actingAs(appRelUsuario((int) $this->tenant->id, []), [], 'api');
    $this->getJson('/api/app/relatorios')->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
});

it('kpis e DRE por competência no período: delta bate com a conta à mão; cancelado e outro business fora', function () {
    appRelPlano(true);
    $biz = (int) $this->tenant->id;
    $eu = appRelUsuario($biz, ['financeiro.access']);
    Passport::actingAs($eu, [], 'api');
    $mes = CarbonImmutable::today()->format('Y-m');
    $cat = DB::table('fin_categorias')->insertGetId([
        'business_id' => $biz, 'nome' => 'Categoria APP ' . uniqid(), 'tipo' => 'receita', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $antes = $this->getJson('/api/app/relatorios?periodo=mes&aba=dre')->assertOk()->json();

    appRelTitulo($biz, $eu->id, 'receber', 'aberto', 300.00, $mes, $cat);
    appRelTitulo($biz, $eu->id, 'receber', 'quitado', 200.00, $mes, $cat);
    appRelTitulo($biz, $eu->id, 'pagar', 'aberto', 120.00, $mes);
    appRelTitulo($biz, $eu->id, 'receber', 'cancelado', 999.00, $mes, $cat);           // cancelado: fora
    appRelTitulo($biz, $eu->id, 'receber', 'aberto', 777.00, '2001-01');                 // fora do período
    appRelTitulo((int) $this->outro->id, $eu->id, 'receber', 'aberto', 5000.00, $mes);  // outro business: fora

    $r = $this->getJson('/api/app/relatorios?periodo=mes&aba=dre')->assertOk()->json();
    $d = fn (string $k) => round((float) $r['kpis'][$k] - (float) $antes['kpis'][$k], 2);

    expect($r['periodo']['de'])->toBe(CarbonImmutable::today()->startOfMonth()->toDateString());
    expect($r['periodo']['ate'])->toBe(CarbonImmutable::today()->endOfMonth()->toDateString());
    expect($d('receitas'))->toBe(500.0);
    expect($d('despesas'))->toBe(120.0);
    expect($d('saldo'))->toBe(380.0);
    $nossa = collect($r['dre']['receitas_por_categoria'])->firstWhere('nome', DB::table('fin_categorias')->where('id', $cat)->value('nome'));
    expect((float) $nossa['valor'])->toBe(500.0);
    expect($r['vendas'])->toBeNull();
    expect($r['producao'])->toBeNull();
    expect($r['estoque'])->toBeNull();
});

it('sem Financeiro: kpis e dre vêm null; vendas por dia bate com getSellTotals de hoje e o top cliente é do business', function () {
    appRelPlano(false);
    // Venda criada aqui (com cliente e local): o seed do tenant não tem venda com cliente, e o
    // markTestSkipped de antes fazia este caso sair verde sem rodar (CI 2026-10-02: 1 skipped).
    $ctx = AutosaveContractRunner::setupSellsContext($this);
    $biz = (int) $ctx['business']->id;
    $venda = (int) $ctx['transactionId'];
    $eu = appRelUsuario($biz, ['dashboard.data', 'access_all_locations']);
    Passport::actingAs($eu, [], 'api');
    $hoje = CarbonImmutable::today()->toDateString();
    DB::table('transactions')->where('id', $venda)->update([
        'status' => 'final', 'final_total' => 987654.32, 'transaction_date' => now(),
    ]);
    $contato = DB::table('contacts')->where('id', DB::table('transactions')->where('id', $venda)->value('contact_id'))->first();
    $cliente = $contato->name ?: $contato->supplier_business_name ?: 'Cliente';

    $r = $this->getJson('/api/app/relatorios?periodo=mes&aba=vendas')->assertOk()->json();

    expect($r['kpis'])->toBeNull();
    expect($r['dre'])->toBeNull();
    expect($r['vendas']['receita_por_dia'])->toHaveCount(14);
    expect(end($r['vendas']['receita_por_dia'])['data'])->toBe($hoje);
    $painel = (float) app(TransactionUtil::class)->getSellTotals($biz, $hoje, $hoje, null, null, $eu->permitted_locations($biz))['total_sell_inc_tax'];
    expect((float) end($r['vendas']['receita_por_dia'])['valor'])->toBe(round($painel, 2));
    expect((float) $r['vendas']['top_clientes'][0]['valor'])->toBeGreaterThanOrEqual(987654.32);
    expect($r['vendas']['top_clientes'][0]['nome'])->toBe((string) $cliente);
});

it('estoque: com stock_report.view traz o item abaixo do mínimo (e não o de outro business); sem, o bloco vem null', function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema de estoque ausente.');
    }
    appRelPlano(false);
    $biz = (int) $this->tenant->id;
    // Saldo bem negativo: o relatório ordena por saldo crescente e mostra 20, então o item fica no topo.
    $meu = EstoqueFixture::singleProduct($biz);
    DB::table('products')->where('id', $meu->productId)->update(['alert_quantity' => 10]);
    EstoqueFixture::setStock($meu, 0, EstoqueFixture::locationId($biz), -500);
    $alheio = EstoqueFixture::singleProduct((int) $this->outro->id);
    DB::table('products')->where('id', $alheio->productId)->update(['alert_quantity' => 10]);
    EstoqueFixture::setStock($alheio, 0, EstoqueFixture::locationId((int) $this->outro->id), -600);
    $nomeMeu = DB::table('products')->where('id', $meu->productId)->value('name');
    $nomeAlheio = DB::table('products')->where('id', $alheio->productId)->value('name');
    Passport::actingAs(appRelUsuario($biz, ['stock_report.view', 'access_all_locations']), [], 'api');

    $baixo = collect($this->getJson('/api/app/relatorios?aba=estoque')->assertOk()->json('estoque.baixo'));

    expect($baixo->firstWhere('nome', $nomeMeu))->toMatchArray(['quantidade' => -500, 'minimo' => 10]);
    expect(array_keys($baixo->first()))->toBe(['nome', 'quantidade', 'minimo', 'unidade']);
    expect($baixo->pluck('nome')->all())->not->toContain($nomeAlheio);

    Passport::actingAs(appRelUsuario($biz, ['dashboard.data']), [], 'api');
    expect($this->getJson('/api/app/relatorios?aba=estoque')->assertOk()->json('estoque'))->toBeNull();
});

it('Início: a área relatorios aparece com algum bloco visível e some sem nenhum', function () {
    appRelPlano(false);
    Passport::actingAs(appRelUsuario((int) $this->tenant->id, ['stock_report.view']), [], 'api');
    expect($this->getJson('/api/app/inicio')->assertOk()->json('areas'))->toContain('relatorios');

    Passport::actingAs(appRelUsuario((int) $this->tenant->id, []), [], 'api');
    expect($this->getJson('/api/app/inicio')->assertOk()->json('areas'))->not->toContain('relatorios');
});

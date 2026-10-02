<?php

declare(strict_types=1);

use App\User;
use App\Utils\ModuleUtil;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;

/**
 * API do Financeiro do app das lojas (tela 06) — GET /api/app/financeiro, só leitura.
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §10.1 (formato fechado com o app no
 * rascunho wagnerra23/oimpresso-app#35). NÃO derivado do controller.
 *
 * Os números do resumo são conferidos por DELTA (antes × depois de semear os títulos), contra a
 * conta feita à mão aqui — não contra a mesma query do controller. a_receber/a_pagar também são
 * comparados ao UnificadoService::kpis, o dono do número do Início (dupla prova).
 *
 * Tier 0 (ADR 0093): títulos, baixas e contas do business 2 nunca aparecem no 98. Controle
 * positivo em par: os do próprio business aparecem, senão o "não aparece" seria verde por vácuo.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    // O guard `api` do Passport precisa das chaves mesmo com Passport::actingAs; a lane não as gera.
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['fin_titulos', 'fin_titulo_baixas', 'fin_contas_bancarias', 'accounts'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }

    $this->tenant = $this->seededTenant();
    $this->outro = \App\Business::where('id', '!=', $this->tenant->id)->orderBy('id')->first();
    if (! $this->outro) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }
    Permission::firstOrCreate(['name' => 'financeiro.access', 'guard_name' => 'web']);
});

/** Plano com (ou sem) o módulo Financeiro — o resto do pacote não importa aqui. */
function appFinPlano(bool $comFinanceiro): void
{
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($biz, $perm) => $comFinanceiro && $perm === 'financeiro_module');
    app()->instance(ModuleUtil::class, $mu);
}

function appFinUsuario(int $businessId, bool $acesso): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'APP Fin', 'username' => 'app_fin_' . uniqid(), 'password' => 'x',
        'business_id' => $businessId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $u = User::findOrFail($id);
    if ($acesso) {
        $u->givePermissionTo('financeiro.access');
    }

    return $u;
}

/** @param array<string, mixed> $extra */
function appFinTitulo(int $biz, int $criador, string $tipo, string $status, float $total, float $aberto, string $venc, array $extra = []): int
{
    return DB::table('fin_titulos')->insertGetId($extra + [
        'business_id' => $biz, 'numero' => 'APP-' . strtoupper(Str::random(8)), 'tipo' => $tipo, 'status' => $status,
        'valor_total' => $total, 'valor_aberto' => $aberto, 'emissao' => $venc, 'vencimento' => $venc,
        'competencia_mes' => substr($venc, 0, 7), 'origem' => 'manual', 'created_by' => $criador,
        'cliente_descricao' => 'Cliente APP Fin', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function appFinBaixa(int $biz, int $titulo, int $criador, float $valor, string $data): void
{
    DB::table('fin_titulo_baixas')->insert([
        'business_id' => $biz, 'titulo_id' => $titulo, 'valor_baixa' => $valor, 'data_baixa' => $data,
        'meio_pagamento' => 'pix', 'idempotency_key' => (string) Str::uuid(), 'created_by' => $criador,
        'created_at' => now(),
    ]);
}

function appFinConta(int $biz, int $criador, string $nome, ?float $saldo): int
{
    $acc = DB::table('accounts')->insertGetId([
        'business_id' => $biz, 'name' => $nome, 'account_number' => 'APP-' . uniqid(), 'created_by' => $criador,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return DB::table('fin_contas_bancarias')->insertGetId([
        'business_id' => $biz, 'account_id' => $acc, 'banco_codigo' => '077', 'agencia' => '0001',
        'carteira' => '112', 'beneficiario_documento' => 'DOC-TESTE-APP', 'beneficiario_razao_social' => 'APP Fin',
        'ativo_para_boleto' => false, 'saldo_cached' => $saldo, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('sem token 401; sem financeiro.access 403', function () {
    $this->getJson('/api/app/financeiro')->assertStatus(401);

    appFinPlano(true);
    Passport::actingAs(appFinUsuario((int) $this->tenant->id, false), [], 'api');
    $this->getJson('/api/app/financeiro')->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
});

// Caso próprio: o Laravel guarda o controller na rota depois do 1º request, então trocar o mock
// do plano no meio de um mesmo teste não chega ao controller (o 2º request usaria o mock antigo).
it('sem o módulo Financeiro no plano 403, mesmo com financeiro.access', function () {
    appFinPlano(false);
    Passport::actingAs(appFinUsuario((int) $this->tenant->id, true), [], 'api');
    $this->getJson('/api/app/financeiro')->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
});

it('resumo: o delta bate com a conta à mão, vencido só do a receber, e a_receber/a_pagar = UnificadoService::kpis', function () {
    appFinPlano(true);
    $biz = (int) $this->tenant->id;
    $eu = appFinUsuario($biz, true);
    Passport::actingAs($eu, [], 'api');
    $hoje = CarbonImmutable::today();
    $ontem = $hoje->subDay()->toDateString();
    $amanha = $hoje->addDay()->toDateString();

    $antes = $this->getJson('/api/app/financeiro')->assertOk()->json('resumo');

    appFinTitulo($biz, $eu->id, 'receber', 'aberto', 100.00, 100.00, $amanha);          // a receber, em dia
    appFinTitulo($biz, $eu->id, 'receber', 'parcial', 80.00, 30.00, $ontem);            // a receber, vencido (30 em aberto)
    appFinTitulo($biz, $eu->id, 'pagar', 'aberto', 45.50, 45.50, $ontem);               // a pagar vencido: NÃO entra em vencido
    appFinTitulo($biz, $eu->id, 'receber', 'cancelado', 999.00, 999.00, $ontem);        // cancelado: fora de tudo
    $quitado = appFinTitulo($biz, $eu->id, 'receber', 'quitado', 200.00, 0, $ontem);
    appFinBaixa($biz, $quitado, $eu->id, 200.00, $hoje->toDateString());                // recebido no mês
    $pagoT = appFinTitulo($biz, $eu->id, 'pagar', 'quitado', 60.00, 0, $ontem);
    appFinBaixa($biz, $pagoT, $eu->id, 60.00, $hoje->toDateString());                   // pago no mês
    // Business 2: nada disso pode somar no 98.
    $alheio = appFinTitulo((int) $this->outro->id, $eu->id, 'receber', 'quitado', 5000.00, 0, $ontem);
    appFinBaixa((int) $this->outro->id, $alheio, $eu->id, 5000.00, $hoje->toDateString());
    appFinTitulo((int) $this->outro->id, $eu->id, 'receber', 'aberto', 7000.00, 7000.00, $ontem);

    $r = $this->getJson('/api/app/financeiro')->assertOk()->json('resumo');
    $d = fn (string $k) => round((float) $r[$k] - (float) $antes[$k], 2);

    expect($r['mes'])->toBe($hoje->format('Y-m'));
    expect($d('a_receber'))->toBe(130.0);
    expect($d('vencido'))->toBe(30.0);
    expect($d('a_pagar'))->toBe(45.5);
    expect($d('recebido'))->toBe(200.0);
    expect($d('pago'))->toBe(60.0);
    expect($d('saldo'))->toBe(140.0);
    expect((float) $r['saldo'])->toBe(round((float) $r['recebido'] - (float) $r['pago'], 2));
    expect((float) $r['vencido'])->toBeLessThanOrEqual((float) $r['a_receber']);

    $k = app(\Modules\Financeiro\Services\UnificadoService::class)->kpis($biz);
    expect((float) $r['a_receber'])->toBe((float) $k['total_receber']);
    expect((float) $r['a_pagar'])->toBe((float) $k['total_pagar']);
    expect((float) $this->getJson('/api/app/inicio')->assertOk()->json('financeiro.a_receber'))->toBe((float) $r['a_receber']);
});

it('aba receber: só em aberto do tipo, por vencimento, com status aberto|vencido; outro business fica fora', function () {
    appFinPlano(true);
    $biz = (int) $this->tenant->id;
    $eu = appFinUsuario($biz, true);
    Passport::actingAs($eu, [], 'api');
    $hoje = CarbonImmutable::today();

    $antes = $this->getJson('/api/app/financeiro?aba=receber')->assertOk()->json('contadores');
    // Vencimentos bem no passado: ficam no topo da lista mesmo com outros títulos do seed.
    $velho = appFinTitulo($biz, $eu->id, 'receber', 'parcial', 80.00, 30.00, '2001-01-02', ['parcela_numero' => 2, 'parcela_total' => 3]);
    $maisVelho = appFinTitulo($biz, $eu->id, 'receber', 'aberto', 100.00, 100.00, '2001-01-01');
    $pagar = appFinTitulo($biz, $eu->id, 'pagar', 'aberto', 10.00, 10.00, '2001-01-01');
    $alheio = appFinTitulo((int) $this->outro->id, $eu->id, 'receber', 'aberto', 70.00, 70.00, '2000-01-01');

    $r = $this->getJson('/api/app/financeiro?aba=receber')->assertOk();
    $ids = array_column($r->json('itens'), 'id');

    expect(array_slice($ids, 0, 2))->toBe([$maisVelho, $velho]);
    expect($ids)->not->toContain($pagar);
    expect($ids)->not->toContain($alheio);
    expect($r->json('itens.1'))->toMatchArray([
        'tipo' => 'receber', 'parte' => 'Cliente APP Fin', 'vencimento' => '2001-01-02',
        'pago_em' => null, 'valor' => 30, 'status' => 'vencido',
    ]);
    expect($r->json('itens.1.descricao'))->toContain('parcela 2/3');
    expect($r->json('contadores.receber') - $antes['receber'])->toBe(2);
    expect($r->json('contadores.pagar') - $antes['pagar'])->toBe(1);
    expect($r->json('pagina'))->toBe(1);

    $p = $this->getJson('/api/app/financeiro?aba=pagar')->assertOk();
    expect(array_column($p->json('itens'), 'id'))->toContain($pagar);
    expect(array_column($p->json('itens'), 'tipo'))->each->toBe('pagar');
});

it('aba extrato: liquidados do mês por pago_em desc, valor = o que foi baixado; mês passado e outro business ficam fora', function () {
    appFinPlano(true);
    $biz = (int) $this->tenant->id;
    $eu = appFinUsuario($biz, true);
    Passport::actingAs($eu, [], 'api');
    $hoje = CarbonImmutable::today();
    $mesPassado = $hoje->startOfMonth()->subDay()->toDateString();

    $quitado = appFinTitulo($biz, $eu->id, 'pagar', 'quitado', 90.00, 0, $hoje->toDateString());
    appFinBaixa($biz, $quitado, $eu->id, 90.00, $hoje->toDateString());
    $antigo = appFinTitulo($biz, $eu->id, 'receber', 'quitado', 40.00, 0, $mesPassado);
    appFinBaixa($biz, $antigo, $eu->id, 40.00, $mesPassado);
    $alheio = appFinTitulo((int) $this->outro->id, $eu->id, 'receber', 'quitado', 50.00, 0, $hoje->toDateString());
    appFinBaixa((int) $this->outro->id, $alheio, $eu->id, 50.00, $hoje->toDateString());

    $r = $this->getJson('/api/app/financeiro?aba=extrato')->assertOk();
    $itens = collect($r->json('itens'));
    $ids = $itens->pluck('id')->all();

    expect($ids)->toContain($quitado);
    expect($ids)->not->toContain($antigo);
    expect($ids)->not->toContain($alheio);
    expect($itens->firstWhere('id', $quitado))->toMatchArray([
        'tipo' => 'pagar', 'pago_em' => $hoje->toDateString(), 'valor' => 90, 'status' => 'liquidado',
    ]);
    $datas = $itens->pluck('pago_em')->all();
    $ordenadas = $datas;
    rsort($ordenadas);
    expect($datas)->toBe($ordenadas);
});

it('contas: as do business com saldo (ou null), nunca as de outro business', function () {
    appFinPlano(true);
    $biz = (int) $this->tenant->id;
    $eu = appFinUsuario($biz, true);
    Passport::actingAs($eu, [], 'api');

    $minha = appFinConta($biz, $eu->id, 'Conta APP movimento', 1234.5);
    $semSaldo = appFinConta($biz, $eu->id, 'Conta APP sem saldo', null);
    $alheia = appFinConta((int) $this->outro->id, $eu->id, 'Conta APP alheia', 999.0);

    $contas = collect($this->getJson('/api/app/financeiro')->assertOk()->json('contas'));

    expect($contas->firstWhere('id', $minha))->toBe([
        'id' => $minha, 'nome' => 'Conta APP movimento', 'detalhe' => 'Banco Inter · ag. 0001', 'saldo' => 1234.5,
    ]);
    expect($contas->firstWhere('id', $semSaldo)['saldo'])->toBeNull();
    expect($contas->pluck('id')->all())->not->toContain($alheia);
});

it('Início: a área financeiro aparece com a mesma regra do endpoint', function () {
    appFinPlano(true);
    Passport::actingAs(appFinUsuario((int) $this->tenant->id, true), [], 'api');
    $r = $this->getJson('/api/app/inicio')->assertOk();
    expect($r->json('areas'))->toContain('financeiro');
    expect($r->json('perfil'))->toBe('erp');

    Passport::actingAs(appFinUsuario((int) $this->tenant->id, false), [], 'api');
    expect($this->getJson('/api/app/inicio')->assertOk()->json('areas'))->not->toContain('financeiro');
});

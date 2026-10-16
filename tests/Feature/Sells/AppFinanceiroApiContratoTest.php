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

// ── Filtro avançado (FILTRO_FINANCEIRO do app). Regra de referência: `filtrarLancamentos` em
//    oimpresso-app src/financeiro-filtros.ts — os casos abaixo vêm de lá, não do controller. Cada teste
//    marca os seus títulos com um nome de parte único e filtra por `busca`, para não depender do seed. ──

/** Ids da lista para a query dada, com o marcador do teste em `busca`. */
function appFinIds($test, string $marca, string $query): array
{
    $r = $test->getJson('/api/app/financeiro?busca=' . urlencode($marca) . '&' . $query)->assertOk();

    return array_column($r->json('itens'), 'id');
}

it('situacao=: junta as caixas pedidas (em aberto primeiro, depois liquidados) e conta cada caixa já com o filtro', function () {
    appFinPlano(true);
    $biz = (int) $this->tenant->id;
    $eu = appFinUsuario($biz, true);
    Passport::actingAs($eu, [], 'api');
    $hoje = CarbonImmutable::today()->toDateString();
    $m = 'APPSIT-' . uniqid();

    $rec = appFinTitulo($biz, $eu->id, 'receber', 'aberto', 10.0, 10.0, '2001-03-01', ['cliente_descricao' => $m]);
    $pag = appFinTitulo($biz, $eu->id, 'pagar', 'aberto', 20.0, 20.0, '2001-03-02', ['cliente_descricao' => $m]);
    $pagas = appFinTitulo($biz, $eu->id, 'pagar', 'quitado', 30.0, 0, $hoje, ['cliente_descricao' => $m]);
    appFinBaixa($biz, $pagas, $eu->id, 30.0, $hoje);
    $recebida = appFinTitulo($biz, $eu->id, 'receber', 'quitado', 40.0, 0, $hoje, ['cliente_descricao' => $m]);
    appFinBaixa($biz, $recebida, $eu->id, 40.0, $hoje);

    $r = $this->getJson('/api/app/financeiro?situacao=a_receber,pagas&busca=' . urlencode($m))->assertOk();
    expect(array_column($r->json('itens'), 'id'))->toBe([$rec, $pagas]);
    expect($r->json('contadores_situacao'))->toBe(['a_receber' => 1, 'a_pagar' => 1, 'recebidas' => 1, 'pagas' => 1]);
    expect($r->json('contadores'))->toBe(['receber' => 1, 'pagar' => 1, 'extrato' => 2]);

    // Valor desconhecido é ignorado; nenhuma caixa válida = lista vazia (não cai na aba).
    expect(appFinIds($this, $m, 'situacao=a_pagar,lixo'))->toBe([$pag]);
    expect(appFinIds($this, $m, 'situacao='))->toBe([]);
});

it('registro: excluídos ficam fora por padrão, saem sozinhos com registro=excluidos e junto com registro=todos', function () {
    appFinPlano(true);
    $biz = (int) $this->tenant->id;
    $eu = appFinUsuario($biz, true);
    Passport::actingAs($eu, [], 'api');
    $m = 'APPREG-' . uniqid();

    $ativo = appFinTitulo($biz, $eu->id, 'receber', 'aberto', 10.0, 10.0, '2001-04-01', ['cliente_descricao' => $m]);
    $apagado = appFinTitulo($biz, $eu->id, 'receber', 'aberto', 11.0, 11.0, '2001-04-02', ['cliente_descricao' => $m, 'deleted_at' => now()]);

    expect(appFinIds($this, $m, 'aba=receber'))->toBe([$ativo]);
    $exc = $this->getJson('/api/app/financeiro?registro=excluidos&busca=' . urlencode($m))->assertOk();
    expect(array_column($exc->json('itens'), 'id'))->toBe([$apagado]);
    expect($exc->json('itens.0.excluido'))->toBeTrue();
    expect(appFinIds($this, $m, 'registro=todos'))->toBe([$ativo, $apagado]);
});

it('plano_conta: o grupo pega as contas de baixo; planos_conta vem na ordem do código, só do business', function () {
    if (! Schema::hasTable('fin_planos_conta')) {
        $this->markTestSkipped('Schema ausente (fin_planos_conta).');
    }
    appFinPlano(true);
    $biz = (int) $this->tenant->id;
    $eu = appFinUsuario($biz, true);
    Passport::actingAs($eu, [], 'api');
    $m = 'APPPLANO-' . uniqid();
    $raiz = '9' . random_int(100, 999);
    $plano = fn (int $b, string $cod, string $tipo, int $nivel) => DB::table('fin_planos_conta')->insertGetId([
        'business_id' => $b, 'codigo' => $cod, 'nome' => "Plano {$cod}", 'tipo' => $tipo, 'nivel' => $nivel,
        'natureza' => 'debito', 'aceita_lancamento' => true, 'ativo' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $grupo = $plano($biz, "{$raiz}.2", 'despesa', 2);
    $filha = $plano($biz, "{$raiz}.2.01", 'custo', 3);
    $filha10 = $plano($biz, "{$raiz}.10", 'receita', 2);
    $plano((int) $this->outro->id, "{$raiz}.2.99", 'despesa', 3);

    $noGrupo = appFinTitulo($biz, $eu->id, 'pagar', 'aberto', 5.0, 5.0, '2001-05-01', ['cliente_descricao' => $m, 'plano_conta_id' => $grupo]);
    $naFilha = appFinTitulo($biz, $eu->id, 'pagar', 'aberto', 6.0, 6.0, '2001-05-02', ['cliente_descricao' => $m, 'plano_conta_id' => $filha]);
    appFinTitulo($biz, $eu->id, 'pagar', 'aberto', 7.0, 7.0, '2001-05-03', ['cliente_descricao' => $m, 'plano_conta_id' => $filha10]);

    $r = $this->getJson("/api/app/financeiro?aba=pagar&plano_conta={$raiz}.2&busca=" . urlencode($m))->assertOk();
    expect(array_column($r->json('itens'), 'id'))->toBe([$noGrupo, $naFilha]);
    expect($r->json('itens.1'))->toMatchArray(['plano_codigo' => "{$raiz}.2.01", 'plano_nome' => "Plano {$raiz}.2.01"]);

    $planos = collect($r->json('planos_conta'))->filter(fn ($p) => str_starts_with($p['codigo'], $raiz . '.'))->values()->all();
    expect($planos)->toBe([
        ['codigo' => "{$raiz}.2", 'nome' => "Plano {$raiz}.2", 'tipo' => 'despesa'],
        ['codigo' => "{$raiz}.2.01", 'nome' => "Plano {$raiz}.2.01", 'tipo' => 'despesa'],
        ['codigo' => "{$raiz}.10", 'nome' => "Plano {$raiz}.10", 'tipo' => 'receita'],
    ]);
});

it('valor, documento, período e forma: a regra do app sobre o valor mostrado e a forma realizada', function () {
    appFinPlano(true);
    $biz = (int) $this->tenant->id;
    $eu = appFinUsuario($biz, true);
    Passport::actingAs($eu, [], 'api');
    $hoje = CarbonImmutable::today()->toDateString();
    $m = 'APPVAL-' . uniqid();

    // Parcial: o valor mostrado é o que falta (30), não o total (80).
    $parcial = appFinTitulo($biz, $eu->id, 'receber', 'parcial', 80.0, 30.0, '2001-06-01',
        ['cliente_descricao' => $m, 'numero' => 'DOC-' . random_int(100000, 999999), 'emissao' => '2001-05-10', 'forma_pagamento' => 'boleto']);
    $doc = substr((string) DB::table('fin_titulos')->where('id', $parcial)->value('numero'), 4);
    $grande = appFinTitulo($biz, $eu->id, 'receber', 'aberto', 500.0, 500.0, '2001-06-02',
        ['cliente_descricao' => $m, 'emissao' => '2001-05-20', 'forma_pagamento' => 'boleto']);
    // Previsto boleto, recebido no Pix: a forma realizada manda.
    $quitado = appFinTitulo($biz, $eu->id, 'receber', 'quitado', 50.0, 0, '2001-06-03',
        ['cliente_descricao' => $m, 'emissao' => '2001-05-11', 'forma_pagamento' => 'boleto']);
    appFinBaixa($biz, $quitado, $eu->id, 50.0, $hoje);

    expect(appFinIds($this, $m, 'aba=receber&valor_min=20&valor_max=100'))->toBe([$parcial]);
    expect(appFinIds($this, $m, 'aba=receber&valor_min=31'))->toBe([$grande]);
    expect(appFinIds($this, $m, "aba=receber&documento={$doc}"))->toBe([$parcial]);
    expect(appFinIds($this, $m, 'situacao=a_receber,recebidas&data_campo=emissao&de=2001-05-10&ate=2001-05-11'))->toBe([$parcial, $quitado]);
    // Pagamento: título em aberto não tem data de pagamento, então não entra no período.
    expect(appFinIds($this, $m, "situacao=a_receber,recebidas&data_campo=pagamento&de={$hoje}&ate={$hoje}"))->toBe([$quitado]);
    expect(appFinIds($this, $m, 'situacao=a_receber,recebidas&forma_pagamento=Pix'))->toBe([$quitado]);
    expect(appFinIds($this, $m, 'situacao=a_receber,recebidas&forma_pagamento=Boleto'))->toBe([$parcial, $grande]);
    expect(appFinIds($this, $m, 'situacao=a_receber,recebidas&forma_pagamento=Vale'))->toBe([]);

    $r = $this->getJson('/api/app/financeiro?situacao=recebidas&busca=' . urlencode($m))->assertOk();
    expect($r->json('itens.0'))->toMatchArray(['id' => $quitado, 'forma_pagamento' => 'Pix', 'emissao' => '2001-05-11', 'competencia' => '2001-06', 'excluido' => false]);
    expect($r->json('formas_pagamento'))->toContain('Pix', 'Boleto', 'Cartão de crédito');
});

it('conta, NF e nosso número; o filtro nunca traz título de outro business e não mexe no resumo', function () {
    appFinPlano(true);
    $biz = (int) $this->tenant->id;
    $eu = appFinUsuario($biz, true);
    Passport::actingAs($eu, [], 'api');
    $m = 'APPCONTA-' . uniqid();
    $conta = appFinConta($biz, $eu->id, 'Conta APP filtro', null);

    $comConta = appFinTitulo($biz, $eu->id, 'receber', 'aberto', 10.0, 10.0, '2001-07-01',
        ['cliente_descricao' => $m, 'conta_bancaria_id' => $conta, 'metadata' => json_encode(['nfe_numero' => '004512'])]);
    $comBoleto = appFinTitulo($biz, $eu->id, 'receber', 'aberto', 12.0, 12.0, '2001-07-02', ['cliente_descricao' => $m]);
    DB::table('fin_boleto_remessas')->insert([
        'business_id' => $biz, 'titulo_id' => $comBoleto, 'conta_bancaria_id' => $conta, 'nosso_numero' => '00077123',
        'linha_digitavel' => str_repeat('1', 47), 'codigo_barras' => str_repeat('2', 44), 'valor_total' => 12.0,
        'vencimento' => '2001-07-02', 'strategy' => 'cnab_direct', 'idempotency_key' => (string) Str::uuid(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    // O mesmo marcador e a mesma NF no business 2: não pode aparecer.
    $alheio = appFinTitulo((int) $this->outro->id, $eu->id, 'receber', 'aberto', 99.0, 99.0, '2001-07-01',
        ['cliente_descricao' => $m, 'metadata' => json_encode(['nfe_numero' => '004512'])]);

    $antes = $this->getJson('/api/app/financeiro')->assertOk()->json('resumo');
    expect(appFinIds($this, $m, "aba=receber&conta_id={$conta}"))->toBe([$comConta]);
    expect(appFinIds($this, $m, 'aba=receber&nf=4512'))->toBe([$comConta]);
    expect(appFinIds($this, $m, 'aba=receber&nosso_numero=77123'))->toBe([$comBoleto]);
    expect(appFinIds($this, $m, 'aba=receber'))->toBe([$comConta, $comBoleto]);
    expect(appFinIds($this, $m, 'registro=todos&situacao=a_receber'))->not->toContain($alheio);

    $filtrado = $this->getJson('/api/app/financeiro?aba=receber&valor_min=999999&busca=' . urlencode($m))->assertOk();
    expect($filtrado->json('itens'))->toBe([]);
    expect($filtrado->json('resumo'))->toBe($antes);
    $item = collect($this->getJson('/api/app/financeiro?aba=receber&busca=' . urlencode($m))->json('itens'))->firstWhere('id', $comBoleto);
    expect($item['nosso_numero'])->toBe('00077123');
});

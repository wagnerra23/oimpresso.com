<?php

declare(strict_types=1);

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;

/**
 * "+ Novo" do Financeiro do app (tela 06, chave NOVO_LANCAMENTO): POST /api/app/financeiro/lancamentos.
 *
 * Contrato: corpo `NovoLancamento` e a regra `dividirParcelas` de oimpresso-app src/lancamento-regras.ts, e a
 * resposta da demo ({id, ids, recorrencia_id}) — NÃO derivado do controller.
 *
 * REGRA MESTRE (valor) — dupla prova: o que entrou é conferido (1) somando valor_total/valor_aberto direto no
 * banco e (2) pelo a_receber/a_pagar do UnificadoService::kpis (o número do Início e do cockpit), por DELTA
 * antes × depois. Idempotência: repetir a chave não cria nada a mais.
 *
 * Tier 0 (ADR 0093): plano, pessoa e conta de outro business são recusados; o título nasce no business do token.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['fin_titulos', 'fin_planos_conta', 'fin_contas_bancarias', 'app_idempotencia'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }
    $this->tenant = $this->seededTenant();
    $this->outro = \App\Business::where('id', '!=', $this->tenant->id)->orderBy('id')->first();
    if (! $this->outro) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }
    foreach (['financeiro.access', 'financeiro.lancamentos.create'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')->andReturnUsing(fn ($biz, $perm) => $perm === 'financeiro_module');
    app()->instance(ModuleUtil::class, $mu);

    $biz = (int) $this->tenant->id;
    $raiz = '8' . random_int(100, 999);
    $plano = fn (int $b, string $cod, string $tipo) => DB::table('fin_planos_conta')->insertGetId([
        'business_id' => $b, 'codigo' => $cod, 'nome' => "Plano {$cod}", 'tipo' => $tipo, 'nivel' => substr_count($cod, '.') + 1,
        'natureza' => 'credito', 'aceita_lancamento' => true, 'ativo' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $plano($biz, "{$raiz}.1", 'receita');
    $plano($biz, "{$raiz}.1.01", 'receita');
    $plano($biz, "{$raiz}.2.01", 'despesa');
    $plano((int) $this->outro->id, "{$raiz}.9.01", 'receita');
    $this->raiz = $raiz;
    $this->eu = appNovoUsuario($biz, ['financeiro.access', 'financeiro.lancamentos.create']);
});

/** @param list<string> $permissoes */
function appNovoUsuario(int $biz, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'APP Novo', 'username' => 'app_novo_' . uniqid(), 'password' => 'x',
        'business_id' => $biz, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $u = User::findOrFail($id);
    foreach ($permissoes as $p) {
        $u->givePermissionTo($p);
    }

    return $u;
}

/** @param array<string, mixed> $extra */
function appNovoCorpo(string $raiz, array $extra = []): array
{
    return $extra + [
        'tipo' => 'receber', 'descricao' => 'Banner fachada APP', 'contato_id' => null, 'valor' => 1250.5,
        'vencimento' => '2031-01-31', 'emissao' => '2031-01-10', 'competencia' => '2031-01', 'forma_pagamento' => 'Pix',
        'conta_id' => null, 'plano_codigo' => "{$raiz}.1.01", 'documento' => 'PED-77', 'nf_numero' => '4512',
        'observacao' => null, 'parcelas' => null, 'recorrencia' => null,
    ];
}

/** Soma de valor_total e valor_aberto dos títulos ativos do tipo, no business, em centavos (prova 1). */
function appNovoSoma(int $biz, string $tipo): array
{
    $r = DB::table('fin_titulos')->where('business_id', $biz)->where('tipo', $tipo)->whereNull('deleted_at')->where('status', '!=', 'cancelado')
        ->selectRaw('COALESCE(SUM(valor_total),0) as t, COALESCE(SUM(valor_aberto),0) as a, COUNT(*) as n')->first();

    return ['total' => (int) round($r->t * 100), 'aberto' => (int) round($r->a * 100), 'n' => (int) $r->n];
}

function appNovoKpis(int $biz): array
{
    $k = app(\Modules\Financeiro\Services\UnificadoService::class)->kpis($biz);

    return ['receber' => (int) round($k['total_receber'] * 100), 'pagar' => (int) round($k['total_pagar'] * 100)];
}

it('sem token 401; sem financeiro 403; sem permissão de criar 403 e nada é gravado', function () {
    $this->postJson('/api/app/financeiro/lancamentos', appNovoCorpo($this->raiz))->assertStatus(401);
    $biz = (int) $this->tenant->id;
    $antes = appNovoSoma($biz, 'receber');

    Passport::actingAs(appNovoUsuario($biz, []), [], 'api');
    $this->postJson('/api/app/financeiro/lancamentos', appNovoCorpo($this->raiz), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
    Passport::actingAs(appNovoUsuario($biz, ['financeiro.access']), [], 'api');
    $this->postJson('/api/app/financeiro/lancamentos', appNovoCorpo($this->raiz), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');

    expect(appNovoSoma($biz, 'receber'))->toBe($antes);
});

it('único: 201 {id, ids, recorrencia_id}; grava em aberto o valor exato; banco e kpis andam o valor exato, ao centavo (dupla prova)', function () {
    $biz = (int) $this->tenant->id;
    Passport::actingAs($this->eu, [], 'api');
    $somaAntes = appNovoSoma($biz, 'receber');
    $kpiAntes = appNovoKpis($biz);

    $r = $this->postJson('/api/app/financeiro/lancamentos', appNovoCorpo($this->raiz), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(201);
    $id = $r->json('id');
    expect($r->json())->toBe(['id' => $id, 'ids' => [$id], 'recorrencia_id' => null]);

    $t = DB::table('fin_titulos')->where('id', $id)->first();
    expect((int) $t->business_id)->toBe($biz);
    expect([$t->tipo, $t->status, (string) $t->valor_total, (string) $t->valor_aberto, substr((string) $t->vencimento, 0, 10),
        substr((string) $t->emissao, 0, 10), $t->competencia_mes, $t->forma_pagamento, $t->origem])
        ->toBe(['receber', 'aberto', '1250.5000', '1250.5000', '2031-01-31', '2031-01-10', '2031-01', 'pix', 'manual']);
    expect($t->numero)->toStartWith('R-');

    // Prova 1 (banco) e prova 2 (UnificadoService::kpis): o mesmo delta, ao centavo.
    $somaDepois = appNovoSoma($biz, 'receber');
    expect($somaDepois['total'] - $somaAntes['total'])->toBe(125050);
    expect($somaDepois['aberto'] - $somaAntes['aberto'])->toBe(125050);
    expect($somaDepois['n'] - $somaAntes['n'])->toBe(1);
    expect(appNovoKpis($biz)['receber'] - $kpiAntes['receber'])->toBe(125050);
    expect(appNovoKpis($biz)['pagar'] - $kpiAntes['pagar'])->toBe(0);

    // A lista do app mostra o que foi digitado.
    $item = collect($this->getJson('/api/app/financeiro?aba=receber&documento=PED-77&busca=' . urlencode('Banner fachada APP'))
        ->assertOk()->json('itens'))->firstWhere('id', $id);
    expect($item)->toMatchArray(['descricao' => 'Banner fachada APP', 'documento' => 'PED-77', 'nf_numero' => '4512',
        'valor' => 1250.5, 'forma_pagamento' => 'Pix', 'plano_codigo' => "{$this->raiz}.1.01"]);
});

it('parcelado: o total é dividido como o app (diferença na 1ª), uma parcela por mês sem estourar o dia; soma exata', function () {
    $biz = (int) $this->tenant->id;
    Passport::actingAs($this->eu, [], 'api');
    $somaAntes = appNovoSoma($biz, 'pagar');
    $kpiAntes = appNovoKpis($biz);

    $r = $this->postJson('/api/app/financeiro/lancamentos', appNovoCorpo($this->raiz, [
        'tipo' => 'pagar', 'valor' => 100, 'parcelas' => 3, 'plano_codigo' => "{$this->raiz}.2.01", 'descricao' => 'Lona APP',
    ]), ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(201);
    $ids = $r->json('ids');
    expect($ids)->toHaveCount(3);
    expect($r->json('id'))->toBe($ids[0]);

    $linhas = DB::table('fin_titulos')->whereIn('id', $ids)->orderBy('id')->get();
    expect($linhas->map(fn ($t) => (string) $t->valor_total)->all())->toBe(['33.3400', '33.3300', '33.3300']);
    expect($linhas->map(fn ($t) => substr((string) $t->vencimento, 0, 10))->all())->toBe(['2031-01-31', '2031-02-28', '2031-03-31']);
    expect($linhas->map(fn ($t) => $t->parcela_numero . '/' . $t->parcela_total)->all())->toBe(['1/3', '2/3', '3/3']);
    expect($linhas->map(fn ($t) => json_decode($t->metadata, true)['documento'])->all())->toBe(['PED-77/1', 'PED-77/2', 'PED-77/3']);
    expect($linhas->pluck('numero')->unique()->count())->toBe(3);

    $somaDepois = appNovoSoma($biz, 'pagar');
    expect($somaDepois['total'] - $somaAntes['total'])->toBe(10000);
    expect($somaDepois['aberto'] - $somaAntes['aberto'])->toBe(10000);
    expect(appNovoKpis($biz)['pagar'] - $kpiAntes['pagar'])->toBe(10000);
});

it('idempotência: mesma chave e corpo devolve 200 com os mesmos ids e não grava de novo; corpo diferente 422 sem gravar', function () {
    $biz = (int) $this->tenant->id;
    Passport::actingAs($this->eu, [], 'api');
    $chave = (string) Str::uuid();

    $primeira = $this->postJson('/api/app/financeiro/lancamentos', appNovoCorpo($this->raiz, ['parcelas' => 2]), ['Idempotency-Key' => $chave])
        ->assertStatus(201)->json();
    $depois = appNovoSoma($biz, 'receber');

    $this->postJson('/api/app/financeiro/lancamentos', appNovoCorpo($this->raiz, ['parcelas' => 2]), ['Idempotency-Key' => $chave])
        ->assertStatus(200)->assertExactJson($primeira);
    expect(appNovoSoma($biz, 'receber'))->toBe($depois);

    $this->postJson('/api/app/financeiro/lancamentos', appNovoCorpo($this->raiz, ['valor' => 999]), ['Idempotency-Key' => $chave])
        ->assertStatus(422)->assertJsonPath('erro', 'idempotencia_conflito');
    expect(appNovoSoma($biz, 'receber'))->toBe($depois);

    $this->postJson('/api/app/financeiro/lancamentos', appNovoCorpo($this->raiz))->assertStatus(422)->assertJsonPath('campos.idempotency_key', 'Envie o header Idempotency-Key.');
});

it('recusa sem gravar: grupo, plano de outro tipo, plano/pessoa/conta de outro business, 3 casas, recorrente', function () {
    $biz = (int) $this->tenant->id;
    $outro = (int) $this->outro->id;
    Passport::actingAs($this->eu, [], 'api');
    $antes = [appNovoSoma($biz, 'receber'), appNovoSoma($biz, 'pagar'), appNovoSoma($outro, 'receber')];

    $contatoAlheio = DB::table('contacts')->insertGetId([
        'business_id' => $outro, 'type' => 'customer', 'name' => 'Alheio APP Novo', 'contact_status' => 'active',
        'created_by' => $this->eu->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $contaAlheia = DB::table('fin_contas_bancarias')->insertGetId([
        'business_id' => $outro, 'account_id' => DB::table('accounts')->insertGetId([
            'business_id' => $outro, 'name' => 'Conta APP alheia', 'account_number' => 'APP-' . uniqid(),
            'created_by' => $this->eu->id, 'created_at' => now(), 'updated_at' => now(),
        ]), 'banco_codigo' => '077', 'agencia' => '0001', 'carteira' => '112',
        'beneficiario_documento' => 'DOC-TESTE-APP', 'beneficiario_razao_social' => 'APP Novo', 'ativo_para_boleto' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $casos = [
        'plano' => ['plano_codigo' => "{$this->raiz}.1"],
        'plano ' => ['plano_codigo' => "{$this->raiz}.2.01"],
        ' plano' => ['plano_codigo' => "{$this->raiz}.9.01"],
        'contato_id' => ['contato_id' => $contatoAlheio],
        'conta_id' => ['conta_id' => $contaAlheia],
        'valor' => ['valor' => 10.101],
        'recorrencia' => ['recorrencia' => ['frequencia' => 'mensal', 'dia_geracao' => 5, 'termino' => null, 'contrato' => null]],
        'vencimento' => ['vencimento' => '2031-01-01'],
        'forma_pagamento' => ['forma_pagamento' => 'Vale'],
    ];
    foreach ($casos as $campo => $muda) {
        $this->postJson('/api/app/financeiro/lancamentos', appNovoCorpo($this->raiz, $muda), ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(422)->assertJsonPath('erro', 'validacao')->assertJsonStructure(['campos' => [trim($campo)]]);
    }

    expect([appNovoSoma($biz, 'receber'), appNovoSoma($biz, 'pagar'), appNovoSoma($outro, 'receber')])->toBe($antes);
});

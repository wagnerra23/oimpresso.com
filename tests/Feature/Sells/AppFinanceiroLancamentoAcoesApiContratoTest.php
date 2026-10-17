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
 * Detalhe e ações do lançamento do app (tela 06, chave LANCAMENTO_ACOES):
 * GET/PATCH /api/app/financeiro/lancamentos/{id} e POST .../baixar|estornar|excluir|reativar, GET .../recibo.
 *
 * Contrato: `LancamentoDetalhe`, `BaixaLancamento`, `AlteracaoLancamento` e `ReciboDados` do oimpresso-app
 * (src/api.ts, src/lancamento-regras.ts, src/recibo.ts) e as regras da demo (src/demo.ts) — NÃO do controller.
 *
 * REGRA MESTRE (valor) — cada ação que mexe em dinheiro é conferida por caminhos independentes, por delta:
 * (1) somas no banco: valor_total e valor_aberto dos títulos ativos e as baixas ativas (sem estorno e não
 * estornadas); (2) UnificadoService::kpis (a_receber/a_pagar do Início); (3) `resumo.recebido` do app.
 * Tier 0 (ADR 0093): título de outro business = 404 em toda ação, e nada muda nele.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['fin_titulos', 'fin_titulo_baixas', 'fin_planos_conta', 'app_idempotencia', 'activity_log'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }
    $this->tenant = $this->seededTenant();
    $this->outro = \App\Business::where('id', '!=', $this->tenant->id)->orderBy('id')->first();
    if (! $this->outro) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }
    $perms = ['financeiro.access', 'financeiro.lancamentos.create', 'financeiro.contas_receber.baixar', 'financeiro.contas_pagar.pagar'];
    foreach ($perms as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')->andReturnUsing(fn ($biz, $perm) => $perm === 'financeiro_module');
    app()->instance(ModuleUtil::class, $mu);

    $this->raiz = '7' . random_int(100, 999);
    foreach ([[$this->raiz . '.1.01', 'receita'], [$this->raiz . '.1.02', 'receita']] as [$cod, $tipo]) {
        DB::table('fin_planos_conta')->insert([
            'business_id' => $this->tenant->id, 'codigo' => $cod, 'nome' => "Plano {$cod}", 'tipo' => $tipo, 'nivel' => 3,
            'natureza' => 'credito', 'aceita_lancamento' => true, 'ativo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    $this->plano = (int) DB::table('fin_planos_conta')->where('business_id', $this->tenant->id)->where('codigo', $this->raiz . '.1.01')->value('id');
    $id = DB::table('users')->insertGetId([
        'first_name' => 'APP Acao', 'username' => 'app_acao_' . uniqid(), 'password' => 'x',
        'business_id' => $this->tenant->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->eu = User::findOrFail($id);
    $this->eu->givePermissionTo($perms);
    Passport::actingAs($this->eu, [], 'api');
    $this->hoje = CarbonImmutable::today()->toDateString();
});

function appAcaoTitulo($test, int $centavos, array $extra = []): int
{
    $valor = sprintf('%d.%02d', intdiv($centavos, 100), $centavos % 100);

    return (int) DB::table('fin_titulos')->insertGetId($extra + [
        'business_id' => $test->tenant->id, 'numero' => 'APPAC-' . strtoupper(Str::random(8)), 'tipo' => 'receber', 'status' => 'aberto',
        'valor_total' => $valor, 'valor_aberto' => $valor, 'emissao' => '2031-02-01', 'vencimento' => '2031-02-10',
        'competencia_mes' => '2031-02', 'origem' => 'manual', 'plano_conta_id' => $test->plano, 'created_by' => $test->eu->id,
        'cliente_descricao' => 'Cliente APP Ação', 'metadata' => json_encode(['descricao' => 'Fachada APP']),
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Retrato do dinheiro do business em centavos, por três caminhos. */
function appAcaoRetrato($test): array
{
    $biz = (int) $test->tenant->id;
    // Excluído no app = cancelado: fica fora das somas (como nos kpis).
    $tit = DB::table('fin_titulos')->where('business_id', $biz)->where('tipo', 'receber')->whereNull('deleted_at')->where('status', '!=', 'cancelado')
        ->selectRaw('COALESCE(SUM(valor_total),0) t, COALESCE(SUM(CASE WHEN status IN (\'aberto\',\'parcial\') THEN valor_aberto ELSE 0 END),0) a')->first();
    $baixas = DB::table('fin_titulo_baixas as b')->where('b.business_id', $biz)->whereNull('b.estorno_de_id')
        ->whereNotExists(fn ($e) => $e->from('fin_titulo_baixas as e')->whereColumn('e.estorno_de_id', 'b.id'))->sum('b.valor_baixa');
    $k = app(\Modules\Financeiro\Services\UnificadoService::class)->kpis($biz);
    $resumo = $test->getJson('/api/app/financeiro')->assertOk()->json('resumo');

    return [
        'total' => (int) round($tit->t * 100), 'aberto' => (int) round($tit->a * 100), 'baixado' => (int) round($baixas * 100),
        'kpi_receber' => (int) round($k['total_receber'] * 100), 'recebido' => (int) round($resumo['recebido'] * 100),
    ];
}

function appAcaoDelta(array $antes, array $depois): array
{
    return array_map(fn ($k) => $depois[$k] - $antes[$k], array_combine(array_keys($antes), array_keys($antes)));
}

function appAcao($test, string $metodo, string $caminho, array $corpo = [], ?string $chave = null)
{
    return $test->json($metodo, '/api/app/financeiro/lancamentos/' . $caminho, $corpo, ['Idempotency-Key' => $chave ?? (string) Str::uuid()]);
}

it('detalhe: formato LancamentoDetalhe; outro business 404 em ler e em toda ação, sem mudar nada', function () {
    $id = appAcaoTitulo($this, 100000, ['observacoes' => 'obs APP', 'parcela_numero' => 2, 'parcela_total' => 3]);
    $d = $this->getJson("/api/app/financeiro/lancamentos/{$id}")->assertOk()->json();
    expect($d)->toMatchArray([
        'id' => $id, 'tipo' => 'receber', 'descricao' => 'Fachada APP', 'valor' => 1000, 'status' => 'aberto', 'excluido' => false,
        'observacao' => 'obs APP', 'parcela' => '2/3', 'restante' => null, 'origem_id' => null, 'historico' => [], 'motivo_exclusao' => null,
    ]);
    expect(array_keys($d))->toContain('parte_id', 'recorrencia_id', 'conta_nome', 'plano_codigo');

    $alheio = (int) DB::table('fin_titulos')->insertGetId([
        'business_id' => $this->outro->id, 'numero' => 'APPAC-X' . Str::random(5), 'tipo' => 'receber', 'status' => 'aberto',
        'valor_total' => 50, 'valor_aberto' => 50, 'emissao' => '2031-02-01', 'vencimento' => '2031-02-10', 'competencia_mes' => '2031-02',
        'origem' => 'manual', 'created_by' => $this->eu->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $antes = (array) DB::table('fin_titulos')->where('id', $alheio)->first();
    $this->getJson("/api/app/financeiro/lancamentos/{$alheio}")->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
    appAcao($this, 'POST', "{$alheio}/baixar", ['pago_em' => $this->hoje, 'conta_id' => null, 'forma_pagamento' => 'Pix', 'valor_pago' => 50, 'vencimento_restante' => null])->assertStatus(404);
    appAcao($this, 'POST', "{$alheio}/excluir", ['motivo' => 'x'])->assertStatus(404);
    $this->getJson("/api/app/financeiro/lancamentos/{$alheio}/recibo")->assertStatus(404);
    expect((array) DB::table('fin_titulos')->where('id', $alheio)->first())->toBe($antes);
    expect(DB::table('fin_titulo_baixas')->where('titulo_id', $alheio)->count())->toBe(0);
});

it('baixa total: liquida o lançamento; baixado +1.000,00, aberto e kpi −1.000,00, recebido do mês +1.000,00; repetir a chave não baixa de novo', function () {
    $id = appAcaoTitulo($this, 100000);
    $antes = appAcaoRetrato($this);
    $chave = (string) Str::uuid();
    $corpo = ['pago_em' => $this->hoje, 'conta_id' => null, 'forma_pagamento' => 'Pix', 'valor_pago' => 1000, 'vencimento_restante' => null];

    $d = appAcao($this, 'POST', "{$id}/baixar", $corpo, $chave)->assertOk()->json();
    expect($d)->toMatchArray(['status' => 'liquidado', 'pago_em' => $this->hoje, 'valor' => 1000, 'forma_pagamento' => 'Pix', 'restante' => null]);
    expect(end($d['historico'])['texto'])->toBe('Recebido em ' . implode('/', array_reverse(explode('-', $this->hoje))));

    $delta = appAcaoDelta($antes, appAcaoRetrato($this));
    expect($delta)->toBe(['total' => 0, 'aberto' => -100000, 'baixado' => 100000, 'kpi_receber' => -100000, 'recebido' => 100000]);

    appAcao($this, 'POST', "{$id}/baixar", $corpo, $chave)->assertOk()->assertJsonPath('status', 'liquidado');
    expect(DB::table('fin_titulo_baixas')->where('titulo_id', $id)->count())->toBe(1);
    appAcao($this, 'POST', "{$id}/baixar", $corpo)->assertStatus(409)->assertJsonPath('erro', 'estado');
    expect(appAcaoDelta($antes, appAcaoRetrato($this)))->toBe($delta);
});

it('baixa parcial: este fica liquidado com o pago, o restante vira lançamento novo em aberto; somas fecham e o recibo traz o restante', function () {
    $id = appAcaoTitulo($this, 100000);
    $antes = appAcaoRetrato($this);
    $vence = '2031-03-15';

    $d = appAcao($this, 'POST', "{$id}/baixar", ['pago_em' => $this->hoje, 'conta_id' => null, 'forma_pagamento' => 'Dinheiro',
        'valor_pago' => 400, 'vencimento_restante' => $vence])->assertOk()->json();
    expect($d)->toMatchArray(['status' => 'liquidado', 'valor' => 400]);
    $restanteId = $d['restante']['id'];
    expect($d['restante'])->toBe(['id' => $restanteId, 'valor' => 600, 'vencimento' => $vence]);

    $r = $this->getJson("/api/app/financeiro/lancamentos/{$restanteId}")->assertOk()->json();
    expect($r)->toMatchArray(['status' => 'aberto', 'valor' => 600, 'vencimento' => $vence, 'origem_id' => $id, 'descricao' => 'Fachada APP (restante)']);
    expect((string) DB::table('fin_titulos')->where('id', $id)->value('valor_total'))->toBe('400.0000');

    expect(appAcaoDelta($antes, appAcaoRetrato($this)))
        ->toBe(['total' => 0, 'aberto' => -40000, 'baixado' => 40000, 'kpi_receber' => -40000, 'recebido' => 40000]);

    $recibo = $this->getJson("/api/app/financeiro/lancamentos/{$id}/recibo")->assertOk()->json();
    expect($recibo)->toMatchArray(['tipo' => 'receber', 'valor' => 400, 'data' => $this->hoje, 'referente' => 'Fachada APP',
        'forma' => 'Dinheiro', 'restante' => ['valor' => 600, 'vencimento' => $vence]]);
    expect($recibo['numero'])->toBe('REC-' . substr($this->hoje, 0, 4) . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT));
    $this->getJson("/api/app/financeiro/lancamentos/{$restanteId}/recibo")->assertStatus(409);
});

it('baixa recusada sem mudar nada: acima do aberto, 3 casas, data futura, parcial sem vencimento do restante, sem permissão', function () {
    $id = appAcaoTitulo($this, 100000);
    $antes = appAcaoRetrato($this);
    $base = ['pago_em' => $this->hoje, 'conta_id' => null, 'forma_pagamento' => null, 'valor_pago' => 1000, 'vencimento_restante' => null];
    appAcao($this, 'POST', "{$id}/baixar", ['valor_pago' => 1000.01] + $base)->assertStatus(422)->assertJsonStructure(['campos' => ['valor']]);
    appAcao($this, 'POST', "{$id}/baixar", ['valor_pago' => 10.101] + $base)->assertStatus(422)->assertJsonStructure(['campos' => ['valor']]);
    appAcao($this, 'POST', "{$id}/baixar", ['pago_em' => CarbonImmutable::today()->addDay()->toDateString()] + $base)->assertStatus(422)->assertJsonStructure(['campos' => ['pago_em']]);
    appAcao($this, 'POST', "{$id}/baixar", ['valor_pago' => 400] + $base)->assertStatus(422)->assertJsonStructure(['campos' => ['vencimento_restante']]);
    $this->postJson("/api/app/financeiro/lancamentos/{$id}/baixar", $base)->assertStatus(422)->assertJsonStructure(['campos' => ['idempotency_key']]);

    $semBaixa = User::findOrFail(DB::table('users')->insertGetId([
        'first_name' => 'APP Acao', 'username' => 'app_acao_sb_' . uniqid(), 'password' => 'x',
        'business_id' => $this->tenant->id, 'created_at' => now(), 'updated_at' => now(),
    ]));
    $semBaixa->givePermissionTo(['financeiro.access']);
    Passport::actingAs($semBaixa, [], 'api');
    appAcao($this, 'POST', "{$id}/baixar", $base)->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
    Passport::actingAs($this->eu, [], 'api');

    expect(appAcaoRetrato($this))->toBe($antes);
    expect(DB::table('fin_titulo_baixas')->where('titulo_id', $id)->count())->toBe(0);
});

it('retornar um parcial: estorno ledger, o restante volta para este e tudo fica como antes da baixa; restante já pago bloqueia', function () {
    $id = appAcaoTitulo($this, 100000);
    $antes = appAcaoRetrato($this);
    $d = appAcao($this, 'POST', "{$id}/baixar", ['pago_em' => $this->hoje, 'conta_id' => null, 'forma_pagamento' => 'Pix',
        'valor_pago' => 400, 'vencimento_restante' => '2031-03-15'])->assertOk()->json();
    $restanteId = $d['restante']['id'];

    $e = appAcao($this, 'POST', "{$id}/estornar")->assertOk()->json();
    expect($e)->toMatchArray(['status' => 'aberto', 'valor' => 1000, 'pago_em' => null, 'restante' => null]);
    expect(end($e['historico'])['texto'])->toContain('Retornado a receber')->toContain("o restante #{$restanteId} voltou para este lançamento");

    // Tudo como antes da baixa, pelos três caminhos — inclusive o recebido do mês (a baixa estornada não conta mais).
    expect(appAcaoRetrato($this))->toBe($antes);
    $baixa = DB::table('fin_titulo_baixas')->where('titulo_id', $id)->whereNull('estorno_de_id')->first();
    expect((string) DB::table('fin_titulo_baixas')->where('estorno_de_id', $baixa->id)->value('valor_baixa'))->toBe('-400.0000');
    $r = $this->getJson("/api/app/financeiro/lancamentos/{$restanteId}")->assertOk()->json();
    expect($r)->toMatchArray(['excluido' => true, 'motivo_exclusao' => "Juntado de volta ao lançamento #{$id}"]);
    appAcao($this, 'POST', "{$restanteId}/reativar")->assertStatus(409);

    // De novo, agora pagando o restante: retornar o original fica bloqueado até retornar o restante.
    $d2 = appAcao($this, 'POST', "{$id}/baixar", ['pago_em' => $this->hoje, 'conta_id' => null, 'forma_pagamento' => 'Pix',
        'valor_pago' => 300, 'vencimento_restante' => '2031-04-01'])->assertOk()->json();
    $resto2 = $d2['restante']['id'];
    appAcao($this, 'POST', "{$resto2}/baixar", ['pago_em' => $this->hoje, 'conta_id' => null, 'forma_pagamento' => 'Pix',
        'valor_pago' => 700, 'vencimento_restante' => null])->assertOk();
    $meio = appAcaoRetrato($this);
    appAcao($this, 'POST', "{$id}/estornar")->assertStatus(409)->assertJsonPath('erro', 'estado');
    expect(appAcaoRetrato($this))->toBe($meio);
});

it('retornar baixa que veio de pagamento da venda é recusado', function () {
    $id = appAcaoTitulo($this, 5000, ['status' => 'quitado', 'valor_aberto' => 0, 'origem' => 'venda']);
    DB::table('fin_titulo_baixas')->insert([
        'business_id' => $this->tenant->id, 'titulo_id' => $id, 'valor_baixa' => 50, 'data_baixa' => $this->hoje, 'meio_pagamento' => 'pix',
        'idempotency_key' => (string) Str::uuid(), 'transaction_payment_id' => 999999, 'created_by' => $this->eu->id, 'created_at' => now(),
    ]);
    appAcao($this, 'POST', "{$id}/estornar")->assertStatus(409)->assertJsonPath('erro', 'estado');
    expect(DB::table('fin_titulo_baixas')->where('titulo_id', $id)->count())->toBe(1);
});

it('alterar: grava o novo valor e registra antes → depois; liquidado 409; valor de lançamento de venda se altera na origem', function () {
    $id = appAcaoTitulo($this, 100000);
    $antes = appAcaoRetrato($this);
    $corpo = ['descricao' => 'Fachada APP', 'contato_id' => null, 'valor' => 1200, 'vencimento' => '2031-02-20', 'emissao' => '2031-02-01',
        'competencia' => '2031-02', 'forma_pagamento' => null, 'conta_id' => null, 'plano_codigo' => $this->raiz . '.1.02',
        'documento' => 'DOC-9', 'nf_numero' => null, 'observacao' => null, 'contrato' => null];

    $d = appAcao($this, 'PATCH', (string) $id, $corpo)->assertOk()->json();
    expect($d)->toMatchArray(['valor' => 1200, 'vencimento' => '2031-02-20', 'documento' => 'DOC-9', 'plano_codigo' => $this->raiz . '.1.02']);
    $texto = end($d['historico'])['texto'];
    expect($texto)->toStartWith('Alterado: ')->toContain('valor R$ 1.000,00 → R$ 1.200,00')->toContain('vencimento 10/02/2031 → 20/02/2031');
    expect(appAcaoDelta($antes, appAcaoRetrato($this)))->toBe(['total' => 20000, 'aberto' => 20000, 'baixado' => 0, 'kpi_receber' => 20000, 'recebido' => 0]);

    $venda = appAcaoTitulo($this, 5000, ['origem' => 'venda', 'origem_id' => 999999]);
    appAcao($this, 'PATCH', (string) $venda, ['valor' => 99] + $corpo)->assertStatus(422)->assertJsonStructure(['campos' => ['valor']]);
    appAcao($this, 'PATCH', (string) $venda, ['valor' => 50] + $corpo)->assertOk()->assertJsonPath('vencimento', '2031-02-20');

    $pago = appAcaoTitulo($this, 5000, ['status' => 'quitado', 'valor_aberto' => 0]);
    appAcao($this, 'PATCH', (string) $pago, $corpo)->assertStatus(409);
});

it('excluir e reativar: sai e volta das somas; motivo no detalhe; liquidado e lançamento de venda não se excluem', function () {
    $id = appAcaoTitulo($this, 25000);
    $antes = appAcaoRetrato($this);

    $d = appAcao($this, 'POST', "{$id}/excluir", ['motivo' => 'Lançado em duplicidade'])->assertOk()->json();
    expect($d)->toMatchArray(['excluido' => true, 'motivo_exclusao' => 'Lançado em duplicidade']);
    expect(appAcaoDelta($antes, appAcaoRetrato($this)))->toBe(['total' => -25000, 'aberto' => -25000, 'baixado' => 0, 'kpi_receber' => -25000, 'recebido' => 0]);
    appAcao($this, 'POST', "{$id}/excluir", ['motivo' => 'de novo'])->assertOk()->assertJsonPath('motivo_exclusao', 'Lançado em duplicidade');

    $r = appAcao($this, 'POST', "{$id}/reativar")->assertOk()->json();
    expect($r)->toMatchArray(['excluido' => false, 'motivo_exclusao' => null]);
    expect(array_column($r['historico'], 'texto'))->toBe(['Excluído: Lançado em duplicidade', 'Reativado']);
    expect(appAcaoRetrato($this))->toBe($antes);

    $pago = appAcaoTitulo($this, 5000, ['status' => 'quitado', 'valor_aberto' => 0]);
    appAcao($this, 'POST', "{$pago}/excluir")->assertStatus(409);
    $venda = appAcaoTitulo($this, 5000, ['origem' => 'venda', 'origem_id' => 999998]);
    appAcao($this, 'POST', "{$venda}/excluir")->assertStatus(409);
    expect(DB::table('fin_titulos')->whereIn('id', [$pago, $venda])->where('status', 'cancelado')->count())->toBe(0);
    // Excluir = cancelar (o Financeiro não deixa apagar título): o registro segue, com o status de antes guardado.
    expect(DB::table('fin_titulos')->where('id', $id)->value('status'))->toBe('aberto');
});

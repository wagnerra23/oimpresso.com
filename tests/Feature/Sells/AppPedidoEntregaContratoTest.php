<?php
// @covers-us US-SELL-073

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Tests\Contract\AutosaveContractRunner;

/**
 * POST /api/app/pedidos/{id}/entrega — protocolo de entrega do pedido pelo app (P3 · tela 22).
 *
 * Contrato: memory/requisitos/AppMobile/api/tela-22-entrega.md (todos os itens conferidos,
 * assinatura PNG ≤ 200 KB OU motivo 5–200, ajustes app_entrega_exige_assinatura/_localizacao,
 * protocolo ENT-NNNN por business, FSM numa transação, Idempotency-Key, etapa_esperada → 409).
 * NÃO derivado do controller.
 *
 * Tier 0 (ADR 0093 / ADR 0358): pedido de OUTRO business → 404 e nada é gravado. Controle positivo
 * em par: o pedido do próprio business É entregue — senão o "nada gravado" seria verde por vácuo.
 */
uses(DatabaseTransactions::class);

/** PNG 1×1 válido. */
const APP_ENTREGA_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

/**
 * Processo de teste no business: paid --entregar--> delivered --concluir--> completed.
 *
 * @return array<string, int> chave do estágio → id
 */
function appEntregaProcesso(int $businessId): array
{
    $proc = DB::table('sale_processes')->insertGetId([
        'business_id' => $businessId, 'key' => 'app_entrega_' . uniqid(), 'name' => 'App entrega teste',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $st = [];
    foreach (['in_production' => false, 'paid' => false, 'delivered' => false, 'completed' => true] as $k => $term) {
        $st[$k] = DB::table('sale_process_stages')->insertGetId([
            'process_id' => $proc, 'key' => $k, 'name' => 'Etapa ' . $k, 'sort_order' => count($st),
            'is_terminal' => $term, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    foreach ([['paid', 'entregar', 'delivered'], ['delivered', 'concluir', 'completed']] as [$de, $key, $para]) {
        DB::table('sale_stage_actions')->insert([
            'stage_id' => $st[$de], 'key' => $key, 'label' => 'Ação ' . $key, 'target_stage_id' => $st[$para],
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $st;
}

/** Produto + variação próprios do business (o seed da lane não garante produto). @return array{0:int,1:int} */
function appEntregaProduto(int $bizId, int $userId): array
{
    $carimbo = uniqid();
    $unit = DB::table('units')->where('business_id', $bizId)->value('id') ?? DB::table('units')->insertGetId([
        'business_id' => $bizId, 'actual_name' => 'Unidade entrega', 'short_name' => 'Un', 'allow_decimal' => 1,
        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $produto = DB::table('products')->insertGetId([
        'business_id' => $bizId, 'name' => 'Produto entrega', 'type' => 'single', 'unit_id' => $unit,
        'enable_stock' => 0, 'sku' => 'APP-ENT-' . $carimbo, 'barcode_type' => 'C128', 'created_by' => $userId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $pv = DB::table('product_variations')->insertGetId([
        'product_id' => $produto, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $variacao = DB::table('variations')->insertGetId([
        'product_id' => $produto, 'product_variation_id' => $pv, 'name' => 'DUMMY', 'sub_sku' => 'APP-ENT-' . $carimbo,
        'default_purchase_price' => 0, 'dpp_inc_tax' => 0, 'profit_percent' => 0, 'default_sell_price' => 10,
        'sell_price_inc_tax' => 10, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return [(int) $produto, (int) $variacao];
}

function appEntregaLinha(int $vendaId, int $produto, int $variacao, float $qtd): int
{
    return (int) DB::table('transaction_sell_lines')->insertGetId([
        'transaction_id' => $vendaId, 'product_id' => $produto, 'variation_id' => $variacao, 'quantity' => $qtd,
        'unit_price' => 10, 'unit_price_inc_tax' => 10, 'unit_price_before_discount' => 10, 'item_tax' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function appEntregaEtapa(int $vendaId): ?string
{
    return DB::table('transactions as t')->join('sale_process_stages as s', 's.id', '=', 't.current_stage_id')
        ->where('t.id', $vendaId)->value('s.key');
}

function appEntregaAjustes(int $bizId, array $ajustes): void
{
    $pos = json_decode((string) DB::table('business')->where('id', $bizId)->value('pos_settings'), true) ?: [];
    foreach (['app_entrega_exige_assinatura', 'app_entrega_exige_localizacao'] as $k) {
        unset($pos[$k]);
    }
    DB::table('business')->where('id', $bizId)->update(['pos_settings' => json_encode($ajustes + $pos)]);
}

beforeEach(function () {
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['transactions', 'transaction_sell_lines', 'sale_processes', 'sale_process_stages', 'sale_stage_actions',
        'sale_stage_history', 'app_idempotencia', 'app_entregas', 'arquivos'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }
    Storage::fake('arquivos');
    Storage::fake('vault');

    $ctx = AutosaveContractRunner::setupSellsContext($this);
    $this->biz = $ctx['business'];
    $this->user = $ctx['user'];
    $this->venda = (int) $ctx['transactionId'];
    $this->outroBiz = \App\Business::where('id', '!=', $this->biz->id)->first();
    if (! $this->outroBiz) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }

    foreach (['direct_sell.view', 'access_all_locations'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        $this->user->givePermissionTo($p);
    }

    $this->st = appEntregaProcesso((int) $this->biz->id);
    DB::table('transactions')->where('id', $this->venda)->update([
        'status' => 'final', 'sub_type' => null, 'current_stage_id' => $this->st['paid'],
    ]);
    // Duas linhas de topo garantidas (a conferência exige TODAS).
    // (uk_tsl_dup_prevent: uma linha por produto+variação na venda — um produto por linha.)
    foreach ([1, 2] as $n) {
        [$this->produto, $this->variacao] = appEntregaProduto((int) $this->biz->id, (int) $this->user->id);
        appEntregaLinha($this->venda, $this->produto, $this->variacao, $n);
    }
    $this->linhas = DB::table('transaction_sell_lines')->where('transaction_id', $this->venda)
        ->whereNull('parent_sell_line_id')->pluck('id')->map(fn ($x) => (int) $x)->all();
    appEntregaAjustes((int) $this->biz->id, []);

    // O limite de escrita (throttle:20,1) tem teste próprio abaixo; aqui não pode vazar entre testes
    // (o cache do CT 100 persiste e o usuário do contexto é o mesmo).
    $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    Passport::actingAs($this->user, [], 'api');
});

$corpo = function (array $extra = []): array {
    return $extra + [
        'itens' => collect(test()->linhas)->map(fn ($id) => ['linha_id' => $id, 'conferido' => true])->all(),
        'recebido_por' => 'Recebedor Teste',
        'documento' => null,
        'assinatura_png' => APP_ENTREGA_PNG,
        'sem_assinatura_motivo' => null,
        'lat' => -26.8977, 'lng' => -49.2316, 'precisao_m' => 12,
        'etapa_esperada' => 'paid',
    ];
};

$post = function (int $id, array $corpo, ?string $chave = 'k-') {
    $h = $chave === null ? [] : ['Idempotency-Key' => $chave === 'k-' ? 'k-' . uniqid() : $chave];

    // withHeaders acumula entre requisições do mesmo teste: limpa antes.
    return test()->flushHeaders()->withHeaders($h)->postJson('/api/app/pedidos/' . $id . '/entrega', $corpo);
};

it('detalhe expõe entrega.pode na etapa de Entrega e linha_id nos itens; fora dela entrega = null', function () {
    $r = $this->getJson('/api/app/pedidos/' . $this->venda)->assertOk();
    expect($r->json('entrega.pode'))->toBeTrue();
    expect(collect($r->json('itens_venda'))->pluck('linha_id')->sort()->values()->all())->toBe(collect($this->linhas)->sort()->values()->all());
    expect(collect($r->json('acoes'))->pluck('chave')->all())->not->toContain('entregar');

    DB::table('transactions')->where('id', $this->venda)->update(['current_stage_id' => $this->st['in_production']]);
    expect($this->getJson('/api/app/pedidos/' . $this->venda)->assertOk()->json('entrega'))->toBeNull();
});

it('registra a entrega: 201 com ENT-NNNN, assinatura em arquivos, etapa delivered e trilha com origem app — tudo junto', function () use ($post, $corpo) {
    $antes = (int) DB::table('app_entregas')->where('business_id', $this->biz->id)->max('numero');

    $r = $post($this->venda, $corpo())->assertStatus(201);

    $esperado = 'ENT-' . str_pad((string) ($antes + 1), 4, '0', STR_PAD_LEFT);
    expect($r->json('protocolo'))->toBe($esperado);
    expect($r->json('com_assinatura'))->toBeTrue();
    expect($r->json('recebido_por'))->toBe('Recebedor Teste');
    expect($r->json('pedido.etapa.chave'))->toBe('delivered');
    expect(appEntregaEtapa($this->venda))->toBe('delivered');

    $ent = DB::table('app_entregas')->where('business_id', $this->biz->id)->where('transaction_id', $this->venda)->first();
    expect($ent->protocolo)->toBe($esperado);
    expect((float) $ent->lat)->toBe(-26.8977);
    $arq = DB::table('arquivos')->where('id', (int) $ent->assinatura_arquivo_id)->first();
    expect((int) $arq->business_id)->toBe((int) $this->biz->id);
    expect($arq->arquivable_type)->toBe(\App\Transaction::class);
    expect((int) $arq->arquivable_id)->toBe($this->venda);
    expect($arq->mime_type)->toBe('image/png');

    $hist = DB::table('sale_stage_history')->where('transaction_id', $this->venda)->orderByDesc('id')->first();
    expect((int) $hist->to_stage_id)->toBe($this->st['delivered']);
    $payload = json_decode((string) $hist->payload_snapshot, true);
    expect($payload['origem'])->toBe('app');
    expect($payload['protocolo'])->toBe($esperado);

    // Sequencial por business: a segunda entrega leva o próximo número.
    $venda2 = DB::table('transactions')->insertGetId([
        'business_id' => $this->biz->id, 'created_by' => $this->user->id, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'paid', 'invoice_no' => 'APP-ENT-2-' . uniqid(), 'transaction_date' => now(),
        'total_before_tax' => 10, 'final_total' => 10, 'current_stage_id' => $this->st['paid'],
        'location_id' => DB::table('transactions')->where('id', $this->venda)->value('location_id'),
        'contact_id' => DB::table('transactions')->where('id', $this->venda)->value('contact_id'),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $linha2 = appEntregaLinha((int) $venda2, $this->produto, $this->variacao, 1);
    $post($venda2, $corpo(['itens' => [['linha_id' => $linha2, 'conferido' => true]], 'assinatura_png' => null,
        'sem_assinatura_motivo' => 'Cliente com as mãos ocupadas']))
        ->assertStatus(201)
        ->assertJsonPath('protocolo', 'ENT-' . str_pad((string) ($antes + 2), 4, '0', STR_PAD_LEFT))
        ->assertJsonPath('com_assinatura', false);
});

it('Idempotency-Key: mesma chave e mesmo corpo devolvem a mesma resposta sem gravar de novo; corpo diferente → 422', function () use ($post, $corpo) {
    $chave = 'idem-' . uniqid();

    $a = $post($this->venda, $corpo(), $chave)->assertStatus(201)->json();
    $b = $post($this->venda, $corpo(), $chave)->assertStatus(201)->json();

    // Mesmo conteúdo, não a mesma ordem: a coluna JSON do MySQL reordena as chaves ao gravar
    // (o MariaDB do CT 100 não). Ordem de chave não é contrato de JSON.
    expect($b)->toEqual($a);
    expect($b['protocolo'])->toBe($a['protocolo']);
    expect(DB::table('app_entregas')->where('transaction_id', $this->venda)->count())->toBe(1);
    expect(DB::table('sale_stage_history')->where('transaction_id', $this->venda)->count())->toBe(1);

    $post($this->venda, $corpo(['recebido_por' => 'Outra Pessoa']), $chave)
        ->assertStatus(422)->assertJsonPath('erro', 'idempotencia_conflito');
});

it('etapa_esperada velha ou pedido fora da etapa de Entrega → 409 etapa_mudou e nada é gravado', function () use ($post, $corpo) {
    $post($this->venda, $corpo(['etapa_esperada' => 'in_production']))
        ->assertStatus(409)->assertJsonPath('erro', 'etapa_mudou');

    DB::table('transactions')->where('id', $this->venda)->update(['current_stage_id' => $this->st['in_production']]);
    $post($this->venda, $corpo(['etapa_esperada' => 'in_production']))
        ->assertStatus(409)->assertJsonPath('erro', 'etapa_mudou');

    expect(DB::table('app_entregas')->where('transaction_id', $this->venda)->count())->toBe(0);
    expect(DB::table('sale_stage_history')->where('transaction_id', $this->venda)->count())->toBe(0);
});

it('falha da FSM no meio desfaz protocolo, assinatura e reserva da chave (transação única)', function () use ($post, $corpo) {
    // A ação entregar com side effect fica só na web: o 403 sai antes de gravar.
    DB::table('sale_stage_actions')->where('stage_id', $this->st['paid'])->where('key', 'entregar')
        ->update(['side_effect_class' => 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade']);
    $chave = 'tx-' . uniqid();
    $post($this->venda, $corpo(), $chave)->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');

    expect(DB::table('app_entregas')->where('transaction_id', $this->venda)->count())->toBe(0);
    expect(DB::table('arquivos')->where('arquivable_type', \App\Transaction::class)->where('arquivable_id', $this->venda)->count())->toBe(0);
    expect(DB::table('app_idempotencia')->where('chave', $chave)->count())->toBe(0);
    expect(appEntregaEtapa($this->venda))->toBe('paid');

    // Falha DENTRO do execute (protocolo e assinatura já gravados na transação): tudo volta.
    DB::table('sale_stage_actions')->where('stage_id', $this->st['paid'])->where('key', 'entregar')
        ->update(['side_effect_class' => null]);
    $falha = Mockery::mock(\App\Domain\Fsm\Services\ExecuteStageActionService::class);
    $falha->shouldReceive('execute')->once()->andThrow(new \RuntimeException('falha simulada da FSM'));
    app()->instance(\App\Domain\Fsm\Services\ExecuteStageActionService::class, $falha);
    $chave2 = 'tx2-' . uniqid();
    $post($this->venda, $corpo(), $chave2)->assertStatus(500)->assertJsonPath('erro', 'falha');
    expect(DB::table('app_entregas')->where('transaction_id', $this->venda)->count())->toBe(0);
    expect(DB::table('arquivos')->where('arquivable_type', \App\Transaction::class)->where('arquivable_id', $this->venda)->count())->toBe(0);
    expect(DB::table('app_idempotencia')->where('chave', $chave2)->count())->toBe(0);
    expect(appEntregaEtapa($this->venda))->toBe('paid');
});

it('validação: item não conferido, sem assinatura nem motivo, PNG inválido, motivo curto → 422 com o campo', function () use ($post, $corpo) {
    $itens = collect($this->linhas)->map(fn ($id) => ['linha_id' => $id, 'conferido' => true])->all();
    $itens[0]['conferido'] = false;
    $post($this->venda, $corpo(['itens' => $itens]))
        ->assertStatus(422)->assertJsonPath('erro', 'validacao')->assertJsonStructure(['campos' => ['itens']]);
    $post($this->venda, $corpo(['itens' => array_slice($itens, 1)]))
        ->assertStatus(422)->assertJsonStructure(['campos' => ['itens']]);
    $post($this->venda, $corpo(['assinatura_png' => null]))
        ->assertStatus(422)->assertJsonStructure(['campos' => ['assinatura_png']]);
    $post($this->venda, $corpo(['assinatura_png' => 'data:image/png;base64,' . base64_encode('não é png')]))
        ->assertStatus(422)->assertJsonStructure(['campos' => ['assinatura_png']]);
    $post($this->venda, $corpo(['assinatura_png' => 'data:image/png;base64,' . base64_encode("\x89PNG\r\n\x1a\n" . str_repeat('a', 205 * 1024))]))
        ->assertStatus(422)->assertJsonStructure(['campos' => ['assinatura_png']]);
    $post($this->venda, $corpo(['assinatura_png' => null, 'sem_assinatura_motivo' => 'não']))
        ->assertStatus(422)->assertJsonStructure(['campos' => ['sem_assinatura_motivo']]);
    $post($this->venda, $corpo(['recebido_por' => '']))
        ->assertStatus(422)->assertJsonStructure(['campos' => ['recebido_por']]);
    $post($this->venda, $corpo(), null)
        ->assertStatus(422)->assertJsonStructure(['campos' => ['idempotency_key']]);

    expect(DB::table('app_entregas')->where('transaction_id', $this->venda)->count())->toBe(0);
    expect(appEntregaEtapa($this->venda))->toBe('paid');
});

it('ajustes da empresa: exige assinatura recusa o motivo; exige localização recusa lat nulo; padrão aceita os dois', function () use ($post, $corpo) {
    $semAssinatura = $corpo(['assinatura_png' => null, 'sem_assinatura_motivo' => 'Cliente com as mãos ocupadas', 'lat' => null, 'lng' => null, 'precisao_m' => null]);

    appEntregaAjustes((int) $this->biz->id, ['app_entrega_exige_assinatura' => true]);
    $post($this->venda, $semAssinatura)->assertStatus(422)->assertJsonStructure(['campos' => ['assinatura_png']]);

    appEntregaAjustes((int) $this->biz->id, ['app_entrega_exige_localizacao' => true]);
    $post($this->venda, $semAssinatura)->assertStatus(422)->assertJsonStructure(['campos' => ['lat']]);
    expect(appEntregaEtapa($this->venda))->toBe('paid');

    // Controle positivo: com o padrão (false, false) o mesmo corpo entrega.
    appEntregaAjustes((int) $this->biz->id, []);
    $post($this->venda, $semAssinatura)->assertStatus(201)->assertJsonPath('com_assinatura', false);
    expect(appEntregaEtapa($this->venda))->toBe('delivered');
    expect(DB::table('app_entregas')->where('transaction_id', $this->venda)->value('sem_assinatura_motivo'))
        ->toBe('Cliente com as mãos ocupadas');
});

it('pedido de OUTRO business → 404 nao_encontrado e nada é gravado', function () use ($post, $corpo) {
    $stAlheio = appEntregaProcesso((int) $this->outroBiz->id);
    $alheia = DB::table('transactions')->insertGetId([
        'business_id' => $this->outroBiz->id, 'created_by' => $this->user->id, 'type' => 'sell',
        'status' => 'final', 'payment_status' => 'paid', 'invoice_no' => 'APP-ENT-ALHEIA-' . uniqid(),
        'transaction_date' => now(), 'total_before_tax' => 10, 'final_total' => 10,
        'current_stage_id' => $stAlheio['paid'], 'created_at' => now(), 'updated_at' => now(),
    ]);

    $post($alheia, $corpo())->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');

    expect(appEntregaEtapa($alheia))->toBe('paid');
    expect(DB::table('app_entregas')->where('transaction_id', $alheia)->count())->toBe(0);
    expect(DB::table('sale_stage_history')->where('transaction_id', $alheia)->count())->toBe(0);
});

it('usuário sem nenhuma permissão de venda → 403 sem_permissao', function () use ($post, $corpo) {
    $semPerm = \App\User::query()->whereKey(
        DB::table('users')->insertGetId([
            'first_name' => 'APP sem perm', 'username' => 'app_ent_sp_' . uniqid(), 'password' => 'x',
            'business_id' => $this->biz->id, 'created_at' => now(), 'updated_at' => now(),
        ])
    )->firstOrFail();
    Passport::actingAs($semPerm, [], 'api');

    $post($this->venda, $corpo())->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
    expect(appEntregaEtapa($this->venda))->toBe('paid');
});

it('rota de escrita com throttle de 20 por minuto (playbook: 20–30/min)', function () {
    $rota = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($r) => $r->uri() === 'api/app/pedidos/{id}/entrega' && in_array('POST', $r->methods(), true));
    expect($rota)->not->toBeNull();
    expect($rota->gatherMiddleware())->toContain('throttle:20,1');
});

// ── Volumes no protocolo (UC-FLX-23 · US-SELL-073) ─────────────────────────────────────────────
// Contrato: tela-22-entrega.md §Volumes — total de volumes do protocolo, inteiro 1–9999, OPCIONAL
// (o app antigo não manda o campo). Casos UC-ENTR-01..04 em resources/js/Pages/Sells/Show.casos.md.

it('UC-ENTR-01 · grava os volumes no protocolo e devolve na resposta, junto com itens e quem recebeu', function () use ($post, $corpo) {
    $chave = 'vol-' . uniqid();
    $r = $post($this->venda, $corpo(['volumes' => 3]), $chave)->assertStatus(201);

    expect($r->json('volumes'))->toBe(3);
    expect($r->json('recebido_por'))->toBe('Recebedor Teste');
    $ent = DB::table('app_entregas')->where('business_id', $this->biz->id)->where('transaction_id', $this->venda)->first();
    expect((int) $ent->volumes)->toBe(3);
    expect($ent->protocolo)->toBe($r->json('protocolo'));
    expect(collect(json_decode((string) $ent->itens, true))->pluck('linha_id')->sort()->values()->all())
        ->toBe(collect($this->linhas)->sort()->values()->all());
    expect(appEntregaEtapa($this->venda))->toBe('delivered');

    // Mesma chave e mesmos volumes devolvem a resposta gravada; mesma chave com outro número é conflito.
    $post($this->venda, $corpo(['volumes' => 3]), $chave)
        ->assertStatus(201)->assertJsonPath('protocolo', $r->json('protocolo'))->assertJsonPath('volumes', 3);
    $post($this->venda, $corpo(['volumes' => 5]), $chave)
        ->assertStatus(422)->assertJsonPath('erro', 'idempotencia_conflito');
    expect(DB::table('app_entregas')->where('transaction_id', $this->venda)->count())->toBe(1);
});

it('UC-ENTR-02 · volumes zero, negativo, fracionado, texto ou acima de 9999 → 422 campos.volumes e nada é gravado', function () use ($post, $corpo) {
    foreach ([0, -1, 2.5, 'tres', 10000] as $ruim) {
        $post($this->venda, $corpo(['volumes' => $ruim]))
            ->assertStatus(422)->assertJsonPath('erro', 'validacao')->assertJsonStructure(['campos' => ['volumes']]);
    }

    expect(DB::table('app_entregas')->where('transaction_id', $this->venda)->count())->toBe(0);
    expect(DB::table('app_idempotencia')->where('transaction_id', $this->venda)->count())->toBe(0);
    expect(appEntregaEtapa($this->venda))->toBe('paid');
});

it('UC-ENTR-03 · app antigo, sem o campo volumes: a entrega continua registrada como hoje e o protocolo fica sem volumes', function () use ($post, $corpo) {
    $chave = 'antigo-' . uniqid();
    $corpoAntigo = $corpo();
    expect(array_key_exists('volumes', $corpoAntigo))->toBeFalse();

    $r = $post($this->venda, $corpoAntigo, $chave)->assertStatus(201);

    expect($r->json('volumes'))->toBeNull();
    expect($r->json('protocolo'))->toStartWith('ENT-');
    expect(DB::table('app_entregas')->where('transaction_id', $this->venda)->value('volumes'))->toBeNull();
    expect(appEntregaEtapa($this->venda))->toBe('delivered');
    // A repetição do corpo antigo segue idempotente (o campo novo não mudou o hash dele).
    $post($this->venda, $corpoAntigo, $chave)->assertStatus(201)->assertJsonPath('protocolo', $r->json('protocolo'));
    expect(DB::table('app_entregas')->where('transaction_id', $this->venda)->count())->toBe(1);
});

it('UC-ENTR-04 · [T0] volumes no pedido de OUTRA empresa → 404 e nada gravado; no da própria empresa, gravado nela', function () use ($post, $corpo) {
    $stAlheio = appEntregaProcesso((int) $this->outroBiz->id);
    $alheia = DB::table('transactions')->insertGetId([
        'business_id' => $this->outroBiz->id, 'created_by' => $this->user->id, 'type' => 'sell',
        'status' => 'final', 'payment_status' => 'paid', 'invoice_no' => 'APP-ENT-VOL-ALHEIA-' . uniqid(),
        'transaction_date' => now(), 'total_before_tax' => 10, 'final_total' => 10,
        'current_stage_id' => $stAlheio['paid'], 'created_at' => now(), 'updated_at' => now(),
    ]);

    $post($alheia, $corpo(['volumes' => 4]))->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
    expect(DB::table('app_entregas')->where('transaction_id', $alheia)->count())->toBe(0);
    expect(appEntregaEtapa($alheia))->toBe('paid');

    // Controle positivo: o mesmo corpo no pedido da própria empresa grava, com o business do usuário.
    $post($this->venda, $corpo(['volumes' => 4]))->assertStatus(201)->assertJsonPath('volumes', 4);
    $ent = DB::table('app_entregas')->where('transaction_id', $this->venda)->first();
    expect((int) $ent->business_id)->toBe((int) $this->biz->id);
    expect((int) $ent->volumes)->toBe(4);
});
